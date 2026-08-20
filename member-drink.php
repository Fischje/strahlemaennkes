<?php
require_once __DIR__ . '/includes/app.php';
$u = require_login();
$config = require __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'choose') {
        $drinkId = (int)($_POST['drink_id'] ?? 0);
        $st = $pdo->prepare('SELECT id FROM drinks WHERE id=? AND is_active=1 AND (is_approved=1 OR created_by=?)');
        $st->execute([$drinkId, $u['id']]);
        if (!$st->fetchColumn()) {
            flash('danger', 'Dieses Getränk steht dir nicht zur Auswahl.');
            redirect('/member-drink.php');
        }
        $st = $pdo->prepare('INSERT INTO user_drink_preferences(user_id,drink_id,updated_at) VALUES(?,?,CURRENT_TIMESTAMP) ON CONFLICT(user_id) DO UPDATE SET drink_id=excluded.drink_id, updated_at=CURRENT_TIMESTAMP');
        $st->execute([$u['id'], $drinkId]);
        flash('success', 'Getränkestatus gespeichert.');
        redirect('/member-drink.php');
    }

    if ($action === 'none') {
        $st = $pdo->prepare('INSERT INTO user_drink_preferences(user_id,drink_id,updated_at) VALUES(?,NULL,CURRENT_TIMESTAMP) ON CONFLICT(user_id) DO UPDATE SET drink_id=NULL, updated_at=CURRENT_TIMESTAMP');
        $st->execute([$u['id']]);
        flash('success', 'Du stehst jetzt auf „Derzeit nichts“.');
        redirect('/member-drink.php');
    }

    if ($action === 'propose') {
        $name = trim($_POST['name'] ?? '');
        $emoji = trim($_POST['emoji'] ?? '🥤');
        if (mb_strlen($name) < 2 || mb_strlen($name) > 50) {
            flash('danger', 'Bitte einen Getränkenamen mit 2 bis 50 Zeichen angeben.');
            redirect('/member-drink.php');
        }
        if ($emoji === '') $emoji = '🥤';
        try {
            $pdo->beginTransaction();
            $st = $pdo->prepare('INSERT INTO drinks(name,emoji,is_active,is_approved,created_by,sort_order) VALUES(?,?,1,0,?,999)');
            $st->execute([$name, mb_substr($emoji, 0, 8), $u['id']]);
            $drinkId = (int)$pdo->lastInsertId();
            $st = $pdo->prepare('INSERT INTO user_drink_preferences(user_id,drink_id,updated_at) VALUES(?,?,CURRENT_TIMESTAMP) ON CONFLICT(user_id) DO UPDATE SET drink_id=excluded.drink_id, updated_at=CURRENT_TIMESTAMP');
            $st->execute([$u['id'], $drinkId]);
            $pdo->commit();
            flash('success', 'Neues Getränk vorgeschlagen und für dich ausgewählt. Der Spieß kann es für alle freigeben.');
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            flash('warning', 'Dieses Getränk gibt es bereits. Bitte wähle es aus der Liste oder verwende einen anderen Namen.');
        }
        redirect('/member-drink.php');
    }
}

$st = $pdo->prepare('SELECT p.drink_id,p.updated_at,d.name,d.emoji,d.is_approved,d.created_by FROM user_drink_preferences p LEFT JOIN drinks d ON d.id=p.drink_id WHERE p.user_id=?');
$st->execute([$u['id']]);
$current = $st->fetch() ?: ['drink_id'=>null,'updated_at'=>null,'name'=>null,'emoji'=>null,'is_approved'=>null,'created_by'=>null];

$st = $pdo->prepare('SELECT id,name,emoji,is_approved,created_by FROM drinks WHERE is_active=1 AND (is_approved=1 OR created_by=?) ORDER BY is_approved DESC, sort_order, name COLLATE NOCASE');
$st->execute([$u['id']]);
$drinks = $st->fetchAll();

$latestReminder = $pdo->query("SELECT target,message,sent_at FROM drink_reminders ORDER BY id DESC LIMIT 1")->fetch();
$showReminder = false;
if ($latestReminder) {
    $changedAt = $current['updated_at'] ?? '1970-01-01 00:00:00';
    $showReminder = $latestReminder['sent_at'] > $changedAt && ($latestReminder['target'] === 'all_members' || empty($current['drink_id']));
}

$pageTitle = 'Mein Getränk';
require __DIR__ . '/includes/header.php';
?>
<div class="container py-4 py-md-5" style="max-width:820px">
  <div class="d-flex justify-content-between align-items-start gap-3 mb-4">
    <div><div class="eyebrow">Dein Status</div><h1 class="section-title h2 mb-1">Mein Getränk</h1><p class="text-secondary mb-0">Bleibt gespeichert, bis du es änderst.</p></div>
    <button type="button" class="btn btn-soft pwa-install-only-browser" data-pwa-install hidden><i class="bi bi-phone me-1"></i> App installieren</button>
  </div>

  <?php if ($showReminder): ?>
    <div class="alert alert-warning border-0 shadow-sm"><i class="bi bi-bell-fill me-2"></i><strong>Der Spieß fragt nach:</strong> <?= e($latestReminder['message']) ?></div>
  <?php endif; ?>

  <section class="panel current-drink p-4 mb-4 text-center">
    <div class="small text-secondary">Aktuell ausgewählt</div>
    <?php if ($current['drink_id']): ?>
      <div class="current-emoji mt-2"><?= e($current['emoji'] ?: '🥤') ?></div>
      <div class="display-6 fw-bold mb-1"><?= e($current['name']) ?></div>
      <?php if (!(int)$current['is_approved']): ?><span class="badge rounded-pill text-bg-warning mb-2">Dein Vorschlag · Freigabe offen</span><?php endif; ?>
      <div class="small text-success"><i class="bi bi-check-circle-fill me-1"></i>Gilt auch für die nächsten Runden</div>
    <?php else: ?>
      <div class="current-emoji mt-2">⏸️</div><div class="display-6 fw-bold mb-1">Derzeit nichts</div><div class="small text-secondary">Du wirst bei der nächsten Bestellung nicht mitgezählt.</div>
    <?php endif; ?>
  </section>

  <h2 class="h5 fw-bold mb-3">Auswahl ändern</h2>
  <div class="row g-3 mb-4">
    <?php foreach ($drinks as $drink): $active = (int)$current['drink_id'] === (int)$drink['id']; ?>
      <div class="col-6 col-md-4">
        <form method="post" class="h-100">
          <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="choose"><input type="hidden" name="drink_id" value="<?= (int)$drink['id'] ?>">
          <button class="drink-choice <?= $active ? 'active' : '' ?>" type="submit">
            <span class="emoji"><?= e($drink['emoji'] ?: '🥤') ?></span><strong><?= e($drink['name']) ?></strong>
            <?php if (!(int)$drink['is_approved']): ?><small class="text-warning-emphasis">nur dein Vorschlag</small><?php endif; ?>
            <?php if ($active): ?><span class="position-absolute top-0 end-0 m-2 text-danger"><i class="bi bi-check-circle-fill"></i></span><?php endif; ?>
          </button>
        </form>
      </div>
    <?php endforeach; ?>
    <div class="col-6 col-md-4"><button class="drink-choice" type="button" data-bs-toggle="modal" data-bs-target="#newDrink"><span class="emoji">＋</span><strong>Neues Getränk</strong><small class="text-secondary">vorschlagen</small></button></div>
  </div>

  <form method="post" class="mb-3"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="none"><button class="btn btn-outline-danger btn-lg w-100"><i class="bi bi-pause-circle me-2"></i>Derzeit nichts</button></form>

  <div class="panel p-3 p-md-4 mt-4">
    <div class="d-flex gap-3 align-items-center"><div class="icon-tile green"><i class="bi bi-bell"></i></div><div class="flex-grow-1"><strong>Erinnerungen aufs Handy</strong><div class="small text-secondary">Der Spieß kann dich vor der nächsten Runde an deine Auswahl erinnern.</div></div></div>
    <button type="button" class="btn btn-outline-dark w-100 mt-3" id="pushEnable"><i class="bi bi-bell-fill me-2"></i>Push-Benachrichtigungen aktivieren</button>
    <div id="pushResult" class="small mt-2 text-center"></div>
  </div>
</div>

<div class="modal fade" id="newDrink" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><form method="post" class="modal-content"><div class="modal-header"><h2 class="modal-title fs-5">Getränk vorschlagen</h2><button class="btn-close" type="button" data-bs-dismiss="modal"></button></div><div class="modal-body"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="propose"><div class="mb-3"><label class="form-label">Name</label><input class="form-control" name="name" maxlength="50" placeholder="z. B. Spezi" required></div><div><label class="form-label">Emoji / Symbol</label><input class="form-control" name="emoji" maxlength="8" value="🥤"></div><div class="small text-secondary mt-3">Du kannst es sofort selbst auswählen. Für alle anderen erscheint es erst, wenn der Spieß es freigibt.</div></div><div class="modal-footer"><button class="btn btn-brand">Vorschlagen & auswählen</button></div></form></div></div>
<script>
document.getElementById('pushEnable')?.addEventListener('click', async function(){const out=document.getElementById('pushResult'); this.disabled=true; out.className='small mt-2 text-center text-secondary'; out.textContent='Wird eingerichtet …'; try{await SM.enablePush(<?= json_encode($config['push']['public_key']) ?>);out.className='small mt-2 text-center text-success';out.textContent='Push-Benachrichtigungen sind aktiviert.';}catch(e){out.className='small mt-2 text-center text-danger';out.textContent=e.message;}finally{this.disabled=false;}});
</script>
<?php require __DIR__ . '/includes/footer.php'; ?>
