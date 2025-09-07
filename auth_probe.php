<?php
declare(strict_types=1);
if (session_status()===PHP_SESSION_NONE) session_start();
header('Content-Type: application/json; charset=utf-8');

// === НАЛАШТУВАННЯ (як у navbar.php) ===
$DB_HOST='127.0.0.1'; $DB_USER='root'; $DB_PASS=''; $DB_NAME='dfbiu_clone';
$PASSWORD_COLUMNS = ['password_hash','password','pass']; // перевіримо кілька можливих колонок
// ======================================

function db(): mysqli {
  global $DB_HOST,$DB_USER,$DB_PASS,$DB_NAME;
  $m = @new mysqli($DB_HOST,$DB_USER,$DB_PASS,$DB_NAME);
  if ($m->connect_errno) { http_response_code(500); echo json_encode(['ok'=>false,'step'=>'db', 'err'=>$m->connect_error]); exit; }
  $m->set_charset('utf8mb4'); return $m;
}

$u = trim($_GET['u'] ?? '');
$p = (string)($_GET['p'] ?? '');
if ($u==='' || $p==='') { http_response_code(400); echo json_encode(['ok'=>false,'step'=>'input','msg'=>'u&p required']); exit; }

$conn = db();
$dbname = $conn->query('SELECT DATABASE() AS d')->fetch_assoc()['d'] ?? null;

// знайдемо користувача
$stmt = $conn->prepare("SELECT * FROM users WHERE username=? OR email=? LIMIT 1");
$stmt->bind_param('ss',$u,$u);
$stmt->execute();
$res = $stmt->get_result();
$user = $res? $res->fetch_assoc() : null;
$stmt->close();

if (!$user) { echo json_encode(['ok'=>false,'step'=>'user','db'=>$dbname,'msg'=>'user not found','login'=>$u]); exit; }

// вичислимо, в якій колонці реальний хеш
$foundCol = null; $hash = '';
foreach ($PASSWORD_COLUMNS as $col) {
  if (array_key_exists($col,$user) && !empty($user[$col])) { $foundCol=$col; $hash=(string)$user[$col]; break; }
}
if (!$foundCol) { echo json_encode(['ok'=>false,'step'=>'hash','db'=>$dbname,'msg'=>'no password column with value','columns'=>$PASSWORD_COLUMNS]); exit; }

$verify = password_verify($p, $hash);

// Додатково: вкажемо довжину хеша (щоб упевнитись, що його не обрізало)
echo json_encode([
  'ok'=>$verify,
  'step'=>'verify',
  'db'=>$dbname,
  'user'=>['id'=>$user['id']??null,'username'=>$user['username']??null,'email'=>$user['email']??null],
  'hash_col'=>$foundCol,
  'hash_len'=>strlen($hash),
  'hash_prefix'=>substr($hash,0,7), // $2y$12?
], JSON_PRETTY_PRINT);
