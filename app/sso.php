<?php
require_once __DIR__.'/config.php';

// Простий підписаний токен (HMAC). Краще мати JWT (lcobucci/jwt або firebase/php-jwt),
// але для MVP достатньо безпечного HMAC + короткий строк дії.
function sso_build_token(array $payload, int $ttlSeconds = 300): string {
  $payload['iat'] = time();
  $payload['exp'] = time() + $ttlSeconds;

  $data = base64_encode(json_encode($payload, JSON_UNESCAPED_UNICODE));
  $sig  = hash_hmac('sha256', $data, SSO_SECRET);
  return $data.'.'.$sig;
}

function sso_verify_token(string $token): ?array {
  [$data, $sig] = explode('.', $token, 2) + [null, null];
  if (!$data || !$sig) return null;
  $calc = hash_hmac('sha256', $data, SSO_SECRET);
  if (!hash_equals($calc, $sig)) return null;
  $payload = json_decode(base64_decode($data), true);
  if (!$payload || time() > ($payload['exp'] ?? 0)) return null;
  return $payload;
}
