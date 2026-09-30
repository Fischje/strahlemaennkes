<?php
$config = require __DIR__ . '/../config.php';
$db = $config['db'] ?? [];

try {
    $host = $db['host'] ?? '127.0.0.1';
    $port = (int)($db['port'] ?? 5432);
    $name = $db['name'] ?? 'strahlemaennkes';
    $sslmode = $db['sslmode'] ?? 'prefer';
    $dsn = "pgsql:host={$host};port={$port};dbname={$name};sslmode={$sslmode}";
    $pdo = new PDO($dsn, $db['user'] ?? '', $db['password'] ?? '', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    $pdo->exec("SET TIME ZONE 'UTC'");
    require_once __DIR__ . '/migrate.php';
    run_migrations($pdo, __DIR__ . '/../migrations');
} catch (Throwable $e) {
    http_response_code(500);
    exit('PostgreSQL-Datenbank konnte nicht geöffnet oder aktualisiert werden: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'));
}
