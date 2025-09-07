import time, re, json, csv, tempfile, shutil
from pathlib import Path
from urllib.parse import urljoin
import requests

from selenium import webdriver
from selenium.webdriver.common.by import By
from selenium.webdriver.chrome.service import Service
from selenium.webdriver.chrome.options import Options
from selenium.webdriver.support.ui import WebDriverWait
from selenium.webdriver.support import expected_conditions as EC
from selenium.common.exceptions import TimeoutException
from webdriver_manager.chrome import ChromeDriverManager

# ---------- НАЛАШТУВАННЯ ----------
START_URL = "https://pc.dfbiu.com/category2?code=game"
WAIT = 10
MAX_PAGES_PER_TAB = 60          # ліміт, щоб не зациклитись
OUT = Path("out_images")
IMG_DIR = OUT / "images"
OUT.mkdir(parents=True, exist_ok=True)
IMG_DIR.mkdir(parents=True, exist_ok=True)
# ----------------------------------

def driver_start():
    user_data = tempfile.mkdtemp(prefix="selenium-prof-")
    cache_dir = tempfile.mkdtemp(prefix="selenium-cache-")
    opts = Options()
    opts.add_argument("--start-maximized")
    opts.add_argument(f"--user-data-dir={user_data}")
    opts.add_argument(f"--disk-cache-dir={cache_dir}")
    # opts.add_argument("--headless=new")

    drv = webdriver.Chrome(service=Service(ChromeDriverManager().install()), options=opts)
    def _cleanup():
        try: drv.quit()
        except: pass
        for p in (user_data, cache_dir):
            try: shutil.rmtree(p, ignore_errors=True)
            except: pass
    drv._cleanup = _cleanup
    return drv

def wait_css(drv, css, t=WAIT):
    return WebDriverWait(drv, t).until(EC.presence_of_element_located((By.CSS_SELECTOR, css)))

def maybe_switch_into_iframe_with_grid(drv):
    drv.switch_to.default_content()
    # якщо грід видимий в основному документі — нічого не робимо
    try:
        wait_css(drv, ".imgList .imgItem", t=2)
        return
    except TimeoutException:
        pass
    # шукаємо iframe з грідом
    for fr in drv.find_elements(By.CSS_SELECTOR, "iframe"):
        drv.switch_to.default_content()
        drv.switch_to.frame(fr)
        try:
            wait_css(drv, ".imgList .imgItem", t=2)
            return
        except TimeoutException:
            continue
    drv.switch_to.default_content()

def scroll_page(drv):
    last = 0
    for _ in range(30):
        drv.execute_script("window.scrollBy(0, 1200);")
        time.sleep(0.15)
        h = drv.execute_script("return document.body.scrollHeight")
        if h == last: break
        last = h

def get_tabs(drv):
    tabs = []
    candidates = drv.find_elements(By.CSS_SELECTOR, ".el-button, .el-tag, .typeList .el-button, .gameType .el-button")
    seen = set()
    for el in candidates:
        try:
            if not el.is_displayed(): continue
            txt = (el.text or "").strip()
            if not txt: continue
            # фільтр від сміття
            if len(txt) > 12 and "电子" not in txt: continue
            if txt in seen: continue
            seen.add(txt)
            tabs.append((el, txt))
        except: pass
    # якщо вкладок не знайшли, працюємо з активною сторінкою
    return tabs or [(None, "All")]

def collect_images_on_page(drv, vendor):
    items = []
    for card in drv.find_elements(By.CSS_SELECTOR, ".imgList .imgItem"):
        try:
            drv.execute_script("arguments[0].scrollIntoView({block:'center'});", card)
            time.sleep(0.03)
            # назва (якщо є)
            try:
                title = card.find_element(By.CSS_SELECTOR, ".title .text").text.strip()
            except: title = ""
            # сам IMG, як на твоєму скріні
            try:
                img = card.find_element(By.CSS_SELECTOR, "img").get_attribute("src")
            except: img = ""
            if img:
                items.append({"vendor": vendor, "title": title, "img": img})
        except: pass
    return items

def transfer_cookies(drv) -> requests.Session:
    s = requests.Session()
    s.headers.update({"User-Agent": "Mozilla/5.0"})
    for c in drv.get_cookies():
        s.cookies.set(c["name"], c["value"], domain=c.get("domain"), path=c.get("path", "/"))
    return s

def download_image(session: requests.Session, url: str, vendor: str, title: str) -> str | None:
    try:
        ext = Path(url.split("?")[0]).suffix or ".png"
        safe = re.sub(r"[^\w\-\u4e00-\u9fff]+", "_", (title or "img"))[:60].strip("_") or "img"
        vnd = re.sub(r"[^\w\-\u4e00-\u9fff]+", "_", (vendor or "vendor"))[:40].strip("_") or "vendor"
        path = IMG_DIR / f"{vnd}__{safe}{ext}"
        r = session.get(url, timeout=20, stream=True, verify=False)
        r.raise_for_status()
        with open(path, "wb") as f:
            for chunk in r.iter_content(8192):
                if chunk: f.write(chunk)
        return str(path)
    except Exception:
        return None

def click_next(drv) -> bool:
    # кнопка «вперед» або наступний номер
    try:
        nxt = drv.find_element(By.CSS_SELECTOR, ".el-pagination .btn-next")
        if nxt.is_enabled() and nxt.get_attribute("disabled") is None and nxt.get_attribute("aria-disabled") != "true":
            nxt.click();  return True
    except: pass
    try:
        active = drv.find_element(By.CSS_SELECTOR, ".el-pagination .is-active")
        nxt_num = active.find_element(By.XPATH, "following-sibling::*[contains(@class,'number')][1]")
        nxt_num.click();  return True
    except: return False

def main():
    drv = driver_start()
    drv.get(START_URL)
    input("👉 Залогінься вручну, відкрий каталог і натисни Enter тут... ")
    maybe_switch_into_iframe_with_grid(drv)

    # чекаємо перший грід
    wait_css(drv, ".imgList .imgItem", t=WAIT)

    tabs = get_tabs(drv)
    session = transfer_cookies(drv)

    all_rows = []
    for el, vendor in tabs:
        print(f"\n=== Вкладка: {vendor} ===")
        if el: 
            try:
                drv.execute_script("arguments[0].scrollIntoView({block:'center'});", el)
                el.click()
                time.sleep(0.6)
                wait_css(drv, ".imgList .imgItem", t=WAIT)
            except: pass

        page = 1
        while page <= MAX_PAGES_PER_TAB:
            print(f"[+] {vendor}: сторінка {page}")
            scroll_page(drv)
            rows = collect_images_on_page(drv, vendor)
            for r in rows:
                r["img_path"] = download_image(session, r["img"], r["vendor"], r["title"])
            all_rows.extend(rows)

            if not click_next(drv):
                print("    [i] Пагінація закінчилась.")
                break
            time.sleep(0.8)
            page += 1

    drv._cleanup()

    # дедуп по (vendor,title,img)
    uniq = {}
    for r in all_rows:
        uniq[(r["vendor"], r["title"], r["img"])] = r
    rows = list(uniq.values())

    with open(OUT / "images.csv", "w", newline="", encoding="utf-8-sig") as f:
        w = csv.DictWriter(f, fieldnames=["vendor","title","img","img_path"])
        w.writeheader(); w.writerows(rows)

    with open(OUT / "images.json", "w", encoding="utf-8") as f:
        json.dump(rows, f, ensure_ascii=False, indent=2)

    print(f"\n[✓] Готово. Збережено {len(rows)} записів.")
    print(f"CSV: {OUT/'images.csv'}")
    print(f"Папка з картинками: {IMG_DIR}")

if __name__ == "__main__":
    main()
