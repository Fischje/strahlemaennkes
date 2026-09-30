<?php
require_once __DIR__.'/includes/app.php';
if($_SERVER['REQUEST_METHOD']==='POST'){
    verify_csrf();
    $email=trim($_POST['email']??'');
    if(filter_var($email,FILTER_VALIDATE_EMAIL)){
        $st=$pdo->prepare('SELECT id,first_name,last_name,email FROM users WHERE email=? AND is_active=1 LIMIT 1');
        $st->execute([$email]);
        $user=$st->fetch();
        if($user){
            $pdo->prepare('DELETE FROM password_reset_tokens WHERE user_id=? OR expires_at <= CURRENT_TIMESTAMP')->execute([(int)$user['id']]);
            $token=bin2hex(random_bytes(32));
            $hash=hash('sha256',$token);
            $expires=date('Y-m-d H:i:s',time()+3600);
            $pdo->prepare('INSERT INTO password_reset_tokens(user_id,token_hash,expires_at) VALUES(?,?,?)')->execute([(int)$user['id'],$hash,$expires]);
            $url=absolute_url('/reset-password.php?token='.rawurlencode($token));
            send_password_reset_mail($email, trim($user['first_name'].' '.$user['last_name']), $url);
        }
    }
    // Keine Information darüber preisgeben, ob die Adresse existiert.
    flash('success','Wenn die E-Mail-Adresse einem aktiven Konto zugeordnet ist, wurde ein Link zum Zurücksetzen verschickt.');
    redirect('/forgot-password.php');
}
$pageTitle='Passwort vergessen'; require __DIR__.'/includes/header.php';
?>
<div class="container login-wrap"><div class="panel p-4 p-md-5 login-card"><div class="eyebrow">Kontozugang</div><h1 class="h3 fw-bold">Passwort zurücksetzen</h1><p class="text-secondary">Gib die E-Mail-Adresse deines Kontos ein. Du erhältst einen Link, der 60 Minuten gültig ist.</p><form method="post" class="mt-4"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><div class="mb-4"><label class="form-label">E-Mail-Adresse</label><input type="email" name="email" required autocomplete="email" class="form-control form-control-lg"></div><button class="btn btn-brand w-100">Reset-Link anfordern</button><div class="text-center mt-3"><a class="link-light-subtle small" href="/login.php">Zurück zum Login</a></div></form></div></div>
<?php require __DIR__.'/includes/footer.php'; ?>
