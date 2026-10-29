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
 if(($_POST['action']??'')==='background_remove'){
  $pdo->exec('DELETE FROM site_background WHERE id=1');
  flash('success','Hintergrundbild entfernt.');
  redirect('/admin-features.php');
 }
 if(($_POST['action']??'')==='background_upload'){
  try{
   $f=$_FILES['background']??null;
   if(!$f||$f['error']===UPLOAD_ERR_NO_FILE) throw new RuntimeException('Bitte ein Bild auswählen.');
   if($f['error']===UPLOAD_ERR_INI_SIZE||$f['error']===UPLOAD_ERR_FORM_SIZE) throw new RuntimeException('Das Bild ist zu groß für den Server (Limit upload_max_filesize). Bitte unter ca. 8 MB bleiben.');
   if($f['error']!==UPLOAD_ERR_OK) throw new RuntimeException('Upload fehlgeschlagen (Fehlercode '.(int)$f['error'].').');
   if($f['size']>12*1024*1024) throw new RuntimeException('Das Bild ist größer als 12 MB.');
   $raw=file_get_contents($f['tmp_name']);
   $info=$raw===false?false:@getimagesizefromstring($raw);
   if(!$info||!in_array($info[2],[IMAGETYPE_JPEG,IMAGETYPE_PNG,IMAGETYPE_WEBP],true)) throw new RuntimeException('Bitte ein JPG-, PNG- oder WebP-Bild hochladen.');
   [$w,$h]=$info;
   $img=@imagecreatefromstring($raw);
   if(!$img) throw new RuntimeException('Das Bild konnte nicht gelesen werden.');
   if($w>2560){
    $nh=(int)round($h*2560/$w);
    $scaled=imagescale($img,2560,$nh,IMG_BICUBIC);
    if($scaled){imagedestroy($img);$img=$scaled;$w=2560;$h=$nh;}
   }
   ob_start();imagejpeg($img,null,82);$jpeg=(string)ob_get_clean();imagedestroy($img);
   if($jpeg==='') throw new RuntimeException('Das Bild konnte nicht verarbeitet werden.');
   $stream=fopen('php://memory','r+');fwrite($stream,$jpeg);rewind($stream);
   $st=$pdo->prepare('INSERT INTO site_background(id,mime,data,width,height,updated_at,updated_by) VALUES(1,?,?,?,?,CURRENT_TIMESTAMP,?) ON CONFLICT(id) DO UPDATE SET mime=EXCLUDED.mime,data=EXCLUDED.data,width=EXCLUDED.width,height=EXCLUDED.height,updated_at=CURRENT_TIMESTAMP,updated_by=EXCLUDED.updated_by');
   $st->bindValue(1,'image/jpeg');$st->bindValue(2,$stream,PDO::PARAM_LOB);$st->bindValue(3,$w,PDO::PARAM_INT);$st->bindValue(4,$h,PDO::PARAM_INT);$st->bindValue(5,(int)$u['id'],PDO::PARAM_INT);
   $st->execute();
   flash('success','Hintergrundbild gespeichert ('.$w.'×'.$h.').');
  }catch(Throwable $e){ flash('danger',user_error_message($e)); }
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
$bgInfo=$pdo->query('SELECT width,height,updated_at,LENGTH(data) AS bytes FROM site_background WHERE id=1')->fetch();
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
<div class="panel p-4 mb-4"><h2 class="h5 mb-1">🖼️ Hintergrundbild</h2><p class="text-secondary">Das Bild scheint hinter der Seite zu 15&nbsp;% durch und wird immer auf die Bildschirmbreite skaliert. Jede Größe ist möglich, JPG, PNG oder WebP, bis ca. 8&nbsp;MB. Sehr große Bilder werden automatisch auf 2560 Pixel Breite verkleinert; kleine Bilder können auf großen Bildschirmen unscharf wirken.</p><?php if($bgInfo):?><div class="d-flex flex-wrap gap-3 align-items-start mb-3"><div style="width:240px;height:135px;background:#0B0C0E;overflow:hidden;border:1px solid var(--sm-border-dark)"><img src="/background.php?v=<?=e(strtotime($bgInfo['updated_at']))?>" alt="Aktuelles Hintergrundbild" style="width:100%;height:100%;object-fit:cover;opacity:.15"></div><div class="small text-secondary">Aktuell: <?=(int)$bgInfo['width']?>×<?=(int)$bgInfo['height']?> Pixel, <?=e(number_format(((int)$bgInfo['bytes'])/1024,0,',','.'))?> KB<br>Vorschau mit 15&nbsp;% Sichtbarkeit.</div></div><?php else:?><p class="small text-secondary">Noch kein Bild hochgeladen. Die Seite zeigt nur das dunkle Grau.</p><?php endif;?><form method="post" enctype="multipart/form-data" class="d-flex flex-wrap gap-2 align-items-center"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="background_upload"><input class="form-control" style="max-width:360px" type="file" name="background" accept="image/jpeg,image/png,image/webp" required><button class="btn btn-brand"><?=$bgInfo?'Bild austauschen':'Bild hochladen'?></button></form><?php if($bgInfo):?><form method="post" class="mt-2"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="background_remove"><button class="btn btn-sm btn-outline-danger" onclick="return confirm('Hintergrundbild entfernen?')">Bild entfernen</button></form><?php endif;?></div>
<div class="panel p-4 mb-4"><div class="d-flex flex-wrap justify-content-between align-items-center gap-3"><div><h2 class="h5 mb-1">📝 Selbstregistrierung neuer Mitglieder</h2><p class="text-secondary mb-0">Wenn aktiviert, erscheint auf der Login-Seite ein Registrierungslink. Neue Accounts werden ausschließlich als normale Mitglieder angelegt.</p></div><form method="post" class="d-flex align-items-center gap-3"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="toggle_registration"><div class="form-check form-switch fs-5 mb-0"><input class="form-check-input" type="checkbox" role="switch" name="enabled" value="1" id="registrationEnabled" <?=$registrationEnabled?'checked':''?> onchange="this.form.submit()"><label class="form-check-label fs-6" for="registrationEnabled"><?=$registrationEnabled?'Aktiv':'Aus'?></label></div></form></div><?php if($registrationEnabled):?><div class="alert alert-success mt-3 mb-0 py-2"><i class="bi bi-person-plus me-1"></i> Registrierung ist aktuell geöffnet.</div><?php else:?><div class="alert alert-secondary mt-3 mb-0 py-2">Registrierung ist aktuell geschlossen.</div><?php endif;?></div>
<div class="panel p-4"><div class="d-flex flex-wrap justify-content-between gap-3 align-items-center"><div><h2 class="h5 mb-1">🥂 Sektbar-Funktion</h2><p class="text-secondary mb-0">Erst nach Freigabe können Spieß und aktiver Spieß-Vertreter den Sektbarmodus sehen und bedienen.</p></div><span class="badge <?= $enabled?'text-bg-success':'text-bg-secondary' ?> fs-6"><?= $enabled?'Freigeschaltet':'Gesperrt' ?></span></div>
<form method="post" class="mt-4"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="sektbar_enabled" value="<?=$enabled?'0':'1'?>"><button class="btn <?=$enabled?'btn-outline-danger':'btn-brand'?> btn-lg"><?=$enabled?'Sektbar-Funktion sperren':'Sektbar-Funktion freischalten'?></button></form>
<?php if($enabled):?><div class="small text-secondary mt-3">Beim Sperren wird ein eventuell aktiver Sektbarmodus automatisch beendet.</div><?php endif;?>
</div></section></div></div><?php require __DIR__.'/includes/footer.php';?>
