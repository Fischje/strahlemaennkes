<?php
// Bilder der Seite: Hintergrund-Muster (gekachelt, 15 % sichtbar) und Gruppenfoto über dem Zeitstrahl der Chronik.
require_once __DIR__.'/includes/app.php';
$u=require_login();
if(!can_manage_site_images()){http_response_code(403);exit('Nur Administratoren dürfen die Bilder der Seite ändern.');}

if($_SERVER['REQUEST_METHOD']==='POST'){
    verify_csrf();
    $slot=(string)($_POST['slot']??'');
    $action=(string)($_POST['action']??'');
    try{
        if(!isset(SITE_IMAGE_SLOTS[$slot])) throw new RuntimeException('Unbekannter Bildplatz.');
        $caption=trim((string)($_POST['caption']??''));
        if(mb_strlen($caption,'UTF-8')>250) throw new RuntimeException('Die Bildunterschrift darf höchstens 250 Zeichen lang sein.');
        if($action==='upload'){
            [$mime,$bytes,$w,$h]=site_image_process($_FILES['image']??null,SITE_IMAGE_SLOTS[$slot]['max_width']);
            site_image_save($pdo,$slot,$mime,$bytes,$w,$h,$slot==='group_photo'?$caption:'',(int)$u['id']);
            flash('success',SITE_IMAGE_SLOTS[$slot]['label'].' gespeichert ('.$w.'×'.$h.').');
        }elseif($action==='caption' && $slot==='group_photo'){
            $pdo->prepare('UPDATE site_images SET caption=?,updated_by=? WHERE slot=?')->execute([$caption,(int)$u['id'],$slot]);
            flash('success','Bildunterschrift gespeichert.');
        }elseif($action==='delete'){
            $pdo->prepare('DELETE FROM site_images WHERE slot=?')->execute([$slot]);
            flash('success',SITE_IMAGE_SLOTS[$slot]['label'].' entfernt.');
        }else throw new RuntimeException('Unbekannte Aktion.');
    }catch(Throwable $e){ flash('danger',user_error_message($e)); }
    redirect('/bilder.php#'.(isset(SITE_IMAGE_SLOTS[$slot])?$slot:''));
}

$pattern=site_image_info($pdo,'pattern');
$group=site_image_info($pdo,'group_photo');
$size=fn(array $i)=>(int)$i['width'].'×'.(int)$i['height'].' Pixel, '.number_format(max(1,round(((int)$i['bytes'])/1024)),0,',','.').' KB';

$pageTitle='Bilder der Seite';
require __DIR__.'/includes/header.php';
?>
<div class="container dashboard-shell"><div class="row g-4"><section class="col-12">
  <div class="eyebrow">Funktionen</div>
  <h1 class="section-title h2 mb-1">Bilder der Seite</h1>
  <p class="text-secondary mb-4">Hintergrund-Muster der ganzen Seite und Gruppenfoto der öffentlichen Chronik.</p>

  <div class="row g-4">
    <div class="col-lg-6">
      <div class="panel p-4 h-100 site-image-panel" id="pattern">
        <h2 class="h5 mb-1"><i class="bi bi-grid-3x3 me-1"></i> Hintergrund-Muster</h2>
        <p class="text-secondary small">Das Muster wird über die ganze Seite wiederholt (gekachelt) und scheint zu 15&nbsp;% durch. Am besten ein nahtloses Muster, gern als PNG mit transparentem Hintergrund. JPG, PNG oder WebP, bis ca. 8&nbsp;MB.</p>
        <?php if($pattern): ?>
          <div class="site-image-pattern-preview mb-2" style="background-image:url('<?= site_image_url($pdo,'pattern') ?>')" role="img" aria-label="Vorschau des Musters"></div>
          <div class="small text-secondary mb-3">Aktuell: <?= e($size($pattern)) ?>. Vorschau oben in voller Stärke, auf der Seite mit 15&nbsp;%.</div>
        <?php else: ?>
          <p class="small text-secondary">Noch kein Muster hochgeladen – die Seite zeigt nur das dunkle Grau.</p>
        <?php endif; ?>
        <form method="post" enctype="multipart/form-data" class="d-flex flex-wrap gap-2"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="slot" value="pattern"><input type="hidden" name="action" value="upload">
          <input class="form-control" style="max-width:320px" type="file" name="image" accept="image/jpeg,image/png,image/webp" required>
          <button class="btn btn-brand"><i class="bi bi-upload me-1"></i><?= $pattern?'Muster austauschen':'Muster hochladen' ?></button></form>
        <?php if($pattern): ?><form method="post" class="mt-2" onsubmit="return confirm('Hintergrund-Muster entfernen?')"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="slot" value="pattern"><input type="hidden" name="action" value="delete"><button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash3 me-1"></i>Muster entfernen</button></form><?php endif; ?>
      </div>
    </div>

    <div class="col-lg-6">
      <div class="panel p-4 h-100 site-image-panel" id="group_photo">
        <h2 class="h5 mb-1"><i class="bi bi-people me-1"></i> Gruppenfoto der Chronik</h2>
        <p class="text-secondary small">Erscheint auf der öffentlichen <a href="/public/chronik.php">Chronik-Seite</a> über dem Zeitstrahl. Querformat wirkt am besten. JPG, PNG oder WebP, bis ca. 8&nbsp;MB; große Fotos werden auf 2400 Pixel Breite verkleinert. Standort- und Kameradaten werden dabei entfernt.</p>
        <?php if($group): ?>
          <img class="site-image-photo-preview mb-2" src="<?= e(site_image_url($pdo,'group_photo')) ?>" alt="Aktuelles Gruppenfoto">
          <div class="small text-secondary mb-3">Aktuell: <?= e($size($group)) ?></div>
          <form method="post" class="d-flex flex-wrap gap-2 mb-3"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="slot" value="group_photo"><input type="hidden" name="action" value="caption">
            <input class="form-control" style="max-width:360px" name="caption" maxlength="250" value="<?= e($group['caption']) ?>" placeholder="Bildunterschrift (optional), z. B. Kirmes 2026">
            <button class="btn btn-outline-secondary">Unterschrift speichern</button></form>
        <?php else: ?>
          <p class="small text-secondary">Noch kein Gruppenfoto hochgeladen – die Chronik zeigt dann nur den Zeitstrahl.</p>
        <?php endif; ?>
        <form method="post" enctype="multipart/form-data" class="d-flex flex-wrap gap-2"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="slot" value="group_photo"><input type="hidden" name="action" value="upload">
          <input class="form-control" style="max-width:320px" type="file" name="image" accept="image/jpeg,image/png,image/webp" required>
          <?php if(!$group): ?><input class="form-control" style="max-width:320px" name="caption" maxlength="250" placeholder="Bildunterschrift (optional)"><?php else: ?><input type="hidden" name="caption" value="<?= e($group['caption']) ?>"><?php endif; ?>
          <button class="btn btn-brand"><i class="bi bi-upload me-1"></i><?= $group?'Foto austauschen':'Foto hochladen' ?></button></form>
        <?php if($group): ?><form method="post" class="mt-2" onsubmit="return confirm('Gruppenfoto entfernen?')"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="slot" value="group_photo"><input type="hidden" name="action" value="delete"><button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash3 me-1"></i>Foto entfernen</button></form><?php endif; ?>
      </div>
    </div>
  </div>
</section></div></div>
<?php require __DIR__.'/includes/footer.php'; ?>
