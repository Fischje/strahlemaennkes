<?php
require_once __DIR__.'/includes/app.php';

$u=current_user();
if(!$u){
    flash('warning','Bitte zuerst mit deinem Startpasswort anmelden.');
    redirect('/login.php');
}
if(empty($u['must_change_password'])){
    redirect('/dashboard.php');
}

if($_SERVER['REQUEST_METHOD']==='POST'){
    verify_csrf();
    $password=(string)($_POST['password']??'');
    $confirm=(string)($_POST['password_confirm']??'');

    if($password!==$confirm){
        flash('danger','Die beiden Passwörter stimmen nicht überein.');
        redirect('/first-login-password.php');
    }
    if(!password_is_valid($password)){
        flash('danger','Das neue Passwort erfüllt die Anforderungen nicht. '.password_rule_help());
        redirect('/first-login-password.php');
    }

    $pdo->beginTransaction();
    try{
        $pdo->prepare("
            UPDATE users
            SET password_hash=?,must_change_password=0,updated_at=CURRENT_TIMESTAMP
            WHERE id=?
        ")->execute([password_hash($password,PASSWORD_DEFAULT),(int)$u['id']]);

        $pdo->prepare('DELETE FROM remember_tokens WHERE user_id=?')->execute([(int)$u['id']]);
        $pdo->commit();
    }catch(Throwable $e){
        if($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }

    $_SESSION['user']['must_change_password']=0;

    if(!empty($_SESSION['remember_after_password_change'])){
        create_remember_login($pdo,(int)$u['id']);
    }
    unset($_SESSION['remember_after_password_change']);

    flash('success','Dein persönliches Passwort wurde gespeichert.');
    redirect('/dashboard.php');
}

$pageTitle='Startpasswort ändern';
require __DIR__.'/includes/header.php';
?>
<div class="container login-wrap">
  <div class="panel p-4 p-md-5 login-card">
    <div class="eyebrow">Erster Login</div>
    <h1 class="h3 fw-bold">Eigenes Passwort festlegen</h1>
    <p class="text-secondary">Du hast dich mit einem vom Administrator vergebenen Startpasswort angemeldet. Bevor du die App benutzen kannst, musst du ein eigenes Passwort festlegen.</p>

    <div class="alert alert-warning mt-3">
      <strong>Passwortanforderungen</strong><br>
      <?=e(password_rule_help())?>
    </div>

    <form method="post" class="mt-4">
      <input type="hidden" name="csrf" value="<?=e(csrf_token())?>">
      <div class="mb-3">
        <label class="form-label">Neues Passwort</label>
        <input type="password" name="password" minlength="8" required autocomplete="new-password" class="form-control form-control-lg">
        <div class="form-text"><?=e(password_rule_help())?></div>
      </div>
      <div class="mb-4">
        <label class="form-label">Neues Passwort wiederholen</label>
        <input type="password" name="password_confirm" minlength="8" required autocomplete="new-password" class="form-control form-control-lg">
        <div class="form-text"><?=e(password_rule_help())?></div>
      </div>
      <button class="btn btn-brand btn-lg w-100">Passwort speichern und fortfahren</button>
    </form>
  </div>
</div>
<?php require __DIR__.'/includes/footer.php'; ?>
