<?php
// Liefert ein Bild der Seite (Hintergrund-Muster, Gruppenfoto). Öffentlich, weil auch Startseite, Login und Chronik es zeigen.
require_once __DIR__.'/includes/app.php';
$slot=(string)($_GET['slot']??'');
if(!isset(SITE_IMAGE_SLOTS[$slot])){http_response_code(404);exit;}
$st=$pdo->prepare('SELECT mime,data,EXTRACT(EPOCH FROM updated_at)::bigint AS ts FROM site_images WHERE slot=?');
$st->execute([$slot]);
$row=$st->fetch();
if(!$row){http_response_code(404);exit;}
$data=$row['data'];
if(is_resource($data)) $data=stream_get_contents($data);
$etag='"img-'.$slot.'-'.$row['ts'].'"';
header('ETag: '.$etag);
header('Cache-Control: public, max-age=86400');
header('X-Content-Type-Options: nosniff');
if(($_SERVER['HTTP_IF_NONE_MATCH']??'')===$etag){http_response_code(304);exit;}
header('Content-Type: '.$row['mime']);
header('Content-Length: '.strlen($data));
echo $data;
