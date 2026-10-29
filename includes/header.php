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
<?php if(isset($pdo)&&($patternUrl=site_image_url($pdo,'pattern'))): ?>  <style>:root{--sm-bg-image:url('<?= $patternUrl ?>')}</style>
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
          <?php
            // Hauptmenü in Gruppen. 'href' = Gruppe ist selbst ein Link (Klick öffnet die Seite, Untermenü per Maus);
            // ohne 'href' öffnet ein Klick das Untermenü. Am Handy klappt jede Gruppe per Tipp auf.
            $navGroups=[
              ['label'=>'Termine','icon'=>'bi-calendar-event','items'=>[
                ['/calendar.php','bi-calendar-event','Kalender'],
                ['/stammtisch.php','bi-calendar2-check','Stammtisch'],
                ['/antreten.php','bi-clock','Antreten'],
              ]],
              ['label'=>'Getränke','icon'=>'bi-cup-straw','href'=>'/member-drink.php','items'=>[
                ['/member-drink.php','bi-cup-straw','Mein Getränk'],
                ['/theke.php','bi-list-check','Theke'],
                ['/fines.php','bi-receipt','Runden'],
              ]],
              ['label'=>'Info','icon'=>'bi-info-circle','items'=>[
                ['/public/chronik.php','bi-book','Chronik'],
                ['/protokolle.php','bi-journal-text','Protokolle'],
                ['/members.php','bi-people','Mitglieder'],
                ['/cash.php','bi-cash-coin','Kasse'],
              ]],
            ];
            $functionItems=function_menu_items($pdo,$u);
            if($functionItems){
              $navGroups[]=['label'=>'Funktionen','icon'=>'bi-grid-3x3-gap','href'=>'/functions.php','items'=>array_merge(
                [['/functions.php','bi-grid-3x3-gap','Alle Funktionen']],
                array_map(fn($f)=>[$f[0],$f[1],$f[2]],$functionItems)
              )];
            }
          ?>
          <li class="nav-item"><a class="nav-link <?= $currentPath==='/dashboard.php'?'active':'' ?>" href="/dashboard.php" <?= $currentPath==='/dashboard.php'?'aria-current="page"':'' ?>>Übersicht</a></li>
          <?php foreach($navGroups as $gi=>$g):
            $groupActive=in_array($currentPath,array_column($g['items'],0),true); ?>
            <li class="nav-item dropdown nav-group">
              <?php if(!empty($g['href'])): ?>
                <a class="nav-link nav-group-link d-none d-lg-flex <?= $groupActive?'active':'' ?>" href="<?= e($g['href']) ?>" aria-haspopup="true"><?= e($g['label']) ?><i class="bi bi-chevron-down nav-group-caret"></i></a>
              <?php endif; ?>
              <button type="button" class="nav-link nav-group-toggle <?= !empty($g['href'])?'d-lg-none':'d-flex' ?> <?= $groupActive?'active':'' ?>" data-bs-toggle="dropdown" aria-expanded="false"><?= e($g['label']) ?><i class="bi bi-chevron-down nav-group-caret"></i></button>
              <ul class="dropdown-menu nav-group-menu">
                <?php foreach($g['items'] as [$href,$icon,$label]): $here=$currentPath===$href; ?>
                  <li><a class="dropdown-item <?= $here?'active':'' ?>" href="<?= e($href) ?>" <?= $here?'aria-current="page"':'' ?>><i class="bi <?= e($icon) ?> me-2"></i><?= e($label) ?></a></li>
                <?php endforeach; ?>
              </ul>
            </li>
          <?php endforeach; ?>
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
<?php
// Hinweisleisten nur im internen Bereich (Seiten mit require_login), nie auf Startseite, Login oder öffentlichen Seiten.
$bannerUser=($u && !empty($GLOBALS['smInternalPage'])) ? $u : null;
$sektbarBanner=$bannerUser ? (sektbar_is_enabled($pdo) && sektbar_is_active($pdo)) : false; $assemblyBanner=($bannerUser && !$sektbarBanner) ? active_assembly($pdo) : null; ?>
<?php if($sektbarBanner): ?>
<a class="sektbar-topline" href="<?=can_use_sektbar($pdo,$u)?'/sektbar.php':'/theke.php'?>"><div class="container"><strong>🥂 ✨ SEKTbarmodus aktiviert ✨ 🥂</strong></div></a>
<?php elseif($assemblyBanner): ?>
<a class="assembly-topline" href="/antreten.php" aria-label="Antreten-Details anzeigen"><div class="container">
<i class="bi bi-clock me-1"></i><strong>Antreten:</strong> <?=e($assemblyBanner['weekday_short'].', '.$assemblyBanner['display_date'])?> · <?=e($assemblyBanner['display_time'])?> Uhr · <strong>Ort:</strong> <?=e($assemblyBanner['location'])?> <i class="bi bi-chevron-right ms-1 assembly-topline-arrow"></i>
</div></a>
<?php endif; ?>
<?php
$stammtischBanner=null;
if($bannerUser && !$sektbarBanner){
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
