<?php
// Liefert ein Hintergrundbild der Website (öffentlich, damit auch Login und Startseite es zeigen).
// Abgewählte Bilder sehen nur Admins (für die Vorschau in den Freischaltungen).
require_once __DIR__.'/includes/app.php';
$st=$pdo->prepare('SELECT mime,data,is_active,EXTRACT(EPOCH FROM updated_at)::bigint AS ts FROM site_backgrounds WHERE id=?');
$st->execute([(int)($_GET['id']??0)]);
$row=$st->fetch();
if(!$row || (!(int)$row['is_active'] && !has_role('admin'))){http_response_code(404);exit;}
$data=$row['data'];
if(is_resource($data)) $data=stream_get_contents($data);
$etag='"bg-'.(int)($_GET['id']??0).'-'.$row['ts'].'"';
header('ETag: '.$etag);
header('Cache-Control: '.((int)$row['is_active'] ? 'public' : 'private').', max-age=86400');
header('X-Content-Type-Options: nosniff');
if(($_SERVER['HTTP_IF_NONE_MATCH']??'')===$etag){http_response_code(304);exit;}
header('Content-Type: '.$row['mime']);
header('Content-Length: '.strlen($data));
echo $data;
