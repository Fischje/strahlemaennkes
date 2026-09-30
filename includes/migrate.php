<?php

/**
 * Führt alle noch nicht angewandten *.sql-Dateien aus /migrations aus.
 * Dateinamen sollten mit einer aufsteigenden Nummer beginnen, z. B. 001_initial.sql.
 */
function run_migrations(PDO $pdo, string $migrationDir): void
{
    $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS schema_migrations (
    version TEXT PRIMARY KEY,
    applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
)
SQL);

    $files = glob(rtrim($migrationDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . '*.sql') ?: [];
    sort($files, SORT_NATURAL);

    $applied = $pdo->query('SELECT version FROM schema_migrations')->fetchAll(PDO::FETCH_COLUMN);
    $applied = array_fill_keys($applied, true);

    foreach ($files as $file) {
        $version = basename($file);
        if (isset($applied[$version])) {
            continue;
        }

        $sql = file_get_contents($file);
        if ($sql === false) {
            throw new RuntimeException('Migration konnte nicht gelesen werden: ' . $version);
        }

        $pdo->beginTransaction();
        try {
            $pdo->exec($sql);
            $stmt = $pdo->prepare('INSERT INTO schema_migrations(version) VALUES(?)');
            $stmt->execute([$version]);
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw new RuntimeException('Migration fehlgeschlagen (' . $version . '): ' . $e->getMessage(), 0, $e);
        }
    }
}
