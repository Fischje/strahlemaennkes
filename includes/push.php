<?php
/**
 * Web-Push-Versand. Alle drei öffentlichen Funktionen nutzen push_deliver(); sie liefern jeweils
 * [configured, queued, sent, failed, expired_removed, error].
 */

/** Kategorien für persönliche Push-Einstellungen (Schlüssel => [Titel, Beschreibung]). */
function push_categories(): array
{
    return [
        'stammtisch' => ['Stammtisch', 'Neue Terminfindung, festgelegter Termin, Absage und Erinnerungen.'],
        'sektbar'    => ['Sektbar', 'Wenn der Sektbarmodus startet.'],
        'getraenke'  => ['Getränke', 'Erinnerung zur Getränkewahl und Hinweise zu Getränkewechseln.'],
        'runden'     => ['Runden', 'Wenn du eine Runde holen sollst oder eine Runde als gegeben markiert wurde.'],
    ];
}

/** SQL-Zusatz: schließt Mitglieder aus, die die Kategorie abgeschaltet haben. */
function push_category_filter(string $category): string
{
    return $category === '' ? '' : " AND NOT EXISTS(SELECT 1 FROM user_push_prefs pp WHERE pp.user_id=u.id AND pp.category=? AND pp.enabled=0)";
}

/**
 * Gemeinsamer Versand: wählt die Push-Abos über $whereSql (Bedingungen auf users u / push_subscriptions s)
 * aus, verschickt $payload und entfernt abgelaufene Abos.
 */
function push_deliver(PDO $pdo, string $whereSql, array $params, string $category, array $payload, array $options): array
{
    $result = ['configured' => false, 'queued' => 0, 'sent' => 0, 'failed' => 0, 'expired_removed' => 0, 'error' => null];

    $config = require __DIR__ . '/../config.php';
    $push = $config['push'] ?? [];
    $subject = trim((string)($push['subject'] ?? ''));
    $publicKey = trim((string)($push['public_key'] ?? ''));
    $privateKey = trim((string)($push['private_key'] ?? ''));
    if ($subject === '' || $publicKey === '' || $privateKey === '') {
        $result['error'] = 'VAPID-Schlüssel sind noch nicht vollständig konfiguriert.';
        return $result;
    }

    $autoload = __DIR__ . '/../vendor/autoload.php';
    if (!is_file($autoload)) {
        $result['error'] = 'Composer-Abhängigkeiten für Web Push fehlen. Bitte im App-Verzeichnis „composer install --no-dev --optimize-autoloader“ ausführen.';
        return $result;
    }
    require_once $autoload;
    if (!class_exists(\Minishlink\WebPush\WebPush::class) || !class_exists(\Minishlink\WebPush\Subscription::class)) {
        $result['error'] = 'Web-Push-Bibliothek konnte nicht geladen werden.';
        return $result;
    }
    $result['configured'] = true;

    $sql = "SELECT s.endpoint,s.p256dh,s.auth_token
            FROM push_subscriptions s
            JOIN users u ON u.id=s.user_id
            LEFT JOIN user_drink_preferences p ON p.user_id=u.id
            WHERE u.is_active=1" . $whereSql . push_category_filter($category) . "
            ORDER BY s.id";
    if ($category !== '') $params[] = $category;
    $st = $pdo->prepare($sql);
    $st->execute($params);
    $subscriptions = $st->fetchAll();
    if (!$subscriptions) return $result;

    try {
        $webPush = new \Minishlink\WebPush\WebPush([
            'VAPID' => ['subject' => $subject, 'publicKey' => $publicKey, 'privateKey' => $privateKey],
        ], $options + ['urgency' => 'high']);

        $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        foreach ($subscriptions as $row) {
            $webPush->queueNotification(\Minishlink\WebPush\Subscription::create([
                'endpoint' => $row['endpoint'],
                'publicKey' => $row['p256dh'],
                'authToken' => $row['auth_token'],
                'contentEncoding' => 'aes128gcm',
            ]), $body);
            $result['queued']++;
        }

        $deleteExpired = $pdo->prepare('DELETE FROM push_subscriptions WHERE endpoint=?');
        foreach ($webPush->flush() as $report) {
            if ($report->isSuccess()) {
                $result['sent']++;
                continue;
            }
            $result['failed']++;
            if ($report->isSubscriptionExpired()) {
                $deleteExpired->execute([$report->getEndpoint()]);
                $result['expired_removed'] += $deleteExpired->rowCount();
            } else {
                error_log('Strahlemännkes Web Push fehlgeschlagen (' . ($payload['tag'] ?? '') . '): ' . $report->getReason());
            }
        }
    } catch (Throwable $e) {
        error_log('Strahlemännkes Web Push Fehler: ' . $e->getMessage());
        $result['error'] = 'Push-Versand fehlgeschlagen.';
    }

    return $result;
}

/** Getränke-Erinnerung an alle aktiven Mitglieder oder nur an die ohne Auswahl. */
function send_drink_reminder_pushes(PDO $pdo, string $target, string $message, string $category = 'getraenke'): array
{
    $where = $target === 'members_without_choice' ? ' AND (p.user_id IS NULL OR p.drink_id IS NULL)' : '';
    return push_deliver($pdo, $where, [], $category,
        ['title' => 'Strahlemännkes · Getränkerunde', 'body' => $message, 'url' => '/member-drink.php', 'tag' => 'drink-reminder'],
        ['TTL' => 60 * 60, 'topic' => 'drink-reminder']);
}

/** Push an alle Geräte eines Mitglieds. */
function send_push_to_user(PDO $pdo, int $userId, string $title, string $message, string $url = '/member-drink.php', string $tag = 'strahlemaennkes', string $category = ''): array
{
    return push_deliver($pdo, ' AND s.user_id=?', [$userId], $category,
        ['title' => $title, 'body' => $message, 'url' => $url, 'tag' => $tag],
        ['TTL' => 60 * 30]);
}

/** Push an alle Geräte aller aktiven Mitglieder. */
function send_push_to_all_active_users(PDO $pdo, string $title, string $message, string $url = '/dashboard.php', string $tag = 'strahlemaennkes', string $category = ''): array
{
    return push_deliver($pdo, '', [], $category,
        ['title' => $title, 'body' => $message, 'url' => $url, 'tag' => $tag],
        ['TTL' => 60 * 60]);
}
