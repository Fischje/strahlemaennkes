<?php
require_once __DIR__.'/includes/app.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

try {
    $u = require_login();
    $raw = file_get_contents('php://input');
    $data = json_decode($raw ?: '{}', true, 512, JSON_THROW_ON_ERROR);
    $endpoint = trim((string)($data['endpoint'] ?? ''));

    if ($endpoint !== '') {
        $st = $pdo->prepare('DELETE FROM push_subscriptions WHERE user_id=? AND endpoint=?');
        $st->execute([(int)$u['id'], $endpoint]);
    } else {
        // Fallback: alle gespeicherten Geräte dieses Accounts entfernen.
        $pdo->prepare('DELETE FROM push_subscriptions WHERE user_id=?')->execute([(int)$u['id']]);
    }

    echo json_encode(['ok'=>true], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    error_log('Strahlemännkes Push-Abo entfernen fehlgeschlagen: '.$e->getMessage());
    http_response_code(500);
    echo json_encode([
        'ok'=>false,
        'error'=>'Push-Abo konnte serverseitig nicht entfernt werden: '.$e->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
}
