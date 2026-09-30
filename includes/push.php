<?php

/**
 * Sendet die Getränkeerinnerung per Web Push.
 *
 * Rückgabe:
 * [
 *   configured => bool,
 *   queued => int,
 *   sent => int,
 *   failed => int,
 *   expired_removed => int,
 *   error => ?string
 * ]
 */
function send_drink_reminder_pushes(PDO $pdo, string $target, string $message): array
{
    $result = [
        'configured' => false,
        'queued' => 0,
        'sent' => 0,
        'failed' => 0,
        'expired_removed' => 0,
        'error' => null,
    ];

    $config = require __DIR__ . '/../config.php';
    $push = $config['push'] ?? [];
    $subject = trim((string)($push['subject'] ?? ''));
    $publicKey = trim((string)($push['public_key'] ?? ''));
    $privateKey = trim((string)($push['private_key'] ?? ''));

    if ($subject === '' || $publicKey === '' || $privateKey === '') {
        $result['error'] = 'VAPID-Schlüssel sind noch nicht vollständig in config.php eingetragen.';
        return $result;
    }

    $autoload = __DIR__ . '/../vendor/autoload.php';
    if (!is_file($autoload)) {
        $result['error'] = 'Composer-Abhängigkeiten fehlen. Bitte im App-Verzeichnis „composer install --no-dev --optimize-autoloader“ ausführen.';
        return $result;
    }

    require_once $autoload;

    if (!class_exists(\Minishlink\WebPush\WebPush::class) || !class_exists(\Minishlink\WebPush\Subscription::class)) {
        $result['error'] = 'Die Web-Push-Bibliothek konnte nicht geladen werden.';
        return $result;
    }

    $result['configured'] = true;

    $sql = "
        SELECT s.endpoint,s.p256dh,s.auth_token
        FROM push_subscriptions s
        JOIN users u ON u.id=s.user_id
        LEFT JOIN user_drink_preferences p ON p.user_id=u.id
        WHERE u.is_active=1
    ";
    if ($target === 'members_without_choice') {
        $sql .= " AND (p.user_id IS NULL OR p.drink_id IS NULL)";
    }

    $subscriptions = $pdo->query($sql)->fetchAll();
    if (!$subscriptions) {
        return $result;
    }

    try {
        $webPush = new \Minishlink\WebPush\WebPush([
            'VAPID' => [
                'subject' => $subject,
                'publicKey' => $publicKey,
                'privateKey' => $privateKey,
            ],
        ], [
            'TTL' => 60 * 60,
            'urgency' => 'high',
            'topic' => 'drink-reminder',
        ]);

        $payload = json_encode([
            'title' => 'Strahlemännkes · Getränkerunde',
            'body' => $message,
            'url' => '/member-drink.php',
            'tag' => 'drink-reminder',
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

        foreach ($subscriptions as $row) {
            $subscription = \Minishlink\WebPush\Subscription::create([
                'endpoint' => $row['endpoint'],
                'publicKey' => $row['p256dh'],
                'authToken' => $row['auth_token'],
                'contentEncoding' => 'aes128gcm',
            ]);
            $webPush->queueNotification($subscription, $payload);
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
                error_log('Strahlemännkes Web Push fehlgeschlagen: ' . $report->getReason());
            }
        }
    } catch (Throwable $e) {
        $result['error'] = $e->getMessage();
    }

    return $result;
}


/**
 * Sendet eine einzelne Push-Nachricht an alle registrierten Geräte eines Mitglieds.
 */
function send_push_to_user(PDO $pdo, int $userId, string $title, string $message, string $url='/member-drink.php', string $tag='strahlemaennkes'): array
{
    $result = [
        'configured' => false,
        'queued' => 0,
        'sent' => 0,
        'failed' => 0,
        'expired_removed' => 0,
        'error' => null,
    ];

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
        $result['error'] = 'Composer-Abhängigkeiten für Web Push fehlen.';
        return $result;
    }

    require_once $autoload;
    if (!class_exists(\Minishlink\WebPush\WebPush::class) || !class_exists(\Minishlink\WebPush\Subscription::class)) {
        $result['error'] = 'Web-Push-Bibliothek konnte nicht geladen werden.';
        return $result;
    }

    $result['configured'] = true;

    $st = $pdo->prepare("
        SELECT s.endpoint,s.p256dh,s.auth_token
        FROM push_subscriptions s
        JOIN users u ON u.id=s.user_id
        WHERE s.user_id=? AND u.is_active=1
    ");
    $st->execute([$userId]);
    $subscriptions = $st->fetchAll();
    if (!$subscriptions) return $result;

    try {
        $webPush = new \Minishlink\WebPush\WebPush([
            'VAPID' => [
                'subject' => $subject,
                'publicKey' => $publicKey,
                'privateKey' => $privateKey,
            ],
        ], [
            'TTL' => 60 * 30,
            'urgency' => 'high',
        ]);

        $payload = json_encode([
            'title' => $title,
            'body' => $message,
            'url' => $url,
            'tag' => $tag,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

        foreach ($subscriptions as $row) {
            $subscription = \Minishlink\WebPush\Subscription::create([
                'endpoint' => $row['endpoint'],
                'publicKey' => $row['p256dh'],
                'authToken' => $row['auth_token'],
                'contentEncoding' => 'aes128gcm',
            ]);
            $webPush->queueNotification($subscription, $payload);
            $result['queued']++;
        }

        $deleteExpired = $pdo->prepare('DELETE FROM push_subscriptions WHERE endpoint=?');
        foreach ($webPush->flush() as $report) {
            if ($report->isSuccess()) {
                $result['sent']++;
            } else {
                $result['failed']++;
                if ($report->isSubscriptionExpired()) {
                    $deleteExpired->execute([$report->getEndpoint()]);
                    $result['expired_removed'] += $deleteExpired->rowCount();
                } else {
                    error_log('Strahlemännkes Einzel-Push fehlgeschlagen: '.$report->getReason());
                }
            }
        }
    } catch (Throwable $e) {
        $result['error'] = $e->getMessage();
    }

    return $result;
}


/**
 * Sendet eine Push-Nachricht an alle registrierten Geräte aktiver Mitglieder.
 */
function send_push_to_all_active_users(PDO $pdo, string $title, string $message, string $url='/dashboard.php', string $tag='strahlemaennkes'): array
{
    $result = [
        'configured' => false,
        'queued' => 0,
        'sent' => 0,
        'failed' => 0,
        'expired_removed' => 0,
        'error' => null,
    ];

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
        $result['error'] = 'Composer-Abhängigkeiten für Web Push fehlen.';
        return $result;
    }

    require_once $autoload;
    if (!class_exists(\Minishlink\WebPush\WebPush::class) || !class_exists(\Minishlink\WebPush\Subscription::class)) {
        $result['error'] = 'Web-Push-Bibliothek konnte nicht geladen werden.';
        return $result;
    }

    $result['configured'] = true;

    $subscriptions = $pdo->query("
        SELECT s.endpoint,s.p256dh,s.auth_token
        FROM push_subscriptions s
        JOIN users u ON u.id=s.user_id
        WHERE u.is_active=1
        ORDER BY s.id
    ")->fetchAll();

    if (!$subscriptions) return $result;

    try {
        $webPush = new \Minishlink\WebPush\WebPush([
            'VAPID' => [
                'subject' => $subject,
                'publicKey' => $publicKey,
                'privateKey' => $privateKey,
            ],
        ], [
            'TTL' => 60 * 60,
            'urgency' => 'high',
        ]);

        $payload = json_encode([
            'title' => $title,
            'body' => $message,
            'url' => $url,
            'tag' => $tag,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

        foreach ($subscriptions as $row) {
            $subscription = \Minishlink\WebPush\Subscription::create([
                'endpoint' => $row['endpoint'],
                'publicKey' => $row['p256dh'],
                'authToken' => $row['auth_token'],
                'contentEncoding' => 'aes128gcm',
            ]);
            $webPush->queueNotification($subscription, $payload);
            $result['queued']++;
        }

        $deleteExpired = $pdo->prepare('DELETE FROM push_subscriptions WHERE endpoint=?');
        foreach ($webPush->flush() as $report) {
            if ($report->isSuccess()) {
                $result['sent']++;
            } else {
                $result['failed']++;
                if ($report->isSubscriptionExpired()) {
                    $deleteExpired->execute([$report->getEndpoint()]);
                    $result['expired_removed'] += $deleteExpired->rowCount();
                } else {
                    error_log('Strahlemännkes News-Push fehlgeschlagen: '.$report->getReason());
                }
            }
        }
    } catch (Throwable $e) {
        $result['error'] = $e->getMessage();
    }

    return $result;
}
