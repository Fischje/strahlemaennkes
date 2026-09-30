<?php
require_once __DIR__ . '/../includes/app.php';
require_login();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
$items=[];
$memberRows=$pdo->query("SELECT d.id,d.name,d.emoji,u.first_name || ' ' || substr(u.last_name,1,1) || '.' person, upper(substr(trim(u.first_name),1,1) || substr(trim(u.last_name),1,1)) export_code FROM users u JOIN user_drink_preferences p ON p.user_id=u.id JOIN drinks d ON d.id=p.drink_id WHERE u.is_active=1")->fetchAll();
$guestRows=$pdo->query("SELECT d.id,d.name,d.emoji,g.guest_name || ' (Gast)' person, 'G' || g.id export_code FROM guest_drink_orders g JOIN drinks d ON d.id=g.drink_id")->fetchAll();
foreach(array_merge($memberRows,$guestRows) as $r){
 $id=(int)$r['id'];
 if(!isset($items[$id]))$items[$id]=['id'=>$id,'name'=>$r['name'],'emoji'=>$r['emoji'],'qty'=>0,'members'=>[],'export_codes'=>[]];
 $items[$id]['qty']++; $items[$id]['members'][]=$r['person']; $items[$id]['export_codes'][]=$r['export_code'];
}
$rows=array_values($items);
usort($rows,fn($a,$b)=>$b['qty']<=>$a['qty'] ?: strcasecmp($a['name'],$b['name']));
$total=(int)$pdo->query("SELECT COUNT(*) FROM users WHERE is_active=1")->fetchColumn();
$noneMembers=$pdo->query("SELECT u.first_name || ' ' || substr(u.last_name,1,1) || '.' AS name FROM users u LEFT JOIN user_drink_preferences p ON p.user_id=u.id WHERE u.is_active=1 AND p.drink_id IS NULL ORDER BY u.last_name,u.first_name")->fetchAll(PDO::FETCH_COLUMN);
$memberChosen=count($memberRows); $guestCount=count($guestRows);
echo json_encode(['updated_at'=>date(DATE_ATOM),'total'=>$total,'chosen'=>$memberChosen+$guestCount,'member_chosen'=>$memberChosen,'guest_count'=>$guestCount,'none'=>max(0,$total-$memberChosen),'none_members'=>$noneMembers,'drinks'=>$rows],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
