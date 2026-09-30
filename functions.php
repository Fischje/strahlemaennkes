<?php
require_once __DIR__.'/includes/app.php';
$u=require_login();

$items=[];
if(can_create_meetings($pdo,$u)) $items[]=['/stammtisch.php','bi-calendar2-plus','Terminfindung starten','Neuen Stammtisch mit Terminoptionen anlegen.'];
if(can_act_as_spiess($pdo,$u)) $items[]=['/drinks.php','bi-sliders','Getränke verwalten','Getränke bearbeiten, freigeben und für die Sektbar kennzeichnen.'];
if(has_role('admin') || sektbar_is_enabled($pdo)) {
    $sektText=sektbar_is_active($pdo) ? 'Sektbarmodus ist aktuell aktiv.' : (sektbar_is_enabled($pdo) ? 'Sektbar öffnen und Status ansehen.' : 'Sektbar ist noch nicht freigeschaltet.');
    $items[]=['/sektbar.php','bi-stars','Sektbar',$sektText];
}
if(can_manage_spiess_delegation()) $items[]=['/spiess-vertretung.php','bi-person-badge','Spieß-Vertretung','Vertretung einsetzen oder wieder beenden.'];
if(can_manage_assembly()) $items[]=['/antreten.php','bi-clock','Antreten festlegen','Nächsten Termin, Treffpunkt und Kleidung festlegen.'];
if(can_edit_chronicle()) $items[]=['/chronik-admin.php','bi-pencil-square','Chronik verwalten','Einträge der Zug-Chronik bearbeiten und veröffentlichen.'];
if(has_role('admin')) $items[]=['/admin-features.php','bi-toggles','Freischaltungen','Registrierung und optionale Funktionen zentral freischalten.'];

$pageTitle='Funktionen';
require __DIR__.'/includes/header.php';
?>
<div class="container dashboard-shell"><div class="row g-4">
<?php require __DIR__.'/includes/sidebar.php';?>
<section class="col-lg-9">
  <div class="eyebrow">Werkzeuge</div>
  <h1 class="section-title h2 mb-1">Funktionen</h1>
  <p class="text-secondary mb-4">Hier findest du die zusätzlichen Funktionen, für die du berechtigt bist.</p>
  <?php if(!$items):?>
    <div class="panel p-4"><p class="mb-0 text-secondary">Für dein Konto sind derzeit keine zusätzlichen Funktionen verfügbar.</p></div>
  <?php else:?>
    <div class="row g-3">
      <?php foreach($items as [$href,$icon,$title,$text]):?>
      <div class="col-md-6">
        <a class="function-card panel p-3 h-100 d-flex gap-3 align-items-start text-decoration-none" href="<?=e($href)?>">
          <span class="function-card-icon"><i class="bi <?=e($icon)?>"></i></span>
          <span><strong class="d-block mb-1"><?=e($title)?></strong><span class="small text-secondary"><?=e($text)?></span></span>
        </a>
      </div>
      <?php endforeach;?>
    </div>
  <?php endif;?>
</section>
</div></div>
<?php require __DIR__.'/includes/footer.php';?>
