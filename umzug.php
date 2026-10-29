<?php
/*
 * Umzugsseite für den Wechsel auf eine neue Adresse (siehe docs/domain-umzug.md).
 * - Alte Adresse: erklärt den Umzug und bringt angemeldete Mitglieder per Einmal-Link angemeldet auf die neue Adresse.
 * - Neue Adresse: löst den Einmal-Link ein und erklärt die Neuinstallation der App (Installieren, Push, altes Symbol löschen).
 */
require_once __DIR__.'/includes/app.php';
header('Cache-Control: no-store');
header('Referrer-Policy: no-referrer');

$legacy = request_is_legacy_host();
$newBase = app_base_url();
$newName = app_display_host();

if (!$legacy && isset($_GET['t'])) {
    $user = redeem_domain_handoff($pdo, (string)$_GET['t']);
    if ($user) {
        login_user($user);
        create_remember_login($pdo, (int)$user['id']);
        flash('success', 'Willkommen auf der neuen Adresse – du bist angemeldet.');
    } elseif (!current_user()) {
        flash('warning', 'Der Umzugs-Link ist abgelaufen oder wurde schon benutzt. Bitte melde dich einmal mit deinem Passwort an.');
    }
    redirect('/umzug.php');
}

$member = current_user();
$handoffUrl = '';
if ($legacy) {
    $handoffUrl = $member ? create_domain_handoff($pdo, (int)$member['id']) : $newBase . '/login.php';
}

$hideAppNav = $legacy;
$hidePwaInstall = true; // eigener Installieren-Knopf; auf der alten Adresse würde er die alte App installieren
$pageTitle = 'Umzug';
require __DIR__.'/includes/header.php';
$logo = '/assets/brand/strahlemaennkes-logo.png?v='.e(app_version()['cache_key']);
?>
<div class="container login-wrap"><div class="panel p-4 p-md-5 login-card umzug-card" style="max-width:560px">
<img class="login-club-logo mb-4" src="<?=$logo?>" alt="Strahlemännkes Logo">
<?php if ($legacy): ?>
  <div class="eyebrow">Neue Adresse</div>
  <h1 class="h3 fw-bold">Die App ist umgezogen 📦</h1>
  <p>Die Strahlemännkes-App findest du ab jetzt unter<br><strong class="fs-5"><?=e($newName)?></strong></p>
  <p class="text-secondary">Alles bleibt wie gewohnt – Getränke, Stammtisch, Runden. Nur die Adresse ist neu, deshalb muss die App einmal neu aufs Handy.</p>
  <a class="btn btn-brand btn-lg w-100 mb-3" href="<?=e($handoffUrl)?>" data-umzug-go>
    <?php if ($member): ?><i class="bi bi-box-arrow-up-right me-2"></i>Weiter zur neuen Adresse<?php else: ?><i class="bi bi-person-circle me-2"></i>Zur neuen Adresse &amp; anmelden<?php endif; ?>
  </a>
  <?php if ($member): ?><p class="small text-secondary mb-3">Hallo <?=e($member['first_name'])?>, du wirst dort automatisch angemeldet. Der Knopf funktioniert 30 Minuten lang; danach diese Seite einfach neu laden.</p><?php endif; ?>
  <div class="alert alert-secondary small mb-0" data-umzug-standalone hidden>
    Du bist gerade in der <strong>alten App</strong>. Der Knopf öffnet die neue Adresse im Browser. Dort installierst du die neue App – danach kannst du dieses alte App-Symbol löschen.
  </div>
<?php else: ?>
  <div class="eyebrow">Umzug · Neue Adresse <?=e($newName)?></div>
  <h1 class="h3 fw-bold">Fast geschafft! 🎉</h1>
  <?php if (!$member): ?>
    <p>Bitte melde dich einmal mit deinem gewohnten Benutzernamen und Passwort an.</p>
    <a class="btn btn-brand btn-lg w-100 mb-4" href="/login.php"><i class="bi bi-person-circle me-2"></i>Anmelden</a>
  <?php endif; ?>
  <ol class="umzug-steps ps-3">
    <li class="mb-3"><strong>Neue App installieren</strong>
      <div data-umzug-installed hidden class="text-success small"><i class="bi bi-check-circle-fill me-1"></i>Erledigt – du nutzt schon die installierte App.</div>
      <div data-umzug-install>
        <div class="small text-secondary mb-2" data-umzug-ios hidden><strong>iPhone:</strong> Diese Seite in <strong>Safari</strong> öffnen (nicht in WhatsApp), unten auf <i class="bi bi-box-arrow-up"></i> <em>Teilen</em> tippen und <em>Zum Home-Bildschirm</em> wählen.</div>
        <div class="small text-secondary mb-2" data-umzug-android hidden><strong>Android:</strong> Auf den Knopf tippen. Klappt das nicht: im Chrome-Menü <i class="bi bi-three-dots-vertical"></i> <em>App installieren</em> bzw. <em>Zum Startbildschirm hinzufügen</em>.</div>
        <button type="button" class="btn btn-brand" data-umzug-install-btn><i class="bi bi-download me-1"></i>App installieren</button>
      </div>
    </li>
    <li class="mb-3"><strong>Neue App öffnen und Push einschalten</strong>
      <div class="small text-secondary">In der neuen App unter <a href="/settings.php">Einstellungen</a> die Mitteilungen erlauben. Auf dem iPhone geht das nur in der installierten App, nicht im Browser.</div>
    </li>
    <li><strong>Altes App-Symbol löschen</strong>
      <div class="small text-secondary">Das alte Symbol erkennst du daran, dass es nur noch „Die App ist umgezogen“ zeigt. Lange drauf tippen → <em>App entfernen</em> bzw. <em>Deinstallieren</em>.</div>
    </li>
  </ol>
  <?php if ($member): ?><a class="btn btn-soft w-100 mt-2" href="/dashboard.php">Zur Übersicht</a><?php endif; ?>
<?php endif; ?>
</div></div>
<script>
document.addEventListener('DOMContentLoaded',()=>{
  const SMx=window.SM||{};
  const standalone=!!SMx.isStandalone;
  const go=document.querySelector('[data-umzug-go]');
  // In der alten, installierten App die neue Adresse im echten Browser öffnen: nur dort lässt sie sich installieren.
  if(go&&standalone){ go.target='_blank'; go.rel='noopener'; document.querySelector('[data-umzug-standalone]').hidden=false; }
  const box=document.querySelector('[data-umzug-install]');
  if(box){
    if(standalone){ box.hidden=true; document.querySelector('[data-umzug-installed]').hidden=false; return; }
    document.querySelector(SMx.isIOS?'[data-umzug-ios]':'[data-umzug-android]').hidden=false;
    const btn=document.querySelector('[data-umzug-install-btn]');
    if(SMx.isIOS) btn.hidden=true;
    btn.addEventListener('click',async()=>{
      if(SMx.installPwa){ const r=await SMx.installPwa(); if(r&&r.installed){ box.hidden=true; document.querySelector('[data-umzug-installed]').hidden=false; } }
    });
  }
});
</script>
<?php require __DIR__.'/includes/footer.php'; ?>
