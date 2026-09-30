<?php
require_once __DIR__ . '/includes/app.php';
$u = require_login();

$isOpsDashboard = can_act_as_spiess($pdo,$u) || is_leader();

$activeMembers = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE is_active=1")->fetchColumn();
$chosenCount = (int)$pdo->query("
    SELECT COUNT(*)
    FROM user_drink_preferences p
    JOIN users u ON u.id=p.user_id
    WHERE u.is_active=1 AND p.drink_id IS NOT NULL
")->fetchColumn();

$fineStats = $pdo->query("
    SELECT COUNT(*) entries
    FROM fines
    WHERE status='open'
")->fetch();
$totalOpenRounds=(int)($fineStats['entries']??0);

$st=$pdo->prepare("SELECT COUNT(*) FROM fines WHERE user_id=? AND status='open'");
$st->execute([(int)$u['id']]);
$myOpenRounds=(int)$st->fetchColumn();

$activeMeeting=active_meeting($pdo);
$hasVoted=false;
if($activeMeeting && $activeMeeting['status']==='poll'){
  $st=$pdo->prepare('SELECT 1 FROM meeting_votes v JOIN meeting_poll_options o ON o.id=v.option_id WHERE o.meeting_id=? AND v.user_id=? LIMIT 1');
  $st->execute([$activeMeeting['id'],(int)$u['id']]); $hasVoted=(bool)$st->fetchColumn();
}

$st=$pdo->prepare("
    SELECT p.drink_id,d.name,d.emoji,d.is_approved
    FROM user_drink_preferences p
    LEFT JOIN drinks d ON d.id=p.drink_id
    WHERE p.user_id=?
    LIMIT 1
");
$st->execute([(int)$u['id']]);
$currentDrink=$st->fetch() ?: null;

$recentFines=$pdo->query("
    SELECT f.id,f.reason,f.status,f.occurred_at,f.dispatched_at,
           u.first_name,u.last_name
    FROM fines f
    JOIN users u ON u.id=f.user_id
    ORDER BY f.created_at DESC,f.id DESC
    LIMIT 3
")->fetchAll();

$pageTitle='Übersicht';
require __DIR__.'/includes/header.php';
?>
<div class="container dashboard-shell">
  <div class="row g-4">
    <?php require __DIR__.'/includes/sidebar.php'; ?>

    <section class="col-lg-9">
      <?php require __DIR__.'/includes/active-round-widget.php'; ?>

      <?php $delegation=active_spiess_delegation($pdo); ?>
      <?php if($delegation && is_spiess_delegate($pdo,$u)): ?>
        <div class="panel p-3 mb-4 spiess-delegation-notice">
          <div class="d-flex align-items-center gap-3">
            <i class="bi bi-person-badge fs-3"></i>
            <div><strong>Du vertrittst aktuell den Spieß.</strong><div class="small text-secondary">Theke, Getränkeverwaltung und Runden stehen dir vorübergehend zur Verfügung.</div></div>
          </div>
        </div>
      <?php elseif($delegation && can_manage_spiess_delegation()): ?>
        <div class="panel p-3 mb-4 spiess-delegation-notice">
          <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
            <div><strong>Spieß-Vertretung aktiv: <?=e($delegation['representative_first'].' '.$delegation['representative_last'])?></strong><div class="small text-secondary">Seit <?=e(date('d.m.Y H:i',strtotime($delegation['started_at'])))?> Uhr.</div></div>
            <a class="btn btn-sm btn-outline-secondary" href="/spiess-vertretung.php">Vertretung verwalten</a>
          </div>
        </div>
      <?php endif; ?>

      <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div>
          <div class="eyebrow">Kirmeszug-Zentrale</div>
          <h1 class="section-title h2 mb-1">Grüß Dich, <?=e($u['first_name'])?>!</h1>
          <div class="text-secondary">
            <?=e(role_label($u['role']))?>
            <?php if(is_leader()): ?> · Zugführer<?php endif;?>
            <?php if(is_treasurer()): ?> · Kassierer<?php endif;?>
            <?php if(is_secretary()): ?> · Schriftführer<?php endif;?>
          </div>
        </div>

        <?php if(can_act_as_spiess($pdo,$u)): ?>
          <a class="btn btn-brand btn-lg" href="/theke.php"><i class="bi bi-cup-straw me-1"></i> Runde abrufen</a>
        <?php else: ?>
          <a class="btn btn-brand" href="/member-drink.php"><i class="bi bi-cup-straw me-1"></i> Getränk wählen</a>
        <?php endif;?>
      </div>

      <?php if($isOpsDashboard): ?>
        <div class="row g-3 mb-4 dashboard-ops-stats">
          <div class="col-sm-6">
            <div class="panel p-4 h-100">
              <div class="text-secondary">Getränke gewählt</div>
              <div class="stat-number"><?= $chosenCount ?></div>
              <div class="small text-secondary">von <?=$activeMembers?> aktiven Mitgliedern</div>
              <?php if(can_act_as_spiess($pdo,$u)): ?>
                <a class="btn btn-sm btn-outline-secondary mt-3" href="/theke.php">Live-Bestellung öffnen</a>
              <?php else: ?>
                <a class="btn btn-sm btn-outline-secondary mt-3" href="/member-drink.php">Eigenes Getränk öffnen</a>
              <?php endif;?>
            </div>
          </div>

          <div class="col-sm-6">
            <div class="panel p-4 h-100">
              <div class="text-secondary">Offene Runden</div>
              <div class="stat-number"><?=$totalOpenRounds?></div>
              <div class="small text-secondary">im gesamten Zug</div>
              <a class="btn btn-sm btn-outline-secondary mt-3" href="/fines.php">Runden ansehen</a>
            </div>
          </div>
        </div>
      <?php endif; ?>

        <?php if($activeMeeting): ?>
        <div class="panel p-4 mb-4 dashboard-news-panel">
          <div class="d-flex justify-content-between align-items-center gap-2 mb-3">
            <div>
              <?php if($activeMeeting['status']==='poll'): ?>
                <h2 class="h5 fw-bold mb-1">📅 Terminfindung läuft</h2>
                <div class="small text-secondary"><?= $hasVoted?'Du hast bereits abgestimmt.':'Deine Stimme fehlt noch.' ?> · <?=e($activeMeeting['title'])?></div>
              <?php else: ?>
                <h2 class="h5 fw-bold mb-1">🍻 Nächster Stammtisch</h2>
                <div class="small text-secondary"><?=e($activeMeeting['title'])?> · <?=e(meeting_display($activeMeeting['starts_at_utc']))?> Uhr<?= $activeMeeting['location']?' · '.e($activeMeeting['location']):''?></div>
              <?php endif; ?>
            </div>
            <a class="btn btn-sm btn-outline-secondary" href="/stammtisch.php"><?= $activeMeeting['status']==='poll'?'Abstimmen':'Ansehen' ?></a>
          </div>
        </div>
        <?php endif; ?>

        <div class="row g-3 mb-4">
          <div class="col-md-4">
            <div class="panel p-4 h-100">
              <div class="small text-secondary">Dein aktueller Getränkewunsch</div>
              <?php if($currentDrink && $currentDrink['drink_id']): ?>
                <div class="dashboard-personal-value"><?=e($currentDrink['emoji']?:'🥤')?> <?=e($currentDrink['name'])?></div>
                <?php if(!(int)$currentDrink['is_approved']): ?><div class="small text-warning">Vorschlag noch nicht freigegeben</div><?php endif;?>
              <?php else: ?>
                <div class="dashboard-personal-value">⏸ Derzeit nichts</div>
              <?php endif;?>
              <a class="small d-inline-block mt-2" href="/member-drink.php">Getränk ändern</a>
            </div>
          </div>

          <div class="col-md-4">
            <div class="panel p-4 h-100">
              <div class="small text-secondary">Deine offenen Runden</div>
              <div class="stat-number"><?=$myOpenRounds?></div>
              <a class="small d-inline-block mt-2" href="/fines.php">Runden ansehen</a>
            </div>
          </div>

          <div class="col-md-4">
            <div class="panel p-4 h-100">
              <div class="small text-secondary">Runden offen insgesamt</div>
              <div class="stat-number"><?=$totalOpenRounds?></div>
              <div class="small text-secondary">im gesamten Zug</div>
            </div>
          </div>
        </div>

        <div class="panel p-4 mb-4">
          <div class="d-flex justify-content-between align-items-center gap-2 mb-3">
            <div>
              <h2 class="h5 fw-bold mb-1">Letzte Runden</h2>
              <div class="small text-secondary">Die letzten drei Einträge im Rundenbuch</div>
            </div>
            <a class="btn btn-sm btn-outline-secondary" href="/fines.php">Alle Runden</a>
          </div>

          <?php if($recentFines): foreach($recentFines as $i=>$f):
            $isDispatched=$f['status']==='open' && !empty($f['dispatched_at']);
            $statusLabel=$isDispatched?'Runde holen':($f['status']==='open'?'Offen':($f['status']==='paid'?'Gegeben':'Gestrichen'));
          ?>
            <div class="d-flex flex-wrap justify-content-between gap-3 py-2 <?=$i<count($recentFines)-1?'border-bottom':''?>">
              <div>
                <strong><?=e($f['first_name'].' '.$f['last_name'])?></strong>
                <div class="small text-secondary"><?=e($f['reason'])?> · <?=e(date('d.m.Y',strtotime($f['occurred_at'])))?></div>
              </div>
              <span class="badge text-bg-<?=$isDispatched?'danger':($f['status']==='open'?'warning':($f['status']==='paid'?'success':'secondary'))?> align-self-center"><?=e($statusLabel)?></span>
            </div>
          <?php endforeach; else: ?>
            <div class="text-secondary py-2">Noch keine Runden erfasst.</div>
          <?php endif;?>
        </div>

      <?php require __DIR__.'/includes/push-status-card.php'; ?>
    </section>
  </div>
</div>
<?php require __DIR__.'/includes/footer.php'; ?>
