<?php
require_once __DIR__.'/includes/app.php';
$u=require_spiess_operator($pdo);
if($_SERVER['REQUEST_METHOD']!=='POST') redirect('/theke.php');
verify_csrf(); $a=$_POST['action']??'';
if($a==='add'){
 $name=trim($_POST['guest_name']??''); $did=(int)($_POST['drink_id']??0);
 $st=$pdo->prepare("SELECT id FROM drinks WHERE id=? AND is_active=1 AND is_approved=1");$st->execute([$did]);
 if($name===''||mb_strlen($name)>80||!$st->fetchColumn()){flash('danger','Gast oder Getränk ungültig.');redirect('/theke.php');}
 $pdo->prepare("INSERT INTO guest_drink_orders(guest_name,drink_id,created_by) VALUES(?,?,?)")->execute([$name,$did,(int)$u['id']]);
}
if($a==='delete'){$pdo->prepare("DELETE FROM guest_drink_orders WHERE id=?")->execute([(int)($_POST['id']??0)]);}
redirect('/theke.php');
