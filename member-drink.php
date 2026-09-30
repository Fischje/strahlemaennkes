<?php
require_once __DIR__.'/includes/app.php';
$u=require_login();

if($_SERVER['REQUEST_METHOD']==='POST'){
    verify_csrf();
    $action=$_POST['action']??'';


    if($action==='toggle_favorite'){
        $drinkId=(int)($_POST['drink_id']??0);
        $st=$pdo->prepare("SELECT id FROM drinks WHERE id=? AND (is_approved=1 OR created_by=?)");
        $st->execute([$drinkId,$u['id']]);
        if(!$st->fetchColumn()){ flash('danger','Dieses Getränk kann nicht als Favorit markiert werden.'); redirect('/member-drink.php'); }
        $st=$pdo->prepare("SELECT 1 FROM user_drink_favorites WHERE user_id=? AND drink_id=?");
        $st->execute([$u['id'],$drinkId]);
        if($st->fetchColumn()){
            $pdo->prepare("DELETE FROM user_drink_favorites WHERE user_id=? AND drink_id=?")->execute([$u['id'],$drinkId]);
        }else{
            $pdo->prepare("INSERT INTO user_drink_favorites(user_id,drink_id) VALUES(?,?) ON CONFLICT(user_id,drink_id) DO NOTHING")->execute([$u['id'],$drinkId]);
        }
        redirect('/member-drink.php');
    }

    if($action==='choose'){
        $drinkId=(int)($_POST['drink_id']??0);
        $st=$pdo->prepare("SELECT id FROM drinks WHERE id=? AND (is_approved=1 OR created_by=?) AND is_active=1");
        $st->execute([$drinkId,$u['id']]);
        if(!$st->fetchColumn()){ flash('danger','Dieses Getränk kann nicht ausgewählt werden.'); redirect('/member-drink.php'); }
        $pdo->prepare("INSERT INTO user_drink_preferences(user_id,drink_id,updated_at) VALUES(?,?,CURRENT_TIMESTAMP) ON CONFLICT(user_id) DO UPDATE SET drink_id=excluded.drink_id,updated_at=CURRENT_TIMESTAMP")->execute([$u['id'],$drinkId]);
        $dn=$pdo->prepare("SELECT name FROM drinks WHERE id=?"); $dn->execute([$drinkId]);
        notify_active_spiess_drink_change($pdo,$u,(string)$dn->fetchColumn());
        flash('success','Dein Getränk wurde gespeichert.');
        redirect('/member-drink.php');
    }

    if($action==='none'){
        $pdo->prepare("INSERT INTO user_drink_preferences(user_id,drink_id,updated_at) VALUES(?,NULL,CURRENT_TIMESTAMP) ON CONFLICT(user_id) DO UPDATE SET drink_id=NULL,updated_at=CURRENT_TIMESTAMP")->execute([$u['id']]);
        notify_active_spiess_drink_change($pdo,$u,'Derzeit nichts');
        flash('success','Du bist jetzt auf „Derzeit nichts“ gesetzt.');
        redirect('/member-drink.php');
    }

    if($action==='propose'){
        $name=trim($_POST['name']??'');
        $emoji=trim($_POST['emoji']??'🥤');
        if(mb_strlen($name)<2 || mb_strlen($name)>60){ flash('danger','Bitte einen Getränkenamen mit 2 bis 60 Zeichen eingeben.'); redirect('/member-drink.php'); }
        try{
            $pdo->beginTransaction();
            $st=$pdo->prepare("INSERT INTO drinks(name,emoji,is_active,is_approved,created_by,sort_order) VALUES(?,?,1,0,?,999) RETURNING id");
            $st->execute([$name,$emoji?:'🥤',$u['id']]);
            $drinkId=(int)$st->fetchColumn();
            $pdo->prepare("INSERT INTO user_drink_preferences(user_id,drink_id,updated_at) VALUES(?,?,CURRENT_TIMESTAMP) ON CONFLICT(user_id) DO UPDATE SET drink_id=excluded.drink_id,updated_at=CURRENT_TIMESTAMP")->execute([$u['id'],$drinkId]);
            $pdo->commit();
            notify_active_spiess_drink_change($pdo,$u,$name);
            flash('success','Getränk vorgeschlagen und für dich ausgewählt. Der Spieß kann es für alle freigeben.');
        }catch(Throwable $e){
            if($pdo->inTransaction())$pdo->rollBack();
            flash('danger','Das Getränk existiert möglicherweise bereits.');
        }
        redirect('/member-drink.php');
    }
}

$st=$pdo->prepare("SELECT p.drink_id,d.name,d.emoji,d.is_approved FROM user_drink_preferences p LEFT JOIN drinks d ON d.id=p.drink_id WHERE p.user_id=?");
$st->execute([$u['id']]); $current=$st->fetch() ?: null;
$st=$pdo->prepare("SELECT d.id,d.name,d.emoji,d.is_approved,d.created_by,
       CASE WHEN f.drink_id IS NULL THEN 0 ELSE 1 END AS is_favorite
    FROM drinks d
    LEFT JOIN user_drink_favorites f ON f.drink_id=d.id AND f.user_id=?
    WHERE d.is_active=1 AND (d.is_approved=1 OR d.created_by=?)
    ORDER BY is_favorite DESC,d.is_approved DESC,d.sort_order,d.name");
$st->execute([$u['id'],$u['id']]); $drinks=$st->fetchAll();

$pageTitle='Mein Getränk';
require __DIR__.'/includes/header.php';
?>
<div class="container dashboard-shell">
  <div class="row g-4">
    <?php require __DIR__.'/includes/sidebar.php'; ?>
    <section class="col-lg-9">
      <?php require __DIR__.'/includes/active-round-widget.php'; ?>
      <div class="mb-4"><div class="eyebrow">Getränkestatus</div><h1 class="section-title h2">Mein Getränk</h1><p class="text-secondary mb-0">Deine Auswahl bleibt gespeichert, bis du sie änderst. Mit dem Stern markierst du Favoriten – sie stehen automatisch ganz oben.</p></div>

      <div class="panel current-drink p-4 mb-4 text-center">
        <div class="small text-secondary">Aktuell ausgewählt</div>
        <?php if($current && $current['drink_id']): ?>
          <div class="display-6 fw-bold my-2"><?=e($current['emoji']?:'🥤')?> <?=e($current['name'])?></div>
          <?php if(!(int)$current['is_approved']): ?><div class="small text-warning">Dein Vorschlag ist noch nicht allgemein freigegeben.</div><?php else:?><div class="small text-success"><i class="bi bi-check-circle-fill me-1"></i>Bleibt aktiv, bis du es änderst</div><?php endif;?>
        <?php else: ?>
          <div class="display-6 fw-bold my-2">⏸ Derzeit nichts</div>
          <div class="small text-secondary">Du wirst bei der nächsten Runde nicht mitgezählt.</div>
        <?php endif;?>
      </div>

      <div class="row g-3 mb-4">
        <?php foreach($drinks as $d): $active=$current && (int)$current['drink_id']===(int)$d['id']; ?>
          <div class="col-6 col-md-4">
            <div class="drink-choice-wrap h-100">
              <form method="post" class="h-100">
                <input type="hidden" name="csrf" value="<?=e(csrf_token())?>">
                <input type="hidden" name="action" value="choose">
                <input type="hidden" name="drink_id" value="<?=(int)$d['id']?>">
                <button class="drink-choice <?=$active?'active':''?>">
                  <span class="emoji"><?=e($d['emoji']?:'🥤')?></span>
                  <strong><?=e($d['name'])?></strong>
                  <?php if(!(int)$d['is_approved']): ?><span class="small text-warning">dein Vorschlag</span><?php endif;?>
                </button>
              </form>
              <form method="post" class="drink-favorite-form">
                <input type="hidden" name="csrf" value="<?=e(csrf_token())?>">
                <input type="hidden" name="action" value="toggle_favorite">
                <input type="hidden" name="drink_id" value="<?=(int)$d['id']?>">
                <button class="drink-favorite-btn <?=((int)$d['is_favorite'])?'active':''?>" title="<?=((int)$d['is_favorite'])?'Favorit entfernen':'Als Favorit markieren'?>" aria-label="<?=((int)$d['is_favorite'])?'Favorit entfernen':'Als Favorit markieren'?>"><i class="bi <?=((int)$d['is_favorite'])?'bi-star-fill':'bi-star'?>"></i></button>
              </form>
            </div>
          </div>
        <?php endforeach;?>
        <div class="col-6 col-md-4">
          <button class="drink-choice" type="button" data-bs-toggle="modal" data-bs-target="#newDrink"><span class="emoji">＋</span><strong>Neu</strong></button>
        </div>
      </div>

      <form method="post"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="none"><button class="btn btn-outline-danger btn-lg w-100"><i class="bi bi-pause-circle me-2"></i>Derzeit nichts</button></form>


    </section>
  </div>
</div>

<div class="modal fade" id="newDrink" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><form method="post" class="modal-content"><div class="modal-header"><h2 class="modal-title fs-5">Getränk vorschlagen</h2><button class="btn-close" type="button" data-bs-dismiss="modal"></button></div><div class="modal-body"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="propose"><div class="mb-3"><label class="form-label">Name</label><input class="form-control" name="name" maxlength="60" required></div><div><label class="form-label">Emoji / Symbol</label><input class="form-control" name="emoji" maxlength="12" value="🥤"></div><div class="form-text mt-2">Du kannst den Vorschlag sofort selbst auswählen. Für alle anderen wird er erst nach Freigabe durch den Spieß sichtbar.</div></div><div class="modal-footer"><button class="btn btn-brand">Vorschlagen & auswählen</button></div></form></div></div>
<?php require __DIR__.'/includes/footer.php'; ?>
