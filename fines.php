<?php
require_once __DIR__.'/includes/app.php';
$u=require_login();
$canManage=can_act_as_spiess($pdo,$u);

function format_round_duration(int $seconds): string {
    $seconds=max(0,$seconds);
    if($seconds<60) return $seconds.' Sek.';
    $minutes=intdiv($seconds,60);
    $rest=$seconds%60;
    if($minutes<60) return $minutes.' Min. '.($rest?$rest.' Sek.':'');
    $hours=intdiv($minutes,60);
    $mins=$minutes%60;
    return $hours.' Std. '.($mins?$mins.' Min.':'');
}

if($_SERVER['REQUEST_METHOD']==='POST'){
    if(!$canManage){ http_response_code(403); exit('Nur Spieß oder Administrator dürfen Runden verwalten.'); }
    verify_csrf();
    $action=$_POST['action']??'';

    if($action==='create'){
        $userId=(int)($_POST['user_id']??0);
        $rounds=max(1,min(50,(int)($_POST['rounds_count']??1)));
        $reason=trim($_POST['reason']??'');
        $details=trim($_POST['details']??'');
        $date=trim($_POST['occurred_at']??'');
        $validDate=DateTime::createFromFormat('Y-m-d',$date);

        if($userId<1 || $reason==='' || !$validDate || $validDate->format('Y-m-d')!==$date){
            flash('danger','Bitte Mitglied, Anzahl, Datum und Grund vollständig angeben.');
            redirect('/fines.php');
        }

        $pdo->beginTransaction();
        try{
            $insert=$pdo->prepare("
                INSERT INTO fines(user_id,created_by,reason,status,occurred_at,details)
                VALUES(?,?,?,'open',?,?)
            ");
            for($i=0;$i<$rounds;$i++){
                $insert->execute([$userId,$u['id'],$reason,$date.' 12:00:00',$details!==''?$details:null]);
            }
            $pdo->commit();
        }catch(Throwable $e){
            if($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }

        flash('success',$rounds===1?'Eine Runde wurde aufgeschrieben.':$rounds.' einzelne Runden wurden aufgeschrieben.');
        redirect('/fines.php');
    }

    if($action==='dispatch'){
        $id=(int)($_POST['id']??0);
        $st=$pdo->prepare("
            SELECT f.id,f.user_id,f.reason,u.first_name,u.last_name
            FROM fines f JOIN users u ON u.id=f.user_id
            WHERE f.id=? AND f.status='open'
        ");
        $st->execute([$id]);
        $round=$st->fetch();
        if(!$round){
            flash('danger','Diese Runde ist nicht mehr offen.');
            redirect('/fines.php');
        }

        $active=active_round_for_user($pdo,(int)$round['user_id']);
        if($active && (int)$active['id']!==$id){
            flash('warning',$round['first_name'].' hat bereits eine aktive Runde zum Holen.');
            redirect('/fines.php');
        }

        $pdo->prepare("UPDATE fines SET dispatched_at=CURRENT_TIMESTAMP,dispatched_by=? WHERE id=?")
            ->execute([$u['id'],$id]);

        $push=send_push_to_user(
            $pdo,
            (int)$round['user_id'],
            'Strahlemännkes 🍻 – Du bist dran!',
            'Der Spieß schickt dich eine Runde holen. Öffne die App für die aktuelle Live-Bestellung.',
            '/round-order.php'
        );

        if($push['sent']>0){
            flash('success','Runde holen an '.$round['first_name'].' geschickt. Push wurde an '.$push['sent'].' Gerät(e) zugestellt.');
        }elseif($push['configured'] && !$push['error']){
            flash('warning','Runde holen wurde aktiviert. Für '.$round['first_name'].' ist aber kein aktives Push-Gerät registriert.');
        }else{
            flash('warning','Runde holen wurde aktiviert. Push konnte nicht gesendet werden'.($push['error']?': '.$push['error']:'.'));
        }
        redirect('/fines.php');
    }

    if($action==='undispatch'){
        $id=(int)($_POST['id']??0);
        $pdo->prepare("UPDATE fines SET dispatched_at=NULL,dispatched_by=NULL WHERE id=? AND status='open'")
            ->execute([$id]);
        flash('success','Runde wurde wieder zurückgestellt.');
        redirect('/fines.php');
    }

    if($action==='settle'){
        $id=(int)($_POST['id']??0);
        $st=$pdo->prepare("SELECT user_id,reason FROM fines WHERE id=? AND status='open'");
        $st->execute([$id]);
        $round=$st->fetch();
        if(!$round){
            flash('danger','Diese Runde ist nicht mehr offen.');
            redirect('/fines.php');
        }

        $pdo->prepare("
            UPDATE fines
            SET status='paid',
                settled_at=CURRENT_TIMESTAMP,
                dispatched_at=NULL,
                dispatched_by=NULL
            WHERE id=?
        ")->execute([$id]);

        $givenPush=send_push_to_user($pdo,(int)$round['user_id'],'Runde gegeben 🍻','Deine Runde „'.(string)$round['reason'].'“ wurde vom Spieß als gegeben markiert.','/fines.php','round-settled');
        flash('success','Runde gegeben – dieser Eintrag ist erledigt.'.($givenPush['sent']>0?' Push wurde zugestellt.':''));
        redirect('/fines.php');
    }

    if($action==='reopen'){
        $id=(int)($_POST['id']??0);
        $pdo->prepare("UPDATE fines SET status='open',settled_at=NULL,dispatched_at=NULL,dispatched_by=NULL WHERE id=?")
            ->execute([$id]);
        flash('success','Runde(n) wieder als offen markiert.');
        redirect('/fines.php');
    }

    if($action==='cancel'){
        $id=(int)($_POST['id']??0);
        $pdo->prepare("UPDATE fines SET status='cancelled',settled_at=NULL,dispatched_at=NULL,dispatched_by=NULL WHERE id=?")
            ->execute([$id]);
        flash('success','Eintrag wurde gestrichen.');
        redirect('/fines.php');
    }

    if($action==='delete'){
        // Endgültiges Löschen bleibt ausschließlich dem echten Spieß/Admin vorbehalten.
        // Eine temporäre Spieß-Vertretung darf Runden verwalten, aber niemals löschen.
        if(!has_role('spiess','admin')){
            http_response_code(403);
            exit('Spieß-Vertreter dürfen Rundeneinträge nicht endgültig löschen.');
        }
        $id=(int)($_POST['id']??0);
        $st=$pdo->prepare("SELECT user_id,status,dispatched_at FROM fines WHERE id=?");
        $st->execute([$id]);
        $round=$st->fetch();
        if(!$round){
            flash('danger','Der Rundeneintrag wurde nicht gefunden.');
            redirect('/fines.php');
        }

        $pdo->prepare("DELETE FROM fines WHERE id=?")->execute([$id]);
        flash('success','Rundeneintrag wurde endgültig gelöscht.');
        redirect('/fines.php');
    }
}

$rounds=$pdo->query("
    SELECT f.*,u.first_name,u.last_name,c.first_name creator_first,c.last_name creator_last,
           d.first_name dispatcher_first,d.last_name dispatcher_last
    FROM fines f
    JOIN users u ON u.id=f.user_id
    JOIN users c ON c.id=f.created_by
    LEFT JOIN users d ON d.id=f.dispatched_by
    ORDER BY
      CASE
        WHEN f.status='open' AND f.dispatched_at IS NOT NULL THEN 0
        WHEN f.status='open' THEN 1
        ELSE 2
      END,
      COALESCE(f.dispatched_at,f.occurred_at) DESC,
      f.id DESC
")->fetchAll();

$members=$canManage
    ? $pdo->query("SELECT id,first_name,last_name FROM users WHERE is_active=1 ORDER BY last_name,first_name")->fetchAll()
    : [];

$openRounds=0;
$activeCount=0;
foreach($rounds as $r){
    if($r['status']==='open') $openRounds++;
    if($r['status']==='open' && $r['dispatched_at']) $activeCount++;
}
$myActive=active_round_for_user($pdo,(int)$u['id']);

$st=$pdo->prepare("SELECT COUNT(*) FROM fines WHERE user_id=? AND status='open'");
$st->execute([(int)$u['id']]);
$myOpenRounds=(int)$st->fetchColumn();

$roundStats=[
    'most_open'=>null,
    'most_ever'=>null,
    'fastest'=>null,
    'oldest_open'=>null,
];

$roundStats['most_open']=$pdo->query("
    SELECT u.first_name,u.last_name,
           COUNT(*) AS rounds
    FROM fines f
    JOIN users u ON u.id=f.user_id
    WHERE f.status='open'
    GROUP BY f.user_id,u.first_name,u.last_name
    ORDER BY rounds DESC,u.last_name,u.first_name
    LIMIT 1
")->fetch() ?: null;

$roundStats['most_ever']=$pdo->query("
    SELECT u.first_name,u.last_name,
           COUNT(*) AS rounds
    FROM fines f
    JOIN users u ON u.id=f.user_id
    WHERE f.status<>'cancelled'
    GROUP BY f.user_id,u.first_name,u.last_name
    ORDER BY rounds DESC,u.last_name,u.first_name
    LIMIT 1
")->fetch() ?: null;

$roundStats['fastest']=$pdo->query("
    SELECT u.first_name,u.last_name,f.reason,
           EXTRACT(EPOCH FROM (f.settled_at-f.occurred_at))::BIGINT AS seconds
    FROM fines f
    JOIN users u ON u.id=f.user_id
    WHERE f.status='paid'
      AND f.occurred_at IS NOT NULL
      AND f.settled_at IS NOT NULL
      AND f.settled_at > f.occurred_at
    ORDER BY seconds ASC,f.id ASC
    LIMIT 1
")->fetch() ?: null;

$roundStats['oldest_open']=$pdo->query("
    SELECT u.first_name,u.last_name,f.reason,f.occurred_at,
           1 AS rounds
    FROM fines f
    JOIN users u ON u.id=f.user_id
    WHERE f.status='open'
    ORDER BY f.occurred_at ASC,f.id ASC
    LIMIT 1
")->fetch() ?: null;

$pageTitle='Runden';
require __DIR__.'/includes/header.php';
?>
<div class="container dashboard-shell"><div class="row g-4">
<?php require __DIR__.'/includes/sidebar.php';?>
<section class="col-lg-9">
  <?php if($myActive): ?>
    <div class="round-callout mb-4">
      <div>
        <div class="eyebrow mb-1">🍻 Du bist dran!</div>
        <h1 class="h3 mb-1">Aktuelle Runde holen</h1>
        <div class="text-secondary">1× Runde · <?=e($myActive['reason'])?></div>
      </div>
      <a class="btn btn-brand btn-lg" href="/round-order.php"><i class="bi bi-basket2 me-1"></i> Aktuelle Runde holen</a>
    </div>
  <?php endif;?>

  <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <div>
      <div class="eyebrow">Rundenbuch</div>
      <h1 class="section-title h2 mb-1">Runden</h1>
      <?php if(!$canManage): ?><div class="small text-secondary mb-1">Hier siehst du die Runden des gesamten Zugs inklusive Grund, Datum und Status. Bearbeiten können sie nur Spieß und Administrator.</div><?php endif;?>
      <p class="text-secondary mb-0">
        <?php if($openRounds): ?><strong class="text-light"><?=$openRounds?></strong> offene <?= $openRounds===1?'Runde':'Runden' ?>.
        <?php else: ?>Aktuell keine offenen Runden.<?php endif;?>
        <?php if($canManage && $activeCount): ?> · <strong class="text-warning"><?=$activeCount?></strong> gerade zum Holen unterwegs.<?php endif;?>
      </p>
    </div>
    <?php if($canManage): ?>
      <button class="btn btn-brand" data-bs-toggle="modal" data-bs-target="#newRound"><i class="bi bi-plus-lg me-1"></i> Runde aufschreiben</button>
    <?php endif;?>
  </div>

  <div class="panel p-3 p-md-4 mb-4 personal-round-count">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
      <div>
        <div class="small text-secondary">Deine offenen Runden derzeit</div>
        <div class="personal-round-number"><?=$myOpenRounds?></div>
        <div class="small text-secondary">
          <?= $myOpenRounds===0 ? 'Du hast aktuell keine offene Runde.' : ($myOpenRounds===1 ? 'Du hast aktuell eine offene Runde.' : 'Du hast aktuell '.$myOpenRounds.' offene Runden.') ?>
        </div>
      </div>
      <div class="personal-round-icon" aria-hidden="true">🍻</div>
    </div>
  </div>

  <div class="row g-3 mb-4 round-stats">
    <div class="col-sm-6 col-xl-3">
      <div class="panel p-3 h-100">
        <div class="small text-secondary">Meiste offene Runden</div>
        <?php if($roundStats['most_open']): ?>
          <div class="stat-number"><?=(int)$roundStats['most_open']['rounds']?></div>
          <div class="small"><?=e($roundStats['most_open']['first_name'].' '.$roundStats['most_open']['last_name'])?></div>
        <?php else: ?>
          <div class="stat-number">0</div><div class="small text-secondary">Niemand</div>
        <?php endif;?>
      </div>
    </div>
    <div class="col-sm-6 col-xl-3">
      <div class="panel p-3 h-100">
        <div class="small text-secondary">Meiste seit Aufzeichnung</div>
        <?php if($roundStats['most_ever']): ?>
          <div class="stat-number"><?=(int)$roundStats['most_ever']['rounds']?></div>
          <div class="small"><?=e($roundStats['most_ever']['first_name'].' '.$roundStats['most_ever']['last_name'])?></div>
        <?php else: ?>
          <div class="stat-number">0</div><div class="small text-secondary">Noch keine Daten</div>
        <?php endif;?>
      </div>
    </div>
    <div class="col-sm-6 col-xl-3">
      <div class="panel p-3 h-100">
        <div class="small text-secondary">Schnellste gegebene Runde</div>
        <?php if($roundStats['fastest']): ?>
          <div class="round-stat-time"><?=e(format_round_duration((int)$roundStats['fastest']['seconds']))?></div>
          <div class="small"><?=e($roundStats['fastest']['first_name'].' '.$roundStats['fastest']['last_name'])?></div>
        <?php else: ?>
          <div class="round-stat-time">–</div><div class="small text-secondary">Ab jetzt messbar</div>
        <?php endif;?>
      </div>
    </div>
    <div class="col-sm-6 col-xl-3">
      <div class="panel p-3 h-100">
        <div class="small text-secondary">Älteste offene Runde</div>
        <?php if($roundStats['oldest_open']): ?>
          <div class="round-stat-date"><?=e(date('d.m.Y',strtotime($roundStats['oldest_open']['occurred_at'])))?></div>
          <div class="small"><?=e($roundStats['oldest_open']['first_name'].' '.$roundStats['oldest_open']['last_name'])?> · <?=(int)$roundStats['oldest_open']['rounds']?>× offen</div>
        <?php else: ?>
          <div class="round-stat-date">–</div><div class="small text-secondary">Keine offene Runde</div>
        <?php endif;?>
      </div>
    </div>
  </div>

  <div class="panel p-4">
    <?php if(!$rounds): ?>
      <div class="text-center text-secondary py-5"><div class="fs-1 mb-2">🍻</div>Noch keine Runden eingetragen.</div>
    <?php else: ?>
      <div class="table-responsive"><table class="table align-middle mb-0">
        <thead><tr>
          <th>Mitglied</th>
          <th>Runden</th><th>Wofür</th><th>Wann</th><th>Status</th>
          <?php if($canManage): ?><th class="text-end">Aktion</th><?php endif;?>
        </tr></thead>
        <tbody>
        <?php foreach($rounds as $r):
          $isDispatched=$r['status']==='open' && !empty($r['dispatched_at']);
          if($isDispatched) $status=['danger','Runde holen'];
          else $status=[
            'open'=>['warning','Offen'],
            'paid'=>['success','Gegeben'],
            'cancelled'=>['secondary','Gestrichen'],
          ][$r['status']]??['secondary',$r['status']];
        ?>
          <tr class="<?=$r['status']==='paid'?'round-settled':''?> <?=$isDispatched?'round-dispatched':''?>">
            <td><strong><?=e($r['first_name'].' '.$r['last_name'])?></strong></td>
            <td><span class="round-count">1×</span></td>
            <td>
              <?=e($r['reason'])?>
              <?php if($r['details']): ?><div class="small text-secondary mt-1"><?=e($r['details'])?></div><?php endif;?>
            </td>
            <td><?=e(date('d.m.Y',strtotime($r['occurred_at'])))?></td>
            <td>
              <span class="badge text-bg-<?=$status[0]?>"><?=$status[1]?></span>
              <?php if($isDispatched): ?><div class="small text-secondary mt-1">seit <?=e(date('H:i',strtotime($r['dispatched_at'])))?> Uhr</div><?php endif;?>
              <?php if($r['status']==='paid' && $r['settled_at']): ?><div class="small text-secondary mt-1"><?=e(date('d.m.Y H:i',strtotime($r['settled_at'])))?></div><?php endif;?>
            </td>
            <?php if($canManage): ?><td class="text-end"><div class="d-inline-flex flex-wrap gap-1">
              <?php if($r['status']==='open' && !$isDispatched): ?>
                <form method="post">
                  <input type="hidden" name="csrf" value="<?=e(csrf_token())?>">
                  <input type="hidden" name="action" value="dispatch">
                  <input type="hidden" name="id" value="<?=(int)$r['id']?>">
                  <button class="btn btn-sm btn-brand"><i class="bi bi-send me-1"></i>Runde holen schicken</button>
                </form>
                <form method="post">
                  <input type="hidden" name="csrf" value="<?=e(csrf_token())?>">
                  <input type="hidden" name="action" value="settle">
                  <input type="hidden" name="id" value="<?=(int)$r['id']?>">
                  <button class="btn btn-sm btn-outline-success"><i class="bi bi-check-lg me-1"></i>Gegeben</button>
                </form>
                <form method="post" onsubmit="return confirm('Eintrag wirklich streichen?');">
                  <input type="hidden" name="csrf" value="<?=e(csrf_token())?>">
                  <input type="hidden" name="action" value="cancel">
                  <input type="hidden" name="id" value="<?=(int)$r['id']?>">
                  <button class="btn btn-sm btn-outline-danger">Streichen</button>
                </form>
              <?php elseif($isDispatched): ?>
                <form method="post">
                  <input type="hidden" name="csrf" value="<?=e(csrf_token())?>">
                  <input type="hidden" name="action" value="settle">
                  <input type="hidden" name="id" value="<?=(int)$r['id']?>">
                  <button class="btn btn-sm btn-success"><i class="bi bi-check-lg me-1"></i>Gegeben</button>
                </form>
                <form method="post">
                  <input type="hidden" name="csrf" value="<?=e(csrf_token())?>">
                  <input type="hidden" name="action" value="undispatch">
                  <input type="hidden" name="id" value="<?=(int)$r['id']?>">
                  <button class="btn btn-sm btn-outline-secondary">Zurückstellen</button>
                </form>
              <?php else: ?>
                <form method="post">
                  <input type="hidden" name="csrf" value="<?=e(csrf_token())?>">
                  <input type="hidden" name="action" value="reopen">
                  <input type="hidden" name="id" value="<?=(int)$r['id']?>">
                  <button class="btn btn-sm btn-outline-secondary">Wieder öffnen</button>
                </form>
              <?php endif;?>
              <?php if(has_role('spiess','admin')): ?>
                <form method="post" onsubmit="return confirm('Diesen Rundeneintrag wirklich ENDGÜLTIG löschen? Diese Aktion kann nicht rückgängig gemacht werden.');">
                  <input type="hidden" name="csrf" value="<?=e(csrf_token())?>">
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="id" value="<?=(int)$r['id']?>">
                  <button class="btn btn-sm btn-danger"><i class="bi bi-trash3 me-1"></i>Löschen</button>
                </form>
              <?php endif;?>
            </div></td><?php endif;?>
          </tr>
        <?php endforeach;?>
        </tbody>
      </table></div>
    <?php endif;?>
  </div>
</section></div></div>

<?php if($canManage): ?>
<div class="modal fade" id="newRound" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><form method="post" class="modal-content">
  <div class="modal-header"><h2 class="modal-title fs-5">Runde aufschreiben</h2><button class="btn-close" type="button" data-bs-dismiss="modal"></button></div>
  <div class="modal-body">
    <input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="create">
    <div class="mb-3"><label class="form-label">Mitglied</label><select class="form-select" name="user_id" required><option value="">Bitte wählen</option><?php foreach($members as $m):?><option value="<?=(int)$m['id']?>"><?=e($m['last_name'].', '.$m['first_name'])?></option><?php endforeach;?></select></div>
    <div class="row g-3 mb-3">
      <div class="col-5"><label class="form-label">Anzahl Runden</label><input type="number" min="1" max="50" class="form-control" name="rounds_count" value="1" required><div class="form-text">Jede Runde wird als eigener Eintrag angelegt.</div></div>
      <div class="col-7"><label class="form-label">Wann?</label><input type="date" class="form-control" name="occurred_at" value="<?=e(date('Y-m-d'))?>" required></div>
    </div>
    <div class="mb-3"><label class="form-label">Wofür?</label><input class="form-control" name="reason" maxlength="160" placeholder="z. B. zu spät beim Antreten" required></div>
    <div><label class="form-label">Wie / Notiz <span class="text-secondary">(optional)</span></label><textarea class="form-control" name="details" rows="3" maxlength="500"></textarea></div>
  </div>
  <div class="modal-footer"><button class="btn btn-brand">Aufschreiben</button></div>
</form></div></div>
<?php endif;?>
<?php require __DIR__.'/includes/footer.php';?>
