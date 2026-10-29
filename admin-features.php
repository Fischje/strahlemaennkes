<?php
require_once __DIR__.'/includes/app.php';
$u=require_login();
if(!has_role('admin')){http_response_code(403);exit('Nur Administratoren dürfen Funktionen freischalten.');}
if($_SERVER['REQUEST_METHOD']==='POST'){
 verify_csrf();
 if(($_POST['action']??'')==='domain_move_push'){
  if(!domain_move_active()){flash('warning','Der Umzug ist noch nicht eingeschaltet.');redirect('/admin-features.php');}
  $params=[];
  $r=push_deliver($pdo,legacy_push_condition($params),$params,'',
   ['title'=>'Die App ist umgezogen 📦','body'=>'Neue Adresse: '.app_display_host().' – hier tippen, in einer Minute ist die neue App drauf.','url'=>'/umzug.php','tag'=>'domain-move'],
   ['TTL'=>60*60*24]);
  if($r['error']) flash('danger',$r['error']);
  else flash('success','Umzugs-Erinnerung an '.(int)$r['sent'].' Gerät(e) auf der alten Adresse geschickt'.($r['expired_removed']?' ('.(int)$r['expired_removed'].' veraltete Geräte entfernt)':'').'.');
  redirect('/admin-features.php');
 }
 if(($_POST['action']??'')==='toggle_registration'){
  $enabled=!empty($_POST['enabled']);
  set_app_setting($pdo,'registration_enabled',$enabled?'1':'0',(int)$u['id']);
  flash('success',$enabled?'Selbstregistrierung wurde aktiviert.':'Selbstregistrierung wurde deaktiviert.');
  redirect('/admin-features.php');
 }
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
$registrationEnabled=public_registration_enabled($pdo);
$pageTitle='Funktionen';require __DIR__.'/includes/header.php';
?>
<div class="container dashboard-shell"><div class="row g-4"><section class="col-12">
<div class="eyebrow">Administration</div><h1 class="section-title h2">Funktionen freischalten</h1>
<?php if(domain_move_active()):
 $lp=[];$legacyCond=legacy_push_condition($lp);
 $mq=$pdo->prepare("SELECT u.first_name || ' ' || substr(u.last_name,1,1) || '.' AS name,
   EXISTS(SELECT 1 FROM push_subscriptions s WHERE s.user_id=u.id AND s.origin_host=?) AS on_new,
   EXISTS(SELECT 1 FROM push_subscriptions s WHERE s.user_id=u.id".$legacyCond.") AS on_old
   FROM users u WHERE u.is_active=1 ORDER BY u.last_name,u.first_name");
 $mq->execute(array_merge([app_canonical_host()],$lp));
 $moveGroups=['new'=>[],'old'=>[],'none'=>[]];
 foreach($mq->fetchAll() as $row) $moveGroups[$row['on_new']?'new':($row['on_old']?'old':'none')][]=$row['name'];
?>
<div class="panel p-4 mb-4"><h2 class="h5 mb-1">📦 Umzug auf <?=e(app_display_host())?></h2><p class="text-secondary">Die alte Adresse (<?=e(implode(', ',app_legacy_hosts()))?>) zeigt nur noch die Umzugsseite. Gezählt wird, wo die Mitglieder Push-Mitteilungen bekommen.</p>
<ul class="small mb-3">
<li><strong>✅ Neue App mit Push (<?=count($moveGroups['new'])?>):</strong> <?=e(implode(', ',$moveGroups['new'])?:'–')?></li>
<li><strong>⏳ Nur alte App (<?=count($moveGroups['old'])?>):</strong> <?=e(implode(', ',$moveGroups['old'])?:'–')?></li>
<li><strong>❔ Ohne Push (<?=count($moveGroups['none'])?>):</strong> <?=e(implode(', ',$moveGroups['none'])?:'–')?> <span class="text-secondary">– am besten per WhatsApp den Link schicken.</span></li>
</ul>
<form method="post" class="d-inline"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="domain_move_push"><button class="btn btn-brand" <?=$moveGroups['old']?'':'disabled'?> onclick="return confirm('Umzugs-Erinnerung an alle Geräte schicken, die noch auf der alten Adresse sind?')"><i class="bi bi-send me-1"></i>Umzugs-Erinnerung an „Nur alte App“ schicken</button></form>
<div class="small text-secondary mt-2">Tippt man auf die Mitteilung, öffnet sich die alte App mit der Umzugsseite und bringt das Mitglied angemeldet auf die neue Adresse.</div></div>
<?php endif; ?>

<div class="panel p-4 mb-4"><div class="d-flex flex-wrap justify-content-between align-items-center gap-3"><div><h2 class="h5 mb-1">📝 Selbstregistrierung neuer Mitglieder</h2><p class="text-secondary mb-0">Wenn aktiviert, erscheint auf der Login-Seite ein Registrierungslink. Neue Accounts werden ausschließlich als normale Mitglieder angelegt.</p></div><form method="post" class="d-flex align-items-center gap-3"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="toggle_registration"><div class="form-check form-switch fs-5 mb-0"><input class="form-check-input" type="checkbox" role="switch" name="enabled" value="1" id="registrationEnabled" <?=$registrationEnabled?'checked':''?> onchange="this.form.submit()"><label class="form-check-label fs-6" for="registrationEnabled"><?=$registrationEnabled?'Aktiv':'Aus'?></label></div></form></div><?php if($registrationEnabled):?><div class="alert alert-success mt-3 mb-0 py-2"><i class="bi bi-person-plus me-1"></i> Registrierung ist aktuell geöffnet.</div><?php else:?><div class="alert alert-secondary mt-3 mb-0 py-2">Registrierung ist aktuell geschlossen.</div><?php endif;?></div>
<div class="panel p-4"><div class="d-flex flex-wrap justify-content-between gap-3 align-items-center"><div><h2 class="h5 mb-1">🥂 Sektbar-Funktion</h2><p class="text-secondary mb-0">Erst nach Freigabe können Spieß und aktiver Spieß-Vertreter den Sektbarmodus sehen und bedienen.</p></div><span class="badge <?= $enabled?'text-bg-success':'text-bg-secondary' ?> fs-6"><?= $enabled?'Freigeschaltet':'Gesperrt' ?></span></div>
<form method="post" class="mt-4"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="sektbar_enabled" value="<?=$enabled?'0':'1'?>"><button class="btn <?=$enabled?'btn-outline-danger':'btn-brand'?> btn-lg"><?=$enabled?'Sektbar-Funktion sperren':'Sektbar-Funktion freischalten'?></button></form>
<?php if($enabled):?><div class="small text-secondary mt-3">Beim Sperren wird ein eventuell aktiver Sektbarmodus automatisch beendet.</div><?php endif;?>
</div></section></div></div><?php require __DIR__.'/includes/footer.php';?>
