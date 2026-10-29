<?php
/*
 * Kalender-Abo: liefert den Kalender als iCalendar (.ics) für Handy- und Computerkalender.
 * Aufruf mit dem persönlichen Schlüssel: /calendar-feed.php?t=…  (ohne Anmeldung, nur lesen).
 * Kalender-Apps fragen den Link regelmäßig neu ab; Änderungen gibt es nur über die Webseite.
 */
require_once __DIR__ . '/includes/app.php';
require_once __DIR__ . '/includes/calendar.php';

$token = (string)($_GET['t'] ?? '');
$userId = false;
if (preg_match('/^[0-9a-f]{64}$/', $token)) {
    $q = $pdo->prepare('SELECT t.user_id FROM calendar_feed_tokens t JOIN users u ON u.id=t.user_id AND u.is_active=1 WHERE t.token=?');
    $q->execute([$token]);
    $userId = $q->fetchColumn();
}
// Sitzungscookie für den Abruf nicht behalten.
if (session_status() === PHP_SESSION_ACTIVE) { session_write_close(); header_remove('Set-Cookie'); }
header('X-Robots-Tag: noindex');
header('Referrer-Policy: no-referrer');
if ($userId === false) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    exit('Dieser Kalender-Link ist ungültig oder wurde erneuert.');
}
$pdo->prepare('UPDATE calendar_feed_tokens SET last_used_at=CURRENT_TIMESTAMP WHERE user_id=? AND (last_used_at IS NULL OR last_used_at < CURRENT_TIMESTAMP - INTERVAL \'10 minutes\')')->execute([(int)$userId]);

$ics = calendar_ics($pdo);
// DTSTAMP ändert sich bei jedem Abruf; für den Vergleich daher ohne diese Zeilen.
$etag = '"' . sha1(preg_replace('/^DTSTAMP:.*\r\n/m', '', $ics)) . '"';
header('Cache-Control: private, no-cache');
header('ETag: ' . $etag);
if (trim((string)($_SERVER['HTTP_IF_NONE_MATCH'] ?? '')) === $etag) { http_response_code(304); exit; }
header('Content-Type: text/calendar; charset=utf-8');
header('Content-Disposition: inline; filename="strahlemaennkes.ics"');
echo $ics;
