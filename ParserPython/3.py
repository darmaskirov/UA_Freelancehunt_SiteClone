# file: 3.py
import csv, time, re, hashlib, os, json, tempfile, shutil
from pathlib import Path
from urllib.parse import urljoin
import requests

from selenium import webdriver
from selenium.webdriver.common.by import By
from selenium.webdriver.chrome.service import Service
from selenium.webdriver.chrome.options import Options
from selenium.webdriver.common.action_chains import ActionChains
from selenium.webdriver.support.ui import WebDriverWait
from selenium.webdriver.support import expected_conditions as EC
from selenium.common.exceptions import TimeoutException, NoSuchElementException, ElementClickInterceptedException
from webdriver_manager.chrome import ChromeDriverManager

# ------------ НАСТРОЙКИ ------------
START_URL = "https://pc.dfbiu.com/category2?code=game"
MAX_PAGES_PER_VENDOR = 60       # чтобы не залипать
WAIT_SEC  = 10
OUTDIR    = Path("crawl_out")
IMG_DIR   = OUTDIR / "images"
OUTDIR.mkdir(exist_ok=True, parents=True)
IMG_DIR.mkdir(exist_ok=True, parents=True)
# -----------------------------------

def get_driver():
    user_data_dir = tempfile.mkdtemp(prefix="selenium-profile-")
    cache_dir     = tempfile.mkdtemp(prefix="selenium-cache-")

    opts = Options()
    opts.add_argument("--start-maximized")
    opts.add_argument(f"--user-data-dir={user_data_dir}")
    opts.add_argument(f"--disk-cache-dir={cache_dir}")
    # opts.add_argument("--headless=new")

    driver_path = ChromeDriverManager().install()
    driver = webdriver.Chrome(service=Service(driver_path), options=opts)

    def _cleanup():
        try:
            driver.quit()
        except Exception:
            pass
        for p in (user_data_dir, cache_dir):
            try:
                shutil.rmtree(p, ignore_errors=True)
            except Exception:
                pass
    driver._cleanup = _cleanup
    return driver

def wait_css(driver, css, t=WAIT_SEC):
    return WebDriverWait(driver, t).until(EC.presence_of_element_located((By.CSS_SELECTOR, css)))

def any_css_present(driver, selectors, t=WAIT_SEC):
    for sel in selectors:
        try:
            wait_css(driver, sel, t=2)
            return sel
        except TimeoutException:
            continue
    return None

def find_iframe_with_content(driver):
    driver.switch_to.default_content()
    if any_css_present(driver, [".imgList .imgItem", ".el-pagination"], t=2):
        return None
    frames = driver.find_elements(By.CSS_SELECTOR, "iframe")
    for idx, fr in enumerate(frames):
        driver.switch_to.default_content()
        driver.switch_to.frame(fr)
        if any_css_present(driver, [".imgList .imgItem", ".el-pagination"], t=2):
            return idx
    driver.switch_to.default_content()
    return None

def scroll_all(driver):
    last = 0
    for _ in range(40):
        driver.execute_script("window.scrollBy(0, 1200);")
        time.sleep(0.2)
        h = driver.execute_script("return document.body.scrollHeight")
        if h == last:
            break
        last = h

def dom_hash(driver):
    html = driver.execute_script("return document.documentElement.outerHTML;")
    return hashlib.md5(html.encode("utf-8", errors="ignore")).hexdigest()

def save_artifacts(driver, page_idx, vendor_slug):
    png = OUTDIR / f"{vendor_slug}_page_{page_idx:03d}.png"
    html = OUTDIR / f"{vendor_slug}_page_{page_idx:03d}.html"
    try:
        driver.save_screenshot(str(png))
    except Exception:
        pass
    try:
        src = driver.execute_script("return document.documentElement.outerHTML;")
        html.write_text(src, encoding="utf-8")
    except Exception:
        pass

def vendor_slugify(txt: str) -> str:
    return re.sub(r"[^a-zA-Z0-9\-\u4e00-\u9fff]+", "_", (txt or "vendor")).strip("_")[:40] or "vendor"

def safe_name(txt: str) -> str:
    return re.sub(r"[^\w\-\u4e00-\u9fff]+", "_", (txt or "img")).strip("_")[:60] or "img"

def transfer_cookies_to_session(driver) -> requests.Session:
    s = requests.Session()
    for c in driver.get_cookies():
        s.cookies.set(c["name"], c["value"], domain=c.get("domain"), path=c.get("path", "/"))
    s.headers.update({"User-Agent": "Mozilla/5.0"})
    return s

def try_download_image(session: requests.Session, url: str, title: str, vendor_slug: str) -> str | None:
    if not url: 
        return None
    try:
        ext = Path(url.split("?")[0]).suffix or ".png"
        name = f"{vendor_slug}__{safe_name(title)}{ext}"
        path = IMG_DIR / name
        r = session.get(url, timeout=20, stream=True, verify=False)
        r.raise_for_status()
        with open(path, "wb") as f:
            for chunk in r.iter_content(8192):
                if chunk:
                    f.write(chunk)
        return str(path)
    except Exception:
        return None

def get_vendor_tabs(driver):
    """
    Возвращает список элементов-вкладок (мини-пилюль) + их видимый текст.
    Селекторы сделаны избыточно, чтобы подхватить разные версии вёрстки.
    """
    tabs = []
    candidates = driver.find_elements(
        By.CSS_SELECTOR,
        # кнопки-пилюли брендов
        ".el-button, .el-tag, .vendor-list .item, .typeList .el-button, .gameType .el-button"
    )
    for el in candidates:
        try:
            if not el.is_displayed():
                continue
            txt = (el.text or "").strip()
            # фильтруем только «похожие на бренды»: часто заканчиваются на ‘电子’ или латиница
            if not txt:
                continue
            if len(txt) > 10 and "电子" not in txt:
                # слишком длинная подпись — скорее не бренд
                continue
            # исключим явно системные кнопки (Поиск и т.п.)
            if "Input" in txt or "搜索" in txt or "Search" in txt:
                continue
            tabs.append((el, txt))
        except Exception:
            continue

    # уберём дубликаты по тексту
    seen = set()
    unique = []
    for el, txt in tabs:
        if txt in seen:
            continue
        seen.add(txt)
        unique.append((el, txt))
    return unique

def click_safely(driver, el):
    try:
        driver.execute_script("arguments[0].scrollIntoView({block:'center'});", el)
        time.sleep(0.05)
        el.click()
        return True
    except (ElementClickInterceptedException, Exception):
        try:
            driver.execute_script("arguments[0].click();", el)
            return True
        except Exception:
            return False

def collect_game_cards_on_page(driver, vendor_name):
    """
    Возвращает [{"vendor","title","img","href"}] для текущей страницы.
    href может быть пустым, если клик не дал реальный URL (но title/img мы всё равно берём).
    """
    out, seen = [], set()
    actions = ActionChains(driver)
    cards = driver.find_elements(By.CSS_SELECTOR, ".imgList .imgItem")
    for card in cards:
        try:
            driver.execute_script("arguments[0].scrollIntoView({block:'center'});", card)
            time.sleep(0.05)
            actions.move_to_element(card).pause(0.05).perform()

            try:
                title = card.find_element(By.CSS_SELECTOR, ".title .text").text.strip()
            except Exception:
                title = None
            try:
                img = card.find_element(By.CSS_SELECTOR, "img").get_attribute("src")
            except Exception:
                img = None

            href = None
            # пытаемся кликнуть "查看" чтобы достать URL запуска
            click_target = None
            for sel in [".img .link button", ".img .link a", "a[href]"]:
                try:
                    click_target = card.find_element(By.CSS_SELECTOR, sel)
                    break
                except NoSuchElementException:
                    continue
            if click_target is None:
                click_target = card

            before = set(driver.window_handles)
            cur_before = driver.current_url
            try:
                click_target.click()
                time.sleep(0.25)
                WebDriverWait(driver, 3).until(lambda d: len(d.window_handles) > len(before))
                new_handle = list(set(driver.window_handles) - before)[0]
                driver.switch_to.window(new_handle)
                WebDriverWait(driver, 8).until(lambda d: d.current_url != "about:blank")
                href = driver.current_url
                driver.close()
                driver.switch_to.window(list(before)[-1])
            except TimeoutException:
                # возможно в этом же табе
                cur_now = driver.current_url
                if cur_now != cur_before and ("launch" in cur_now or "game" in cur_now or "token" in cur_now):
                    href = cur_now
                    driver.back()
                    try:
                        wait_css(driver, ".imgList .imgItem", t=WAIT_SEC)
                    except TimeoutException:
                        pass
                else:
                    # клика нет — оставим href пустым
                    pass
            except Exception:
                pass

            key = (title or "", img or "", href or "", vendor_name or "")
            if key in seen:
                continue
            seen.add(key)
            out.append({
                "vendor": vendor_name,
                "title": title,
                "img": img,
                "href": href
            })

        except Exception:
            continue

    return out

def click_next_page(driver):
    try:
        nxt = driver.find_element(By.CSS_SELECTOR, ".el-pagination .btn-next")
        if nxt.is_enabled() and nxt.get_attribute("disabled") is None and nxt.get_attribute("aria-disabled") != "true":
            nxt.click()
            return True
    except Exception:
        pass
    try:
        active = driver.find_element(By.CSS_SELECTOR, ".el-pagination .is-active")
        nxt_num = active.find_element(By.XPATH, "following-sibling::*[contains(@class,'number')][1]")
        nxt_num.click()
        return True
    except Exception:
        return False

def main():
    driver = get_driver()
    driver.get(START_URL)

    input("👉 Залогинься вручную (test228/test228), открой каталог игр и нажми Enter здесь... ")

    # iframe
    driver.switch_to.default_content()
    iframe_idx = find_iframe_with_content(driver)
    if iframe_idx is not None:
        try:
            frames = driver.find_elements(By.CSS_SELECTOR, "iframe")
            driver.switch_to.frame(frames[iframe_idx])
            print(f"[i] Контент внутри iframe #{iframe_idx}")
        except Exception:
            driver.switch_to.default_content()

    # ждём карточки/пилюли
    try:
        wait_css(driver, ".imgList .imgItem", t=WAIT_SEC)
    except TimeoutException:
        print("[!] Карточки не найдены. Сохраняю страницу и выхожу.")
        save_artifacts(driver, 0, "no_vendor")
        driver._cleanup()
        return

    # собираем список вкладок-пилюль (включая активную)
    tabs = get_vendor_tabs(driver)
    if not tabs:
        print("[i] Вкладки не найдены — работаю только с активной.")
        tabs = [(None, "All")]

    # для закачки картинок перенесём cookies
    session = transfer_cookies_to_session(driver)

    all_rows = []
    visited_vendor_hashes = set()

    for el, vendor_name in tabs:
        vendor_slug = vendor_slugify(vendor_name)
        print(f"\n=== Вкладка: {vendor_name} ===")

        if el is not None:
            if not click_safely(driver, el):
                print(f"[i] Не кликнулось: {vendor_name}. Продолжаю как есть.")
            time.sleep(0.6)

        try:
            wait_css(driver, ".imgList .imgItem", t=WAIT_SEC)
        except TimeoutException:
            print("[!] Нет карточек на этой вкладке.")
            continue

        # пагинация внутри текущей вкладки
        page = 1
        last_hash = None
        while page <= MAX_PAGES_PER_VENDOR:
            print(f"[+] {vendor_name}: страница {page}")
            scroll_all(driver)
            save_artifacts(driver, page, vendor_slug)

            rows = collect_game_cards_on_page(driver, vendor_name)
            # скачать PNG (если получится)
            for r in rows:
                # если картинка .webp — тоже сохраним; укажем путь, если успех
                r["img_path"] = try_download_image(session, r["img"], r.get("title") or "game", vendor_slug)

            all_rows.extend(rows)

            cur_hash = dom_hash(driver)
            key = (vendor_slug, cur_hash)
            if key in visited_vendor_hashes:
                print("    [i] DOM не меняется — стоп по вкладке.")
                break
            visited_vendor_hashes.add(key)

            if not click_next_page(driver):
                print("    [i] Пагинация закончилась по вкладке.")
                break
            time.sleep(0.8)
            page += 1

    # закрытие
    try:
        driver.switch_to.default_content()
    except Exception:
        pass
    driver._cleanup()

    # дедуп и сохранение
    dedup = {}
    for r in all_rows:
        key = (r.get("vendor"), r.get("title"), r.get("img"))
        dedup[key] = r
    final = list(dedup.values())

    with open("games_links.csv", "w", newline="", encoding="utf-8-sig") as f:
        w = csv.DictWriter(f, fieldnames=["vendor", "title", "href", "img", "img_path"])
        w.writeheader()
        w.writerows(final)

    with open(OUTDIR / "links_raw.json", "w", encoding="utf-8") as f:
        json.dump(final, f, ensure_ascii=False, indent=2)

    print(f"\n[✓] Готово. Строк: {len(final)}")
    print(f"CSV: games_links.csv")
    print(f"Картинки: {IMG_DIR}")

if __name__ == "__main__":
    main()
