<?php
require_once __DIR__.'/includes/app.php';
$token=(string)($_GET['token']??$_POST['token']??'');
$tokenRow=null;
if($token!==''){
    $st=$pdo->prepare('SELECT r.id,r.user_id,r.expires_at,r.used_at,u.username FROM password_reset_tokens r JOIN users u ON u.id=r.user_id WHERE r.token_hash=? AND r.used_at IS NULL AND r.expires_at > CURRENT_TIMESTAMP AND u.is_active=1 LIMIT 1');
    $st->execute([hash('sha256',$token)]);
    $tokenRow=$st->fetch();
}
if($_SERVER['REQUEST_METHOD']==='POST'){
    verify_csrf();
    if(!$tokenRow){ flash('danger','Der Reset-Link ist ungültig oder abgelaufen.'); redirect('/forgot-password.php'); }
    $password=$_POST['password']??'';
    $confirm=$_POST['password_confirm']??'';
    if($password!==$confirm || !password_is_valid($password)){
        flash('danger','Die Passwörter stimmen nicht überein oder erfüllen die Anforderungen nicht. '.password_rule_text());
        redirect('/reset-password.php?token='.rawurlencode($token));
    }
    $pdo->beginTransaction();
    try{
        $pdo->prepare('UPDATE users SET password_hash=?,must_change_password=0,updated_at=CURRENT_TIMESTAMP WHERE id=?')->execute([password_hash($password,PASSWORD_DEFAULT),(int)$tokenRow['user_id']]);
        $pdo->prepare('UPDATE password_reset_tokens SET used_at=CURRENT_TIMESTAMP WHERE id=?')->execute([(int)$tokenRow['id']]);
        $pdo->prepare('DELETE FROM remember_tokens WHERE user_id=?')->execute([(int)$tokenRow['user_id']]);
        $pdo->commit();
    }catch(Throwable $e){ if($pdo->inTransaction())$pdo->rollBack(); throw $e; }
    flash('success','Dein Passwort wurde geändert. Du kannst dich jetzt anmelden.');
    redirect('/login.php');
}
$pageTitle='Neues Passwort'; require __DIR__.'/includes/header.php';
?>
<div class="container login-wrap"><div class="panel p-4 p-md-5 login-card"><div class="eyebrow">Kontozugang</div><h1 class="h3 fw-bold">Neues Passwort setzen</h1><?php if(!$tokenRow):?><div class="alert alert-danger mt-4">Dieser Link ist ungültig oder abgelaufen.</div><a class="btn btn-soft w-100" href="/forgot-password.php">Neuen Link anfordern</a><?php else:?><p class="text-secondary">Für <?=e($tokenRow['username'])?>. <?=e(password_rule_help())?></p><form method="post" class="mt-4"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="token" value="<?=e($token)?>"><div class="mb-3"><label class="form-label">Neues Passwort</label><input type="password" name="password" required minlength="8" class="form-control form-control-lg" autocomplete="new-password"><div class="form-text"><?=e(password_rule_help())?></div></div><div class="mb-4"><label class="form-label">Passwort wiederholen</label><input type="password" name="password_confirm" required minlength="8" class="form-control form-control-lg" autocomplete="new-password"></div><button class="btn btn-brand w-100">Passwort speichern</button></form><?php endif;?></div></div>
<?php require __DIR__.'/includes/footer.php'; ?>
