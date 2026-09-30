<?php
require_once __DIR__.'/includes/app.php';

if(current_user()) redirect('/dashboard.php');

if(!public_registration_enabled($pdo)){
    http_response_code(403);
    $pageTitle='Registrierung geschlossen';
    require __DIR__.'/includes/header.php';
    ?>
    <div class="container login-wrap">
      <div class="panel p-4 p-md-5 login-card text-center">
        <img class="login-club-logo mb-4" src="/assets/brand/strahlemaennkes-logo.png?v=<?=e(app_version()['cache_key'])?>" alt="Strahlemännkes Logo">
        <h1 class="h3 fw-bold">Registrierung geschlossen</h1>
        <p class="text-secondary">Der Administrator hat die Selbstregistrierung derzeit nicht freigeschaltet.</p>
        <a class="btn btn-brand mt-2" href="/login.php">Zum Login</a>
      </div>
    </div>
    <?php
    require __DIR__.'/includes/footer.php';
    exit;
}

if($_SERVER['REQUEST_METHOD']==='POST'){
    verify_csrf();

    // Noch einmal direkt vor dem INSERT prüfen, falls der Admin die Registrierung
    // zwischen dem Öffnen und Absenden des Formulars deaktiviert hat.
    if(!public_registration_enabled($pdo)){
        flash('warning','Die Registrierung wurde inzwischen geschlossen.');
        redirect('/login.php');
    }

    $first=trim((string)($_POST['first_name']??''));
    $last=trim((string)($_POST['last_name']??''));
    $username=trim((string)($_POST['username']??''));
    $email=trim((string)($_POST['email']??''));
    $password=(string)($_POST['password']??'');
    $confirm=(string)($_POST['password_confirm']??'');

    if($first==='' || $last==='' || mb_strlen($username)<3){
        flash('danger','Bitte Vorname, Nachname und einen Benutzernamen mit mindestens 3 Zeichen eingeben.');
        redirect('/register.php');
    }
    if(!filter_var($email,FILTER_VALIDATE_EMAIL)){
        flash('danger','Bitte eine gültige E-Mail-Adresse eingeben.');
        redirect('/register.php');
    }
    if($password!==$confirm){
        flash('danger','Die beiden Passwörter stimmen nicht überein.');
        redirect('/register.php');
    }
    if(!password_is_valid($password)){
        flash('danger','Das Passwort erfüllt die Anforderungen nicht. '.password_rule_help());
        redirect('/register.php');
    }

    try{
        $st=$pdo->prepare("
            INSERT INTO users(
                username,email,password_hash,first_name,last_name,role,
                is_treasurer,is_leader,is_secretary,must_change_password,is_active
            )
            VALUES(?,?,?,?,?,'member',0,0,0,0,1)
        ");
        $st->execute([
            $username,
            $email,
            password_hash($password,PASSWORD_DEFAULT),
            $first,
            $last,
        ]);

        flash('success','Registrierung erfolgreich. Du kannst dich jetzt anmelden.');
        redirect('/login.php');
    }catch(PDOException $e){
        // Keine Datenbankdetails nach außen geben.
        flash('danger','Benutzername oder E-Mail-Adresse ist bereits vergeben.');
        redirect('/register.php');
    }
}

$pageTitle='Registrieren';
require __DIR__.'/includes/header.php';
?>
<div class="container login-wrap">
  <div class="panel p-4 p-md-5 login-card">
    <img class="login-club-logo mb-4" src="/assets/brand/strahlemaennkes-logo.png?v=<?=e(app_version()['cache_key'])?>" alt="Strahlemännkes Logo">
    <h1 class="h3 fw-bold">Mitglied registrieren</h1>
    <p class="text-secondary">Erstelle deinen persönlichen Zugang zum internen Bereich.</p>

    <form method="post" class="mt-4">
      <input type="hidden" name="csrf" value="<?=e(csrf_token())?>">

      <div class="row g-3">
        <div class="col-6">
          <label class="form-label">Vorname</label>
          <input name="first_name" class="form-control" required autocomplete="given-name">
        </div>
        <div class="col-6">
          <label class="form-label">Nachname</label>
          <input name="last_name" class="form-control" required autocomplete="family-name">
        </div>
        <div class="col-12">
          <label class="form-label">Benutzername</label>
          <input name="username" class="form-control" minlength="3" required autocomplete="username">
        </div>
        <div class="col-12">
          <label class="form-label">E-Mail</label>
          <input type="email" name="email" class="form-control" required autocomplete="email">
        </div>
        <div class="col-12">
          <label class="form-label">Passwort</label>
          <input type="password" name="password" class="form-control" minlength="8" required autocomplete="new-password">
          <div class="form-text"><?=e(password_rule_help())?></div>
        </div>
        <div class="col-12">
          <label class="form-label">Passwort wiederholen</label>
          <input type="password" name="password_confirm" class="form-control" minlength="8" required autocomplete="new-password">
        </div>
      </div>

      <button class="btn btn-brand btn-lg w-100 mt-4"><i class="bi bi-person-plus me-1"></i> Registrieren</button>
      <div class="text-center mt-3"><a href="/login.php" class="link-light-subtle small">Bereits registriert? Zum Login</a></div>
    </form>
  </div>
</div>
<?php require __DIR__.'/includes/footer.php'; ?>
