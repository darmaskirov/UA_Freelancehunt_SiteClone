<?php
// public/logout.php
require_once __DIR__ . '/../app/auth.php';

// Куди вертати після виходу:
// 1) ?next=/membership/user-info
// 2) або HTTP_REFERER
// 3) або на головну
$next = $_GET['next'] 
    ?? ($_SERVER['HTTP_REFERER'] ?? (BASE_URL . '/'));

do_logout();
redirect($next);
