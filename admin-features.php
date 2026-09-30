<?php
require_once __DIR__.'/includes/app.php';
$u=require_login();
if(!has_role('admin')){http_response_code(403);exit('Nur Administratoren dürfen Funktionen freischalten.');}
if($_SERVER['REQUEST_METHOD']==='POST'){
 verify_csrf();
 $enable=($_POST['sektbar_enabled']??'0')==='1';
 $pdo->beginTransaction();
 try{
  if(!$enable && sektbar_is_active($pdo)){
   $ids=$pdo->query("SELECT drink_id FROM sektbar_activated_drinks")->fetchAll(PDO::FETCH_COLUMN);
   $up=$pdo->prepare("UPDATE drinks SET is_active=0 WHERE id=?");
   foreach($ids as $id)$up->execute([$id]);
   $pdo->exec("DELETE FROM sektbar_activated_drinks");
   $pdo->exec("UPDATE sektbar_state SET is_active=0,activated_at=NULL,activated_by=NULL WHERE id=1");
  }
  $pdo->prepare("INSERT INTO feature_settings(feature_key,is_enabled,updated_at,updated_by) VALUES('sektbar',?,CURRENT_TIMESTAMP,?) ON CONFLICT(feature_key) DO UPDATE SET is_enabled=excluded.is_enabled,updated_at=CURRENT_TIMESTAMP,updated_by=excluded.updated_by")->execute([$enable?1:0,(int)$u['id']]);
  $pdo->commit();
  flash('success',$enable?'Sektbar-Funktion wurde freigeschaltet.':'Sektbar-Funktion wurde gesperrt.');
 }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
 redirect('/admin-features.php');
}
$enabled=sektbar_is_enabled($pdo);
$pageTitle='Funktionen';require __DIR__.'/includes/header.php';
?>
<div class="container dashboard-shell"><div class="row g-4"><?php require __DIR__.'/includes/sidebar.php';?><section class="col-lg-9">
<div class="eyebrow">Administration</div><h1 class="section-title h2">Funktionen freischalten</h1>
<div class="panel p-4"><div class="d-flex flex-wrap justify-content-between gap-3 align-items-center"><div><h2 class="h5 mb-1">🥂 Sektbar-Funktion</h2><p class="text-secondary mb-0">Erst nach Freigabe können Spieß und aktiver Spieß-Vertreter den Sektbarmodus sehen und bedienen.</p></div><span class="badge <?= $enabled?'text-bg-success':'text-bg-secondary' ?> fs-6"><?= $enabled?'Freigeschaltet':'Gesperrt' ?></span></div>
<form method="post" class="mt-4"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="sektbar_enabled" value="<?=$enabled?'0':'1'?>"><button class="btn <?=$enabled?'btn-outline-danger':'btn-brand'?> btn-lg"><?=$enabled?'Sektbar-Funktion sperren':'Sektbar-Funktion freischalten'?></button></form>
<?php if($enabled):?><div class="small text-secondary mt-3">Beim Sperren wird ein eventuell aktiver Sektbarmodus automatisch beendet.</div><?php endif;?>
</div></section></div></div><?php require __DIR__.'/includes/footer.php';?>
