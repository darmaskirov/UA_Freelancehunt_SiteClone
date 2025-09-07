<?php
// public/admin/admin_api.php
if (session_status()===PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../../config/config.php';

// Перевірка адміна
$me = null;
if (!empty($_SESSION['user_id'])) {
  $st = $conn->prepare("SELECT id,username,role FROM users WHERE id=? LIMIT 1");
  $st->execute([$_SESSION['user_id']]);
  $me = $st->fetch();
}
if (!$me || $me['role']!=='admin') { http_response_code(403); echo json_encode(['ok'=>false,'err'=>'forbidden']); exit; }

// CSRF
$csrf = $_POST['csrf'] ?? '';
if (!$csrf || !hash_equals($_SESSION['csrf'] ?? '', $csrf)) {
  http_response_code(403); echo json_encode(['ok'=>false,'err'=>'bad_csrf']); exit;
}

$op = $_POST['op'] ?? '';

function admin_log(PDO $conn, int $admin_id, string $action, array $details=[]): void {
  $st = $conn->prepare("INSERT INTO admin_logs (admin_id, action, details) VALUES (?,?,?)");
  $st->execute([$admin_id, $action, json_encode($details, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)]);
}

try {
  if ($op === 'user_update') {
    $uid = (int)($_POST['user_id']??0);
    $username = trim($_POST['username']??'');
    $email    = trim($_POST['email']??'');
    $role     = $_POST['role']??'user';
    $status   = $_POST['status']??'active';
    $currency = $_POST['currency']??'USD';
    if (!$uid || $username==='' || $email==='') throw new RuntimeException("bad_input");

    $st = $conn->prepare("UPDATE users SET username=?, email=?, role=?, status=?, currency=? WHERE id=?");
    $st->execute([$username,$email,$role,$status,$currency,$uid]);

    admin_log($conn, $me['id'], 'user_update', compact('uid','role','status','currency'));
    echo json_encode(['ok'=>true]); exit;
  }

  if ($op === 'user_pass_reset') {
    $uid = (int)($_POST['user_id']??0);
    $new = $_POST['new_password'] ?? '';
    if ($uid<=0 || strlen($new)<6) throw new RuntimeException("bad_input");
    $hash = password_hash($new, PASSWORD_BCRYPT);
    $conn->prepare("UPDATE users SET password_hash=? WHERE id=?")->execute([$hash,$uid]);
    admin_log($conn, $me['id'], 'user_pass_reset', ['user_id'=>$uid]);
    echo json_encode(['ok'=>true]); exit;
  }

  if ($op === 'balance') {
    $uid = (int)($_POST['user_id']??0);
    $type = $_POST['type']??'deposit'; // deposit|withdraw|bonus
    $amount = (float)($_POST['amount']??0);
    $currency = $_POST['currency']??'USD';
    if ($uid<=0 || $amount<=0) throw new RuntimeException("bad_input");

    $conn->beginTransaction();
    try {
      $st = $conn->prepare("SELECT amount,currency FROM balances WHERE user_id=? LIMIT 1");
      $st->execute([$uid]);
      $row = $st->fetch();

      if (!$row) {
        $conn->prepare("INSERT INTO balances (user_id,currency,amount) VALUES (?,?,0.00)")
             ->execute([$uid,$currency]);
        $amountOld = 0.00;
      } else {
        $amountOld = (float)$row['amount'];
        if ($row['currency']!==$currency) {
          $conn->prepare("UPDATE balances SET currency=? WHERE user_id=?")->execute([$currency,$uid]);
        }
      }

      $delta = ($type==='withdraw') ? -$amount : +$amount;
      $amountNew = round($amountOld + $delta, 2);
      if ($amountNew < 0) throw new RuntimeException("insufficient_funds");

      $conn->prepare("UPDATE balances SET amount=? WHERE user_id=?")->execute([$amountNew,$uid]);
      $conn->prepare("INSERT INTO transactions (user_id,type,amount,status) VALUES (?,?,?,'completed')")
           ->execute([$uid,$type,abs($amount)]);

      admin_log($conn, $me['id'], "balance_$type",
        ['user_id'=>$uid,'delta'=>$delta,'currency'=>$currency,'newAmount'=>$amountNew]);

      $conn->commit();
    } catch(Throwable $e) { $conn->rollBack(); throw $e; }

    echo json_encode(['ok'=>true]); exit;
  }

  if ($op === 'toggle_active') {
    $uid = (int)($_POST['user_id']??0);
    $to  = $_POST['to_status'] ?? 'inactive'; // inactive|active|banned|pending
    if ($uid<=0) throw new RuntimeException("bad_input");
    $conn->prepare("UPDATE users SET status=? WHERE id=?")->execute([$to,$uid]);
    admin_log($conn, $me['id'], 'user_status', ['user_id'=>$uid,'to'=>$to]);
    echo json_encode(['ok'=>true]); exit;
  }

  if ($op === 'user_delete') {
    $uid = (int)($_POST['user_id']??0);
    if ($uid<=0) throw new RuntimeException("bad_input");

    $conn->beginTransaction();
    try {
      $conn->prepare("DELETE FROM balances WHERE user_id=?")->execute([$uid]);
      $conn->prepare("DELETE FROM transactions WHERE user_id=?")->execute([$uid]);
      $conn->prepare("DELETE FROM profiles WHERE user_id=?")->execute([$uid]);
      $conn->prepare("DELETE FROM users WHERE id=?")->execute([$uid]);
      admin_log($conn, $me['id'], 'user_delete', ['user_id'=>$uid]);
      $conn->commit();
    } catch(Throwable $e) { $conn->rollBack(); throw $e; }

    echo json_encode(['ok'=>true]); exit;
  }

  throw new RuntimeException("unknown_op");
} catch(Throwable $e) {
  http_response_code(400);
  echo json_encode(['ok'=>false,'err'=>$e->getMessage()]);
}
