<?php
require_once __DIR__ . '/includes/app.php'; $u=require_login(); $pageTitle='Kalender'; require __DIR__.'/includes/header.php';
?>
<div class="container dashboard-shell"><div class="row g-4"><?php require __DIR__.'/includes/sidebar.php'; ?><section class="col-lg-9"><div class="eyebrow mb-2">In Vorbereitung</div><h1 class="section-title h2 mb-4">Kalender</h1><section class="panel placeholder-module p-4 p-md-5 text-center"><div class="placeholder-icon mx-auto mb-3"><i class="bi bi-calendar-event"></i></div><h2 class="h4 fw-bold">Dieses Modul kommt später</h2><p class="text-secondary mb-0">Hier entsteht später der gemeinsame Terminkalender für Ereignisse und Termine des Schützenzugs.</p></section></section></div></div>
<?php require __DIR__.'/includes/footer.php'; ?>