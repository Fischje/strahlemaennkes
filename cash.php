<?php
require_once __DIR__.'/includes/app.php';
$u=require_login();
$canEdit=can_edit_cash();

if($_SERVER['REQUEST_METHOD']==='POST'){
    if(!$canEdit){ http_response_code(403); exit('Keine Berechtigung für die Kassenverwaltung.'); }
    verify_csrf();
    $action=$_POST['action']??'';

    if($action==='balance'){
        $raw=str_replace(',','.',trim($_POST['amount']??''));
        $date=trim($_POST['balance_date']??'');
        $note=trim($_POST['note']??'');
        $validDate=DateTime::createFromFormat('Y-m-d',$date);
        if(!is_numeric($raw) || !$validDate || $validDate->format('Y-m-d')!==$date){
            flash('danger','Bitte einen gültigen Kassenstand und ein Datum angeben.');
            redirect('/cash.php');
        }
        $pdo->prepare("INSERT INTO cash_balance_history(amount,balance_date,note,updated_by) VALUES(?,?,?,?)")
            ->execute([(float)$raw,$date,$note!==''?$note:null,$u['id']]);
        flash('success','Kassenstand wurde aktualisiert.');
        redirect('/cash.php');
    }

    if($action==='fee'){
        $raw=str_replace(',','.',trim($_POST['monthly_fee']??''));
        if(!is_numeric($raw) || (float)$raw<0){
            flash('danger','Bitte einen gültigen Monatsbeitrag eingeben.');
            redirect('/cash.php');
        }
        $pdo->prepare("UPDATE cash_settings SET monthly_fee=?,updated_at=CURRENT_TIMESTAMP,updated_by=? WHERE id=1")
            ->execute([(float)$raw,$u['id']]);
        flash('success','Monatlicher Mitgliedsbeitrag wurde aktualisiert.');
        redirect('/cash.php');
    }
}

$current=$pdo->query("
    SELECT c.*,u.first_name,u.last_name
    FROM cash_balance_history c
    JOIN users u ON u.id=c.updated_by
    ORDER BY c.balance_date DESC,c.id DESC LIMIT 1
")->fetch();

$history=$pdo->query("
    SELECT c.*,u.first_name,u.last_name
    FROM cash_balance_history c
    JOIN users u ON u.id=c.updated_by
    ORDER BY c.balance_date DESC,c.id DESC LIMIT 12
")->fetchAll();

$settings=$pdo->query("
    SELECT s.*,u.first_name,u.last_name
    FROM cash_settings s
    LEFT JOIN users u ON u.id=s.updated_by
    WHERE s.id=1
")->fetch() ?: ['monthly_fee'=>0,'updated_at'=>null,'first_name'=>null,'last_name'=>null];

$pageTitle='Kasse';
require __DIR__.'/includes/header.php';
?>
<div class="container dashboard-shell"><div class="row g-4">
<?php require __DIR__.'/includes/sidebar.php';?>
<section class="col-lg-9">
  <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
    <div>
      <div class="eyebrow">Zugkasse</div>
      <h1 class="section-title h2 mb-1">Kasse</h1>
      <p class="text-secondary mb-0">Aktueller Kassenstand und Mitgliedsbeitrag.</p>
    </div>
    <?php if($canEdit): ?><span class="badge text-bg-success fs-6"><i class="bi bi-key me-1"></i>Kassenrecht</span><?php endif;?>
  </div>

  <div class="row g-3 mb-4">
    <div class="col-md-7">
      <div class="panel cash-highlight p-4 h-100">
        <div class="small text-secondary">Aktueller Kassenstand</div>
        <?php if($current): ?>
          <div class="cash-amount my-2"><?=number_format((float)$current['amount'],2,',','.')?> €</div>
          <div class="text-secondary">Stand vom <strong class="text-light"><?=e(date('d.m.Y',strtotime($current['balance_date'])))?></strong></div>
          <div class="small text-secondary mt-2">Eingetragen von <?=e($current['first_name'].' '.$current['last_name'])?></div>
          <?php if(!empty($current['note'])):?><div class="cash-note mt-3"><?=e($current['note'])?></div><?php endif;?>
        <?php else: ?>
          <div class="cash-amount my-2">–</div>
          <div class="text-secondary">Noch kein Kassenstand hinterlegt.</div>
        <?php endif;?>
        <?php if($canEdit): ?><button class="btn btn-brand mt-4" data-bs-toggle="modal" data-bs-target="#balanceModal"><i class="bi bi-pencil me-1"></i>Kassenstand eintragen</button><?php endif;?>
      </div>
    </div>

    <div class="col-md-5">
      <div class="panel p-4 h-100">
        <div class="small text-secondary">Monatlicher Mitgliedsbeitrag</div>
        <div class="cash-fee my-2"><?=number_format((float)$settings['monthly_fee'],2,',','.')?> €</div>
        <div class="small text-secondary">pro Mitglied / Monat</div>
        <?php if(!empty($settings['updated_at'])):?><div class="small text-secondary mt-3">Zuletzt geändert am <?=e(date('d.m.Y',strtotime($settings['updated_at'])))?><?php if($settings['first_name']):?> von <?=e($settings['first_name'].' '.$settings['last_name'])?><?php endif;?></div><?php endif;?>
        <?php if($canEdit): ?><button class="btn btn-outline-secondary mt-4" data-bs-toggle="modal" data-bs-target="#feeModal"><i class="bi bi-pencil me-1"></i>Beitrag ändern</button><?php endif;?>
      </div>
    </div>
  </div>

  <div class="panel p-4">
    <div class="d-flex justify-content-between align-items-center mb-3"><div><h2 class="h5 fw-bold mb-1">Kassenverlauf</h2><div class="small text-secondary">Die letzten hinterlegten Kassenstände</div></div></div>
    <?php if($history): ?>
      <div class="table-responsive"><table class="table align-middle mb-0">
        <thead><tr><th>Stichtag</th><th>Kassenstand</th><th>Notiz</th><th>Eingetragen von</th></tr></thead>
        <tbody><?php foreach($history as $h):?><tr>
          <td><?=e(date('d.m.Y',strtotime($h['balance_date'])))?></td>
          <td><strong><?=number_format((float)$h['amount'],2,',','.')?> €</strong></td>
          <td><?=e($h['note']?:'–')?></td>
          <td><?=e($h['first_name'].' '.$h['last_name'])?></td>
        </tr><?php endforeach;?></tbody>
      </table></div>
    <?php else: ?><div class="text-secondary py-3">Noch kein Kassenverlauf vorhanden.</div><?php endif;?>
  </div>
</section></div></div>

<?php if($canEdit): ?>
<div class="modal fade" id="balanceModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><form method="post" class="modal-content">
  <div class="modal-header"><h2 class="modal-title fs-5">Kassenstand eintragen</h2><button class="btn-close" type="button" data-bs-dismiss="modal"></button></div>
  <div class="modal-body">
    <input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="balance">
    <div class="mb-3"><label class="form-label">Kontostand in €</label><input class="form-control form-control-lg" name="amount" inputmode="decimal" value="<?=$current?e(number_format((float)$current['amount'],2,',','')):''?>" required></div>
    <div class="mb-3"><label class="form-label">Stand vom</label><input type="date" class="form-control" name="balance_date" value="<?=e(date('Y-m-d'))?>" required></div>
    <div><label class="form-label">Notiz <span class="text-secondary">(optional)</span></label><input class="form-control" name="note" maxlength="160" placeholder="z. B. nach Kirmeswochenende"></div>
  </div>
  <div class="modal-footer"><button class="btn btn-brand">Kassenstand speichern</button></div>
</form></div></div>

<div class="modal fade" id="feeModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><form method="post" class="modal-content">
  <div class="modal-header"><h2 class="modal-title fs-5">Mitgliedsbeitrag ändern</h2><button class="btn-close" type="button" data-bs-dismiss="modal"></button></div>
  <div class="modal-body">
    <input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="fee">
    <label class="form-label">Monatlicher Beitrag in €</label><input class="form-control form-control-lg" name="monthly_fee" inputmode="decimal" value="<?=e(number_format((float)$settings['monthly_fee'],2,',',''))?>" required>
  </div>
  <div class="modal-footer"><button class="btn btn-brand">Beitrag speichern</button></div>
</form></div></div>
<?php endif;?>
<?php require __DIR__.'/includes/footer.php';?>
