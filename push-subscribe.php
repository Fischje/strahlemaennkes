<?php
require_once __DIR__.'/includes/app.php';
$u = require_login();

$data = json_decode(file_get_contents('php://input'), true);
$keys = $data['keys'] ?? [];
if (empty($data['endpoint']) || empty($keys['p256dh']) || empty($keys['auth'])) {
    http_response_code(422);
    exit;
}

$st = $pdo->prepare(
    'INSERT INTO push_subscriptions(user_id,endpoint,p256dh,auth_token,user_agent,last_used_at)\n'
    . 'VALUES(?,?,?,?,?,CURRENT_TIMESTAMP)\n'
    . 'ON CONFLICT(endpoint) DO UPDATE SET\n'
    . 'user_id=excluded.user_id,\n'
    . 'p256dh=excluded.p256dh,\n'
    . 'auth_token=excluded.auth_token,\n'
    . 'user_agent=excluded.user_agent,\n'
    . 'last_used_at=CURRENT_TIMESTAMP'
);
$st->execute([
    $u['id'],
    $data['endpoint'],
    $keys['p256dh'],
    $keys['auth'],
    substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
]);
http_response_code(204);
