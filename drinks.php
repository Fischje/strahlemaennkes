<?php
require_once __DIR__.'/includes/app.php';
$u=require_spiess_operator($pdo);

if($_SERVER['REQUEST_METHOD']==='POST'){
    verify_csrf(); $action=$_POST['action']??'';

    if($action==='create' || $action==='edit'){
        $name=trim($_POST['name']??''); $emoji=trim($_POST['emoji']??'🥤'); $sort=(int)($_POST['sort_order']??0);
        if(mb_strlen($name)<2 || mb_strlen($name)>60){flash('danger','Getränkename muss 2 bis 60 Zeichen lang sein.');redirect('/drinks.php');}
        try{
            if($action==='create'){
                $pdo->prepare("INSERT INTO drinks(name,emoji,is_active,is_approved,created_by,sort_order) VALUES(?,?,1,1,?,?)")->execute([$name,$emoji?:'🥤',$u['id'],$sort]);
                flash('success','Getränk angelegt.');
            }else{
                $id=(int)($_POST['id']??0);
                $pdo->prepare("UPDATE drinks SET name=?,emoji=?,sort_order=? WHERE id=?")->execute([$name,$emoji?:'🥤',$sort,$id]);
                flash('success','Getränk bearbeitet.');
            }
        }catch(Throwable $e){flash('danger','Das Getränk konnte nicht gespeichert werden. Der Name ist möglicherweise bereits vergeben.');}
        redirect('/drinks.php');
    }

    if($action==='reject'){
        $id=(int)($_POST['id']??0);
        $st=$pdo->prepare("SELECT name,is_approved FROM drinks WHERE id=?");
        $st->execute([$id]);
        $drink=$st->fetch();
        if(!$drink){
            flash('danger','Getränkevorschlag wurde nicht gefunden.');
            redirect('/drinks.php');
        }
        if((int)$drink['is_approved']){
            flash('danger','Bereits freigegebene Getränke werden über „Löschen“ entfernt.');
            redirect('/drinks.php');
        }
        $pdo->prepare("DELETE FROM drinks WHERE id=?")->execute([$id]);
        flash('success','Getränkevorschlag „'.$drink['name'].'“ wurde abgelehnt.');
        redirect('/drinks.php');
    }

    if($action==='delete_drink'){
        $id=(int)($_POST['id']??0);
        $st=$pdo->prepare("SELECT name FROM drinks WHERE id=?");
        $st->execute([$id]);
        $drink=$st->fetch();
        if(!$drink){
            flash('danger','Getränk wurde nicht gefunden.');
            redirect('/drinks.php');
        }
        $pdo->prepare("UPDATE user_drink_preferences SET drink_id=NULL,updated_at=CURRENT_TIMESTAMP WHERE drink_id=?")->execute([$id]);
        $pdo->prepare("DELETE FROM drinks WHERE id=?")->execute([$id]);
        flash('success','Getränkeart „'.$drink['name'].'“ wurde endgültig gelöscht.');
        redirect('/drinks.php');
    }

    if($action==='toggle_sektbar'){
        if(!has_role('admin') && !sektbar_is_enabled($pdo)){http_response_code(403);exit('Sektbar ist nicht freigeschaltet.');}
        $id=(int)($_POST['id']??0);
        $pdo->prepare("UPDATE drinks SET is_sektbar=CASE is_sektbar WHEN 1 THEN 0 ELSE 1 END WHERE id=?")->execute([$id]);
        redirect('/drinks.php');
    }

    if($action==='toggle'){
        $id=(int)($_POST['id']??0); $pdo->prepare("UPDATE drinks SET is_active=CASE is_active WHEN 1 THEN 0 ELSE 1 END WHERE id=?")->execute([$id]);
        flash('success','Getränkestatus geändert.'); redirect('/drinks.php');
    }

    if($action==='approve'){
        $id=(int)($_POST['id']??0); $pdo->prepare("UPDATE drinks SET is_approved=1,is_active=1 WHERE id=?")->execute([$id]);
        flash('success','Getränkevorschlag freigegeben.'); redirect('/drinks.php');
    }

    if($action==='set_member_drink'){
        $memberId=(int)($_POST['member_id']??0);
        $drinkValue=$_POST['drink_id']??'';

        $st=$pdo->prepare("SELECT id,first_name,last_name FROM users WHERE id=? AND is_active=1");
        $st->execute([$memberId]);
        $member=$st->fetch();
        if(!$member){
            flash('danger','Mitglied wurde nicht gefunden oder ist nicht aktiv.');
            redirect('/drinks.php');
        }

        if($drinkValue==='none'){
            $pdo->prepare("
                INSERT INTO user_drink_preferences(user_id,drink_id,updated_at)
                VALUES(?,NULL,CURRENT_TIMESTAMP)
                ON CONFLICT(user_id) DO UPDATE SET drink_id=NULL,updated_at=CURRENT_TIMESTAMP
            ")->execute([$memberId]);

            flash('success','Getränkestatus von '.$member['first_name'].' '.$member['last_name'].' auf „Derzeit nichts“ gesetzt.');
            redirect('/drinks.php');
        }

        $drinkId=(int)$drinkValue;
        $st=$pdo->prepare("SELECT id,name FROM drinks WHERE id=? AND is_active=1 AND is_approved=1");
        $st->execute([$drinkId]);
        $drink=$st->fetch();
        if(!$drink){
            flash('danger','Dieses Getränk steht aktuell nicht zur Auswahl.');
            redirect('/drinks.php');
        }

        $pdo->prepare("
            INSERT INTO user_drink_preferences(user_id,drink_id,updated_at)
            VALUES(?,?,CURRENT_TIMESTAMP)
            ON CONFLICT(user_id) DO UPDATE SET drink_id=excluded.drink_id,updated_at=CURRENT_TIMESTAMP
        ")->execute([$memberId,$drinkId]);

        flash('success',$member['first_name'].' '.$member['last_name'].' wurde auf „'.$drink['name'].'“ gesetzt.');
        redirect('/drinks.php');
    }

    if($action==='reset'){
        $reason=trim($_POST['reason']??'Neuer Abend');
        $pdo->beginTransaction();
        try{
            $pdo->exec("UPDATE user_drink_preferences SET drink_id=NULL,updated_at=CURRENT_TIMESTAMP");
            $pdo->prepare("INSERT INTO drink_status_resets(reset_by,reason) VALUES(?,?)")->execute([$u['id'],$reason]);
            $pdo->commit(); flash('success','Alle Getränkestatus wurden auf „Derzeit nichts“ zurückgesetzt.');
        }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();flash('danger','Zurücksetzen fehlgeschlagen.');}
        redirect('/drinks.php');
    }

    if($action==='remind'){
        $target=($_POST['target']??'')==='without'?'members_without_choice':'all_members';
        if($target==='members_without_choice'){
            $count=(int)$pdo->query("SELECT COUNT(*) FROM users u LEFT JOIN user_drink_preferences p ON p.user_id=u.id WHERE u.is_active=1 AND (p.drink_id IS NULL OR p.user_id IS NULL)")->fetchColumn();
        } else {
            $count=(int)$pdo->query("SELECT COUNT(*) FROM users WHERE is_active=1")->fetchColumn();
        }
        $message='Bitte prüfe deinen aktuellen Getränkewunsch und wähle dein Getränk für die nächste Runde.';
        $pdo->prepare("INSERT INTO drink_reminders(sent_by,target,message,recipients_count) VALUES(?,?,?,?)")->execute([$u['id'],$target,$message,$count]);

        $pushResult=send_drink_reminder_pushes($pdo,$target,$message);
        if(!$pushResult['configured']){
            flash('warning',"Erinnerung für {$count} Mitglied(er) protokolliert. ".$pushResult['error']);
        }elseif($pushResult['error']){
            flash('danger',"Erinnerung wurde protokolliert, aber Push-Versand schlug fehl: ".$pushResult['error']);
        }else{
            $text="Erinnerung protokolliert. Push erfolgreich an {$pushResult['sent']} Gerät(e) gesendet.";
            if($pushResult['failed']>0) $text.=" {$pushResult['failed']} Versand/Versände fehlgeschlagen.";
            if($pushResult['expired_removed']>0) $text.=" {$pushResult['expired_removed']} abgelaufene Push-Anmeldung(en) wurden entfernt.";
            if($pushResult['queued']===0) $text.=" Es ist noch kein passendes Gerät für Push registriert.";
            flash('success',$text);
        }
        redirect('/drinks.php');
    }
}

$membersForDrink=$pdo->query("
    SELECT u.id,u.first_name,u.last_name,p.drink_id,d.name drink_name,d.emoji drink_emoji
    FROM users u
    LEFT JOIN user_drink_preferences p ON p.user_id=u.id
    LEFT JOIN drinks d ON d.id=p.drink_id
    WHERE u.is_active=1
    ORDER BY u.last_name,u.first_name
")->fetchAll();

$approvedDrinks=$pdo->query("
    SELECT id,name,emoji
    FROM drinks
    WHERE is_active=1 AND is_approved=1
    ORDER BY sort_order,name
")->fetchAll();

$drinks=$pdo->query("SELECT d.*,u.first_name,u.last_name FROM drinks d LEFT JOIN users u ON u.id=d.created_by ORDER BY d.is_approved ASC,d.is_active DESC,d.sort_order,d.name")->fetchAll();
$status=$pdo->query("SELECT d.id,d.name,d.emoji,COUNT(*) qty FROM user_drink_preferences p JOIN users u ON u.id=p.user_id AND u.is_active=1 JOIN drinks d ON d.id=p.drink_id GROUP BY d.id,d.name,d.emoji ORDER BY qty DESC,d.sort_order")->fetchAll();
$active=(int)$pdo->query("SELECT COUNT(*) FROM users WHERE is_active=1")->fetchColumn();
$chosen=array_sum(array_map(fn($r)=>(int)$r['qty'],$status));
$pageTitle='Getränke verwalten'; require __DIR__.'/includes/header.php';
?>
<div class="container dashboard-shell"><div class="row g-4">
<?php require __DIR__.'/includes/sidebar.php'; ?>
<section class="col-lg-9 drinks-admin-page">
  <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4"><div><div class="eyebrow">Getränkezentrale</div><h1 class="section-title h2 mb-1">Getränke verwalten</h1><p class="text-secondary mb-0">Auswahl, Vorschläge, Erinnerungen und Reset.</p></div><a class="btn btn-brand" href="/theke.php"><i class="bi bi-list-check me-1"></i>Thekenansicht</a></div>

  <div class="panel p-4 mb-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
      <div>
        <h2 class="h5 fw-bold mb-1">Getränk für Mitglied setzen</h2>
        <div class="small text-secondary">Falls jemand kein Handy dabeihat oder der Akku leer ist, kannst du den aktuellen Wunsch stellvertretend erfassen.</div>
      </div>
    </div>
    <form method="post">
      <input type="hidden" name="csrf" value="<?=e(csrf_token())?>">
      <input type="hidden" name="action" value="set_member_drink">
      <div class="row g-3 align-items-end">
        <div class="col-md-5">
          <label class="form-label">Mitglied</label>
          <select class="form-select" name="member_id" id="memberDrinkUser" required>
            <option value="">Bitte wählen</option>
            <?php foreach($membersForDrink as $m): ?>
              <option value="<?=(int)$m['id']?>" data-current="<?=e($m['drink_name']??'')?>">
                <?=e($m['last_name'].', '.$m['first_name'])?><?= $m['drink_name'] ? ' · aktuell: '.e($m['drink_name']) : ' · derzeit nichts' ?>
              </option>
            <?php endforeach;?>
          </select>
        </div>
        <div class="col-md-5">
          <label class="form-label">Getränk</label>
          <select class="form-select" name="drink_id" required>
            <option value="none">⏸ Derzeit nichts</option>
            <?php foreach($approvedDrinks as $d): ?>
              <option value="<?=(int)$d['id']?>"><?=e(($d['emoji']?:'🥤').' '.$d['name'])?></option>
            <?php endforeach;?>
          </select>
        </div>
        <div class="col-md-2">
          <button class="btn btn-brand w-100"><i class="bi bi-check-lg me-1"></i> Setzen</button>
        </div>
      </div>
    </form>
  </div>

  <div class="row g-3 mb-4">
    <div class="col-md-6"><div class="panel p-4 h-100"><h2 class="h5">Mitglieder erinnern</h2><p class="small text-secondary">Fordert Mitglieder auf, ihren aktuellen Getränkestatus zu prüfen.</p>
      <div class="d-grid gap-2"><form method="post"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="remind"><input type="hidden" name="target" value="all"><button class="btn btn-brand w-100"><i class="bi bi-bell-fill me-2"></i>Alle aktiven Mitglieder erinnern</button></form>
      <form method="post"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="remind"><input type="hidden" name="target" value="without"><button class="btn btn-outline-secondary w-100"><i class="bi bi-person-exclamation me-2"></i>Nur ohne Auswahl</button></form></div>
    </div></div>
    <div class="col-md-6"><div class="panel p-4 h-100 border border-danger-subtle"><h2 class="h5">Status zurücksetzen</h2><p class="small text-secondary">Für einen neuen Abend. Die Getränkeliste bleibt erhalten.</p><button class="btn btn-outline-danger w-100" data-bs-toggle="modal" data-bs-target="#resetModal"><i class="bi bi-arrow-counterclockwise me-2"></i>Alle Getränkestatus zurücksetzen</button></div></div>
  </div>

  <div class="panel p-4 mb-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3"><div><h2 class="h5 fw-bold mb-1">Aktueller Stand</h2><div class="small text-secondary"><?=$chosen?> von <?=$active?> aktiven Mitgliedern gewählt</div></div><a class="btn btn-dark" href="/theke.php">Bestellansicht</a></div>
    <div class="row g-2"><?php if($status): foreach($status as $s):?><div class="col-sm-6 col-xl-4"><div class="drink-chip d-flex justify-content-between"><span><?=e($s['emoji']?:'🥤')?> <?=e($s['name'])?></span><strong><?=(int)$s['qty']?></strong></div></div><?php endforeach;else:?><div class="text-secondary">Noch keine Auswahl.</div><?php endif;?></div>
  </div>

  <div class="panel p-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3"><div><h2 class="h5 fw-bold mb-1">Getränkeauswahl</h2><div class="small text-secondary">Anlegen, bearbeiten, freigeben, ablehnen, deaktivieren und löschen</div></div><button class="btn btn-brand" data-bs-toggle="modal" data-bs-target="#drinkModal" data-mode="create"><i class="bi bi-plus-lg me-1"></i>Getränk anlegen</button></div>
    <div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Getränk</th><th>Status</th><th>Quelle</th><th>Sort.</th><th class="text-end">Aktionen</th></tr></thead><tbody>
    <?php foreach($drinks as $d):?>
      <tr><td data-label="Getränk"><strong><?=e($d['emoji']?:'🥤')?> <?=e($d['name'])?></strong></td><td data-label="Status"><?php if(!(int)$d['is_approved']):?><span class="badge text-bg-warning">Vorschlag</span><?php elseif((int)$d['is_active']):?><span class="badge text-bg-success">Aktiv</span><?php else:?><span class="badge text-bg-secondary">Inaktiv</span><?php endif;?> <?php if((int)($d['is_sektbar']??0)):?><span class="badge text-bg-info ms-1">Sektbar</span><?php endif;?></td><td data-label="Quelle"><?= $d['created_by'] ? e(trim(($d['first_name']??'').' '.($d['last_name']??''))) : 'System' ?></td><td data-label="Sortierung"><?=(int)$d['sort_order']?></td><td data-label="Aktionen" class="text-end"><div class="d-inline-flex flex-wrap gap-1 drink-admin-actions">
        <?php if(!(int)$d['is_approved']):?><form method="post"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="approve"><input type="hidden" name="id" value="<?=(int)$d['id']?>"><button class="btn btn-sm btn-outline-success">Freigeben</button></form><form method="post" onsubmit="return confirm('Diesen Getränkevorschlag wirklich ablehnen?');"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="reject"><input type="hidden" name="id" value="<?=(int)$d['id']?>"><button class="btn btn-sm btn-outline-danger">Ablehnen</button></form><?php endif;?>
        <?php if(sektbar_is_enabled($pdo) || has_role('admin')):?><form method="post"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="toggle_sektbar"><input type="hidden" name="id" value="<?=(int)$d['id']?>"><button class="btn btn-sm btn-outline-info"><?= (int)($d['is_sektbar']??0)?'Sektbar ✓':'Sektbar markieren' ?></button></form><?php endif;?>
        <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#drinkModal" data-mode="edit" data-id="<?=(int)$d['id']?>" data-name="<?=e($d['name'])?>" data-emoji="<?=e($d['emoji']??'')?>" data-sort="<?=(int)$d['sort_order']?>">Bearbeiten</button>
        <form method="post"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?=(int)$d['id']?>"><button class="btn btn-sm <?= (int)$d['is_active']?'btn-outline-danger':'btn-outline-success' ?>"><?= (int)$d['is_active']?'Deaktivieren':'Aktivieren' ?></button></form><form method="post" onsubmit="return confirm('Diese Getränkeart wirklich ENDGÜLTIG löschen? Aktuelle Wünsche dafür werden auf „Derzeit nichts“ gesetzt.');"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="delete_drink"><input type="hidden" name="id" value="<?=(int)$d['id']?>"><button class="btn btn-sm btn-danger"><i class="bi bi-trash3 me-1"></i>Löschen</button></form>
      </div></td></tr>
    <?php endforeach;?></tbody></table></div>
  </div>
</section></div></div>

<div class="modal fade" id="drinkModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><form method="post" class="modal-content"><div class="modal-header"><h2 class="modal-title fs-5" id="drinkModalTitle">Getränk anlegen</h2><button class="btn-close" type="button" data-bs-dismiss="modal"></button></div><div class="modal-body"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" id="drinkAction" value="create"><input type="hidden" name="id" id="drinkId"><div class="mb-3"><label class="form-label">Name</label><input class="form-control" id="drinkName" name="name" maxlength="60" required></div><div class="row g-3"><div class="col-6"><label class="form-label">Emoji</label><input class="form-control" id="drinkEmoji" name="emoji" maxlength="12" value="🥤"></div><div class="col-6"><label class="form-label">Sortierung</label><input type="number" class="form-control" id="drinkSort" name="sort_order" value="0"></div></div></div><div class="modal-footer"><button class="btn btn-brand">Speichern</button></div></form></div></div>
<div class="modal fade" id="resetModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><form method="post" class="modal-content"><div class="modal-header"><h2 class="modal-title fs-5">Getränkestatus zurücksetzen?</h2><button class="btn-close" type="button" data-bs-dismiss="modal"></button></div><div class="modal-body"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="reset"><p>Alle Mitglieder werden auf <strong>„Derzeit nichts“</strong> gesetzt.</p><label class="form-label">Anlass</label><input class="form-control" name="reason" value="Neuer Abend" maxlength="100"></div><div class="modal-footer"><button class="btn btn-outline-danger">Jetzt zurücksetzen</button></div></form></div></div>
<script>
document.getElementById('drinkModal').addEventListener('show.bs.modal',e=>{const b=e.relatedTarget,m=b?.dataset.mode||'create',edit=m==='edit';document.getElementById('drinkModalTitle').textContent=edit?'Getränk bearbeiten':'Getränk anlegen';document.getElementById('drinkAction').value=edit?'edit':'create';document.getElementById('drinkId').value=edit?b.dataset.id:'';document.getElementById('drinkName').value=edit?b.dataset.name:'';document.getElementById('drinkEmoji').value=edit?b.dataset.emoji:'🥤';document.getElementById('drinkSort').value=edit?b.dataset.sort:'0';});
</script>
<?php require __DIR__.'/includes/footer.php'; ?>
