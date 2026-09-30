<?php
require_once __DIR__.'/includes/app.php';
$count=(int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
if($count>0){ http_response_code(404); exit('Setup ist bereits abgeschlossen.'); }
if($_SERVER['REQUEST_METHOD']==='POST'){
 verify_csrf();
 $username=trim($_POST['username']??'');
 $email=trim($_POST['email']??'');
 $first=trim($_POST['first_name']??'');
 $last=trim($_POST['last_name']??'');
 $password=$_POST['password']??'';
 if(strlen($username)<3||$first===''||$last===''||!password_is_valid($password)||($email!==''&&!filter_var($email,FILTER_VALIDATE_EMAIL))){
   flash('danger','Bitte alle Felder korrekt ausfüllen. '.password_rule_text()); redirect('/setup.php');
 }
 $stmt=$pdo->prepare("INSERT INTO users(username,email,password_hash,first_name,last_name,role,is_treasurer) VALUES(?,?,?,?,?, 'admin', 1)");
 $stmt->execute([$username,$email!==''?$email:null,password_hash($password,PASSWORD_DEFAULT),$first,$last]);
 flash('success','Administrator wurde angelegt. Du kannst dich jetzt anmelden.'); redirect('/login.php');
}
$pageTitle='Ersteinrichtung'; require __DIR__.'/includes/header.php'; ?>
<div class="container login-wrap"><div class="panel p-4 p-md-5 login-card"><div class="eyebrow">Ersteinrichtung</div><h1 class="h3 fw-bold">Ersten Admin anlegen</h1><p class="text-secondary">Diese Seite ist nur erreichbar, solange noch kein Benutzer existiert.</p><form method="post"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><div class="row g-3"><div class="col-12"><label class="form-label">Benutzername</label><input class="form-control" name="username" required></div><div class="col-12"><label class="form-label">E-Mail <span class="text-secondary">(für Passwort-Reset)</span></label><input type="email" class="form-control" name="email"></div><div class="col-6"><label class="form-label">Vorname</label><input class="form-control" name="first_name" required></div><div class="col-6"><label class="form-label">Nachname</label><input class="form-control" name="last_name" required></div><div class="col-12"><label class="form-label">Passwort</label><input type="password" minlength="8" class="form-control" name="password" required><div class="form-text"><?=e(password_rule_help())?></div></div></div><button class="btn btn-brand w-100 mt-4">Admin anlegen</button></form></div></div><?php require __DIR__.'/includes/footer.php'; ?>
