<?php
require_once __DIR__.'/includes/app.php';
require_once __DIR__.'/includes/push.php';
$u=require_login();
$categories=push_categories();

if($_SERVER['REQUEST_METHOD']==='POST'){
    verify_csrf();
    $selected=array_flip((array)($_POST['push']??[]));
    $pdo->beginTransaction();
    try{
        $st=$pdo->prepare('INSERT INTO user_push_prefs(user_id,category,enabled) VALUES(?,?,?) ON CONFLICT(user_id,category) DO UPDATE SET enabled=EXCLUDED.enabled,updated_at=CURRENT_TIMESTAMP');
        foreach($categories as $key=>$info) $st->execute([(int)$u['id'],$key,isset($selected[$key])?1:0]);
        $pdo->commit();
        flash('success','Einstellungen gespeichert.');
    }catch(Throwable $e){
        if($pdo->inTransaction()) $pdo->rollBack();
        flash('danger','Einstellungen konnten nicht gespeichert werden.');
    }
    redirect('/settings.php');
}

$st=$pdo->prepare('SELECT category,enabled FROM user_push_prefs WHERE user_id=?');
$st->execute([(int)$u['id']]);
$prefs=[];
foreach($st->fetchAll() as $row) $prefs[$row['category']]=(int)$row['enabled'];

$pageTitle='Einstellungen';
require __DIR__.'/includes/header.php';
?>
<div class="container dashboard-shell"><div class="row g-4">
<?php require __DIR__.'/includes/sidebar.php'; ?>
<section class="col-lg-9">
  <div class="eyebrow">Persönlich</div>
  <h1 class="section-title h2 mb-1">Einstellungen</h1>
  <p class="text-secondary mb-4">Hier legst du fest, welche Push-Benachrichtigungen du bekommst.</p>

  <?php require __DIR__.'/includes/push-status-card.php'; ?>

  <div class="panel p-4">
    <h2 class="h5 mb-3">Welche Benachrichtigungen möchtest du?</h2>
    <form method="post">
      <input type="hidden" name="csrf" value="<?=e(csrf_token())?>">
      <?php foreach($categories as $key=>[$label,$text]): $on=($prefs[$key]??1)===1; ?>
        <div class="d-flex justify-content-between align-items-center gap-3 py-3 border-top">
          <label class="flex-grow-1 mb-0" for="push_<?=e($key)?>"><strong class="d-block"><?=e($label)?></strong><span class="small text-secondary"><?=e($text)?></span></label>
          <div class="form-check form-switch fs-4 mb-0"><input class="form-check-input" type="checkbox" role="switch" name="push[]" value="<?=e($key)?>" id="push_<?=e($key)?>" <?=$on?'checked':''?>></div>
        </div>
      <?php endforeach; ?>
      <button class="btn btn-brand mt-3">Speichern</button>
    </form>
    <p class="small text-secondary mt-3 mb-0">Die Auswahl gilt für alle deine Geräte. Ob dieses Gerät überhaupt Benachrichtigungen bekommt, steuerst du oben.</p>
  </div>
</section>
</div></div>
<?php require __DIR__.'/includes/footer.php'; ?>
