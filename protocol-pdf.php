<?php
require_once __DIR__.'/includes/app.php';
require_once __DIR__.'/includes/pdf.php';
$u=require_login();

$q=$pdo->prepare("SELECT * FROM meetings WHERE id=? AND status='completed'");
$q->execute([(int)($_GET['meeting']??0)]);
$m=$q->fetch();
if(!$m){http_response_code(404);exit('Protokoll nicht gefunden.');}

$q=$pdo->prepare('SELECT a.*,'.AGENDA_PROPOSER_SELECT.' FROM meeting_agenda_items a'.AGENDA_PROPOSER_JOIN.' WHERE a.meeting_id=? ORDER BY a.sort_order,a.id');
$q->execute([$m['id']]);
$items=$q->fetchAll();
$q=$pdo->prepare('SELECT TRIM(u.first_name||\' \'||u.last_name) FROM meeting_attendees a JOIN users u ON u.id=a.user_id WHERE a.meeting_id=? ORDER BY u.last_name,u.first_name');
$q->execute([$m['id']]);
$attendees=$q->fetchAll(PDO::FETCH_COLUMN);
$tasksByItem=meeting_tasks_by_item($pdo,(int)$m['id']);
$doneHere=meeting_tasks_done_in($pdo,(int)$m['id']);

$dateShort=meeting_display($m['starts_at_utc'],'d.m.Y');
$pdf=new SimplePdf('Schützenzug Strahlemännkes · Protokoll '.$m['title'].' vom '.$dateShort);
$pdf->text('Protokoll: '.$m['title'],18,true,0,'',2);
$pdf->text(meeting_display($m['starts_at_utc']).' Uhr'.($m['location']?' · '.$m['location']:''),10.5,false,0,'',2);
if($attendees) $pdf->text('Anwesend ('.count($attendees).'): '.implode(', ',$attendees),10,false,0,'',2);
$pdf->rule();

$taskLine=function(array $t): string{
    $line=($t['status']==='done'?'[x] ':'[ ] ').$t['title'];
    $line.=' - '.($t['assignee']?'Zuständig: '.$t['assignee']:'Zuständig: offen');
    if($t['due_date']) $line.=' - Frist: '.date('d.m.Y',strtotime($t['due_date']));
    if($t['status']==='done'&&$t['done_at']) $line.=' - erledigt am '.local_time($t['done_at'],'d.m.Y');
    return $line;
};

foreach($items as $n=>$item){
    $pdf->text(($n+1).'. '.$item['title'],13,true,0,'',3);
    if(($meta=agenda_meta_text($item))!=='') $pdf->text($meta,9.5,false,0,'',3);
    if(trim((string)$item['protocol_html'])!=='') pdf_add_html($pdf,$pdo,(string)$item['protocol_html']);
    else $pdf->text('Kein Protokolleintrag.',10,false,0,'',3);
    foreach($tasksByItem[(int)$item['id']]??[] as $t) $pdf->text($taskLine($t),10,false,14,'',2);
    $pdf->space(6);
}
if($doneHere){
    $pdf->rule();
    $pdf->text('In diesem Stammtisch erledigte Aufgaben aus früheren Protokollen',11,true,0,'',3);
    foreach($doneHere as $t) $pdf->text('[x] '.$t['title'].' (aus: '.$t['meeting_title'].', '.meeting_display($t['meeting_starts'],'d.m.Y').')',10,false,14,'',2);
}

$file='Protokoll-'.meeting_display($m['starts_at_utc'],'Y-m-d').'.pdf';
$out=$pdf->output();
header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="'.$file.'"');
header('Content-Length: '.strlen($out));
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store');
echo $out;
