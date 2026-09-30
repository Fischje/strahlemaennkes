<?php
$sideUser = current_user();
$sidePath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
function side_active(string $path, string $current): string { return $path === $current ? 'active' : ''; }
?>
<aside class="col-lg-3">
  <div class="side-nav sticky-lg-top" style="top:92px">
    <div class="side-user px-2 py-2 mb-2">
      <div class="small text-secondary">Angemeldet als</div>
      <strong><?= e(trim(($sideUser['first_name'] ?? '').' '.($sideUser['last_name'] ?? ''))) ?></strong>
      <div class="mt-1 d-flex flex-wrap gap-1">
        <span class="badge badge-role"><?= e(role_label($sideUser['role'] ?? 'member')) ?></span>
        <?php if(is_treasurer()): ?><span class="badge text-bg-success">Kassierer</span><?php endif; ?>
        <?php if(is_leader()): ?><span class="badge text-bg-info">Zugführer</span><?php endif; ?>
        <?php if(is_secretary()): ?><span class="badge text-bg-warning">Schriftführer</span><?php endif; ?>
        <?php if(is_spiess_delegate($pdo,$sideUser)): ?><span class="badge text-bg-danger">Spieß-Vertreter</span><?php endif; ?>
      </div>
    </div>

    <a class="<?= side_active('/dashboard.php',$sidePath) ?>" href="/dashboard.php"><i class="bi bi-grid"></i> Übersicht</a>
    <a class="<?= side_active('/stammtisch.php',$sidePath) ?>" href="/stammtisch.php"><i class="bi bi-calendar2-check"></i> Stammtisch</a>
    <a class="<?= side_active('/protokolle.php',$sidePath) ?>" href="/protokolle.php"><i class="bi bi-journal-text"></i> Protokolle</a>
    <a class="<?= side_active('/member-drink.php',$sidePath) ?>" href="/member-drink.php"><i class="bi bi-cup-straw"></i> Mein Getränk</a>

    <a class="<?= side_active('/theke.php',$sidePath) ?>" href="/theke.php"><i class="bi bi-list-check"></i> Thekenansicht</a>
    <a class="<?= side_active('/fines.php',$sidePath) ?>" href="/fines.php"><i class="bi bi-receipt"></i> Runden</a>
    <a class="<?= side_active('/members.php',$sidePath) ?>" href="/members.php"><i class="bi bi-people"></i> Mitglieder</a>
    <a class="<?= side_active('/cash.php',$sidePath) ?>" href="/cash.php"><i class="bi bi-cash-coin"></i> Kasse</a>
    <a class="<?= side_active('/antreten.php',$sidePath) ?>" href="/antreten.php"><i class="bi bi-clock"></i> Antreten</a>
    <a class="<?= side_active('/public/chronik.php',$sidePath) ?>" href="/public/chronik.php"><i class="bi bi-book"></i> Chronik</a>
    <?php
      $showSideFunctions = can_act_as_spiess($pdo,$sideUser) || can_manage_spiess_delegation()
        || can_manage_assembly() || can_edit_chronicle() || has_role('admin') || sektbar_is_enabled($pdo);
    ?>
    <?php if($showSideFunctions): ?>
      <hr class="border-secondary my-2">
      <a class="<?= side_active('/functions.php',$sidePath) ?>" href="/functions.php"><i class="bi bi-grid-3x3-gap"></i> Funktionen</a>
    <?php endif; ?>

    <hr class="border-secondary">
    <a class="<?= side_active('/calendar.php',$sidePath) ?>" href="/calendar.php"><i class="bi bi-calendar-event"></i> Kalender <span class="badge text-bg-secondary ms-auto">später</span></a>

    <hr class="border-secondary">
    <a href="/logout.php"><i class="bi bi-box-arrow-right"></i> Abmelden</a>
  </div>
</aside>
