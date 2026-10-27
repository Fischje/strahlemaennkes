<?php
// Per Cron minütlich ausführen. Verschickt pro Stammtisch genau einmal je Erinnerung:
//  - eine Woche vorher (ab 09:00 Uhr am Tag, der sieben Tage vor dem Stammtisch liegt),
//  - am Tag des Stammtischs (ab 09:00 Uhr),
//  - eine Stunde vorher (Zeitfenster 55 bis 65 Minuten vor Beginn).
// Ein Stammtisch, der erst kurzfristig festgelegt wird, löst die zu spät liegenden Erinnerungen nicht nachträglich aus.
require_once __DIR__.'/../includes/app.php';

$tz=new DateTimeZone('Europe/Berlin');
$now=new DateTimeImmutable('now',$tz);
$rows=$pdo->query("SELECT id,title,starts_at_utc,location,reminder_sent_at,reminder_week_sent_at,reminder_day_sent_at FROM meetings WHERE status='scheduled' AND starts_at_utc IS NOT NULL AND starts_at_utc > CURRENT_TIMESTAMP")->fetchAll();
$mark=fn(int $id,string $col)=>$pdo->prepare("UPDATE meetings SET $col=CURRENT_TIMESTAMP WHERE id=?")->execute([$id]);

foreach($rows as $m){
    $start=(new DateTimeImmutable($m['starts_at_utc'],new DateTimeZone('UTC')))->setTimezone($tz);
    $day=$start->setTime(0,0);
    $weekFrom=$day->modify('-7 days')->setTime(9,0);
    $dayFrom=$day->setTime(9,0);
    $info=$m['title'].' · '.meeting_display($m['starts_at_utc']).' Uhr'.($m['location']?' · '.$m['location']:'');

    if(!$m['reminder_week_sent_at'] && $now>=$weekFrom && $now<$weekFrom->modify('+1 day')){
        send_push_to_all_active_users($pdo,'Stammtisch in einer Woche',$info,'/stammtisch.php','meeting-reminder-week','stammtisch');
        $mark((int)$m['id'],'reminder_week_sent_at');echo 'Wochen-Erinnerung '.$m['id'].PHP_EOL;
    }
    if(!$m['reminder_day_sent_at'] && $now>=$dayFrom && $now<$start){
        send_push_to_all_active_users($pdo,'Heute ist Stammtisch',$info,'/stammtisch.php','meeting-reminder-day','stammtisch');
        $mark((int)$m['id'],'reminder_day_sent_at');echo 'Tages-Erinnerung '.$m['id'].PHP_EOL;
    }
    $minutes=($start->getTimestamp()-$now->getTimestamp())/60;
    if(!$m['reminder_sent_at'] && $minutes>=55 && $minutes<=65){
        send_push_to_all_active_users($pdo,'Stammtisch in einer Stunde',$info,'/stammtisch.php','meeting-reminder','stammtisch');
        $mark((int)$m['id'],'reminder_sent_at');echo 'Erinnerung '.$m['id'].PHP_EOL;
    }
}
