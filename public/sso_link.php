<?php
require_once __DIR__.'/../app/helpers.php';
require_once __DIR__.'/../app/auth.php';
require_once __DIR__.'/../app/sso.php';
require_auth();

$u = current_user();
$token = sso_build_token([
  'sub' => $u['id'],
  'email' => $u['email'],
  'name' => $u['name'],
  'balance' => $u['balance'],
]);

// Якщо у клієнта просто звичайне "перейти по посиланню" — цього достатньо.
// Коли дасть фінальний URL — внесеш у config.
$target = SSO_TARGET_URL; // замінити пізніше
$sep = (strpos($target,'?')!==false) ? '&' : '?';
redirect($target.$sep.'token='.urlencode($token));
