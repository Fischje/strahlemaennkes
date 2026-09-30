<?php
require_once __DIR__.'/includes/app.php';
$u=require_login();
$enabled=sektbar_is_enabled($pdo);
$canOperate=can_act_as_spiess($pdo,$u);
if(!$enabled && !has_role('admin')){http_response_code(403);exit('Die Sektbar-Funktion ist derzeit nicht freigeschaltet.');}
if($_SERVER['REQUEST_METHOD']==='POST'){
 if(!$canOperate){http_response_code(403);exit('Nur der aktive Spieß oder Administrator darf den Sektbarmodus bedienen.');}
 verify_csrf();$a=$_POST['action']??'';
 if($a==='activate' && $enabled && !sektbar_is_active($pdo)){
  $pdo->beginTransaction();
  try{
   $pdo->exec("DELETE FROM sektbar_activated_drinks");
   $ids=$pdo->query("SELECT id FROM drinks WHERE is_sektbar=1 AND is_approved=1 AND is_active=0")->fetchAll(PDO::FETCH_COLUMN);
   $ins=$pdo->prepare("INSERT INTO sektbar_activated_drinks(drink_id) VALUES(?)");$up=$pdo->prepare("UPDATE drinks SET is_active=1 WHERE id=?");
   foreach($ids as $id){$ins->execute([$id]);$up->execute([$id]);}
   $pdo->prepare("UPDATE sektbar_state SET is_active=1,activated_at=CURRENT_TIMESTAMP,activated_by=? WHERE id=1")->execute([(int)$u['id']]);
   $pdo->commit();
   require_once __DIR__.'/includes/push.php';
   $push=send_push_to_all_active_users($pdo,'Sektbarmodus aktiviert 🥂','Die Sektbar ist jetzt geöffnet. Schau in die App für die aktuelle Getränkeauswahl.','/sektbar.php');
   flash('success','Sektbarmodus aktiviert.'.($push['sent']>0?' Push an '.$push['sent'].' Gerät(e) zugestellt.':''));
  }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
 }
 if($a==='deactivate' && sektbar_is_active($pdo)){
  $pdo->beginTransaction();
  try{
   $ids=$pdo->query("SELECT drink_id FROM sektbar_activated_drinks")->fetchAll(PDO::FETCH_COLUMN);$up=$pdo->prepare("UPDATE drinks SET is_active=0 WHERE id=?");
   foreach($ids as $id)$up->execute([$id]);
   $pdo->exec("DELETE FROM sektbar_activated_drinks");$pdo->exec("UPDATE sektbar_state SET is_active=0,activated_at=NULL,activated_by=NULL WHERE id=1");
   $pdo->commit();flash('success','Sektbarmodus beendet.');
  }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
 }
 redirect('/sektbar.php');
}
$active=sektbar_is_active($pdo);
$drinks=$pdo->query("SELECT name,emoji,is_active FROM drinks WHERE is_sektbar=1 AND is_approved=1 ORDER BY sort_order,name")->fetchAll();
$pageTitle='Sektbar';require __DIR__.'/includes/header.php';
?>
<div class="container dashboard-shell"><div class="row g-4"><?php require __DIR__.'/includes/sidebar.php';?><section class="col-lg-9">
<div class="eyebrow">Spezialmodus</div><h1 class="section-title h2">Sektbar</h1>
<div class="panel p-4 text-center sektbar-control">
<?php if(!$enabled):?>
  <div class="alert alert-secondary">Die Sektbar-Funktion ist noch nicht freigeschaltet. <a href="/admin-features.php">Jetzt als Admin freischalten</a>.</div>
<?php elseif(!$active && $canOperate):?>
  <p class="text-secondary">Sicherheitskappe hochklappen und anschließend den Kippschalter betätigen.</p>
  <div class="killswitch-console" id="killSwitch">
    <div class="killswitch-plate">
      <form method="post" class="killswitch-form"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="activate">
        <button class="killswitch-toggle" aria-label="Sektbarmodus einschalten"><span class="toggle-lever"></span><span class="toggle-label">ON</span></button>
      </form>
      <button type="button" class="killswitch-cover" id="killCover" aria-label="Sicherheitskappe öffnen"><span class="cover-ridge"></span><span class="cover-text">LIFT</span></button>
    </div>
  </div>
<?php elseif(!$active):?>
  <div class="display-5 mb-2">🥂</div><h2>Sektbar bereit</h2><p class="text-secondary">Der Sektbarmodus ist freigeschaltet. Der aktive Spieß kann ihn hier einschalten.</p>
<?php else:?>
  <div class="display-5 mb-2">🥂✨</div><h2>Sektbarmodus ist aktiv</h2>
  <?php if($canOperate):?><form method="post" class="mt-4"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="deactivate"><button class="btn btn-outline-danger btn-lg">Sektbarmodus ausschalten</button></form><?php else:?><p class="text-secondary">Der aktive Spieß beendet den Modus wieder.</p><?php endif;?>
<?php endif;?>
<div class="mt-4 small text-secondary">Markierte Getränke: <?= $drinks?e(implode(', ',array_map(fn($d)=>$d['name'],$drinks))):'keine' ?></div>
</div></section></div></div>
<?php if($enabled && !$active && $canOperate):?><script>document.getElementById('killCover')?.addEventListener('click',()=>document.getElementById('killSwitch')?.classList.add('armed'));</script><?php endif;?>
<?php require __DIR__.'/includes/footer.php';?>
