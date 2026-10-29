<?php
require_once __DIR__ . '/app.php';
$pageTitle = $pageTitle ?? 'Strahlemännkes';
// $hideAppNav: Seite ohne App-Navigation und Hinweiszeilen (z. B. Umzugsseite auf der alten Adresse).
$u = empty($hideAppNav) ? current_user() : null;
$currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$version = app_version();
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
?>
<!doctype html>
<html lang="de">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <meta name="theme-color" content="#111315">
  <meta name="mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
  <meta name="apple-mobile-web-app-title" content="Strahlemännkes">
  <meta name="description" content="Interne Zug-App der Strahlemännkes – Getränke, Runden und Organisation.">
  <title><?= e($pageTitle) ?> · Strahlemännkes</title>
  <link rel="manifest" href="/manifest.webmanifest?v=<?= e($version['cache_key']) ?>">
  <link rel="icon" type="image/x-icon" href="/favicon.ico?v=<?= e($version['cache_key']) ?>">
  <link rel="icon" type="image/png" href="/assets/icons/favicon-32.png?v=<?= e($version['cache_key']) ?>" sizes="32x32">
  <link rel="icon" type="image/png" href="/assets/icons/icon-192.png?v=<?= e($version['cache_key']) ?>" sizes="192x192">
  <link rel="apple-touch-icon" href="/assets/icons/apple-touch-icon.png?v=<?= e($version['cache_key']) ?>">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@500;600;700;800&family=Permanent+Marker&family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="/assets/css/app.css?v=<?= e($version['cache_key']) ?>">
  <link rel="stylesheet" href="/assets/css/theme-d.css?v=<?= e($version['cache_key']) ?>">
<?php if(isset($pdo)&&($bgStamp=site_background_stamp($pdo))): ?>  <style>:root{--sm-bg-image:url('/background.php?v=<?= (int)$bgStamp ?>')}</style>
<?php endif; ?>
</head>
<body class="<?= trim(($u ? 'has-mobile-nav ' : '') . ($bodyClass ?? '')) ?>">
<nav class="navbar navbar-expand-lg navbar-dark app-navbar sticky-top">
  <div class="container">
    <a class="navbar-brand d-flex align-items-center gap-2" href="<?= $u ? '/dashboard.php' : '/index.php' ?>">
      <img class="header-club-logo" src="/assets/brand/strahlemaennkes-logo.png?v=<?= e($version['cache_key']) ?>" alt="Strahlemännkes Logo">
      <span><strong>Strahlemännkes</strong></span>
    </a>
    <div class="d-flex align-items-center gap-2 ms-auto d-lg-none">
      <?php if ($u): ?>
        <span class="online-dot" data-online-indicator title="Verbindungsstatus"></span>
        <div class="dropdown">
          <button class="btn btn-sm user-menu-button dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
            <i class="bi bi-person-circle me-1"></i><?=e($u['first_name'])?>
          </button>
          <ul class="dropdown-menu dropdown-menu-end user-dropdown-menu">
            <li><div class="dropdown-header"><?=e($u['first_name'].' '.$u['last_name'])?></div></li>
            <li><a class="dropdown-item" href="/settings.php"><i class="bi bi-gear me-2"></i>Einstellungen</a></li>
            <li><a class="dropdown-item" href="/change-password.php"><i class="bi bi-key me-2"></i>Passwort ändern</a></li>
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item text-danger" href="/logout.php"><i class="bi bi-box-arrow-right me-2"></i>Abmelden</a></li>
          </ul>
        </div>
      <?php endif; ?>
      <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav" aria-label="Menü öffnen"><span class="navbar-toggler-icon"></span></button>
    </div>
    <div class="collapse navbar-collapse" id="mainNav">
      <ul class="navbar-nav ms-auto align-items-lg-center gap-lg-1 py-2 py-lg-0">
        <?php if($u): ?>
          <li class="nav-item"><a class="nav-link" href="/dashboard.php">Übersicht</a></li>
          <li class="nav-item"><a class="nav-link" href="/member-drink.php">Mein Getränk</a></li>
          <li class="nav-item"><a class="nav-link" href="/stammtisch.php">Stammtisch</a></li>
          <li class="nav-item"><a class="nav-link" href="/fines.php">Runden</a></li>
          <li class="nav-item"><a class="nav-link" href="/theke.php">Theke</a></li>
          <li class="nav-item"><a class="nav-link" href="/protokolle.php">Protokolle</a></li>
          <li class="nav-item"><a class="nav-link" href="/antreten.php">Antreten</a></li>
          <li class="nav-item"><a class="nav-link" href="/public/chronik.php">Chronik</a></li>
          <li class="nav-item"><a class="nav-link" href="/members.php">Mitglieder</a></li>
          <li class="nav-item"><a class="nav-link" href="/cash.php">Kasse</a></li>
          <?php
            $showFunctions = can_act_as_spiess($pdo,$u) || can_manage_spiess_delegation()
              || can_manage_assembly() || can_edit_chronicle() || has_role('admin') || sektbar_is_enabled($pdo);
          ?>
          <?php if($showFunctions): ?>
            <li class="nav-item"><a class="nav-link functions-nav-link" href="/functions.php"><i class="bi bi-grid-3x3-gap me-1"></i>Funktionen</a></li>
          <?php endif; ?>
          <li class="nav-item dropdown ms-lg-2 d-none d-lg-block">
            <button class="btn btn-light btn-sm px-3 dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
              <i class="bi bi-person-circle me-1"></i><?=e($u['first_name'])?>
            </button>
            <ul class="dropdown-menu dropdown-menu-end user-dropdown-menu">
              <li><div class="dropdown-header"><?=e($u['first_name'].' '.$u['last_name'])?></div></li>
              <li><a class="dropdown-item" href="/settings.php"><i class="bi bi-gear me-2"></i>Einstellungen</a></li>
            <li><a class="dropdown-item" href="/change-password.php"><i class="bi bi-key me-2"></i>Passwort ändern</a></li>
              <li><hr class="dropdown-divider"></li>
              <li><a class="dropdown-item text-danger" href="/logout.php"><i class="bi bi-box-arrow-right me-2"></i>Abmelden</a></li>
            </ul>
          </li>
        <?php else: ?>
          <li class="nav-item"><a class="nav-link" href="/public/chronik.php">Chronik</a></li>
          <li class="nav-item ms-lg-2"><a class="btn btn-light btn-sm px-3" href="/login.php"><i class="bi bi-person-circle me-1"></i> Login</a></li>
        <?php endif; ?>
      </ul>
    </div>
  </div>
</nav>
<?php $sektbarBanner=$u ? (sektbar_is_enabled($pdo) && sektbar_is_active($pdo)) : false; $assemblyBanner=($u && !$sektbarBanner) ? active_assembly($pdo) : null; ?>
<?php if($sektbarBanner): ?>
<a class="sektbar-topline" href="<?=can_use_sektbar($pdo,$u)?'/sektbar.php':'/theke.php'?>"><div class="container"><strong>🥂 ✨ SEKTbarmodus aktiviert ✨ 🥂</strong></div></a>
<?php elseif($assemblyBanner): ?>
<a class="assembly-topline" href="/antreten.php" aria-label="Antreten-Details anzeigen"><div class="container">
<i class="bi bi-clock me-1"></i><strong>Antreten:</strong> <?=e($assemblyBanner['weekday_short'].', '.$assemblyBanner['display_date'])?> · <?=e($assemblyBanner['display_time'])?> Uhr · <strong>Ort:</strong> <?=e($assemblyBanner['location'])?> <i class="bi bi-chevron-right ms-1 assembly-topline-arrow"></i>
</div></a>
<?php endif; ?>
<?php
$stammtischBanner=null;
if($u && !$sektbarBanner){
  $smMeeting=active_meeting($pdo);
  if($smMeeting && $smMeeting['status']==='poll'){
    $q=$pdo->prepare('SELECT EXISTS(SELECT 1 FROM meeting_votes v JOIN meeting_poll_options o ON o.id=v.option_id WHERE o.meeting_id=? AND v.user_id=?) OR EXISTS(SELECT 1 FROM meeting_poll_declines WHERE meeting_id=? AND user_id=?)');
    $q->execute([$smMeeting['id'],(int)$u['id'],$smMeeting['id'],(int)$u['id']]);
    $stammtischBanner=['text'=>$q->fetchColumn()?'Terminfindung läuft – du hast abgestimmt':'Terminfindung läuft – deine Stimme fehlt noch','where'=>''];
  } elseif($smMeeting && $smMeeting['status']==='running'){
    $stammtischBanner=['text'=>'läuft gerade – Protokoll wird geschrieben','where'=>''];
  } elseif($smMeeting && $smMeeting['status']==='scheduled' && $smMeeting['starts_at_utc']){
    $startUtc=new DateTimeImmutable($smMeeting['starts_at_utc'],new DateTimeZone('UTC'));
    if(new DateTimeImmutable('now',new DateTimeZone('UTC'))<$startUtc->modify('+4 hours')){
      $local=$startUtc->setTimezone(new DateTimeZone('Europe/Berlin'));
      $stammtischBanner=['text'=>german_weekday_short($local).', '.$local->format('d.m.Y · H:i').' Uhr','where'=>(string)$smMeeting['location']];
    }
  }
}
?>
<?php if($stammtischBanner): ?>
<a class="stammtisch-topline" href="/stammtisch.php" aria-label="Stammtisch-Details anzeigen"><div class="container">
<strong>🍻 Stammtisch:</strong> <?=e($stammtischBanner['text'])?><?php if($stammtischBanner['where']!==''): ?> · <?=e($stammtischBanner['where'])?><?php endif; ?> <i class="bi bi-chevron-right ms-1 stammtisch-topline-arrow"></i>
</div></a>
<?php endif; ?>

<main>
<?php foreach(flashes() as $f): ?>
  <div class="container mt-3"><div class="alert alert-<?= e($f['type']) ?> alert-dismissible fade show shadow-sm" role="alert"><?= e($f['message']) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div></div>
<?php endforeach; ?>
