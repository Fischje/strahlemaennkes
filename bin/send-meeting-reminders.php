<?php
// Per Cron minütlich ausführen; erinnert genau einmal im Zeitfenster eine Stunde vor Beginn.
require_once __DIR__.'/../includes/app.php';
$rows=$pdo->query("SELECT id,title,starts_at_utc FROM meetings WHERE status='scheduled' AND reminder_sent_at IS NULL AND starts_at_utc BETWEEN CURRENT_TIMESTAMP + INTERVAL '55 minutes' AND CURRENT_TIMESTAMP + INTERVAL '65 minutes'")->fetchAll();
foreach($rows as $m){send_push_to_all_active_users($pdo,'Stammtisch in einer Stunde',$m['title'].' · '.meeting_display($m['starts_at_utc']),'/stammtisch.php','meeting-reminder');$pdo->prepare('UPDATE meetings SET reminder_sent_at=CURRENT_TIMESTAMP WHERE id=?')->execute([$m['id']]);echo 'Erinnerung '.$m['id'].PHP_EOL;}
