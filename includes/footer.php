<?php $footerUser = current_user(); $footerVersion = app_version(); $footerHome = str_contains($bodyClass ?? '', 'home-page'); ?>
</main>
<?php if($footerUser): ?>
<nav class="mobile-bottom-nav d-lg-none" aria-label="App-Navigation">
  <a href="/dashboard.php" class="<?= str_contains($_SERVER['REQUEST_URI'] ?? '', 'dashboard.php') ? 'active' : '' ?>"><i class="bi bi-house-door"></i><span>Start</span></a>
  <a href="/stammtisch.php" class="<?= str_contains($_SERVER['REQUEST_URI'] ?? '', 'stammtisch.php') ? 'active' : '' ?>"><i class="bi bi-calendar2-check"></i><span>Stammtisch</span></a>
  <a href="/member-drink.php" class="<?= str_contains($_SERVER['REQUEST_URI'] ?? '', 'member-drink.php') ? 'active' : '' ?>"><i class="bi bi-cup-straw"></i><span>Getränk</span></a>
  <a href="/fines.php" class="<?= str_contains($_SERVER['REQUEST_URI'] ?? '', 'fines.php') ? 'active' : '' ?>"><i class="bi bi-receipt"></i><span>Runden</span></a>
  <a href="/theke.php" class="<?= str_contains($_SERVER['REQUEST_URI'] ?? '', 'theke.php') ? 'active' : '' ?>"><i class="bi bi-list-check"></i><span>Theke</span></a>
</nav>
<?php endif; ?>
<button type="button" class="btn btn-brand pwa-install-button" data-pwa-install hidden aria-label="Strahlemännkes als App installieren"><i class="bi bi-download me-1"></i> App installieren</button>
<footer class="app-footer py-3 mt-5"><div class="container small d-flex flex-wrap gap-2 <?= $footerHome ? 'justify-content-center' : 'justify-content-between' ?> align-items-center"><?php if(!$footerHome): ?><span>© <?= date('Y') ?> Schützenzug „Strahlemännkes“</span><?php endif; ?><button type="button" class="version-note btn btn-link p-0 border-0 text-decoration-none" data-bs-toggle="modal" data-bs-target="#changelogModal" aria-label="Changelog anzeigen"><?= e($footerVersion['label']) ?> <small>· <?= e($footerVersion['date']) ?></small></button></div></footer>
<div class="modal fade" id="changelogModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-dialog-scrollable"><div class="modal-content"><div class="modal-header"><h2 class="h5 mb-0">Changelog</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Schließen"></button></div><div class="modal-body"><?php foreach(app_changelog() as $entry): if(!$entry['items']) continue; ?><h3 class="h6 mt-3"><?= e($entry['title']) ?></h3><ul class="small ps-3"><?php foreach($entry['items'] as $item): ?><li><?= e($item) ?></li><?php endforeach; ?></ul><?php endforeach; ?></div></div></div></div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
<script>window.SM_APP_VERSION=<?= json_encode($footerVersion['cache_key']) ?>;</script>
<script src="/assets/js/app.js?v=<?= e($footerVersion['cache_key']) ?>"></script>
</body></html>
