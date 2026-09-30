<?php
require_once __DIR__.'/includes/app.php';
if(current_user()) redirect('/dashboard.php');
if($_SERVER['REQUEST_METHOD']==='POST'){
  verify_csrf();
  $login=trim($_POST['login']??'');
  $password=$_POST['password']??'';
  $remember = !empty($_POST['remember']);
  $stmt=$pdo->prepare('SELECT * FROM users WHERE (username=? OR email=?) AND is_active=1 LIMIT 1');
  $stmt->execute([$login,$login]);
  $user=$stmt->fetch();
  if($user && password_verify($password,$user['password_hash'])){
    login_user($user);

    if(!empty($user['must_change_password'])){
      // Startpasswort: noch keinen dauerhaften Login-Token anlegen.
      clear_remember_login($pdo);
      $_SESSION['remember_after_password_change']=$remember ? 1 : 0;
      redirect('/first-login-password.php');
    }

    if($remember) create_remember_login($pdo, (int)$user['id']);
    else clear_remember_login($pdo);
    redirect('/dashboard.php');
  }
  flash('danger','Benutzername/E-Mail oder Passwort ist falsch.'); redirect('/login.php');
}
$pageTitle='Login'; require __DIR__.'/includes/header.php';
?>
<div class="container login-wrap"><div class="panel p-4 p-md-5 login-card"><img class="login-club-logo mb-4" src="/assets/brand/strahlemaennkes-logo.png?v=<?= e(app_version()['cache_key']) ?>" alt="Strahlemännkes Logo"><h1 class="h3 fw-bold">Interner Bereich</h1><p class="text-secondary">Anmelden mit deinem persönlichen Zug-Account.</p><form method="post" class="mt-4"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><div class="mb-3"><label class="form-label">Benutzername oder E-Mail</label><input name="login" required autofocus autocomplete="username" class="form-control form-control-lg"></div><div class="mb-3"><label class="form-label">Passwort</label><input name="password" type="password" required autocomplete="current-password" class="form-control form-control-lg"><div class="form-text"><?=e(password_rule_help())?></div></div><div class="form-check mb-4"><input class="form-check-input" type="checkbox" value="1" name="remember" id="remember"><label class="form-check-label" for="remember">Auf diesem Gerät eingeloggt bleiben</label><div class="form-text">Bleibt auf diesem Gerät bis zu 180 Tage aktiv und verlängert sich bei Nutzung automatisch.</div></div><button class="btn btn-brand btn-lg w-100">Anmelden</button><div class="text-center mt-3 d-flex flex-column gap-2"><a href="/forgot-password.php" class="link-light-subtle small">Passwort vergessen?</a><?php if(public_registration_enabled($pdo)): ?><a href="/register.php" class="link-light-subtle small"><i class="bi bi-person-plus me-1"></i>Jetzt registrieren</a><?php endif; ?></div></form></div></div>
<?php require __DIR__.'/includes/footer.php'; ?>
