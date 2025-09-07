<?php
require_once __DIR__.'/config.php';

function db(): PDO {
  static $pdo;
  if (!$pdo) {
    $pdo = new PDO(
      'mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset=utf8mb4',
      DB_USER, DB_PASS,
      [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
  }
  return $pdo;
}
