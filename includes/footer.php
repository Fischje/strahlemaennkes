<?php $footerUser = empty($hideAppNav) ? current_user() : null; $footerVersion = app_version(); $footerHome = str_contains($bodyClass ?? '', 'home-page'); ?>
</main>
<?php if($footerUser): ?>
<nav class="mobile-bottom-nav d-lg-none" aria-label="App-Navigation">
<?php
  // Feste Schnellwahl am Handy/in der App; alles Weitere steht im Menü oben.
  $bottomPath=parse_url($_SERVER['REQUEST_URI'] ?? '/',PHP_URL_PATH) ?: '/';
  foreach([['/dashboard.php','bi-house-door','Übersicht'],['/member-drink.php','bi-cup-straw','Mein Getränk'],['/theke.php','bi-list-check','Theke'],['/calendar.php','bi-calendar-event','Kalender']] as [$href,$icon,$label]):
    $here=$bottomPath===$href; ?>
  <a href="<?= $href ?>" class="<?= $here ? 'active' : '' ?>" <?= $here ? 'aria-current="page"' : '' ?>><i class="bi <?= $icon ?>"></i><span><?= $label ?></span></a>
<?php endforeach; ?>
</nav>
<?php endif; ?>
<?php if(empty($hidePwaInstall)): ?><button type="button" class="btn btn-brand pwa-install-button" data-pwa-install hidden aria-label="Strahlemännkes als App installieren"><i class="bi bi-download me-1"></i> App installieren</button><?php endif; ?>
<footer class="app-footer py-3 mt-5"><div class="container small d-flex flex-wrap gap-2 <?= $footerHome ? 'justify-content-center' : 'justify-content-between' ?> align-items-center"><?php if(!$footerHome): ?><span>© <?= date('Y') ?> Schützenzug „Strahlemännkes“</span><?php endif; ?><button type="button" class="version-note btn btn-link p-0 border-0 text-decoration-none" data-bs-toggle="modal" data-bs-target="#changelogModal" aria-label="Changelog anzeigen"><?= e($footerVersion['label']) ?> <small>· <?= e($footerVersion['date']) ?></small></button></div></footer>
<div class="modal fade" id="changelogModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-dialog-scrollable"><div class="modal-content"><div class="modal-header"><h2 class="h5 mb-0">Changelog</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Schließen"></button></div><div class="modal-body"><?php foreach(app_changelog() as $entry): if(!$entry['items']) continue; ?><h3 class="h6 mt-3"><?= e($entry['title']) ?></h3><ul class="small ps-3"><?php foreach($entry['items'] as $item): ?><li><?= e($item) ?></li><?php endforeach; ?></ul><?php endforeach; ?></div><div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Schließen</button></div></div></div></div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
<script>window.SM_APP_VERSION=<?= json_encode($footerVersion['cache_key']) ?>;</script>
<script src="/assets/js/app.js?v=<?= e($footerVersion['cache_key']) ?>"></script>
</body></html>
