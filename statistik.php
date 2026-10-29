<?php
require_once __DIR__.'/includes/app.php';
$u=require_login();
if(!can_view_stats($pdo,$u)){http_response_code(403);exit('Keine Berechtigung.');}

// Abgeschlossene Stammtische mit erfasster Anwesenheit (ohne Anwesenheitsliste zählt ein Stammtisch nicht mit).
$meetings=$pdo->query("SELECT m.id,m.title,m.starts_at_utc,(SELECT COUNT(*) FROM meeting_attendees a WHERE a.meeting_id=m.id) AS present FROM meetings m WHERE m.status='completed' AND m.starts_at_utc IS NOT NULL ORDER BY m.starts_at_utc DESC")->fetchAll();
$meetings=array_values(array_filter($meetings,fn($m)=>(int)$m['present']>0));
foreach($meetings as &$m) $m['year']=(int)meeting_display($m['starts_at_utc'],'Y');
unset($m);
$years=array_values(array_unique(array_column($meetings,'year')));
rsort($years);

$sel=($_GET['jahr']??'');
$year=$sel==='alle'?0:(in_array((int)$sel,$years,true)?(int)$sel:($years[0]??0));
$filtered=array_values(array_filter($meetings,fn($m)=>$year===0||$m['year']===$year));
$total=count($filtered);
$ids=array_column($filtered,'id');

$rows=[];
if($total){
    $in=implode(',',array_map('intval',$ids));
    $users=$pdo->query("SELECT u.id,TRIM(u.first_name||' '||u.last_name) AS name,u.is_active FROM users u ORDER BY u.last_name,u.first_name")->fetchAll();
    $att=[];$last=[];
    foreach($pdo->query("SELECT a.user_id,m.starts_at_utc FROM meeting_attendees a JOIN meetings m ON m.id=a.meeting_id WHERE a.meeting_id IN ($in)")->fetchAll() as $r){
        $att[(int)$r['user_id']]=($att[(int)$r['user_id']]??0)+1;
        if(!isset($last[(int)$r['user_id']])||$r['starts_at_utc']>$last[(int)$r['user_id']]) $last[(int)$r['user_id']]=$r['starts_at_utc'];
    }
    foreach($users as $x){
        $n=$att[(int)$x['id']]??0;
        if(!$x['is_active']&&$n===0) continue;
        $rows[]=['name'=>$x['name'].($x['is_active']?'':' (inaktiv)'),'n'=>$n,'pct'=>(int)round($n*100/$total),'last'=>$last[(int)$x['id']]??null];
    }
    usort($rows,fn($a,$b)=>[$b['n'],$a['name']]<=>[$a['n'],$b['name']]);
}

$pageTitle='Anwesenheitsstatistik';
require __DIR__.'/includes/header.php';
?>
<div class="container dashboard-shell"><div class="row g-4">

<section class="col-12">
  <div class="eyebrow">Auswertung</div>
  <h1 class="section-title h2 mb-1">Anwesenheitsstatistik</h1>
  <p class="text-secondary mb-4">Wer war wie oft beim Stammtisch? Gezählt werden abgeschlossene Stammtische mit erfasster Anwesenheit.</p>

  <?php if(!$meetings): ?>
    <div class="panel p-4"><p class="mb-0 text-secondary">Noch keine abgeschlossenen Stammtische mit Anwesenheitsliste.</p></div>
  <?php else: ?>
    <div class="d-flex flex-wrap gap-2 mb-3">
      <?php foreach($years as $y): ?><a class="btn btn-sm <?=$year===$y?'btn-brand':'btn-outline-secondary'?>" href="?jahr=<?=$y?>"><?=$y?></a><?php endforeach; ?>
      <a class="btn btn-sm <?=$year===0?'btn-brand':'btn-outline-secondary'?>" href="?jahr=alle">Gesamt</a>
    </div>

    <div class="panel p-4 mb-4">
      <h2 class="h5">Anwesenheit je Mitglied <small class="text-secondary">· <?=$total?> Stammtisch<?=$total===1?'':'e'?></small></h2>
      <?php foreach($rows as $r): ?>
        <div class="py-2 border-top">
          <div class="d-flex justify-content-between gap-2"><strong><?=e($r['name'])?></strong><span><?=$r['n']?> von <?=$total?> · <?=$r['pct']?> %</span></div>
          <div class="progress mt-1" style="height:8px" role="progressbar" aria-label="Anwesenheit <?=e($r['name'])?>" aria-valuenow="<?=$r['pct']?>" aria-valuemin="0" aria-valuemax="100"><div class="progress-bar bg-danger" style="width:<?=$r['pct']?>%"></div></div>
          <small class="text-secondary"><?=$r['last']?'Zuletzt anwesend: '.e(meeting_display($r['last'],'d.m.Y')):'Noch nie anwesend'?></small>
        </div>
      <?php endforeach; ?>
    </div>

    <div class="panel p-4">
      <h2 class="h5">Stammtische</h2>
      <?php foreach($filtered as $m): ?>
        <a class="d-flex justify-content-between border-top py-2 text-decoration-none" href="/protokolle.php?meeting=<?=$m['id']?>"><span><strong><?=e(meeting_display($m['starts_at_utc'],'d.m.Y'))?></strong> <span class="text-secondary"><?=e($m['title'])?></span></span><span><?=$m['present']?> anwesend</span></a>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</section>
</div></div>
<?php require __DIR__.'/includes/footer.php'; ?>
