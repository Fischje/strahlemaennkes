<?php
require_once __DIR__.'/includes/app.php';
$u=require_login();

if($_SERVER['REQUEST_METHOD']==='POST'){
    verify_csrf();

    $current=(string)($_POST['current_password']??'');
    $password=(string)($_POST['password']??'');
    $confirm=(string)($_POST['password_confirm']??'');

    $st=$pdo->prepare("SELECT password_hash FROM users WHERE id=? AND is_active=1");
    $st->execute([(int)$u['id']]);
    $row=$st->fetch();

    if(!$row || !password_verify($current,$row['password_hash'])){
        flash('danger','Dein aktuelles Passwort ist nicht korrekt.');
        redirect('/change-password.php');
    }

    if($password!==$confirm){
        flash('danger','Die beiden neuen Passwörter stimmen nicht überein.');
        redirect('/change-password.php');
    }

    if(!password_is_valid($password)){
        flash('danger','Das neue Passwort erfüllt die Anforderungen nicht. '.password_rule_help());
        redirect('/change-password.php');
    }

    if(password_verify($password,$row['password_hash'])){
        flash('warning','Das neue Passwort muss sich vom aktuellen Passwort unterscheiden.');
        redirect('/change-password.php');
    }

    $pdo->beginTransaction();
    try{
        $pdo->prepare("
            UPDATE users
            SET password_hash=?,must_change_password=0,updated_at=CURRENT_TIMESTAMP
            WHERE id=?
        ")->execute([password_hash($password,PASSWORD_DEFAULT),(int)$u['id']]);

        // Andere dauerhaft angemeldete Geräte verlieren aus Sicherheitsgründen ihre Tokens.
        $pdo->prepare("DELETE FROM remember_tokens WHERE user_id=?")->execute([(int)$u['id']]);
        $pdo->commit();
    }catch(Throwable $e){
        if($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }

    $_SESSION['user']['must_change_password']=0;
    flash('success','Dein Passwort wurde geändert.');
    redirect('/dashboard.php');
}

$pageTitle='Passwort ändern';
require __DIR__.'/includes/header.php';
?>
<div class="container dashboard-shell">
  <div class="row g-4">
    <?php require __DIR__.'/includes/sidebar.php'; ?>
    <section class="col-lg-9">
      <div class="mb-4">
        <div class="eyebrow">Dein Konto</div>
        <h1 class="section-title h2 mb-1">Passwort ändern</h1>
        <p class="text-secondary mb-0">Lege ein neues persönliches Passwort für deinen Account fest.</p>
      </div>

      <div class="panel p-4 p-md-5" style="max-width:720px">
        <div class="alert alert-secondary">
          <strong>Passwortanforderungen</strong><br>
          <?=e(password_rule_help())?>
        </div>

        <form method="post">
          <input type="hidden" name="csrf" value="<?=e(csrf_token())?>">

          <div class="mb-3">
            <label class="form-label">Aktuelles Passwort</label>
            <input type="password" name="current_password" class="form-control form-control-lg" required autocomplete="current-password">
          </div>

          <div class="mb-3">
            <label class="form-label">Neues Passwort</label>
            <input type="password" name="password" class="form-control form-control-lg" minlength="8" required autocomplete="new-password">
            <div class="form-text"><?=e(password_rule_help())?></div>
          </div>

          <div class="mb-4">
            <label class="form-label">Neues Passwort wiederholen</label>
            <input type="password" name="password_confirm" class="form-control form-control-lg" minlength="8" required autocomplete="new-password">
            <div class="form-text"><?=e(password_rule_help())?></div>
          </div>

          <button class="btn btn-brand btn-lg"><i class="bi bi-key me-1"></i> Passwort ändern</button>
        </form>
      </div>
    </section>
  </div>
</div>
<?php require __DIR__.'/includes/footer.php'; ?>
