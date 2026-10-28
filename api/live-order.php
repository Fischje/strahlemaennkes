<?php
// Live-Stand der Getränkewünsche (Theke und „Runde holen“ nutzen dieselben Daten).
require_once __DIR__ . '/../includes/app.php';
require_login();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
echo json_encode(live_drink_status($pdo), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
