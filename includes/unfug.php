<?php
/**
 * Verbindung zu Grober Unfug (eigenes Repo, läuft unter /grober-unfug/, Daten im Schema grober_unfug derselben Datenbank).
 * Hier: Lesen der Events (Kalender, Übersicht), Push-Versand für Grober Unfug (Aufträge + Erinnerungen per Cron).
 * Fehlt das Schema oder das Leserecht (grober-unfug/bin/datenbank-rechte.sql), tut alles hier einfach nichts.
 */

/** Ob die Events von Grober Unfug lesbar sind (Schema da, Rechte gesetzt, Spalte published vorhanden). */
function unfug_available(PDO $pdo): bool {
    static $ok = null;
    if ($ok !== null) return $ok;
    try {
        $ok = (bool)$pdo->query("SELECT CASE
            WHEN NOT EXISTS (SELECT 1 FROM pg_namespace WHERE nspname='grober_unfug') THEN false
            WHEN NOT has_schema_privilege('grober_unfug','USAGE') THEN false
            WHEN to_regclass('grober_unfug.events') IS NULL OR to_regclass('grober_unfug.rsvps') IS NULL THEN false
            WHEN NOT EXISTS (SELECT 1 FROM information_schema.columns
                             WHERE table_schema='grober_unfug' AND table_name='events' AND column_name='published') THEN false
            ELSE has_table_privilege('grober_unfug.events','SELECT') AND has_table_privilege('grober_unfug.rsvps','SELECT') END")->fetchColumn();
    } catch (Throwable $e) {
        $ok = false;
    }
    return $ok;
}

const UNFUG_TYPE_LABELS = ['sommer' => 'Sommerausflug', 'weihnacht' => 'Weihnachtsfeier', 'reise' => 'Reise'];
const UNFUG_TYPE_COLORS = ['sommer' => '#FF7A66', 'weihnacht' => '#8B0000', 'reise' => '#0E7C8A'];

/** „Sa, 20.06.2026 · 10:00 Uhr“ bzw. „Fr, 12.09. bis So, 14.09.2027“. */
function unfug_when(array $ev): string {
    $d = new DateTimeImmutable((string)$ev['starts_on']);
    if (!empty($ev['ends_on']) && $ev['ends_on'] !== $ev['starts_on']) {
        $e = new DateTimeImmutable((string)$ev['ends_on']);
        return german_weekday_short($d) . ', ' . $d->format('d.m.') . ' bis ' . german_weekday_short($e) . ', ' . $e->format('d.m.Y');
    }
    return german_weekday_short($d) . ', ' . $d->format('d.m.Y') . (!empty($ev['start_time']) ? ' · ' . $ev['start_time'] . ' Uhr' : '');
}

/** Nächstes veröffentlichtes Event mit eigener Antwort und Banner (für die Übersicht) oder null. */
function unfug_next_event(PDO $pdo, int $userId): ?array {
    if (!unfug_available($pdo)) return null;
    $today = (new DateTimeImmutable('now', new DateTimeZone('Europe/Berlin')))->format('Y-m-d');
    $banner = 'NULL::bigint';
    try {
        if ($pdo->query("SELECT to_regclass('grober_unfug.event_images') IS NOT NULL AND has_table_privilege('grober_unfug.event_images','SELECT')")->fetchColumn()) {
            $banner = "(SELECT i.id FROM grober_unfug.event_images i WHERE i.event_id=e.id AND i.kind='banner' LIMIT 1)";
        }
        $q = $pdo->prepare("SELECT e.id, e.type, e.title, e.tagline, e.starts_on, e.ends_on, e.start_time, e.location, e.deadline, e.fee_cents,
                   r.status, r.paid, $banner AS banner_id
            FROM grober_unfug.events e
            LEFT JOIN grober_unfug.rsvps r ON r.event_id=e.id AND r.user_id=?
            WHERE e.published=1 AND COALESCE(e.ends_on, e.starts_on) >= ?
            ORDER BY e.starts_on, e.id LIMIT 1");
        $q->execute([$userId, $today]);
        return $q->fetch() ?: null;
    } catch (Throwable $e) {
        error_log('Strahlemännkes: Grober Unfug nicht lesbar: ' . $e->getMessage());
        return null;
    }
}

/** Einmal-Sperre für Erinnerungen: true, wenn diese Erinnerung jetzt (zum ersten Mal) verschickt werden darf. */
function unfug_push_claim(PDO $pdo, int $eventId, string $kind, string $key): bool {
    $q = $pdo->prepare('INSERT INTO unfug_push_log(event_id,kind,key) VALUES(?,?,?) ON CONFLICT DO NOTHING');
    $q->execute([$eventId, $kind, $key]);
    return $q->rowCount() === 1;
}

/**
 * Läuft im Minuten-Cron (bin/send-meeting-reminders.php):
 *  - Aufträge „Alle benachrichtigen“ aus Grober Unfug sofort verschicken,
 *  - 3 Tage vor der Frist an alle ohne Antwort,
 *  - 7 Tage vor dem Event an Zugesagte, die den Beitrag noch nicht bezahlt haben.
 * Erinnerungen nur zwischen 9 und 21 Uhr. Kategorie „unfug“ (erreicht auch Konten „nur Grober Unfug“).
 */
function unfug_push_run(PDO $pdo, DateTimeImmutable $now): void {
    if (!unfug_available($pdo)) return;

    $open = $pdo->query('SELECT q.id, q.event_id, q.title, q.body, q.url FROM unfug_push_queue q WHERE q.sent_at IS NULL ORDER BY q.id')->fetchAll();
    foreach ($open as $job) {
        $claim = $pdo->prepare('UPDATE unfug_push_queue SET sent_at=CURRENT_TIMESTAMP WHERE id=? AND sent_at IS NULL');
        $claim->execute([(int)$job['id']]);
        if ($claim->rowCount() !== 1) continue;
        $pub = $pdo->prepare('SELECT 1 FROM grober_unfug.events WHERE id=? AND published=1');
        $pub->execute([(int)$job['event_id']]);
        if (!$pub->fetchColumn()) continue;
        push_deliver($pdo, '', [], 'unfug',
            ['title' => $job['title'], 'body' => $job['body'], 'url' => $job['url'], 'tag' => 'unfug-' . (int)$job['event_id']], ['TTL' => 60 * 60 * 24]);
        echo 'Grober Unfug: Benachrichtigung ' . $job['event_id'] . PHP_EOL;
    }

    $hour = (int)$now->format('G');
    if ($hour < 9 || $hour >= 21) return;
    $nowLocal = $now->format('Y-m-d H:i:s');
    $today = $now->format('Y-m-d');

    // Frist läuft in den nächsten 3 Tagen ab.
    $q = $pdo->prepare('SELECT id, title, deadline FROM grober_unfug.events WHERE published=1 AND deadline > ? AND deadline <= ?');
    $q->execute([$nowLocal, $now->modify('+3 days')->format('Y-m-d H:i:s')]);
    foreach ($q->fetchAll() as $ev) {
        if (!unfug_push_claim($pdo, (int)$ev['id'], 'frist', substr((string)$ev['deadline'], 0, 16))) continue;
        $d = new DateTimeImmutable((string)$ev['deadline']);
        push_deliver($pdo, ' AND NOT EXISTS (SELECT 1 FROM grober_unfug.rsvps r WHERE r.event_id=? AND r.user_id=u.id)', [(int)$ev['id']], 'unfug',
            ['title' => $ev['title'] . ': Bist du dabei?', 'body' => 'Zu- oder absagen bis ' . german_weekday_short($d) . ', ' . $d->format('d.m. H:i') . ' Uhr.',
             'url' => UNFUG_PATH . 'event.php?id=' . (int)$ev['id'], 'tag' => 'unfug-frist-' . (int)$ev['id']], ['TTL' => 60 * 60 * 24]);
        echo 'Grober Unfug: Frist-Erinnerung ' . $ev['id'] . PHP_EOL;
    }

    // Beitrag offen, Event in den nächsten 7 Tagen.
    $q = $pdo->prepare('SELECT id, title, starts_on, fee_cents FROM grober_unfug.events WHERE published=1 AND fee_cents IS NOT NULL AND starts_on >= ? AND starts_on <= ?');
    $q->execute([$today, $now->modify('+7 days')->format('Y-m-d')]);
    foreach ($q->fetchAll() as $ev) {
        if (!unfug_push_claim($pdo, (int)$ev['id'], 'beitrag', (string)$ev['starts_on'])) continue;
        $fee = number_format(((int)$ev['fee_cents']) / 100, 2, ',', '.');
        push_deliver($pdo, " AND EXISTS (SELECT 1 FROM grober_unfug.rsvps r WHERE r.event_id=? AND r.user_id=u.id AND r.status='yes' AND r.paid=0)", [(int)$ev['id']], 'unfug',
            ['title' => $ev['title'] . ': Beitrag noch offen', 'body' => 'Bitte ' . preg_replace('/,00$/', '', $fee) . ' € überweisen, die Daten stehen beim Event.',
             'url' => UNFUG_PATH . 'event.php?id=' . (int)$ev['id'], 'tag' => 'unfug-beitrag-' . (int)$ev['id']], ['TTL' => 60 * 60 * 24]);
        echo 'Grober Unfug: Beitrags-Erinnerung ' . $ev['id'] . PHP_EOL;
    }
}
