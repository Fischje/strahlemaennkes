<?php
require_once __DIR__.'/includes/app.php';
$u=require_login();

$items=function_menu_items($pdo,$u);

$pageTitle='Funktionen';
require __DIR__.'/includes/header.php';
?>
<div class="container dashboard-shell"><div class="row g-4">

<section class="col-12">
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
