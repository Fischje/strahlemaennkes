<?php
require_once __DIR__ . '/../includes/app.php';
$years=$pdo->query("SELECT * FROM chronicle_years WHERE published=1 ORDER BY year DESC")->fetchAll();
$pageTitle='Chronik';
$publicPage=true;
$bodyClass='public-page chronik-page';
require __DIR__.'/../includes/header.php';
?>
<section class="public-hero">
  <div class="container">
    <div class="eyebrow mb-2">Öffentlich</div>
    <h1 class="section-title display-4 mb-3">Chronik der Strahlemännkes</h1>
    <p class="lead mb-0">Ämter und besondere Würdenträger unseres Zuges – Jahr für Jahr dokumentiert.</p>
  </div>
</section>
<section class="py-5">
  <div class="container">
    <?php if(!$years): ?>
      <div class="panel p-4 p-md-5 text-center">
        <div class="fs-1 mb-3">📖</div>
        <h2 class="h4 fw-bold">Die Chronik wird gerade aufgebaut</h2>
        <p class="text-secondary mb-0">Noch sind keine Jahrgänge veröffentlicht.</p>
      </div>
    <?php else: ?>
      <div class="panel p-4 p-md-5 chronik-list">
        <?php foreach($years as $i=>$y): ?>
          <div class="timeline-row <?=$i<count($years)-1?'pb-5':''?>">
            <div class="chronik-year"><?=e((string)$y['year'])?></div>
            <h2 class="h5 mt-2">Zugjahr <?=e((string)$y['year'])?></h2>
            <div class="row row-cols-1 row-cols-sm-2 g-3 mt-2">
              <?php foreach([
                'Zugkönig'=>$y['zugkoenig'],
                'Zugführer'=>$y['zugfuehrer'],
                'Zugspieß'=>$y['zugspiess'],
                'Kassierer'=>$y['kassierer'],
                'Schriftführer'=>$y['schriftfuehrer'],
                '1. Offizier'=>$y['erster_offizier'],
                '2. Offizier'=>$y['zweiter_offizier'],
              ] as $label=>$value): ?>
                <?php if(trim((string)$value)!==''): ?>
                  <div class="col"><span class="text-secondary"><?=e($label)?></span><br><strong><?=e($value)?></strong></div>
                <?php endif;?>
              <?php endforeach;?>
            </div>
            <?php if(trim((string)$y['notes'])!==''): ?>
              <div class="chronik-notes mt-4"><?=nl2br(e($y['notes']))?></div>
            <?php endif;?>
          </div>
        <?php endforeach;?>
      </div>
    <?php endif;?>
  </div>
</section>
<?php require __DIR__.'/../includes/footer.php';?>
