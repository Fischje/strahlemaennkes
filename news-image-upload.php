<?php
require_once __DIR__.'/includes/app.php';
$u=require_news_writer();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

try {
    verify_csrf();

    if (empty($_FILES['image']) || !is_array($_FILES['image'])) {
        http_response_code(422);
        echo json_encode(['ok'=>false,'error'=>'Keine Bilddatei empfangen.'],JSON_UNESCAPED_UNICODE);
        exit;
    }

    $file=$_FILES['image'];
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        http_response_code(422);
        echo json_encode(['ok'=>false,'error'=>'Der Bild-Upload ist fehlgeschlagen.'],JSON_UNESCAPED_UNICODE);
        exit;
    }

    $size=(int)($file['size'] ?? 0);
    if ($size < 1 || $size > 8*1024*1024) {
        http_response_code(413);
        echo json_encode(['ok'=>false,'error'=>'Bilder dürfen maximal 8 MB groß sein.'],JSON_UNESCAPED_UNICODE);
        exit;
    }

    $tmp=(string)($file['tmp_name'] ?? '');
    if ($tmp==='' || !is_uploaded_file($tmp)) {
        http_response_code(422);
        echo json_encode(['ok'=>false,'error'=>'Ungültige Upload-Datei.'],JSON_UNESCAPED_UNICODE);
        exit;
    }

    $finfo=new finfo(FILEINFO_MIME_TYPE);
    $mime=(string)$finfo->file($tmp);
    $allowed=[
        'image/jpeg'=>'jpg',
        'image/png'=>'png',
        'image/webp'=>'webp',
        'image/gif'=>'gif',
    ];
    if (!isset($allowed[$mime])) {
        http_response_code(415);
        echo json_encode(['ok'=>false,'error'=>'Erlaubt sind JPG, PNG, WebP und GIF.'],JSON_UNESCAPED_UNICODE);
        exit;
    }

    // getimagesize stellt zusätzlich sicher, dass die Datei tatsächlich als Bild dekodierbar ist.
    $imageInfo=@getimagesize($tmp);
    if ($imageInfo===false) {
        http_response_code(415);
        echo json_encode(['ok'=>false,'error'=>'Die Datei ist kein gültiges Bild.'],JSON_UNESCAPED_UNICODE);
        exit;
    }

    $dir=__DIR__.'/uploads/news';
    if (!is_dir($dir) && !mkdir($dir,0750,true) && !is_dir($dir)) {
        throw new RuntimeException('Upload-Ordner konnte nicht angelegt werden.');
    }

    $filename=date('Ymd-His').'-'.bin2hex(random_bytes(8)).'.'.$allowed[$mime];
    $target=$dir.'/'.$filename;

    if (!move_uploaded_file($tmp,$target)) {
        throw new RuntimeException('Bild konnte nicht gespeichert werden.');
    }
    @chmod($target,0640);

    echo json_encode([
        'ok'=>true,
        'url'=>'/uploads/news/'.$filename,
        'width'=>(int)($imageInfo[0]??0),
        'height'=>(int)($imageInfo[1]??0),
    ],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
} catch(Throwable $e) {
    error_log('Strahlemännkes News-Bildupload fehlgeschlagen: '.$e->getMessage());
    http_response_code(500);
    echo json_encode([
        'ok'=>false,
        'error'=>'Bild konnte nicht hochgeladen werden: '.$e->getMessage(),
    ],JSON_UNESCAPED_UNICODE);
}
