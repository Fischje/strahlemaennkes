<?php
$pageTitle = 'Startseite';
require __DIR__ . '/includes/header.php';
?>
<section class="hero">
  <div class="container">
    <div class="row align-items-center g-5">
      <div class="col-lg-8">
        <div class="eyebrow mb-3">Schützenzug · Bettrath</div>
        <h1>Organisation für die <span style="color:var(--sm-red)">Strahlemännkes</span> – ohne Zettelwirtschaft.</h1>
        <p class="lead mt-4">Strafen, Getränkewünsche und Zugorganisation an einem Ort. Schnell genug für den Kirmeszug, ordentlich genug für den Spieß.</p>
        <div class="d-flex flex-wrap gap-2 mt-4">
          <a class="btn btn-brand btn-lg px-4" href="/login.php">Zum internen Bereich</a>
          <a class="btn btn-soft btn-lg px-4" href="/public/chronik.php">Öffentliche Chronik</a>
        </div>
      </div>
      <div class="col-lg-4">
        <div class="panel p-4">
          <div class="d-flex align-items-center gap-3 mb-4"><div class="icon-tile green"><i class="bi bi-cup-straw"></i></div><div><strong>Nächste Runde</strong><div class="text-secondary small">Live-Wünsche einsammeln</div></div></div>
          <div class="drink-chip d-flex justify-content-between mb-2"><span>Alt</span><strong>6×</strong></div>
          <div class="drink-chip d-flex justify-content-between mb-2"><span>Pils</span><strong>4×</strong></div>
          <div class="drink-chip d-flex justify-content-between"><span>Wasser</span><strong>2×</strong></div>
          <div class="small text-secondary mt-3"><i class="bi bi-lightning-charge-fill text-warning"></i> Beispielansicht für den Spieß</div>
        </div>
      </div>
    </div>
  </div>
</section>

<section class="py-5">
  <div class="container">
    <div class="mb-4"><div class="eyebrow">Grundmodule</div><h2 class="section-title">Für den ersten Ausbau vorgesehen</h2></div>
    <div class="row g-3">
      <div class="col-md-6 col-xl-3"><div class="feature-card"><div class="icon-tile mb-3"><i class="bi bi-receipt"></i></div><h5>Strafen</h5><p class="text-secondary mb-0">Der Spieß erfasst Strafen, Beträge, Gründe und Status. Mitglieder sehen ihre eigenen Einträge.</p></div></div>
      <div class="col-md-6 col-xl-3"><div class="feature-card"><div class="icon-tile green mb-3"><i class="bi bi-cup-straw"></i></div><h5>Getränkewünsche</h5><p class="text-secondary mb-0">Jedes Mitglied wählt sein Getränk. Der Spieß erhält beim Thekengang eine kompakte Bestellliste.</p></div></div>
      <div class="col-md-6 col-xl-3"><div class="feature-card"><div class="icon-tile dark mb-3"><i class="bi bi-people"></i></div><h5>Mitglieder & Rollen</h5><p class="text-secondary mb-0">Eigene Accounts mit abgestuften Rechten für Mitglied, Spieß und Administrator.</p></div></div>
      <div class="col-md-6 col-xl-3"><div class="feature-card"><div class="icon-tile mb-3"><i class="bi bi-book"></i></div><h5>Chronik</h5><p class="text-secondary mb-0">Öffentliche Jahreschronik mit Zugkönig, Zugführer, Kassierer, Schriftführer und Offizieren.</p></div></div>
    </div>
  </div>
</section>

<section class="py-5 border-top">
  <div class="container">
    <div class="row g-4 align-items-center">
      <div class="col-lg-5"><div class="eyebrow">Später</div><h2 class="section-title">Schon im Layout mitgedacht</h2><p class="text-secondary">Terminkalender, Ereignisse und Stammtisch-Planung können später als eigene Module ergänzt werden, ohne die Hauptnavigation neu erfinden zu müssen.</p></div>
      <div class="col-lg-7"><div class="panel p-4"><div class="row g-3"><div class="col-sm-6"><div class="p-3 rounded-4 bg-light"><i class="bi bi-calendar-event me-2"></i><strong>Kalender</strong><div class="small text-secondary mt-1">Zugtermine & Ereignisse</div></div></div><div class="col-sm-6"><div class="p-3 rounded-4 bg-light"><i class="bi bi-calendar2-check me-2"></i><strong>Stammtisch</strong><div class="small text-secondary mt-1">Terminfindung & Zusagen</div></div></div></div></div></div>
    </div>
  </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
