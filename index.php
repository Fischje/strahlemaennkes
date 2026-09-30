<?php
$pageTitle = 'Startseite';
$bodyClass = 'home-page';
require __DIR__ . '/includes/header.php';
?>
<section class="home-minimal">
  <div class="container">
    <div class="home-minimal-card mx-auto text-center">
      <img class="home-club-logo mb-4" src="/assets/brand/strahlemaennkes-logo.png?v=<?= e(app_version()['cache_key']) ?>" alt="Logo Schützenzug Strahlemännkes">
      <h1 class="display-4 fw-black mb-4">Schützenzug Strahlemännkes</h1>

      <div class="d-grid gap-3 mx-auto" style="max-width:420px">
        <a class="btn btn-brand btn-lg" href="/login.php">
          <i class="bi bi-person-circle me-2"></i> Login interner Bereich
        </a>
        <a class="btn btn-soft btn-lg" href="/public/chronik.php">
          <i class="bi bi-book me-2"></i> Chronik
        </a>
      </div>
    </div>
  </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
