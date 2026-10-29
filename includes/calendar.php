<?php
/**
 * Kalender: feste Kategorien, Rechte, Abfragen und die Übernahme des Antretens.
 * Termine liegen in calendar_events (Ortszeit), Stammtische kommen direkt aus meetings.
 */

/** Feste Kategorien. Farben und Namen sind bewusst nicht änderbar, neue Kategorien gibt es (noch) nicht. */
const CALENDAR_CATEGORIES = [
    'kirmes'          => ['label' => 'Kirmes',           'color' => '#43A047', 'text' => '#FFFFFF'],
    'zugtreffen'      => ['label' => 'Zugtreffen',       'color' => '#FBC02D', 'text' => '#212429'],
    'ausserhalb'      => ['label' => 'Außerhalb Kirmes', 'color' => '#7A808A', 'text' => '#FFFFFF'],
    'ausflug'         => ['label' => 'Grober Ausflug',   'color' => '#E53935', 'text' => '#FFFFFF'],
    'weihnachtsfeier' => ['label' => 'Weihnachtsfeier',  'color' => '#8B0000', 'text' => '#FFFFFF'],
];
/** Antreten gibt es nur an der Kirmes, daher feste Kategorie. */
const CALENDAR_ASSEMBLY_CATEGORY = 'kirmes';

/** Pflegen dürfen: Zugführer, Spieß (inkl. Vertretung), Schriftführer und Admin. */
function can_edit_calendar(PDO $pdo, ?array $user=null): bool {
    return is_leader() || is_secretary() || can_act_as_spiess($pdo, $user);
}

/** Alle Kalendereinträge (eigene Termine + festgelegte Stammtische) als eine Abfrage. */
const CALENDAR_UNION_SQL = <<<'SQL'
SELECT 'event' AS kind, e.id, e.title, e.category, e.event_date, e.start_time, e.end_time, e.location, e.note, e.attire, e.bring, e.source,
       TRIM(COALESCE(c.first_name,'')||' '||COALESCE(c.last_name,'')) AS author, NULL::varchar AS status, e.updated_at AS changed_at
FROM calendar_events e LEFT JOIN users c ON c.id=e.created_by
UNION ALL
SELECT 'meeting', m.id, m.title, 'zugtreffen',
       ((m.starts_at_utc AT TIME ZONE 'UTC') AT TIME ZONE 'Europe/Berlin')::date,
       ((m.starts_at_utc AT TIME ZONE 'UTC') AT TIME ZONE 'Europe/Berlin')::time,
       NULL::time, m.location, m.note, '', '', 'meeting',
       TRIM(COALESCE(c.first_name,'')||' '||COALESCE(c.last_name,'')), m.status, m.updated_at
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
    $cat = $r['source'] === 'assembly' ? CALENDAR_ASSEMBLY_CATEGORY : (isset(CALENDAR_CATEGORIES[$r['category']]) ? $r['category'] : 'ausserhalb');
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
        'attire' => (string)$r['attire'],
        'bring' => (string)$r['bring'],
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
    $eventId = (int)($previous['calendar_event_id'] ?? 0);
    $stillCurrent = false;
    if ($eventId > 0 && $previous) {
        try {
            $prevUtc = new DateTimeImmutable($previous['starts_at_utc'], new DateTimeZone('UTC'));
            $stillCurrent = new DateTimeImmutable('now', new DateTimeZone('UTC')) < $prevUtc->modify('+1 hour');
        } catch (Throwable $e) { $stillCurrent = false; }
    }
    if ($stillCurrent) {
        $q = $pdo->prepare("UPDATE calendar_events SET event_date=?,start_time=?,location=?,attire=?,updated_by=?,updated_at=CURRENT_TIMESTAMP WHERE id=? AND source='assembly'");
        $q->execute([$date, $time, $location, $attire, $userId, $eventId]);
        if ($q->rowCount() > 0) return $eventId;
    }
    $q = $pdo->prepare("INSERT INTO calendar_events(title,category,event_date,start_time,location,attire,source,created_by,updated_by) VALUES('Antreten','kirmes',?,?,?,?,'assembly',?,?) RETURNING id");
    $q->execute([$date, $time, $location, $attire, $userId, $userId]);
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

/* ---------- Kalender-Abo (iCalendar-Feed, nur lesen) ---------- */

/** Persönlicher Abo-Schlüssel des Mitglieds oder null, wenn noch keiner erstellt wurde. */
function calendar_feed_token(PDO $pdo, int $userId): ?string {
    $q = $pdo->prepare('SELECT token FROM calendar_feed_tokens WHERE user_id=?');
    $q->execute([$userId]);
    $t = $q->fetchColumn();
    return $t === false ? null : (string)$t;
}

/** Neuen Abo-Schlüssel erzeugen. Ein bisheriger Link funktioniert danach nicht mehr. */
function calendar_feed_renew(PDO $pdo, int $userId): string {
    $token = bin2hex(random_bytes(32));
    $pdo->prepare('INSERT INTO calendar_feed_tokens(user_id,token) VALUES(?,?) ON CONFLICT(user_id) DO UPDATE SET token=excluded.token,created_at=CURRENT_TIMESTAMP,last_used_at=NULL')
        ->execute([$userId, $token]);
    return $token;
}

/** Abo-Adresse (https) für Google & Co.; für iPhone/Mac wird daraus webcal://. */
function calendar_feed_url(string $token): string {
    return absolute_url('/calendar-feed.php?t=' . rawurlencode($token));
}

/** Text für iCalendar maskieren (RFC 5545, 3.3.11). */
function ics_text(string $s): string {
    $s = str_replace(["\r\n", "\r"], "\n", $s);
    return str_replace(['\\', ';', ',', "\n"], ['\\\\', '\;', '\\,', '\\n'], $s);
}

/** Zeile nach 75 Bytes umbrechen, ohne UTF-8-Zeichen zu zerteilen. */
function ics_line(string $line): string {
    $out = '';
    $limit = 75;
    while (strlen($line) > $limit) {
        $cut = $limit;
        while ($cut > 0 && (ord($line[$cut]) & 0xC0) === 0x80) $cut--;
        $out .= substr($line, 0, $cut) . "\r\n ";
        $line = substr($line, $cut);
        $limit = 74; // Folgezeilen beginnen mit einem Leerzeichen
    }
    return $out . $line . "\r\n";
}

/**
 * Alle Termine ab einem Jahr zurück als iCalendar.
 * Jeder Termin hat eine feste UID; geänderte Termine bekommen eine höhere SEQUENCE,
 * gelöschte fehlen beim nächsten Abruf – so übernehmen die Handys Änderungen und Löschungen.
 */
function calendar_ics(PDO $pdo): string {
    $from = (new DateTimeImmutable('now', new DateTimeZone('Europe/Berlin')))->modify('-1 year')->format('Y-m-d');
    $q = $pdo->prepare('SELECT * FROM (' . CALENDAR_UNION_SQL . ') x WHERE event_date >= ?' . CALENDAR_ORDER_SQL);
    $q->execute([$from]);
    $host = parse_url(app_base_url(), PHP_URL_HOST) ?: 'strahlemaennkes';
    $calendarUrl = absolute_url('/calendar.php');
    $stamp = gmdate('Ymd\THis\Z');

    $lines = [
        'BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:-//Strahlemaennkes//Kalender//DE', 'CALSCALE:GREGORIAN', 'METHOD:PUBLISH',
        'X-WR-CALNAME:Strahlemännkes', 'X-WR-CALDESC:Termine des Schützenzugs (Änderungen nur über die Webseite)',
        'X-WR-TIMEZONE:Europe/Berlin', 'REFRESH-INTERVAL;VALUE=DURATION:PT1H', 'X-PUBLISHED-TTL:PT1H',
        'BEGIN:VTIMEZONE', 'TZID:Europe/Berlin',
        'BEGIN:DAYLIGHT', 'TZOFFSETFROM:+0100', 'TZOFFSETTO:+0200', 'TZNAME:CEST', 'DTSTART:19700329T020000', 'RRULE:FREQ=YEARLY;BYMONTH=3;BYDAY=-1SU', 'END:DAYLIGHT',
        'BEGIN:STANDARD', 'TZOFFSETFROM:+0200', 'TZOFFSETTO:+0100', 'TZNAME:CET', 'DTSTART:19701025T030000', 'RRULE:FREQ=YEARLY;BYMONTH=10;BYDAY=-1SU', 'END:STANDARD',
        'END:VTIMEZONE',
    ];
    foreach ($q->fetchAll() as $row) {
        $x = calendar_normalize($row);
        $changed = $row['changed_at'] ? new DateTimeImmutable($row['changed_at'], new DateTimeZone('UTC')) : new DateTimeImmutable('2026-01-01', new DateTimeZone('UTC'));
        $day = DateTimeImmutable::createFromFormat('!Y-m-d', $x['date'], new DateTimeZone('Europe/Berlin'));
        $lines[] = 'BEGIN:VEVENT';
        $lines[] = 'UID:' . $x['key'] . '@' . $host;
        $lines[] = 'DTSTAMP:' . $stamp;
        $lines[] = 'LAST-MODIFIED:' . $changed->format('Ymd\THis\Z');
        // Aufsteigend mit jeder Änderung (Minuten seit 2026), damit Kalender-Apps die neue Fassung übernehmen.
        $lines[] = 'SEQUENCE:' . max(0, intdiv($changed->getTimestamp() - 1767225600, 60));
        if ($x['start'] === '') {
            $lines[] = 'DTSTART;VALUE=DATE:' . $day->format('Ymd');
            $lines[] = 'DTEND;VALUE=DATE:' . $day->modify('+1 day')->format('Ymd');
            $lines[] = 'TRANSP:TRANSPARENT';
        } else {
            $start = DateTimeImmutable::createFromFormat('!Y-m-d H:i', $x['date'] . ' ' . $x['start'], new DateTimeZone('Europe/Berlin'));
            $end = $x['end'] !== '' ? DateTimeImmutable::createFromFormat('!Y-m-d H:i', $x['date'] . ' ' . $x['end'], new DateTimeZone('Europe/Berlin')) : $start->modify('+2 hours');
            $lines[] = 'DTSTART;TZID=Europe/Berlin:' . $start->format('Ymd\THis');
            $lines[] = 'DTEND;TZID=Europe/Berlin:' . $end->format('Ymd\THis');
        }
        $lines[] = 'SUMMARY:' . ics_text($x['title']);
        if ($x['location'] !== '') $lines[] = 'LOCATION:' . ics_text($x['location']);
        $desc = [];
        if ($x['attire'] !== '') $desc[] = 'Was anziehen: ' . $x['attire'];
        if ($x['bring'] !== '') $desc[] = 'Was mitbringen: ' . $x['bring'];
        if ($x['note'] !== '') $desc[] = 'Bemerkung: ' . $x['note'];
        $desc[] = 'Kategorie: ' . CALENDAR_CATEGORIES[$x['category']]['label'];
        $desc[] = 'Eingetragen von ' . $x['author'];
        $desc[] = 'Änderungen nur über ' . $calendarUrl;
        $lines[] = 'DESCRIPTION:' . ics_text(implode("\n", $desc));
        $lines[] = 'CATEGORIES:' . ics_text(CALENDAR_CATEGORIES[$x['category']]['label']);
        $lines[] = 'URL:' . $calendarUrl . '?m=' . substr($x['date'], 0, 7);
        $lines[] = 'STATUS:CONFIRMED';
        $lines[] = 'CLASS:PRIVATE';
        $lines[] = 'END:VEVENT';
    }
    $lines[] = 'END:VCALENDAR';
    return implode('', array_map('ics_line', $lines));
}
