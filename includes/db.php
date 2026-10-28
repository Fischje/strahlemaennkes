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
    // Details nur ins Log bzw. auf die Kommandozeile – im Browser keine Server-Interna zeigen.
    error_log('Strahlemännkes: PostgreSQL-Fehler: ' . $e->getMessage());
    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, 'PostgreSQL-Datenbank konnte nicht geöffnet oder aktualisiert werden: ' . $e->getMessage() . PHP_EOL);
        exit(1);
    }
    http_response_code(503);
    exit('Die App ist gerade nicht erreichbar (Datenbank). Bitte in ein paar Minuten erneut versuchen.');
}
