<?php
require_once __DIR__.'/includes/app.php';
$u=require_login();
$canManage=can_manage_assembly();
if($_SERVER['REQUEST_METHOD']==='POST'){
 if(!$canManage){http_response_code(403);exit('Nur Zugführer oder Administrator dürfen Antreten verwalten.');}
 verify_csrf(); $action=$_POST['action']??'';
 if($action==='delete'){
  $pdo->exec('DELETE FROM assembly_status WHERE id=1');
  flash('success','Antreten wurde gelöscht.'); redirect('/antreten.php');
 }
 if($action==='save'){
  $date=trim($_POST['date']??''); $time=trim($_POST['time']??''); $location=trim($_POST['location']??''); $attire=trim($_POST['attire']??'');
  $local=DateTimeImmutable::createFromFormat('!Y-m-d H:i',$date.' '.$time,new DateTimeZone('Europe/Berlin'));
  $errors=DateTimeImmutable::getLastErrors();
  if(!$local || ($errors!==false && ($errors['warning_count']||$errors['error_count']))){
   flash('danger','Bitte gültiges Datum und Uhrzeit angeben.'); redirect('/antreten.php');
  }
  if($location==='' || mb_strlen($location,'UTF-8')>200){
   flash('danger','Bitte einen Antreteort mit höchstens 200 Zeichen angeben.'); redirect('/antreten.php');
  }
  if($attire==='' || mb_strlen($attire,'UTF-8')>500){
   flash('danger','Bitte eine Anzugordnung mit höchstens 500 Zeichen angeben.'); redirect('/antreten.php');
  }
  $utc=$local->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
  $pdo->prepare("INSERT INTO assembly_status(id,starts_at_utc,location,attire,updated_at,updated_by) VALUES(1,?,?,?,CURRENT_TIMESTAMP,?) ON CONFLICT(id) DO UPDATE SET starts_at_utc=excluded.starts_at_utc,location=excluded.location,attire=excluded.attire,updated_at=CURRENT_TIMESTAMP,updated_by=excluded.updated_by")->execute([$utc,$location,$attire,(int)$u['id']]);
  flash('success','Antreten wurde gespeichert.'); redirect('/antreten.php');
 }
}
$raw=$pdo->query('SELECT * FROM assembly_status WHERE id=1 LIMIT 1')->fetch();
$current=active_assembly($pdo); $formDate=$formTime=$formLocation=$formAttire='';
if($raw){try{$dt=(new DateTimeImmutable($raw['starts_at_utc'],new DateTimeZone('UTC')))->setTimezone(new DateTimeZone('Europe/Berlin'));$formDate=$dt->format('Y-m-d');$formTime=$dt->format('H:i');$formLocation=$raw['location']??'';$formAttire=$raw['attire'];}catch(Throwable $e){}}
$pageTitle='Antreten'; require __DIR__.'/includes/header.php';
?>
<div class="container dashboard-shell"><div class="row g-4"><?php require __DIR__.'/includes/sidebar.php';?>
<section class="col-lg-9">
<div class="mb-4"><div class="eyebrow">Nächster Termin</div><h1 class="section-title h2 mb-1">Antreten</h1><p class="text-secondary mb-0"><?= $canManage ? 'Hier kannst du den nächsten Antreten-Termin festlegen oder ändern.' : 'Hier findest du alle Informationen zum nächsten Antreten.' ?></p></div>
<?php if($current):?><div class="panel p-4 mb-4 assembly-current-card"><div class="small text-secondary">Aktuell angezeigt</div><div class="h4 fw-bold mb-1"><?=e($current['weekday_short'].', '.$current['display_date'].' · '.$current['display_time'].' Uhr')?></div><div><strong>Ort:</strong> <?=e($current['location'])?></div><div><strong>Anzug:</strong> <?=e($current['attire'])?></div><div class="small text-secondary mt-2">Verschwindet automatisch eine Stunde nach dem Antreten.</div></div><?php else:?><?php if(!$canManage):?><div class="panel p-4 mb-4"><div class="h5 fw-bold mb-1">Aktuell kein Antreten eingetragen</div><div class="text-secondary">Sobald der Zugführer einen Termin festlegt, wird er hier angezeigt.</div></div><?php endif;?><?php endif;?>
<?php if($canManage):?>
<div class="panel p-4"><form method="post"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="save">
<div class="row g-3"><div class="col-md-6"><label class="form-label">Datum</label><input type="date" name="date" class="form-control" value="<?=e($formDate)?>" required></div><div class="col-md-6"><label class="form-label">Uhrzeit</label><input type="time" name="time" class="form-control" value="<?=e($formTime)?>" required></div><div class="col-12"><label class="form-label">Ort / Treffpunkt</label><input type="text" name="location" class="form-control" maxlength="200" value="<?=e($formLocation)?>" placeholder="z. B. Vereinsheim, Dorfplatz …" required></div><div class="col-12"><label class="form-label">Was wird angezogen?</label><textarea name="attire" class="form-control" rows="3" maxlength="500" required placeholder="z. B. Uniform komplett, schwarze Hose, Zugshirt …"><?=e($formAttire)?></textarea></div></div>
<button class="btn btn-brand mt-4"><i class="bi bi-calendar-check me-1"></i> Antreten speichern</button></form>
<?php if($raw):?><form method="post" class="mt-3" onsubmit="return confirm('Antreten-Termin wirklich löschen?');"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="delete"><button class="btn btn-outline-danger"><i class="bi bi-trash3 me-1"></i> Antreten löschen</button></form><?php endif;?>
</div>
<?php endif;?>
</section></div></div>
<?php require __DIR__.'/includes/footer.php';?>
