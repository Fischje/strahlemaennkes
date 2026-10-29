<?php
/**
 * Kalender: feste Kategorien, Rechte, Abfragen und die Übernahme des Antretens.
 * Termine liegen in calendar_events (Ortszeit), Stammtische kommen direkt aus meetings.
 */

/** Feste Kategorien. Farben und Namen sind bewusst nicht änderbar, neue Kategorien gibt es (noch) nicht. */
const CALENDAR_CATEGORIES = [
    'fruehkirmes'     => ['label' => 'Früh-Kirmes',      'color' => '#43A047', 'text' => '#FFFFFF'],
    'spaetkirmes'     => ['label' => 'Spät-Kirmes',      'color' => '#1B5E20', 'text' => '#FFFFFF'],
    'zugtreffen'      => ['label' => 'Zugtreffen',       'color' => '#FBC02D', 'text' => '#212429'],
    'ausserhalb'      => ['label' => 'Außerhalb Kirmes', 'color' => '#7A808A', 'text' => '#FFFFFF'],
    'ausflug'         => ['label' => 'Großer Ausflug',   'color' => '#E53935', 'text' => '#FFFFFF'],
    'weihnachtsfeier' => ['label' => 'Weihnachtsfeier',  'color' => '#8B0000', 'text' => '#FFFFFF'],
];

/** Pflegen dürfen: Zugführer, Spieß (inkl. Vertretung), Schriftführer und Admin. */
function can_edit_calendar(PDO $pdo, ?array $user=null): bool {
    return is_leader() || is_secretary() || can_act_as_spiess($pdo, $user);
}

/** Alle Kalendereinträge (eigene Termine + festgelegte Stammtische) als eine Abfrage. */
const CALENDAR_UNION_SQL = <<<'SQL'
SELECT 'event' AS kind, e.id, e.title, e.category, e.event_date, e.start_time, e.end_time, e.location, e.note, e.source,
       TRIM(COALESCE(c.first_name,'')||' '||COALESCE(c.last_name,'')) AS author, NULL::varchar AS status
FROM calendar_events e LEFT JOIN users c ON c.id=e.created_by
UNION ALL
SELECT 'meeting', m.id, m.title, 'zugtreffen',
       ((m.starts_at_utc AT TIME ZONE 'UTC') AT TIME ZONE 'Europe/Berlin')::date,
       ((m.starts_at_utc AT TIME ZONE 'UTC') AT TIME ZONE 'Europe/Berlin')::time,
       NULL::time, m.location, m.note, 'meeting',
       TRIM(COALESCE(c.first_name,'')||' '||COALESCE(c.last_name,'')), m.status
FROM meetings m LEFT JOIN users c ON c.id=m.created_by
WHERE m.status IN ('scheduled','running','completed') AND m.starts_at_utc IS NOT NULL
SQL;
const CALENDAR_ORDER_SQL = ' ORDER BY event_date, start_time NULLS FIRST, title, kind, id';

/** Einträge zwischen zwei Tagen (jeweils einschließlich, Format Y-m-d). */
function calendar_entries_between(PDO $pdo, string $from, string $to): array {
    $q = $pdo->prepare('SELECT * FROM (' . CALENDAR_UNION_SQL . ') x WHERE event_date BETWEEN ? AND ?' . CALENDAR_ORDER_SQL);
    $q->execute([$from, $to]);
    return array_map('calendar_normalize', $q->fetchAll());
}

/** Anstehende Einträge ab heute, seitenweise. Liefert [Einträge, Gesamtzahl]. */
function calendar_upcoming(PDO $pdo, int $limit, int $offset): array {
    $today = (new DateTimeImmutable('now', new DateTimeZone('Europe/Berlin')))->format('Y-m-d');
    $q = $pdo->prepare('SELECT COUNT(*) FROM (' . CALENDAR_UNION_SQL . ') x WHERE event_date >= ?');
    $q->execute([$today]);
    $total = (int)$q->fetchColumn();
    $q = $pdo->prepare('SELECT * FROM (' . CALENDAR_UNION_SQL . ') x WHERE event_date >= ?' . CALENDAR_ORDER_SQL . ' LIMIT ' . (int)$limit . ' OFFSET ' . (int)$offset);
    $q->execute([$today]);
    return [array_map('calendar_normalize', $q->fetchAll()), $total];
}

/** Datenbankzeile in die Form bringen, die Seite und JavaScript brauchen. */
function calendar_normalize(array $r): array {
    $cat = isset(CALENDAR_CATEGORIES[$r['category']]) ? $r['category'] : 'ausserhalb';
    $start = $r['start_time'] ? substr((string)$r['start_time'], 0, 5) : '';
    $end = $r['end_time'] ? substr((string)$r['end_time'], 0, 5) : '';
    $link = '';
    if ($r['kind'] === 'meeting') {
        $link = $r['status'] === 'completed' ? '/protokolle.php?meeting=' . (int)$r['id'] : '/stammtisch.php';
    } elseif ($r['source'] === 'assembly') {
        $link = '/antreten.php';
    }
    return [
        'key' => ($r['kind'] === 'meeting' ? 'm' : 'e') . (int)$r['id'],
        'kind' => $r['kind'],
        'id' => (int)$r['id'],
        'title' => (string)$r['title'],
        'category' => $cat,
        'date' => (string)$r['event_date'],
        'dateLabel' => calendar_date_label((string)$r['event_date']),
        'start' => $start,
        'end' => $end,
        'timeLabel' => $start === '' ? 'ganztägig' : ($end !== '' ? $start . '–' . $end . ' Uhr' : $start . ' Uhr'),
        'location' => (string)$r['location'],
        'note' => (string)$r['note'],
        'source' => (string)$r['source'],
        'author' => trim((string)$r['author']) !== '' ? trim((string)$r['author']) : 'unbekannt',
        'link' => $link,
    ];
}

function calendar_date_label(string $date): string {
    $d = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
    if (!$d) return $date;
    $days = ['Sonntag','Montag','Dienstag','Mittwoch','Donnerstag','Freitag','Samstag'];
    return $days[(int)$d->format('w')] . ', ' . $d->format('d.m.Y');
}

function calendar_month_name(int $month): string {
    return ['Januar','Februar','März','April','Mai','Juni','Juli','August','September','Oktober','November','Dezember'][$month - 1] ?? '';
}

/**
 * Antreten in den Kalender übernehmen. Läuft beim Speichern auf der Antreten-Seite.
 * Ist das bisherige Antreten noch nicht vorbei, wird sein Eintrag angepasst; sonst entsteht ein neuer Eintrag,
 * damit vergangene Antreten im Kalender stehen bleiben.
 */
function calendar_sync_assembly(PDO $pdo, ?array $previous, DateTimeImmutable $local, string $location, string $attire, int $userId): int {
    $date = $local->format('Y-m-d');
    $time = $local->format('H:i');
    $note = $attire !== '' ? 'Anzug: ' . $attire : '';
    $eventId = (int)($previous['calendar_event_id'] ?? 0);
    $stillCurrent = false;
    if ($eventId > 0 && $previous) {
        try {
            $prevUtc = new DateTimeImmutable($previous['starts_at_utc'], new DateTimeZone('UTC'));
            $stillCurrent = new DateTimeImmutable('now', new DateTimeZone('UTC')) < $prevUtc->modify('+1 hour');
        } catch (Throwable $e) { $stillCurrent = false; }
    }
    if ($stillCurrent) {
        $q = $pdo->prepare("UPDATE calendar_events SET event_date=?,start_time=?,location=?,note=?,updated_by=?,updated_at=CURRENT_TIMESTAMP WHERE id=? AND source='assembly'");
        $q->execute([$date, $time, $location, $note, $userId, $eventId]);
        if ($q->rowCount() > 0) return $eventId;
    }
    $q = $pdo->prepare("INSERT INTO calendar_events(title,category,event_date,start_time,location,note,source,created_by,updated_by) VALUES('Antreten','zugtreffen',?,?,?,?,'assembly',?,?) RETURNING id");
    $q->execute([$date, $time, $location, $note, $userId, $userId]);
    return (int)$q->fetchColumn();
}

/** Beim Löschen des Antretens: den zugehörigen Eintrag nur entfernen, wenn das Antreten noch nicht vorbei ist. */
function calendar_remove_assembly(PDO $pdo, ?array $previous): void {
    $eventId = (int)($previous['calendar_event_id'] ?? 0);
    if ($eventId <= 0) return;
    try {
        $prevUtc = new DateTimeImmutable($previous['starts_at_utc'], new DateTimeZone('UTC'));
        if (new DateTimeImmutable('now', new DateTimeZone('UTC')) >= $prevUtc->modify('+1 hour')) return;
    } catch (Throwable $e) { return; }
    $pdo->prepare("DELETE FROM calendar_events WHERE id=? AND source='assembly'")->execute([$eventId]);
}
