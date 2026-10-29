<?php
require_once __DIR__ . '/includes/app.php';
require_once __DIR__ . '/includes/calendar.php';
$u = require_login();
$canEdit = can_edit_calendar($pdo, $u);
$tz = new DateTimeZone('Europe/Berlin');

/** Rücksprung auf den Monat (und die Listenseite), aus dem das Formular kam. */
function calendar_back(string $month, int $page = 1): string {
    $q = [];
    if (preg_match('/^\d{4}-\d{2}$/', $month)) $q['m'] = $month;
    if ($page > 1) $q['p'] = $page;
    return '/calendar.php' . ($q ? '?' . http_build_query($q) : '');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $back = calendar_back((string)($_POST['back_month'] ?? ''), (int)($_POST['back_page'] ?? 1));
    try {
        if (in_array((string)($_POST['action'] ?? ''), ['feed_create', 'feed_renew'], true)) {
            $had = calendar_feed_token($pdo, (int)$u['id']) !== null;
            calendar_feed_renew($pdo, (int)$u['id']);
            flash('success', $had ? 'Neuer Abo-Link erstellt. Der alte Link funktioniert nicht mehr – bitte auf deinen Geräten neu abonnieren.' : 'Dein Abo-Link ist fertig.');
            redirect($back . (str_contains($back, '?') ? '&' : '?') . 'abo=1');
        }
        if (!$canEdit) throw new RuntimeException('Den Kalender dürfen nur Zugführer, Spieß, Schriftführer und Admin pflegen.');
        $action = (string)($_POST['action'] ?? '');
        $id = (int)($_POST['id'] ?? 0);
        $existing = null;
        if ($id > 0) {
            $q = $pdo->prepare('SELECT * FROM calendar_events WHERE id=?');
            $q->execute([$id]);
            $existing = $q->fetch() ?: null;
            if (!$existing) throw new RuntimeException('Der Termin wurde nicht gefunden.');
        }
        if ($action === 'delete') {
            if (!$existing) throw new RuntimeException('Der Termin wurde nicht gefunden.');
            $pdo->prepare('DELETE FROM calendar_events WHERE id=?')->execute([$id]);
            flash('success', 'Termin gelöscht.');
            redirect($back);
        }
        if ($action !== 'save') throw new RuntimeException('Unbekannte Aktion.');

        $longText = function (string $key, string $label): string {
            $v = trim((string)($_POST[$key] ?? ''));
            if (mb_strlen($v, 'UTF-8') > 2000) throw new RuntimeException('Das Feld „' . $label . '“ darf höchstens 2000 Zeichen lang sein.');
            return $v;
        };
        $note = $longText('note', 'Bemerkung');
        $bring = $longText('bring', 'Was mitbringen?');

        if ($existing && $existing['source'] === 'assembly') {
            // Datum, Uhrzeit, Ort und Kleidung kommen vom Antreten und werden dort geändert; die Kategorie ist fest Kirmes.
            $pdo->prepare('UPDATE calendar_events SET category=?,bring=?,note=?,updated_by=?,updated_at=CURRENT_TIMESTAMP WHERE id=?')
                ->execute([CALENDAR_ASSEMBLY_CATEGORY, $bring, $note, $u['id'], $id]);
            flash('success', 'Termin gespeichert.');
            redirect(calendar_back(substr((string)$existing['event_date'], 0, 7), (int)($_POST['back_page'] ?? 1)));
        }

        $category = (string)($_POST['category'] ?? '');
        if (!isset(CALENDAR_CATEGORIES[$category])) throw new RuntimeException('Bitte eine Kategorie auswählen.');
        $attire = $longText('attire', 'Was anziehen?');
        $title = trim((string)($_POST['title'] ?? ''));
        if (mb_strlen($title, 'UTF-8') < 2 || mb_strlen($title, 'UTF-8') > 160) throw new RuntimeException('Bitte einen Namen mit 2 bis 160 Zeichen angeben.');
        $date = trim((string)($_POST['event_date'] ?? ''));
        $d = DateTimeImmutable::createFromFormat('!Y-m-d', $date, $tz);
        if (!$d || $d->format('Y-m-d') !== $date) throw new RuntimeException('Bitte ein gültiges Datum angeben.');
        $time = function (string $key): ?string {
            $v = trim((string)($_POST[$key] ?? ''));
            if ($v === '') return null;
            if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $v)) throw new RuntimeException('Bitte gültige Uhrzeiten angeben.');
            return $v;
        };
        $start = $time('start_time');
        $end = $time('end_time');
        if ($end !== null && $start === null) throw new RuntimeException('Für eine Endzeit wird auch eine Startzeit gebraucht.');
        if ($end !== null && $end <= $start) throw new RuntimeException('Die Endzeit muss nach der Startzeit liegen.');
        $location = trim((string)($_POST['location'] ?? ''));
        if (mb_strlen($location, 'UTF-8') > 250) throw new RuntimeException('Der Ort darf höchstens 250 Zeichen lang sein.');

        if ($existing) {
            $pdo->prepare('UPDATE calendar_events SET title=?,category=?,event_date=?,start_time=?,end_time=?,location=?,attire=?,bring=?,note=?,updated_by=?,updated_at=CURRENT_TIMESTAMP WHERE id=?')
                ->execute([$title, $category, $date, $start, $end, $location, $attire, $bring, $note, $u['id'], $id]);
            flash('success', 'Termin gespeichert.');
        } else {
            $pdo->prepare("INSERT INTO calendar_events(title,category,event_date,start_time,end_time,location,attire,bring,note,source,created_by,updated_by) VALUES(?,?,?,?,?,?,?,?,?,'manual',?,?)")
                ->execute([$title, $category, $date, $start, $end, $location, $attire, $bring, $note, $u['id'], $u['id']]);
            flash('success', 'Termin eingetragen.');
        }
        redirect(calendar_back(substr($date, 0, 7), (int)($_POST['back_page'] ?? 1)));
    } catch (Throwable $e) {
        flash('danger', user_error_message($e));
        redirect($back);
    }
}

// Monat bestimmen
$now = new DateTimeImmutable('now', $tz);
$today = $now->format('Y-m-d');
$monthParam = (string)($_GET['m'] ?? '');
$first = preg_match('/^(\d{4})-(\d{2})$/', $monthParam, $mm) && (int)$mm[2] >= 1 && (int)$mm[2] <= 12 && (int)$mm[1] >= 2000 && (int)$mm[1] <= 2100
    ? DateTimeImmutable::createFromFormat('!Y-m-d', $mm[1] . '-' . $mm[2] . '-01', $tz)
    : $now->modify('first day of this month')->setTime(0, 0);
$monthKey = $first->format('Y-m');
$prevKey = $first->modify('-1 month')->format('Y-m');
$nextKey = $first->modify('+1 month')->format('Y-m');
$gridStart = $first->modify('-' . ((int)$first->format('N') - 1) . ' days');
$last = $first->modify('last day of this month');
$gridEnd = $last->modify('+' . (7 - (int)$last->format('N')) . ' days');

$monthEntries = calendar_entries_between($pdo, $gridStart->format('Y-m-d'), $gridEnd->format('Y-m-d'));
$byDay = [];
foreach ($monthEntries as $entry) $byDay[$entry['date']][] = $entry;

// Liste der nächsten Termine
$perPage = 10;
$page = max(1, (int)($_GET['p'] ?? 1));
[$upcoming, $upcomingTotal] = calendar_upcoming($pdo, $perPage, ($page - 1) * $perPage);
$pages = max(1, (int)ceil($upcomingTotal / $perPage));
if ($page > $pages) { $page = $pages; [$upcoming, $upcomingTotal] = calendar_upcoming($pdo, $perPage, ($page - 1) * $perPage); }

// Daten für die Popups
$entryData = [];
foreach (array_merge($monthEntries, $upcoming) as $entry) {
    $entry['editable'] = $canEdit && $entry['kind'] === 'event';
    $entryData[$entry['key']] = $entry;
}
$dayData = [];
foreach ($byDay as $date => $list) $dayData[$date] = ['label' => calendar_date_label($date), 'keys' => array_column($list, 'key')];

$monthUrl = fn(string $m) => calendar_back($m, $page);
$pageUrl = fn(int $p) => calendar_back($monthKey, $p);
$chipStyle = fn(string $cat) => '--cal-c:' . CALENDAR_CATEGORIES[$cat]['color'] . ';--cal-t:' . CALENDAR_CATEGORIES[$cat]['text'];
$maxPerDay = 3;

$feedToken = calendar_feed_token($pdo, (int)$u['id']);
$feedUrl = $feedToken ? calendar_feed_url($feedToken) : '';
$feedWebcal = $feedUrl ? preg_replace('#^https?://#', 'webcal://', $feedUrl) : '';
$openFeed = isset($_GET['abo']);

$pageTitle = 'Kalender';
require __DIR__ . '/includes/header.php';
?>
<div class="container dashboard-shell"><div class="row g-4">
<section class="col-12">
  <div class="d-flex flex-wrap justify-content-between align-items-end gap-2 mb-4">
    <div><div class="eyebrow">Termine des Zuges</div><h1 class="section-title h2 mb-1">Kalender</h1>
      <p class="text-secondary mb-0">Antreten und festgelegte Stammtische erscheinen automatisch.</p></div>
    <div class="d-flex flex-wrap gap-2">
      <button type="button" class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#calFeedModal"><i class="bi bi-phone me-1"></i> Aufs Handy abonnieren</button>
      <?php if ($canEdit): ?><button type="button" class="btn btn-brand" data-cal-new="<?= e($today) ?>"><i class="bi bi-plus-lg me-1"></i> Termin eintragen</button><?php endif; ?>
    </div>
  </div>

  <div class="row g-4">
    <div class="col-xl-8">
      <section class="panel p-3 p-md-4 cal-panel">
        <div class="cal-toolbar d-flex align-items-center gap-2 mb-3">
          <a class="btn btn-outline-secondary btn-sm" href="<?= e($monthUrl($prevKey)) ?>" aria-label="Vorheriger Monat"><i class="bi bi-chevron-left"></i></a>
          <h2 class="h5 fw-bold mb-0 flex-grow-1 text-center"><?= e(calendar_month_name((int)$first->format('n')) . ' ' . $first->format('Y')) ?></h2>
          <a class="btn btn-outline-secondary btn-sm" href="<?= e($monthUrl($nextKey)) ?>" aria-label="Nächster Monat"><i class="bi bi-chevron-right"></i></a>
          <?php if ($monthKey !== $now->format('Y-m')): ?><a class="btn btn-outline-secondary btn-sm" href="<?= e($monthUrl($now->format('Y-m'))) ?>">Heute</a><?php endif; ?>
        </div>
        <div class="cal-grid" role="grid" aria-label="Monatsansicht">
          <?php foreach (['Mo','Di','Mi','Do','Fr','Sa','So'] as $wd): ?><div class="cal-weekday" role="columnheader"><?= $wd ?></div><?php endforeach; ?>
          <?php for ($day = $gridStart; $day <= $gridEnd; $day = $day->modify('+1 day')):
              $key = $day->format('Y-m-d'); $list = $byDay[$key] ?? [];
              $cls = 'cal-day' . ($day->format('Y-m') !== $monthKey ? ' is-other' : '') . ($key === $today ? ' is-today' : '') . ((int)$day->format('N') >= 6 ? ' is-weekend' : '') . ($list ? ' has-entries' : '');
          ?>
            <div class="<?= $cls ?>" role="gridcell" tabindex="0" data-cal-day="<?= e($key) ?>" aria-label="<?= e(calendar_date_label($key) . ($list ? ', ' . count($list) . ' Termin' . (count($list) > 1 ? 'e' : '') : '')) ?>">
              <span class="cal-num"><?= (int)$day->format('j') ?></span>
              <?php foreach (array_slice($list, 0, $maxPerDay) as $entry): ?>
                <button type="button" class="cal-chip" style="<?= e($chipStyle($entry['category'])) ?>" data-cal-entry="<?= e($entry['key']) ?>" title="<?= e($entry['title'] . ' · ' . $entry['timeLabel']) ?>"><?php if ($entry['start'] !== ''): ?><span class="cal-chip-time"><?= e($entry['start']) ?></span> <?php endif; ?><?= e($entry['title']) ?></button>
              <?php endforeach; ?>
              <?php if (count($list) > $maxPerDay): ?><span class="cal-more">+<?= count($list) - $maxPerDay ?> weitere</span><?php endif; ?>
            </div>
          <?php endfor; ?>
        </div>
        <div class="cal-legend d-flex flex-wrap gap-2 mt-3">
          <?php foreach (CALENDAR_CATEGORIES as $cat => $info): ?><span class="cal-badge" style="<?= e($chipStyle($cat)) ?>"><?= e($info['label']) ?></span><?php endforeach; ?>
        </div>
      </section>
    </div>

    <div class="col-xl-4">
      <section class="panel p-3 p-md-4 cal-list-panel">
        <h2 class="h5 fw-bold mb-3">Nächste Termine</h2>
        <?php if (!$upcoming): ?>
          <p class="text-secondary mb-0">Aktuell sind keine Termine eingetragen.</p>
        <?php else: ?>
          <ul class="cal-list list-unstyled mb-0">
            <?php foreach ($upcoming as $entry): $d = DateTimeImmutable::createFromFormat('!Y-m-d', $entry['date'], $tz); ?>
              <li><button type="button" class="cal-list-item" style="<?= e($chipStyle($entry['category'])) ?>" data-cal-entry="<?= e($entry['key']) ?>">
                <span class="cal-list-date"><span class="cal-list-wd"><?= e(german_weekday_short($d)) ?></span><strong><?= e($d->format('d')) ?></strong><span><?= e(mb_substr(calendar_month_name((int)$d->format('n')), 0, 3)) ?></span></span>
                <span class="cal-list-body">
                  <strong class="d-block"><?= e($entry['title']) ?></strong>
                  <span class="d-block small"><i class="bi bi-clock me-1"></i><?= e($entry['timeLabel']) ?><?php if ($entry['location'] !== ''): ?> · <i class="bi bi-geo-alt me-1"></i><?= e($entry['location']) ?><?php endif; ?></span>
                  <?php if ($entry['attire'] !== ''): ?><span class="d-block small cal-list-note"><i class="bi bi-person-standing me-1"></i><?= e($entry['attire']) ?></span><?php endif; ?>
                  <?php if ($entry['bring'] !== ''): ?><span class="d-block small cal-list-note"><i class="bi bi-bag me-1"></i><?= e($entry['bring']) ?></span><?php endif; ?>
                  <?php if ($entry['note'] !== ''): ?><span class="d-block small text-secondary cal-list-note"><?= e($entry['note']) ?></span><?php endif; ?>
                  <span class="d-block small text-secondary">Eingetragen von <?= e($entry['author']) ?></span>
                </span>
              </button></li>
            <?php endforeach; ?>
          </ul>
          <?php if ($pages > 1): ?>
            <nav class="d-flex justify-content-between align-items-center mt-3" aria-label="Weitere Termine">
              <a class="btn btn-outline-secondary btn-sm <?= $page <= 1 ? 'disabled' : '' ?>" href="<?= e($pageUrl($page - 1)) ?>" <?= $page <= 1 ? 'aria-disabled="true" tabindex="-1"' : '' ?>><i class="bi bi-chevron-left"></i> Zurück</a>
              <span class="small text-secondary">Seite <?= $page ?> von <?= $pages ?></span>
              <a class="btn btn-outline-secondary btn-sm <?= $page >= $pages ? 'disabled' : '' ?>" href="<?= e($pageUrl($page + 1)) ?>" <?= $page >= $pages ? 'aria-disabled="true" tabindex="-1"' : '' ?>>Weiter <i class="bi bi-chevron-right"></i></a>
            </nav>
          <?php endif; ?>
        <?php endif; ?>
      </section>
    </div>
  </div>
</section></div></div>

<!-- Popup: Termin oder Tag anzeigen -->
<div class="modal fade" id="calShowModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-dialog-scrollable"><div class="modal-content">
  <div class="modal-header"><h2 class="h5 mb-0" id="calShowTitle"></h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Schließen"></button></div>
  <div class="modal-body" id="calShowBody"></div>
  <div class="modal-footer" id="calShowFooter"></div>
</div></div></div>

<?php if ($canEdit): ?>
<!-- Popup: Termin eintragen oder bearbeiten -->
<div class="modal fade" id="calEditModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-dialog-scrollable"><form method="post" class="modal-content" id="calEditForm">
  <div class="modal-header"><h2 class="h5 mb-0" id="calEditTitle">Termin eintragen</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Schließen"></button></div>
  <div class="modal-body">
    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="0">
    <input type="hidden" name="back_month" value="<?= e($monthKey) ?>"><input type="hidden" name="back_page" value="<?= $page ?>">
    <div class="alert alert-info small py-2" data-assembly-hint hidden>Dieser Termin kommt vom Antreten und gehört fest zur Kategorie Kirmes. Datum, Uhrzeit, Ort und Kleidung änderst du auf der <a href="/antreten.php">Antreten-Seite</a>.</div>
    <div class="row g-2">
      <div class="col-12" data-free><label class="form-label small" for="calTitle">Name des Termins</label><input class="form-control" id="calTitle" name="title" maxlength="160" required placeholder="z. B. Kirmesumzug"></div>
      <div class="col-12" data-free><span class="form-label small d-block">Kategorie</span>
        <div class="cal-cat-picker">
          <?php foreach (CALENDAR_CATEGORIES as $cat => $info): ?>
            <label class="cal-cat-option" style="<?= e($chipStyle($cat)) ?>"><input type="radio" name="category" value="<?= e($cat) ?>" required><span><?= e($info['label']) ?></span></label>
          <?php endforeach; ?>
        </div>
      </div>
      <div class="col-12 col-sm-6" data-free><label class="form-label small" for="calDate">Datum</label><input type="date" class="form-control" id="calDate" name="event_date" required></div>
      <div class="col-6 col-sm-3" data-free><label class="form-label small" for="calStart">Von</label><input type="time" class="form-control" id="calStart" name="start_time"></div>
      <div class="col-6 col-sm-3" data-free><label class="form-label small" for="calEnd">Bis</label><input type="time" class="form-control" id="calEnd" name="end_time"></div>
      <div class="col-12" data-free><small class="text-secondary">Ohne Uhrzeit gilt der Termin als ganztägig.</small></div>
      <div class="col-12" data-free><label class="form-label small" for="calLocation">Ort</label><input class="form-control" id="calLocation" name="location" maxlength="250" placeholder="Festzelt, Kirche, Zugführer, Stähn-Hof, …"></div>
      <div class="col-12" data-free><label class="form-label small" for="calAttire">Was anziehen?</label><textarea class="form-control" id="calAttire" name="attire" rows="2" maxlength="2000" placeholder="Schwarzer Anzug, Stock, Zylinder, Zug-Shirt, leichter Bieranzug, egal, …"></textarea></div>
      <div class="col-12"><label class="form-label small" for="calBring">Was mitbringen?</label><textarea class="form-control" id="calBring" name="bring" rows="2" maxlength="2000" placeholder="Orden, Silber, Kuchen ;), Krepp, …"></textarea></div>
      <div class="col-12"><label class="form-label small" for="calNote">Bemerkung</label><textarea class="form-control" id="calNote" name="note" rows="3" maxlength="2000"></textarea></div>
    </div>
  </div>
  <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Abbrechen</button><button class="btn btn-brand"><i class="bi bi-check-lg me-1"></i> Speichern</button></div>
</form></div></div>
<form method="post" id="calDeleteForm" hidden><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="0"><input type="hidden" name="back_month" value="<?= e($monthKey) ?>"><input type="hidden" name="back_page" value="<?= $page ?>"></form>
<?php endif; ?>

<!-- Popup: Kalender abonnieren -->
<div class="modal fade" id="calFeedModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-dialog-scrollable"><div class="modal-content">
  <div class="modal-header"><h2 class="h5 mb-0">Kalender aufs Handy</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Schließen"></button></div>
  <div class="modal-body">
    <p>Mit einem Abo erscheinen alle Termine im Kalender deines Handys oder Computers. Neue, geänderte und gelöschte Termine kommen automatisch nach – je nach Gerät nach einigen Minuten bis wenigen Stunden.</p>
    <p class="small text-secondary">Das Abo ist nur zum Ansehen. Eintragen und Ändern geht ausschließlich hier auf der Webseite.</p>
    <?php if (!$feedUrl): ?>
      <form method="post"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="feed_create"><input type="hidden" name="back_month" value="<?= e($monthKey) ?>"><input type="hidden" name="back_page" value="<?= $page ?>">
        <button class="btn btn-brand"><i class="bi bi-link-45deg me-1"></i> Meinen Abo-Link erstellen</button></form>
    <?php else: ?>
      <h3 class="h6 mt-3"><i class="bi bi-apple me-1"></i> iPhone, iPad, Mac</h3>
      <a class="btn btn-brand mb-1" href="<?= e($feedWebcal) ?>"><i class="bi bi-calendar-plus me-1"></i> Jetzt abonnieren</a>
      <p class="small text-secondary">Antippen und „Abonnieren“ bestätigen. Tipp: Unter Einstellungen → Kalender → Accounts → Abonnierte Kalender → „Abrufen“ auf „Stündlich“ stellen.</p>
      <h3 class="h6 mt-3"><i class="bi bi-android2 me-1"></i> Android / Google Kalender</h3>
      <ol class="small ps-3 mb-2">
        <li>Link unten kopieren.</li>
        <li>Am Computer oder im Handy-Browser <a href="https://calendar.google.com/calendar/u/0/r/settings/addbyurl" target="_blank" rel="noopener">Google Kalender → „Per URL hinzufügen“</a> öffnen und den Link einfügen.</li>
        <li>In der Kalender-App unter Einstellungen den Kalender „Strahlemännkes“ einblenden und „Synchronisieren“ einschalten.</li>
      </ol>
      <p class="small text-secondary">Google holt Änderungen selbst ab, das kann einige Stunden dauern.</p>
      <h3 class="h6 mt-3"><i class="bi bi-link-45deg me-1"></i> Dein persönlicher Link</h3>
      <div class="input-group mb-2"><input class="form-control form-control-sm" id="calFeedUrl" value="<?= e($feedUrl) ?>" readonly><button type="button" class="btn btn-outline-secondary btn-sm" id="calFeedCopy"><i class="bi bi-clipboard me-1"></i> Kopieren</button></div>
      <p class="small text-secondary mb-2">Bitte nicht weitergeben – der Link gehört nur dir. Wenn er in falsche Hände geraten ist, erneuere ihn; der alte funktioniert dann nicht mehr.</p>
      <form method="post" onsubmit="return confirm('Neuen Link erstellen? Bestehende Abos auf deinen Geräten hören dann auf, sich zu aktualisieren, und müssen neu eingerichtet werden.');"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="feed_renew"><input type="hidden" name="back_month" value="<?= e($monthKey) ?>"><input type="hidden" name="back_page" value="<?= $page ?>">
        <button class="btn btn-outline-danger btn-sm"><i class="bi bi-arrow-repeat me-1"></i> Link erneuern</button></form>
    <?php endif; ?>
  </div>
  <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Schließen</button></div>
</div></div></div>

<script>
(() => {
  const entries = <?= json_encode($entryData ?: new stdClass, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
  const days = <?= json_encode($dayData ?: new stdClass, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
  const cats = <?= json_encode(CALENDAR_CATEGORIES, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
  const canEdit = <?= $canEdit ? 'true' : 'false' ?>;
  // Popups ans Ende von <body> hängen, sonst liegen sie unter der Kopfleiste (main hat einen eigenen Stapelkontext).
  ['calShowModal', 'calEditModal', 'calFeedModal'].forEach(id => { const m = document.getElementById(id); if (m) document.body.append(m); });
  const showEl = document.getElementById('calShowModal');
  const showModal = () => bootstrap.Modal.getOrCreateInstance(showEl);
  const title = document.getElementById('calShowTitle'), body = document.getElementById('calShowBody'), footer = document.getElementById('calShowFooter');
  const el = (tag, cls, text) => { const n = document.createElement(tag); if (cls) n.className = cls; if (text !== undefined) n.textContent = text; return n; };
  const badge = cat => { const b = el('span', 'cal-badge', cats[cat].label); b.style.setProperty('--cal-c', cats[cat].color); b.style.setProperty('--cal-t', cats[cat].text); return b; };
  const row = (icon, label, value) => { const r = el('div', 'cal-detail-row'); r.append(el('i', 'bi ' + icon)); const t = el('div'); t.append(el('div', 'small text-secondary', label), el('div', 'cal-detail-value', value)); r.append(t); return r; };
  const closeBtn = () => { const b = el('button', 'btn btn-outline-secondary', 'Schließen'); b.type = 'button'; b.dataset.bsDismiss = 'modal'; return b; };

  function showEntry(key) {
    const x = entries[key]; if (!x) return;
    title.textContent = x.title; body.replaceChildren(); footer.replaceChildren();
    body.append(badge(x.category));
    body.append(row('bi-calendar3', 'Datum', x.dateLabel), row('bi-clock', 'Uhrzeit', x.timeLabel));
    if (x.location) body.append(row('bi-geo-alt', 'Ort', x.location));
    body.append(row('bi-person', 'Eingetragen von', x.author));
    if (x.attire) body.append(row('bi-person-standing', 'Was anziehen?', x.attire));
    if (x.bring) body.append(row('bi-bag', 'Was mitbringen?', x.bring));
    if (x.note) body.append(row('bi-chat-left-text', 'Bemerkung', x.note));
    if (x.kind === 'meeting') body.append(el('p', 'small text-secondary mt-3 mb-0', 'Kommt automatisch aus dem Stammtisch.'));
    if (x.source === 'assembly') body.append(el('p', 'small text-secondary mt-3 mb-0', 'Kommt automatisch aus dem Antreten.'));
    if (x.link) { const a = el('a', 'btn btn-outline-secondary me-auto', x.kind === 'meeting' ? 'Zum Stammtisch' : 'Zum Antreten'); a.href = x.link; footer.append(a); }
    if (x.editable) {
      const del = el('button', 'btn btn-outline-danger', 'Löschen'); del.type = 'button';
      del.onclick = () => { if (!confirm('Termin „' + x.title + '“ wirklich löschen?')) return; const f = document.getElementById('calDeleteForm'); f.elements.namedItem('id').value = x.id; f.submit(); };
      const ed = el('button', 'btn btn-brand', 'Bearbeiten'); ed.type = 'button'; ed.onclick = () => openForm(x, x.date);
      footer.append(del, ed);
    } else footer.append(closeBtn());
    showModal().show();
  }

  function showDay(date) {
    const d = days[date];
    if (!d) { if (canEdit) openForm(null, date); return; }
    if (d.keys.length === 1 && !canEdit) { showEntry(d.keys[0]); return; }
    title.textContent = d.label; body.replaceChildren(); footer.replaceChildren();
    const list = el('div', 'd-grid gap-2');
    d.keys.forEach(k => {
      const x = entries[k]; const b = el('button', 'cal-list-item'); b.type = 'button';
      b.style.setProperty('--cal-c', cats[x.category].color);
      const s = el('span', 'cal-list-body'); s.append(el('strong', 'd-block', x.title), el('span', 'd-block small', x.timeLabel + (x.location ? ' · ' + x.location : '')));
      b.append(s); b.onclick = () => showEntry(k); list.append(b);
    });
    body.append(list);
    if (canEdit) { const n = el('button', 'btn btn-brand me-auto', 'Termin an diesem Tag eintragen'); n.type = 'button'; n.onclick = () => openForm(null, date); footer.append(n); }
    footer.append(closeBtn());
    showModal().show();
  }

  function openForm(x, date) {
    const f = document.getElementById('calEditForm'); if (!f) return;
    const assembly = !!(x && x.source === 'assembly');
    document.getElementById('calEditTitle').textContent = x ? 'Termin bearbeiten' : 'Termin eintragen';
    const set = (name, value) => { f.elements.namedItem(name).value = value; };
    set('id', x ? x.id : 0);
    set('title', x ? x.title : '');
    set('event_date', x ? x.date : (date || ''));
    set('start_time', x ? x.start : '');
    set('end_time', x ? x.end : '');
    set('location', x ? x.location : '');
    set('note', x ? x.note : '');
    set('attire', x ? x.attire : '');
    set('bring', x ? x.bring : '');
    f.querySelectorAll('[name=category]').forEach(r => r.checked = !!(x && r.value === x.category));
    f.querySelector('[data-assembly-hint]').hidden = !assembly;
    f.querySelectorAll('[data-free]').forEach(c => { c.hidden = assembly; c.querySelectorAll('input, textarea').forEach(i => i.disabled = assembly); });
    bootstrap.Modal.getInstance(showEl)?.hide();
    bootstrap.Modal.getOrCreateInstance(document.getElementById('calEditModal')).show();
  }

  document.querySelectorAll('[data-cal-entry]').forEach(b => b.addEventListener('click', ev => { ev.stopPropagation(); showEntry(b.dataset.calEntry); }));
  document.querySelectorAll('[data-cal-day]').forEach(c => {
    c.addEventListener('click', () => showDay(c.dataset.calDay));
    c.addEventListener('keydown', ev => { if (ev.target === c && (ev.key === 'Enter' || ev.key === ' ')) { ev.preventDefault(); showDay(c.dataset.calDay); } });
  });
  const copy = document.getElementById('calFeedCopy');
  if (copy) copy.addEventListener('click', async () => {
    const input = document.getElementById('calFeedUrl');
    try { await navigator.clipboard.writeText(input.value); } catch (e) { input.select(); document.execCommand('copy'); }
    copy.innerHTML = '<i class="bi bi-check-lg me-1"></i> Kopiert';
  });
  <?php if ($openFeed): ?>document.addEventListener('DOMContentLoaded', () => bootstrap.Modal.getOrCreateInstance(document.getElementById('calFeedModal')).show());<?php endif; ?>

  document.querySelectorAll('[data-cal-new]').forEach(b => b.addEventListener('click', () => openForm(null, b.dataset.calNew)));
  const endInput = document.getElementById('calEnd');
  if (endInput) document.getElementById('calEditForm').addEventListener('submit', ev => {
    const s = document.getElementById('calStart').value, e = endInput.value;
    if (!endInput.disabled && e && (!s || e <= s)) { ev.preventDefault(); alert(s ? 'Die Endzeit muss nach der Startzeit liegen.' : 'Für eine Endzeit wird auch eine Startzeit gebraucht.'); }
  });
})();
</script>
<?php require __DIR__ . '/includes/footer.php'; ?>
