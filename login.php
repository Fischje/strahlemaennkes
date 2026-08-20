<?php
require_once __DIR__.'/includes/app.php';
if(current_user()) redirect('/dashboard.php');
if($_SERVER['REQUEST_METHOD']==='POST'){
  verify_csrf();
  $login=trim($_POST['login']??''); $password=$_POST['password']??'';
  $stmt=$pdo->prepare('SELECT * FROM users WHERE (username=? OR email=?) AND is_active=1 LIMIT 1'); $stmt->execute([$login,$login]); $user=$stmt->fetch();
  if($user && password_verify($password,$user['password_hash'])){ login_user($user); redirect('/dashboard.php'); }
  flash('danger','Benutzername/E-Mail oder Passwort ist falsch.'); redirect('/login.php');
}
$pageTitle='Login'; require __DIR__.'/includes/header.php';
?>
<div class="container login-wrap"><div class="panel p-4 p-md-5 login-card"><div class="brand-mark mb-4">SM</div><h1 class="h3 fw-bold">Interner Bereich</h1><p class="text-secondary">Anmelden mit deinem persönlichen Zug-Account.</p><form method="post" class="mt-4"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><div class="mb-3"><label class="form-label">Benutzername oder E-Mail</label><input name="login" required autofocus class="form-control form-control-lg"></div><div class="mb-4"><label class="form-label">Passwort</label><input name="password" type="password" required class="form-control form-control-lg"></div><button class="btn btn-brand btn-lg w-100">Anmelden</button></form></div></div>
<?php require __DIR__.'/includes/footer.php'; ?>
