<?php
require_once __DIR__.'/includes/app.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

try {
    $u = require_login();

    $raw = file_get_contents('php://input');
    $data = json_decode($raw ?: '', true, 512, JSON_THROW_ON_ERROR);
    $keys = $data['keys'] ?? [];

    if (empty($data['endpoint']) || empty($keys['p256dh']) || empty($keys['auth'])) {
        http_response_code(422);
        echo json_encode([
            'ok' => false,
            'error' => 'Das Push-Abo enthält nicht alle erforderlichen Browser-Schlüssel.',
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $sql = <<<'SQL'
INSERT INTO push_subscriptions (
    user_id,
    endpoint,
    p256dh,
    auth_token,
    user_agent,
    last_used_at
)
VALUES (?, ?, ?, ?, ?, CURRENT_TIMESTAMP)
ON CONFLICT(endpoint) DO UPDATE SET
    user_id = excluded.user_id,
    p256dh = excluded.p256dh,
    auth_token = excluded.auth_token,
    user_agent = excluded.user_agent,
    last_used_at = CURRENT_TIMESTAMP
SQL;

    $st = $pdo->prepare($sql);
    $st->execute([
        (int)$u['id'],
        (string)$data['endpoint'],
        (string)$keys['p256dh'],
        (string)$keys['auth'],
        substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
    ]);

    echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
} catch (JsonException $e) {
    http_response_code(400);
    echo json_encode([
        'ok' => false,
        'error' => 'Ungültige Push-Daten vom Browser.',
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    error_log('Strahlemännkes Push-Abo speichern fehlgeschlagen: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => 'Serverfehler beim Speichern des Push-Abos: ' . $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
}
