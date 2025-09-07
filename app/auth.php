<?php
declare(strict_types=1);

// ЄДИНИЙ вхід: boot_session дає db(), current_user(), redirect()
require_once __DIR__ . '/boot_session.php';

/**
 * Пошук користувача за логіном/емейлом з приєднанням балансу
 */
function find_user_by_login(PDO $pdo, string $login): ?array {
  $sql = "
    SELECT
      u.id, u.username, u.email, u.password_hash, u.role, u.status,
      u.currency AS user_currency,
      COALESCE(b.amount, 0)     AS balance_amount,
      COALESCE(b.currency, u.currency, 'USD') AS balance_currency
    FROM users u
    LEFT JOIN balances b ON b.user_id = u.id
    WHERE u.username = :login OR u.email = :login
    LIMIT 1
  ";
  $stmt = $pdo->prepare($sql);
  $stmt->execute([':login' => $login]);
  $row = $stmt->fetch(PDO::FETCH_ASSOC);
  return $row ?: null;
}

/**
 * Перевірка пароля.
 * Нормальний шлях — bcrypt у password_hash.
 * Тимчасовий фолбек лишаю лише на випадок старих plain записів (як у тебе був).
 */
function verify_password(string $password, string $hash): bool {
  $info = password_get_info($hash);
  if ($info['algo']) {
    return password_verify($password, $hash);
  }
  // legacy/plaintext fallback (позбудься якнайшвидше, перезапиши в bcrypt)
  return hash_equals($hash, $password);
}

/**
 * Логін: повертає масив користувача або null
 */
function attempt_login(string $login, string $password): ?array {
  $pdo = db();
  $u = find_user_by_login($pdo, $login);
  if (!$u) return null;
  if ($u['status'] !== 'active') return null;
  if (!verify_password($password, $u['password_hash'])) return null;
  return $u;
}
