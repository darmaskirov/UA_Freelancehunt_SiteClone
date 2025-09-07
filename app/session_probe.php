<?php
require_once __DIR__ . '/boot_session.php';
header('Content-Type: application/json; charset=utf-8');
echo json_encode([
  'session_id' => session_id(),
  'has_user'   => !empty($_SESSION['user']),
  'user'       => $_SESSION['user'] ?? null,
  'cookies'    => $_COOKIE,
  'boot_time'  => $_SESSION['__boot_loaded'] ?? null,
], JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE);
