<?php
require_once __DIR__.'/includes/app.php';
$u=require_login();
if(!can_view_kirmes($pdo,$u)){http_response_code(403);exit('Keine Berechtigung.');}
$canEdit=can_edit_kirmes($pdo,$u);

function kirmes_input(): array {
    $date=trim((string)($_POST['event_date']??''));
    if(!preg_match('/^\d{4}-\d{2}-\d{2}$/',$date)) throw new RuntimeException('Bitte ein gültiges Datum angeben.');
    $title=trim((string)($_POST['title']??''));
    if(mb_strlen($title)<2) throw new RuntimeException('Bitte eine Überschrift für den Tag angeben.');
    $time=trim((string)($_POST['assembly_time']??''));
    if($time!==''&&!preg_match('/^\d{2}:\d{2}$/',$time)) $time='';
    $f=fn(string $k,int $max)=>mb_substr(trim((string)($_POST[$k]??'')),0,$max);
    return [$date,mb_substr($title,0,160),$time!==''?$time:null,$f('location',250),$f('attire',1000),$f('bring',1000),$f('note',1000)];
}

if($_SERVER['REQUEST_METHOD']==='POST'){
    verify_csrf();
    try{
        if(!$canEdit) throw new RuntimeException('Keine Berechtigung zum Bearbeiten.');
        $action=(string)($_POST['action']??'');
        if($action==='save'){
            [$date,$title,$time,$loc,$attire,$bring,$note]=kirmes_input();
            $id=(int)($_POST['id']??0);
            if($id>0){
                $pdo->prepare('UPDATE kirmes_days SET event_date=?,title=?,assembly_time=?,location=?,attire=?,bring=?,note=?,updated_by=?,updated_at=CURRENT_TIMESTAMP WHERE id=?')->execute([$date,$title,$time,$loc,$attire,$bring,$note,$u['id'],$id]);
                flash('success','Tag gespeichert.');
            }else{
                $pdo->prepare('INSERT INTO kirmes_days(event_date,title,assembly_time,location,attire,bring,note,created_by,updated_by) VALUES(?,?,?,?,?,?,?,?,?)')->execute([$date,$title,$time,$loc,$attire,$bring,$note,$u['id'],$u['id']]);
                flash('success','Tag hinzugefügt.');
            }
        }elseif($action==='delete'){
            $pdo->prepare('DELETE FROM kirmes_days WHERE id=?')->execute([(int)($_POST['id']??0)]);
            flash('success','Tag gelöscht.');
        }
    }catch(Throwable $e){ flash('danger',user_error_message($e)); }
    redirect('/kirmes.php');
}

$all=$pdo->query('SELECT * FROM kirmes_days ORDER BY event_date,assembly_time NULLS LAST,id')->fetchAll();
$today=(new DateTimeImmutable('now',new DateTimeZone('Europe/Berlin')))->format('Y-m-d');
$upcoming=array_values(array_filter($all,fn($d)=>$d['event_date']>=$today));
$past=array_reverse(array_values(array_filter($all,fn($d)=>$d['event_date']<$today)));

$weekdays=['Sonntag','Montag','Dienstag','Mittwoch','Donnerstag','Freitag','Samstag'];
$dayLabel=function(string $date) use($weekdays): string{ $d=new DateTimeImmutable($date); return $weekdays[(int)$d->format('w')].', '.$d->format('d.m.Y'); };

/** Formularfelder für Anlegen und Bearbeiten. */
$form=function(?array $d) use($canEdit): void{ ?>
  <form method="post" class="row g-2">
    <input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?=$d?(int)$d['id']:0?>">
    <div class="col-md-4"><label class="form-label small">Datum</label><input type="date" class="form-control" name="event_date" value="<?=$d?e($d['event_date']):''?>" required></div>
    <div class="col-md-8"><label class="form-label small">Überschrift</label><input class="form-control" name="title" value="<?=$d?e($d['title']):''?>" placeholder="z. B. Kirmessamstag" required maxlength="160"></div>
    <div class="col-md-4"><label class="form-label small">Antreten um</label><input type="time" class="form-control" name="assembly_time" value="<?=$d&&$d['assembly_time']?e(substr($d['assembly_time'],0,5)):''?>"></div>
    <div class="col-md-8"><label class="form-label small">Treffpunkt</label><input class="form-control" name="location" value="<?=$d?e($d['location']):''?>" maxlength="250"></div>
    <div class="col-12"><label class="form-label small">Was wird angezogen?</label><textarea class="form-control" name="attire" rows="2" maxlength="1000"><?=$d?e($d['attire']):''?></textarea></div>
    <div class="col-12"><label class="form-label small">Was muss mitgebracht werden / von wem?</label><textarea class="form-control" name="bring" rows="2" maxlength="1000"><?=$d?e($d['bring']):''?></textarea></div>
    <div class="col-12"><label class="form-label small">Bemerkung</label><textarea class="form-control" name="note" rows="2" maxlength="1000"><?=$d?e($d['note']):''?></textarea></div>
    <div class="col-12"><button class="btn btn-brand btn-sm">Speichern</button></div>
  </form>
<?php };

$card=function(array $d) use($dayLabel,$canEdit,$form): void{ ?>
  <div class="panel p-4 mb-3">
    <div class="d-flex justify-content-between gap-3">
      <div>
        <div class="eyebrow"><?=e($dayLabel($d['event_date']))?></div>
        <h2 class="h5 mb-2"><?=e($d['title'])?></h2>
      </div>
      <?php if($canEdit): ?><form method="post"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?=(int)$d['id']?>"><button class="btn btn-sm btn-outline-danger" onclick="return confirm('Diesen Tag löschen?')">Löschen</button></form><?php endif; ?>
    </div>
    <dl class="row mb-0 small">
      <dt class="col-sm-3">Antreten</dt><dd class="col-sm-9"><?=$d['assembly_time']?e(substr($d['assembly_time'],0,5)).' Uhr':'–'?><?=$d['location']!==''?' · '.e($d['location']):''?></dd>
      <dt class="col-sm-3">Anziehen</dt><dd class="col-sm-9"><?=$d['attire']!==''?nl2br(e($d['attire'])):'–'?></dd>
      <dt class="col-sm-3">Mitbringen</dt><dd class="col-sm-9"><?=$d['bring']!==''?nl2br(e($d['bring'])):'–'?></dd>
      <?php if($d['note']!==''): ?><dt class="col-sm-3">Bemerkung</dt><dd class="col-sm-9"><?=nl2br(e($d['note']))?></dd><?php endif; ?>
    </dl>
    <?php if($canEdit): ?><details class="mt-3"><summary class="small">Bearbeiten</summary><div class="mt-2"><?php $form($d); ?></div></details><?php endif; ?>
  </div>
<?php };

$pageTitle='Kirmes-Planung';
require __DIR__.'/includes/header.php';
?>
<div class="container dashboard-shell"><div class="row g-4">
<?php require __DIR__.'/includes/sidebar.php'; ?>
<section class="col-lg-9">
  <div class="eyebrow">Planung</div>
  <h1 class="section-title h2 mb-1">Kirmes-Planung</h1>
  <p class="text-secondary mb-3">Je Tag: Antreten, Kleidung und was mitgebracht werden muss. <span class="badge text-bg-warning">Vorschau – nur für Admins sichtbar</span></p>

  <?php if($canEdit): ?>
    <details class="panel p-4 mb-4"><summary class="fw-semibold">➕ Neuen Tag anlegen</summary><div class="mt-3"><?php $form(null); ?></div></details>
  <?php endif; ?>

  <?php if(!$all): ?>
    <div class="panel p-4"><p class="mb-0 text-secondary">Noch keine Tage angelegt.</p></div>
  <?php endif; ?>
  <?php foreach($upcoming as $d) $card($d); ?>
  <?php if($past): ?>
    <details class="mt-4"><summary class="text-secondary">Vergangene Tage (<?=count($past)?>)</summary><div class="mt-3"><?php foreach($past as $d) $card($d); ?></div></details>
  <?php endif; ?>
</section>
</div></div>
<?php require __DIR__.'/includes/footer.php'; ?>
