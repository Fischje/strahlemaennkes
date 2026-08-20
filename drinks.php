<?php
require_once __DIR__ . '/includes/app.php';
$u = require_role('spiess','admin');
$config = require __DIR__ . '/config.php';
require_once __DIR__ . '/includes/push.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'reset') {
        $reason = trim($_POST['reason'] ?? 'Neuer Abend / neue Runde');
        $pdo->beginTransaction();
        try {
            $pdo->exec("UPDATE user_drink_preferences SET drink_id=NULL, updated_at=CURRENT_TIMESTAMP WHERE user_id IN (SELECT id FROM users WHERE is_active=1)");
            $pdo->exec("INSERT OR IGNORE INTO user_drink_preferences(user_id,drink_id,updated_at) SELECT id,NULL,CURRENT_TIMESTAMP FROM users WHERE is_active=1");
            $st = $pdo->prepare('INSERT INTO drink_status_resets(reset_by,reason) VALUES(?,?)');
            $st->execute([$u['id'], $reason]);
            $pdo->commit();
            flash('success', 'Alle aktuellen Getränkestatus wurden auf „Derzeit nichts“ zurückgesetzt.');
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            flash('danger', 'Zurücksetzen fehlgeschlagen.');
        }
        redirect('/drinks.php');
    }

    if ($action === 'remind') {
        $target = ($_POST['target'] ?? '') === 'members_without_choice' ? 'members_without_choice' : 'all_members';
        $message = trim($_POST['message'] ?? 'Bitte trage dein aktuelles Getränk für die nächste Runde ein.');
        if ($message === '') $message = 'Bitte trage dein aktuelles Getränk für die nächste Runde ein.';
        $where = $target === 'members_without_choice' ? ' AND p.drink_id IS NULL' : '';
        $count = (int)$pdo->query("SELECT COUNT(*) FROM users u LEFT JOIN user_drink_preferences p ON p.user_id=u.id WHERE u.is_active=1{$where}")->fetchColumn();
        $st = $pdo->prepare('INSERT INTO drink_reminders(sent_by,target,message,recipients_count) VALUES(?,?,?,?)');
        $st->execute([$u['id'], $target, mb_substr($message,0,240), $count]);
        $result = send_drink_push($pdo, $config, $target, $message);
        if ($result['configured']) {
            flash('success', "Erinnerung gespeichert. Push: {$result['sent']} zugestellt, {$result['failed']} fehlgeschlagen.");
        } else {
            flash('warning', "Erinnerung für {$count} aktive Konten gespeichert. Web-Push ist noch nicht vollständig konfiguriert.");
        }
        redirect('/drinks.php');
    }

    if ($action === 'create') {
        $name = trim($_POST['name'] ?? ''); $emoji = trim($_POST['emoji'] ?? '🥤');
        if (mb_strlen($name) < 2 || mb_strlen($name) > 50) { flash('danger','Ungültiger Getränkename.'); redirect('/drinks.php'); }
        try {
            $st = $pdo->prepare('INSERT INTO drinks(name,emoji,is_active,is_approved,created_by,sort_order) VALUES(?,?,1,1,?,?)');
            $st->execute([$name, mb_substr($emoji ?: '🥤',0,8), $u['id'], (int)($_POST['sort_order'] ?? 100)]);
            flash('success','Getränk wurde angelegt.');
        } catch (Throwable $e) { flash('warning','Dieses Getränk existiert bereits.'); }
        redirect('/drinks.php');
    }

    if ($action === 'approve') {
        $st=$pdo->prepare('UPDATE drinks SET is_approved=1,is_active=1 WHERE id=?'); $st->execute([(int)$_POST['id']]);
        flash('success','Getränkevorschlag wurde freigegeben.'); redirect('/drinks.php');
    }
    if ($action === 'toggle') {
        $st=$pdo->prepare('UPDATE drinks SET is_active=CASE WHEN is_active=1 THEN 0 ELSE 1 END WHERE id=?'); $st->execute([(int)$_POST['id']]);
        flash('success','Getränkestatus geändert.'); redirect('/drinks.php');
    }
}

$total = (int)$pdo->query('SELECT COUNT(*) FROM users WHERE is_active=1')->fetchColumn();
$chosen = (int)$pdo->query('SELECT COUNT(*) FROM users u JOIN user_drink_preferences p ON p.user_id=u.id WHERE u.is_active=1 AND p.drink_id IS NOT NULL')->fetchColumn();
$counts = $pdo->query('SELECT d.id,d.name,d.emoji,COUNT(*) AS qty FROM users u JOIN user_drink_preferences p ON p.user_id=u.id JOIN drinks d ON d.id=p.drink_id WHERE u.is_active=1 GROUP BY d.id,d.name,d.emoji ORDER BY qty DESC,d.name')->fetchAll();
$drinks = $pdo->query('SELECT d.*, u.first_name,u.last_name FROM drinks d LEFT JOIN users u ON u.id=d.created_by ORDER BY d.is_approved ASC,d.is_active DESC,d.sort_order,d.name COLLATE NOCASE')->fetchAll();
$lastReset = $pdo->query('SELECT r.reason,r.reset_at,u.first_name,u.last_name FROM drink_status_resets r JOIN users u ON u.id=r.reset_by ORDER BY r.id DESC LIMIT 1')->fetch();
$pageTitle='Getränke verwalten'; require __DIR__.'/includes/header.php';
?>
<div class="container dashboard-shell">
  <div class="row g-4">
    <aside class="col-lg-3"><div class="side-nav sticky-lg-top" style="top:92px"><div class="px-2 py-2 mb-2"><div class="small text-white-50">Angemeldet als</div><strong><?= e($u['first_name'].' '.$u['last_name']) ?></strong><div class="mt-1"><span class="badge rounded-pill text-bg-danger"><?= e(role_label($u['role'])) ?></span></div></div><a href="/dashboard.php"><i class="bi bi-grid"></i> Übersicht</a><a href="/member-drink.php"><i class="bi bi-person-check"></i> Mein Getränk</a><a class="active" href="/drinks.php"><i class="bi bi-cup-straw"></i> Getränke</a><a href="/theke.php"><i class="bi bi-list-check"></i> Thekenansicht</a><?php if(has_role('admin')):?><a href="/members.php"><i class="bi bi-people"></i> Benutzer</a><?php endif; ?><a href="/public/chronik.php"><i class="bi bi-book"></i> Chronik</a></div></aside>
    <section class="col-lg-9">
      <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-4"><div><div class="eyebrow">Getränkezentrale</div><h1 class="section-title h2 mb-1">Wünsche verwalten</h1><p class="text-secondary mb-0">Dauerhafte Status, Erinnerungen und Vorbereitung für den Thekengang.</p></div><a class="btn btn-dark" href="/theke.php"><i class="bi bi-list-check me-1"></i> Thekenansicht öffnen</a></div>

      <div class="row g-3 mb-4">
        <div class="col-md-6"><div class="panel p-4 h-100"><div class="d-flex align-items-center gap-3 mb-3"><div class="icon-tile green"><i class="bi bi-bell-fill"></i></div><div><h2 class="h5 mb-1">Mitglieder erinnern</h2><div class="text-secondary small">Getränkewahl anfordern</div></div></div><form method="post"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="remind"><div class="mb-3"><label class="form-label small">Nachricht</label><input name="message" class="form-control" value="Bitte trage dein aktuelles Getränk für die nächste Runde ein."></div><button name="target" value="all_members" class="btn btn-brand w-100"><i class="bi bi-bell-fill me-2"></i>Alle erinnern</button><button name="target" value="members_without_choice" class="btn btn-outline-dark w-100 mt-2"><i class="bi bi-person-exclamation me-2"></i>Nur ohne Auswahl</button></form></div></div>
        <div class="col-md-6"><div class="panel p-4 h-100 border border-danger-subtle"><div class="d-flex align-items-center gap-3 mb-3"><div class="icon-tile"><i class="bi bi-arrow-counterclockwise"></i></div><div><h2 class="h5 mb-1">Status zurücksetzen</h2><div class="text-secondary small">Neuer Abend / neuer Termin</div></div></div><form method="post" onsubmit="return confirm('Wirklich alle aktuellen Getränkestatus auf „Derzeit nichts“ setzen?');"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="reset"><div class="mb-3"><label class="form-label small">Grund / Anlass</label><input name="reason" class="form-control" value="Neuer Abend"></div><button class="btn btn-outline-danger w-100"><i class="bi bi-arrow-counterclockwise me-2"></i>Alle Status zurücksetzen</button></form><?php if($lastReset):?><div class="small text-secondary mt-3">Zuletzt: <?=e($lastReset['reset_at'])?> · <?=e($lastReset['reason'] ?: 'ohne Grund')?></div><?php endif;?></div></div>
      </div>

      <div class="panel p-4 mb-4"><div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3"><div><h2 class="h5 fw-bold mb-1">Aktueller Stand</h2><div class="small text-secondary"><?= $chosen ?> von <?= $total ?> aktiven Konten mit Getränk</div></div><a class="btn btn-brand" href="/theke.php">An der Theke anzeigen</a></div><div class="row g-2"><?php if(!$counts):?><div class="col-12 text-secondary">Noch keine Getränke ausgewählt.</div><?php endif;?><?php foreach($counts as $c):?><div class="col-sm-6 col-xl-4"><div class="drink-chip d-flex justify-content-between"><span><?=e($c['emoji'] ?: '🥤')?> <?=e($c['name'])?></span><strong><?= (int)$c['qty'] ?></strong></div></div><?php endforeach;?><div class="col-sm-6 col-xl-4"><div class="drink-chip d-flex justify-content-between text-secondary"><span>⏸️ Derzeit nichts</span><strong><?= max(0,$total-$chosen) ?></strong></div></div></div></div>

      <div class="panel p-4"><div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3"><div><h2 class="h5 fw-bold mb-1">Getränkeauswahl</h2><div class="small text-secondary">Mitgliedervorschläge freigeben oder Getränke deaktivieren.</div></div><button class="btn btn-dark" data-bs-toggle="modal" data-bs-target="#newDrink"><i class="bi bi-plus-lg me-1"></i> Getränk anlegen</button></div><div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Getränk</th><th>Status</th><th>Quelle</th><th class="text-end">Aktion</th></tr></thead><tbody><?php foreach($drinks as $d):?><tr><td><strong><?=e($d['emoji'] ?: '🥤')?> <?=e($d['name'])?></strong></td><td><?php if(!(int)$d['is_approved']):?><span class="badge text-bg-warning">Vorschlag</span><?php elseif((int)$d['is_active']):?><span class="badge text-bg-success">Aktiv</span><?php else:?><span class="badge text-bg-secondary">Deaktiviert</span><?php endif;?></td><td><?= $d['created_by'] ? e(trim(($d['first_name']??'').' '.($d['last_name']??''))) : 'System' ?></td><td class="text-end"><div class="d-inline-flex gap-1"><?php if(!(int)$d['is_approved']):?><form method="post"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="approve"><input type="hidden" name="id" value="<?=$d['id']?>"><button class="btn btn-sm btn-outline-success">Freigeben</button></form><?php endif;?><form method="post"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?=$d['id']?>"><button class="btn btn-sm btn-outline-secondary"><?= (int)$d['is_active'] ? 'Deaktivieren' : 'Aktivieren' ?></button></form></div></td></tr><?php endforeach;?></tbody></table></div></div>
    </section>
  </div>
</div>
<div class="modal fade" id="newDrink"><div class="modal-dialog modal-dialog-centered"><form method="post" class="modal-content"><div class="modal-header"><h2 class="modal-title fs-5">Getränk anlegen</h2><button class="btn-close" type="button" data-bs-dismiss="modal"></button></div><div class="modal-body"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="create"><div class="row g-3"><div class="col-9"><label class="form-label">Name</label><input name="name" class="form-control" maxlength="50" required></div><div class="col-3"><label class="form-label">Emoji</label><input name="emoji" class="form-control" value="🥤" maxlength="8"></div><div class="col-12"><label class="form-label">Sortierung</label><input type="number" name="sort_order" class="form-control" value="100"></div></div></div><div class="modal-footer"><button class="btn btn-brand">Anlegen</button></div></form></div></div>
<?php require __DIR__.'/includes/footer.php'; ?>
