<?php
require_once __DIR__.'/../includes/app.php';
$u=require_login();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$round=active_round_for_user($pdo,(int)$u['id']);
if(!$round){
    echo json_encode(['active'=>false],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    exit;
}

echo json_encode([
    'active'=>true,
    'id'=>(int)$round['id'],
    'reason'=>$round['reason'],
    'dispatched_at'=>$round['dispatched_at'],
],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
