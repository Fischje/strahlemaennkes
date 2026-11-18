<?php
// Öffentlicher Push-Schlüssel (VAPID) zum Einschalten von Mitteilungen, z. B. aus Grober Unfug heraus.
require_once __DIR__.'/includes/app.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
require_login();
$pushConfig = (require __DIR__.'/config.php')['push'] ?? [];
echo json_encode(['publicKey' => (string)($pushConfig['public_key'] ?? '')]);
