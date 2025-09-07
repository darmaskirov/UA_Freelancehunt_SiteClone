<?php
if (session_status() === PHP_SESSION_NONE) session_start();

function redirect(string $url) {
  header("Location: $url");
  exit;
}

function is_post(): bool { return $_SERVER['REQUEST_METHOD'] === 'POST'; }

function csrf_token(): string {
  if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
  return $_SESSION['csrf'];
}
function csrf_check(string $token): bool {
  return isset($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $token);
}

function e(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
