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
 if(($_POST['action']??'')==='background_delete'){
  $pdo->prepare('DELETE FROM site_backgrounds WHERE id=?')->execute([(int)($_POST['id']??0)]);
  flash('success','Hintergrundbild gelöscht.');
  redirect('/admin-features.php#hintergrund');
 }
 if(($_POST['action']??'')==='background_toggle'){
  $on=!empty($_POST['active']);
  $pdo->prepare('UPDATE site_backgrounds SET is_active=? WHERE id=?')->execute([$on?1:0,(int)($_POST['id']??0)]);
  flash('success',$on?'Bild wird jetzt im Wechsel angezeigt.':'Bild wird nicht mehr angezeigt.');
  redirect('/admin-features.php#hintergrund');
 }
 if(($_POST['action']??'')==='background_upload'){
  // Mehrere Bilder auf einmal möglich; jedes wird einzeln geprüft, als JPEG gespeichert und gleich angehakt.
  $files=$_FILES['background']??null;
  $list=[];
  if($files && is_array($files['name'])){
   foreach($files['name'] as $i=>$name) $list[]=['name'=>$name,'tmp_name'=>$files['tmp_name'][$i],'error'=>$files['error'][$i],'size'=>$files['size'][$i]];
  }
  $list=array_values(array_filter($list,fn($f)=>$f['error']!==UPLOAD_ERR_NO_FILE));
  if(!$list){flash('danger','Bitte mindestens ein Bild auswählen.');redirect('/admin-features.php#hintergrund');}
  $count=(int)$pdo->query('SELECT COUNT(*) FROM site_backgrounds')->fetchColumn();
  $saved=0;
  foreach($list as $f){
   $label='„'.mb_substr((string)$f['name'],0,60).'“';
   try{
    if($count>=30) throw new RuntimeException('Es sind schon 30 Bilder gespeichert. Bitte zuerst alte Bilder löschen.');
    if($f['error']===UPLOAD_ERR_INI_SIZE||$f['error']===UPLOAD_ERR_FORM_SIZE) throw new RuntimeException('zu groß für den Server (Limit upload_max_filesize). Bitte unter ca. 8 MB bleiben.');
    if($f['error']!==UPLOAD_ERR_OK) throw new RuntimeException('Upload fehlgeschlagen (Fehlercode '.(int)$f['error'].').');
    if($f['size']>12*1024*1024) throw new RuntimeException('größer als 12 MB.');
    $raw=file_get_contents($f['tmp_name']);
    $info=$raw===false?false:@getimagesizefromstring($raw);
    if(!$info||!in_array($info[2],[IMAGETYPE_JPEG,IMAGETYPE_PNG,IMAGETYPE_WEBP],true)) throw new RuntimeException('kein JPG-, PNG- oder WebP-Bild.');
    [$w,$h]=$info;
    $img=@imagecreatefromstring($raw);
    if(!$img) throw new RuntimeException('konnte nicht gelesen werden.');
    if($w>2560){
     $nh=(int)round($h*2560/$w);
     $scaled=imagescale($img,2560,$nh,IMG_BICUBIC);
     if($scaled){imagedestroy($img);$img=$scaled;$w=2560;$h=$nh;}
    }
    ob_start();imagejpeg($img,null,82);$jpeg=(string)ob_get_clean();imagedestroy($img);
    if($jpeg==='') throw new RuntimeException('konnte nicht verarbeitet werden.');
    $stream=fopen('php://memory','r+');fwrite($stream,$jpeg);rewind($stream);
    $st=$pdo->prepare('INSERT INTO site_backgrounds(mime,data,width,height,is_active,created_by) VALUES(?,?,?,?,1,?)');
    $st->bindValue(1,'image/jpeg');$st->bindValue(2,$stream,PDO::PARAM_LOB);$st->bindValue(3,$w,PDO::PARAM_INT);$st->bindValue(4,$h,PDO::PARAM_INT);$st->bindValue(5,(int)$u['id'],PDO::PARAM_INT);
    $st->execute();
    $saved++;$count++;
   }catch(Throwable $e){ flash('danger','Bild '.$label.': '.user_error_message($e)); }
  }
  if($saved) flash('success',$saved===1?'Hintergrundbild gespeichert.':$saved.' Hintergrundbilder gespeichert.');
  redirect('/admin-features.php#hintergrund');
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
$bgList=$pdo->query('SELECT id,width,height,is_active,EXTRACT(EPOCH FROM updated_at)::bigint AS ts,LENGTH(data) AS bytes FROM site_backgrounds ORDER BY id')->fetchAll();
$bgActive=count(array_filter($bgList,fn($b)=>(int)$b['is_active']));
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
<div class="panel p-4 mb-4" id="hintergrund"><h2 class="h5 mb-1">🖼️ Hintergrundbilder</h2>
<p class="text-secondary">Die Bilder scheinen hinter der Seite zu 15&nbsp;% durch und werden auf die Bildschirmbreite skaliert. Alle angehakten Bilder wechseln sich alle <?= SITE_BACKGROUND_INTERVAL ?>&nbsp;Sekunden mit sanfter Überblendung ab; ist nur eins angehakt, bleibt es stehen. JPG, PNG oder WebP, bis ca. 8&nbsp;MB je Bild, mehrere auf einmal möglich. Sehr große Bilder werden auf 2560 Pixel Breite verkleinert.</p>
<?php if($bgList):?>
<div class="small text-secondary mb-2"><?= count($bgList) ?> Bild<?= count($bgList)===1?'':'er' ?>, davon <?= $bgActive ?> angehakt.</div>
<div class="bg-admin-grid mb-3">
<?php foreach($bgList as $bg): $on=(int)$bg['is_active']; ?>
 <div class="bg-admin-item <?= $on?'is-on':'' ?>">
  <img src="/background.php?id=<?=(int)$bg['id']?>&amp;v=<?=(int)$bg['ts']?>" alt="Hintergrundbild <?=(int)$bg['id']?>" loading="lazy">
  <div class="small text-secondary px-2 pt-1"><?=(int)$bg['width']?>×<?=(int)$bg['height']?> · <?=e(number_format(((int)$bg['bytes'])/1024,0,',','.'))?> KB</div>
  <div class="d-flex align-items-center justify-content-between gap-2 p-2">
   <form method="post" class="m-0"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="background_toggle"><input type="hidden" name="id" value="<?=(int)$bg['id']?>">
    <label class="form-check m-0"><input class="form-check-input" type="checkbox" name="active" value="1" <?=$on?'checked':''?> onchange="this.form.submit()"> <span class="form-check-label">anzeigen</span></label></form>
   <form method="post" class="m-0" onsubmit="return confirm('Dieses Bild endgültig löschen?')"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="background_delete"><input type="hidden" name="id" value="<?=(int)$bg['id']?>">
    <button class="btn btn-sm btn-outline-danger" title="Bild löschen" aria-label="Bild löschen"><i class="bi bi-trash3"></i></button></form>
  </div>
 </div>
<?php endforeach;?>
</div>
<?php if(!$bgActive):?><p class="small text-secondary">Kein Bild angehakt – die Seite zeigt nur das dunkle Grau.</p><?php endif;?>
<?php else:?><p class="small text-secondary">Noch kein Bild hochgeladen. Die Seite zeigt nur das dunkle Grau.</p><?php endif;?>
<form method="post" enctype="multipart/form-data" class="d-flex flex-wrap gap-2 align-items-center"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="background_upload"><input class="form-control" style="max-width:360px" type="file" name="background[]" accept="image/jpeg,image/png,image/webp" multiple required><button class="btn btn-brand"><i class="bi bi-upload me-1"></i>Bilder hochladen</button></form>
</div>
<div class="panel p-4 mb-4"><div class="d-flex flex-wrap justify-content-between align-items-center gap-3"><div><h2 class="h5 mb-1">📝 Selbstregistrierung neuer Mitglieder</h2><p class="text-secondary mb-0">Wenn aktiviert, erscheint auf der Login-Seite ein Registrierungslink. Neue Accounts werden ausschließlich als normale Mitglieder angelegt.</p></div><form method="post" class="d-flex align-items-center gap-3"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="toggle_registration"><div class="form-check form-switch fs-5 mb-0"><input class="form-check-input" type="checkbox" role="switch" name="enabled" value="1" id="registrationEnabled" <?=$registrationEnabled?'checked':''?> onchange="this.form.submit()"><label class="form-check-label fs-6" for="registrationEnabled"><?=$registrationEnabled?'Aktiv':'Aus'?></label></div></form></div><?php if($registrationEnabled):?><div class="alert alert-success mt-3 mb-0 py-2"><i class="bi bi-person-plus me-1"></i> Registrierung ist aktuell geöffnet.</div><?php else:?><div class="alert alert-secondary mt-3 mb-0 py-2">Registrierung ist aktuell geschlossen.</div><?php endif;?></div>
<div class="panel p-4"><div class="d-flex flex-wrap justify-content-between gap-3 align-items-center"><div><h2 class="h5 mb-1">🥂 Sektbar-Funktion</h2><p class="text-secondary mb-0">Erst nach Freigabe können Spieß und aktiver Spieß-Vertreter den Sektbarmodus sehen und bedienen.</p></div><span class="badge <?= $enabled?'text-bg-success':'text-bg-secondary' ?> fs-6"><?= $enabled?'Freigeschaltet':'Gesperrt' ?></span></div>
<form method="post" class="mt-4"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="sektbar_enabled" value="<?=$enabled?'0':'1'?>"><button class="btn <?=$enabled?'btn-outline-danger':'btn-brand'?> btn-lg"><?=$enabled?'Sektbar-Funktion sperren':'Sektbar-Funktion freischalten'?></button></form>
<?php if($enabled):?><div class="small text-secondary mt-3">Beim Sperren wird ein eventuell aktiver Sektbarmodus automatisch beendet.</div><?php endif;?>
</div></section></div></div><?php require __DIR__.'/includes/footer.php';?>
