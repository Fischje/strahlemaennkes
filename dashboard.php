<?php
require_once __DIR__ . '/includes/app.php';
$u=require_login();
$st=$pdo->prepare('SELECT p.drink_id,d.name,d.emoji FROM user_drink_preferences p LEFT JOIN drinks d ON d.id=p.drink_id WHERE p.user_id=?');$st->execute([$u['id']]);$myDrink=$st->fetch();
$total=(int)$pdo->query('SELECT COUNT(*) FROM users WHERE is_active=1')->fetchColumn();
$chosen=(int)$pdo->query('SELECT COUNT(*) FROM users u JOIN user_drink_preferences p ON p.user_id=u.id WHERE u.is_active=1 AND p.drink_id IS NOT NULL')->fetchColumn();
$counts=[];
if(has_role('spiess','admin')) $counts=$pdo->query('SELECT d.name,d.emoji,COUNT(*) qty FROM users u JOIN user_drink_preferences p ON p.user_id=u.id JOIN drinks d ON d.id=p.drink_id WHERE u.is_active=1 GROUP BY d.id,d.name,d.emoji ORDER BY qty DESC,d.name LIMIT 6')->fetchAll();
$openFines=(int)$pdo->query("SELECT COUNT(*) FROM fines WHERE status='open'")->fetchColumn();
$openFineSum=(float)$pdo->query("SELECT COALESCE(SUM(amount),0) FROM fines WHERE status='open'")->fetchColumn();
$pageTitle='Dashboard';require __DIR__.'/includes/header.php';
?>
<div class="container dashboard-shell">
  <div class="row g-4">
    <?php if(has_role('spiess','admin')): ?>
    <aside class="col-lg-3"><div class="side-nav sticky-lg-top" style="top:92px"><div class="px-2 py-2 mb-2"><div class="small text-white-50">Angemeldet als</div><strong><?=e($u['first_name'].' '.$u['last_name'])?></strong><div class="mt-1"><span class="badge rounded-pill text-bg-danger"><?=e(role_label($u['role']))?></span></div></div><a class="active" href="/dashboard.php"><i class="bi bi-grid"></i> Übersicht</a><a href="/member-drink.php"><i class="bi bi-person-check"></i> Mein Getränk</a><a href="/drinks.php"><i class="bi bi-cup-straw"></i> Getränke</a><a href="/theke.php"><i class="bi bi-list-check"></i> Theke</a><?php if(has_role('admin')):?><a href="/members.php"><i class="bi bi-people"></i> Benutzer</a><?php endif;?><a href="/public/chronik.php"><i class="bi bi-book"></i> Chronik</a><hr class="border-secondary"><a href="#"><i class="bi bi-calendar-event"></i> Kalender <span class="badge text-bg-secondary ms-auto">später</span></a><a href="#"><i class="bi bi-calendar2-check"></i> Stammtisch <span class="badge text-bg-secondary ms-auto">später</span></a></div></aside>
    <?php endif; ?>
    <section class="<?=has_role('spiess','admin')?'col-lg-9':'col-12'?>">
      <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-4"><div><div class="eyebrow">Kirmeszug-Zentrale</div><h1 class="section-title h2 mb-1">Mahlzeit, <?=e($u['first_name'])?>!</h1><p class="text-secondary mb-0"><?=e(role_label($u['role']))?></p></div><button class="btn btn-soft pwa-install-only-browser" data-pwa-install hidden><i class="bi bi-phone me-1"></i> App installieren</button></div>

      <div class="row g-3 mb-4">
        <div class="col-sm-6 col-xl-4"><div class="panel p-3 h-100"><div class="text-secondary small">Mein Getränk</div><div class="fw-bold fs-4 mt-2"><?= $myDrink && $myDrink['drink_id'] ? e(($myDrink['emoji']?:'🥤').' '.$myDrink['name']) : '⏸️ Derzeit nichts' ?></div><a class="small text-danger text-decoration-none" href="/member-drink.php">Auswahl ändern →</a></div></div>
        <?php if(has_role('spiess','admin')): ?><div class="col-sm-6 col-xl-4"><div class="panel p-3 h-100"><div class="text-secondary small">Getränkestatus</div><div class="stat-number"><?=$chosen?> / <?=$total?></div><a class="small text-danger text-decoration-none" href="/theke.php">Thekenansicht →</a></div></div><div class="col-sm-6 col-xl-4"><div class="panel p-3 h-100"><div class="text-secondary small">Offene Strafen</div><div class="stat-number"><?=$openFines?></div><div class="small text-secondary"><?=number_format($openFineSum,2,',','.')?> €</div></div></div><?php endif; ?>
      </div>

      <?php if(has_role('spiess','admin')): ?>
      <div class="row g-4">
        <div class="col-xl-7"><div class="panel p-4"><div class="d-flex justify-content-between align-items-center mb-3"><div><h2 class="h5 fw-bold mb-0">Aktuelle Getränkewünsche</h2><small class="text-secondary">für den nächsten Thekengang</small></div><span class="badge rounded-pill text-bg-success">Live</span></div><?php if(!$counts):?><div class="text-secondary py-3">Noch keine Getränke ausgewählt.</div><?php endif;?><?php foreach($counts as $c):?><div class="drink-chip d-flex align-items-center justify-content-between mb-2"><span><?=e($c['emoji']?:'🥤')?> <?=e($c['name'])?></span><strong><?= (int)$c['qty'] ?></strong></div><?php endforeach;?><a class="btn btn-dark w-100 mt-3" href="/theke.php"><i class="bi bi-list-check me-2"></i>Bestellansicht öffnen</a></div></div>
        <div class="col-xl-5"><div class="panel p-4 h-100"><h2 class="h5 fw-bold mb-3">Schnellaktionen</h2><a class="btn btn-brand w-100 mb-2" href="/drinks.php"><i class="bi bi-bell-fill me-2"></i>Mitglieder erinnern</a><a class="btn btn-outline-danger w-100 mb-2" href="/drinks.php"><i class="bi bi-arrow-counterclockwise me-2"></i>Status zurücksetzen</a><?php if(has_role('admin')):?><a class="btn btn-outline-dark w-100" href="/members.php"><i class="bi bi-people me-2"></i>Benutzer verwalten</a><?php endif;?></div></div>
      </div>
      <?php else: ?>
      <div class="panel p-4"><div class="d-flex gap-3 align-items-center"><div class="icon-tile green"><i class="bi bi-cup-straw"></i></div><div><h2 class="h5 fw-bold mb-1">Für die nächste Runde bereit?</h2><p class="text-secondary mb-0">Deine Auswahl bleibt so lange aktiv, bis du ein anderes Getränk oder „Derzeit nichts“ auswählst.</p></div></div><a class="btn btn-brand btn-lg w-100 mt-4" href="/member-drink.php">Getränk auswählen</a></div>
      <?php endif; ?>

      <div class="panel install-card p-4 mt-4 pwa-install-only-browser"><div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3"><div><div class="eyebrow">PWA</div><h2 class="h5 fw-bold mb-1">Wie eine App auf dem Handy</h2><div class="small text-secondary">Zum Home-Bildschirm hinzufügen – mit eigenem Icon und direktem Start.</div></div><button class="btn btn-outline-dark" data-pwa-install hidden><i class="bi bi-download me-2"></i>App installieren</button></div></div>
    </section>
  </div>
</div>
<?php require __DIR__.'/includes/footer.php'; ?>
