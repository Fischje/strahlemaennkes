<?php
// Liefert das Hintergrundbild der Website (öffentlich, damit auch Login und Startseite es zeigen).
require_once __DIR__.'/includes/app.php';
$row=$pdo->query('SELECT mime,data,EXTRACT(EPOCH FROM updated_at)::bigint AS ts FROM site_background WHERE id=1')->fetch();
if(!$row){http_response_code(404);exit;}
$data=$row['data'];
if(is_resource($data)) $data=stream_get_contents($data);
$etag='"bg-'.$row['ts'].'"';
header('ETag: '.$etag);
header('Cache-Control: public, max-age=86400');
header('X-Content-Type-Options: nosniff');
if(($_SERVER['HTTP_IF_NONE_MATCH']??'')===$etag){http_response_code(304);exit;}
header('Content-Type: '.$row['mime']);
header('Content-Length: '.strlen($data));
echo $data;
