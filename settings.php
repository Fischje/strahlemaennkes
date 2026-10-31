<?php
require_once __DIR__.'/includes/app.php';
require_once __DIR__.'/includes/push.php';
$u=require_login();
$categories=push_categories();

if($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='account'){
    verify_csrf();
    // Eigenes Konto: Benutzername und E-Mail-Adresse ändern (nur mit aktuellem Passwort).
    $username=trim((string)($_POST['username']??''));
    $email=trim((string)($_POST['email']??''));
    $password=(string)($_POST['current_password']??'');
    $st=$pdo->prepare('SELECT * FROM users WHERE id=? AND is_active=1');
    $st->execute([(int)$u['id']]);
    $me=$st->fetch();
    $throttleKey='account:'.(int)$u['id'];
    $usernameChanged=$me && $username!==(string)$me['username'];
    $emailChanged=$me && mb_strtolower($email,'UTF-8')!==mb_strtolower((string)($me['email']??''),'UTF-8');
    $error=null;
    if(!$me) $error='Konto nicht gefunden.';
    elseif(!$usernameChanged && !$emailChanged && $email===(string)($me['email']??'')){ flash('info','Es gab nichts zu ändern.'); redirect('/settings.php'); }
    elseif(mb_strlen($username)<3 || mb_strlen($username)>80) $error='Der Benutzername muss 3 bis 80 Zeichen lang sein.';
    elseif($usernameChanged && (str_contains($username,'@') || preg_match('/\s/u',$username))) $error='Der Benutzername darf kein @ und keine Leerzeichen enthalten.';
    elseif(!filter_var($email,FILTER_VALIDATE_EMAIL) || mb_strlen($email)>254) $error='Bitte eine gültige E-Mail-Adresse eingeben. Sie wird für „Passwort vergessen“ gebraucht.';
    elseif(login_is_throttled($pdo,$throttleKey)) $error='Zu viele falsche Passwörter. Bitte in '.LOGIN_WINDOW_MINUTES.' Minuten erneut versuchen.';
    elseif(!password_verify($password,(string)$me['password_hash'])){ login_record_failure($pdo,$throttleKey); $error='Dein aktuelles Passwort ist nicht korrekt.'; }
    else{
        // Bei der Anmeldung zählt Benutzername ODER E-Mail, daher in beide Richtungen auf Dopplungen prüfen.
        $dup=$pdo->prepare('SELECT 1 FROM users WHERE id<>? AND (LOWER(username)=LOWER(?) OR LOWER(email)=LOWER(?) OR LOWER(username)=LOWER(?) OR LOWER(email)=LOWER(?)) LIMIT 1');
        $dup->execute([(int)$u['id'],$username,$username,$email,$email]);
        if($dup->fetchColumn()) $error='Dieser Benutzername oder diese E-Mail-Adresse wird schon von einem anderen Mitglied genutzt.';
    }
    if($error){ flash('danger',$error); redirect('/settings.php'); }
    try{
        $pdo->beginTransaction();
        $pdo->prepare('UPDATE users SET username=?,email=?,updated_at=CURRENT_TIMESTAMP WHERE id=?')->execute([$username,$email,(int)$u['id']]);
        // Offene Reset-Links gingen an die alte Adresse und verlieren ihre Gültigkeit.
        if($emailChanged) $pdo->prepare('DELETE FROM password_reset_tokens WHERE user_id=?')->execute([(int)$u['id']]);
        $pdo->commit();
    }catch(Throwable $e){
        if($pdo->inTransaction()) $pdo->rollBack();
        error_log('Strahlemännkes: Konto ändern fehlgeschlagen: '.$e->getMessage());
        flash('danger','Die Änderung konnte nicht gespeichert werden. Ist der Name oder die Adresse schon vergeben?');
        redirect('/settings.php');
    }
    login_clear_failures($pdo,$throttleKey);
    $oldEmail=trim((string)($me['email']??''));
    if($emailChanged && filter_var($oldEmail,FILTER_VALIDATE_EMAIL)) send_email_changed_mail($oldEmail,trim($me['first_name'].' '.$me['last_name']),$email);
    flash('success',$usernameChanged&&$emailChanged?'Benutzername und E-Mail-Adresse geändert.':($usernameChanged?'Benutzername geändert. Melde dich künftig mit „'.$username.'“ an.':'E-Mail-Adresse geändert.'));
    redirect('/settings.php');
}

if($_SERVER['REQUEST_METHOD']==='POST'){
    verify_csrf();
    $selected=array_flip((array)($_POST['push']??[]));
    $pdo->beginTransaction();
    try{
        $st=$pdo->prepare('INSERT INTO user_push_prefs(user_id,category,enabled) VALUES(?,?,?) ON CONFLICT(user_id,category) DO UPDATE SET enabled=EXCLUDED.enabled,updated_at=CURRENT_TIMESTAMP');
        foreach($categories as $key=>$info) $st->execute([(int)$u['id'],$key,isset($selected[$key])?1:0]);
        $pdo->commit();
        flash('success','Einstellungen gespeichert.');
    }catch(Throwable $e){
        if($pdo->inTransaction()) $pdo->rollBack();
        flash('danger','Einstellungen konnten nicht gespeichert werden.');
    }
    redirect('/settings.php');
}

$st=$pdo->prepare('SELECT username,email,first_name,last_name FROM users WHERE id=?');
$st->execute([(int)$u['id']]);
$account=$st->fetch() ?: ['username'=>$u['username']??'','email'=>'','first_name'=>$u['first_name'],'last_name'=>$u['last_name']];

$st=$pdo->prepare('SELECT category,enabled FROM user_push_prefs WHERE user_id=?');
$st->execute([(int)$u['id']]);
$prefs=[];
foreach($st->fetchAll() as $row) $prefs[$row['category']]=(int)$row['enabled'];

$pageTitle='Einstellungen';
require __DIR__.'/includes/header.php';
?>
<div class="container dashboard-shell"><div class="row g-4">

<section class="col-12">
  <div class="eyebrow">Persönlich</div>
  <h1 class="section-title h2 mb-1">Einstellungen</h1>
  <p class="text-secondary mb-4">Dein Konto und deine Push-Benachrichtigungen.</p>

  <div class="panel p-4 mb-4" id="konto">
    <h2 class="h5 mb-1">Mein Konto</h2>
    <p class="small text-secondary mb-3"><?=e($account['first_name'].' '.$account['last_name'])?> · Den Namen ändert bei Bedarf der Administrator.</p>
    <form method="post" autocomplete="off">
      <input type="hidden" name="csrf" value="<?=e(csrf_token())?>">
      <input type="hidden" name="action" value="account">
      <div class="row g-3">
        <div class="col-md-6"><label class="form-label" for="accUsername">Benutzername</label><input id="accUsername" name="username" class="form-control" required minlength="3" maxlength="80" value="<?=e($account['username'])?>" autocomplete="username"><div class="form-text">Damit meldest du dich an.</div></div>
        <div class="col-md-6"><label class="form-label" for="accEmail">E-Mail-Adresse</label><input id="accEmail" type="email" name="email" class="form-control" required maxlength="254" value="<?=e((string)$account['email'])?>" autocomplete="email"><div class="form-text">Für „Passwort vergessen“. Du kannst dich auch damit anmelden.</div></div>
        <div class="col-md-6"><label class="form-label" for="accPassword">Aktuelles Passwort</label><input id="accPassword" type="password" name="current_password" class="form-control" required autocomplete="current-password"><div class="form-text">Zur Sicherheit bei jeder Änderung nötig.</div></div>
      </div>
      <div class="d-flex flex-wrap gap-2 align-items-center mt-3">
        <button class="btn btn-brand">Konto speichern</button>
        <a class="btn btn-soft" href="/change-password.php"><i class="bi bi-key me-1"></i>Passwort ändern</a>
      </div>
    </form>
  </div>

  <?php require __DIR__.'/includes/push-status-card.php'; ?>

  <div class="panel p-4">
    <h2 class="h5 mb-3">Welche Benachrichtigungen möchtest du?</h2>
    <form method="post">
      <input type="hidden" name="csrf" value="<?=e(csrf_token())?>">
      <?php foreach($categories as $key=>[$label,$text]): $on=($prefs[$key]??1)===1; ?>
        <div class="d-flex justify-content-between align-items-center gap-3 py-3 border-top">
          <label class="flex-grow-1 mb-0" for="push_<?=e($key)?>"><strong class="d-block"><?=e($label)?></strong><span class="small text-secondary"><?=e($text)?></span></label>
          <div class="form-check form-switch fs-4 mb-0"><input class="form-check-input" type="checkbox" role="switch" name="push[]" value="<?=e($key)?>" id="push_<?=e($key)?>" <?=$on?'checked':''?>></div>
        </div>
      <?php endforeach; ?>
      <button class="btn btn-brand mt-3">Speichern</button>
    </form>
    <p class="small text-secondary mt-3 mb-0">Die Auswahl gilt für alle deine Geräte. Ob dieses Gerät überhaupt Benachrichtigungen bekommt, steuerst du oben.</p>
  </div>
</section>
</div></div>
<?php require __DIR__.'/includes/footer.php'; ?>
