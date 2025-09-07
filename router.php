<?php
declare(strict_types=1);

ini_set('display_errors','1');
ini_set('display_startup_errors','1');
error_reporting(E_ALL);

// 1) Підтягнути таблицю маршрутів
$routes = require __DIR__ . '/routes.php';

// 2) Витягти шлях без query
$reqPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

// 3) Визначити базовий префікс (папка проекту) і зрізати його
// Напр. при URL /dfbiu/... і розміщенні .htaccess у /dfbiu/
$phpSelfDir = str_replace('\\','/', dirname($_SERVER['PHP_SELF'] ?? '')); // напр. /dfbiu/config
$base = preg_replace('#/config$#','', $phpSelfDir) ?: '';
if ($base && $base !== '/' && strpos($reqPath, $base) === 0) {
    $reqPath = substr($reqPath, strlen($base));
}

// 4) Нормалізація: прибрати зайві слеші, але зберегти корінь
$reqPath = '/' . trim($reqPath, '/');
if ($reqPath === '//') $reqPath = '/';

// 5) Знайти відповідний файл
$fileRel = $routes[$reqPath] ?? null;
$projectRoot = realpath(__DIR__ . '/..'); // корінь проекту: dfbiu/
if ($fileRel) {
    $target = $projectRoot . '/' . ltrim($fileRel, '/');
    if (is_file($target)) {
        require $target;
        exit;
    } else {
        http_response_code(404);
        echo "<h1>404</h1><p>Маршрут знайдено, але файл відсутній: {$fileRel}</p>";
        exit;
    }
}

// 6) Фолбек 404
http_response_code(404);
echo "<h1>404</h1><p>Маршрут не знайдено для: {$reqPath}</p>";
