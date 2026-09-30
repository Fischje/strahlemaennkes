<?php
require_once __DIR__.'/includes/app.php';
$u=require_login();
if(!can_manage_spiess_delegation()){
    http_response_code(403);
    exit('Nur Spieß oder Administrator dürfen eine Spieß-Vertretung festlegen.');
}

if($_SERVER['REQUEST_METHOD']==='POST'){
    verify_csrf();
    $action=$_POST['action']??'';

    if($action==='assign'){
        $representativeId=(int)($_POST['representative_user_id']??0);

        $st=$pdo->prepare("
            SELECT id,first_name,last_name,role
            FROM users
            WHERE id=? AND is_active=1
        ");
        $st->execute([$representativeId]);
        $rep=$st->fetch();

        if(!$rep){
            flash('danger','Das ausgewählte Mitglied wurde nicht gefunden oder ist nicht aktiv.');
            redirect('/spiess-vertretung.php');
        }
        if(in_array($rep['role'],['spiess','admin'],true)){
            flash('warning','Spieß und Administrator haben die operativen Rechte bereits und müssen nicht als Vertreter eingetragen werden.');
            redirect('/spiess-vertretung.php');
        }

        $pdo->beginTransaction();
        try{
            $pdo->prepare("
                UPDATE spiess_delegations
                SET active=0,ended_at=CURRENT_TIMESTAMP,ended_by=?
                WHERE active=1
            ")->execute([(int)$u['id']]);

            $pdo->prepare("
                INSERT INTO spiess_delegations(representative_user_id,assigned_by,active)
                VALUES(?,?,1)
            ")->execute([$representativeId,(int)$u['id']]);

            $pdo->commit();
        }catch(Throwable $e){
            if($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }

        flash('success',$rep['first_name'].' '.$rep['last_name'].' ist jetzt Spieß-Vertreter.');
        redirect('/spiess-vertretung.php');
    }

    if($action==='end'){
        $pdo->prepare("
            UPDATE spiess_delegations
            SET active=0,ended_at=CURRENT_TIMESTAMP,ended_by=?
            WHERE active=1
        ")->execute([(int)$u['id']]);

        flash('success','Die Spieß-Vertretung wurde beendet.');
        redirect('/spiess-vertretung.php');
    }
}

$active=active_spiess_delegation($pdo);

$members=$pdo->query("
    SELECT id,first_name,last_name,role,is_treasurer,is_leader,is_secretary
    FROM users
    WHERE is_active=1 AND role NOT IN ('spiess','admin')
    ORDER BY last_name,first_name
")->fetchAll();

$history=$pdo->query("
    SELECT d.*,
           r.first_name representative_first,r.last_name representative_last,
           a.first_name assigned_first,a.last_name assigned_last,
           e.first_name ended_first,e.last_name ended_last
    FROM spiess_delegations d
    JOIN users r ON r.id=d.representative_user_id
    JOIN users a ON a.id=d.assigned_by
    LEFT JOIN users e ON e.id=d.ended_by
    ORDER BY d.id DESC
    LIMIT 10
")->fetchAll();

$pageTitle='Spieß-Vertretung';
require __DIR__.'/includes/header.php';
?>
<div class="container dashboard-shell">
  <div class="row g-4">
    <?php require __DIR__.'/includes/sidebar.php'; ?>
    <section class="col-lg-9">
      <div class="mb-4">
        <div class="eyebrow">Vertretung</div>
        <h1 class="section-title h2 mb-1">Spieß-Vertretung</h1>
        <p class="text-secondary mb-0">Übertrage die operativen Spieß-Aufgaben vorübergehend an ein anderes Mitglied.</p>
      </div>

      <?php if($active): ?>
        <div class="panel p-4 mb-4 spiess-delegation-active">
          <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
            <div>
              <div class="small text-secondary">Aktuelle Vertretung</div>
              <div class="h4 fw-bold mb-1"><?=e($active['representative_first'].' '.$active['representative_last'])?></div>
              <div class="small text-secondary">
                Seit <?=e(date('d.m.Y H:i',strtotime($active['started_at'])))?> Uhr · eingesetzt von <?=e($active['assigned_first'].' '.$active['assigned_last'])?>
              </div>
            </div>
            <form method="post" onsubmit="return confirm('Spieß-Vertretung jetzt beenden?');">
              <input type="hidden" name="csrf" value="<?=e(csrf_token())?>">
              <input type="hidden" name="action" value="end">
              <button class="btn btn-outline-danger"><i class="bi bi-person-x me-1"></i> Vertretung beenden</button>
            </form>
          </div>
        </div>
      <?php else: ?>
        <div class="panel p-4 mb-4">
          <div class="h5 fw-bold mb-1">Keine Vertretung aktiv</div>
          <div class="text-secondary">Die operativen Spieß-Aufgaben liegen aktuell nur bei Spieß und Administrator.</div>
        </div>
      <?php endif;?>

      <div class="panel p-4 mb-4">
        <h2 class="h5 fw-bold mb-3"><?= $active ? 'Vertreter wechseln' : 'Vertreter einsetzen' ?></h2>
        <form method="post">
          <input type="hidden" name="csrf" value="<?=e(csrf_token())?>">
          <input type="hidden" name="action" value="assign">
          <div class="row g-3 align-items-end">
            <div class="col-md-8">
              <label class="form-label">Mitglied</label>
              <select class="form-select" name="representative_user_id" required>
                <option value="">Bitte auswählen</option>
                <?php foreach($members as $m): ?>
                  <option value="<?=(int)$m['id']?>" <?= $active && (int)$active['representative_user_id']===(int)$m['id'] ? 'disabled' : '' ?>>
                    <?=e($m['last_name'].', '.$m['first_name'])?>
                  </option>
                <?php endforeach;?>
              </select>
              <div class="form-text">Die Vertretung erhält Theken-/Getränke- und Rundenrechte. Benutzerverwaltung, Chronikbearbeitung und andere Ämter werden nicht übertragen.</div>
            </div>
            <div class="col-md-4">
              <button class="btn btn-brand w-100"><i class="bi bi-person-check me-1"></i> <?= $active ? 'Vertreter wechseln' : 'Vertretung starten' ?></button>
            </div>
          </div>
        </form>
      </div>

      <div class="panel p-4">
        <h2 class="h5 fw-bold mb-3">Letzte Vertretungen</h2>
        <?php if(!$history): ?>
          <div class="text-secondary">Noch keine Vertretung erfasst.</div>
        <?php else: ?>
          <div class="table-responsive">
            <table class="table align-middle mb-0">
              <thead><tr><th>Vertreter</th><th>Beginn</th><th>Ende</th><th>Eingesetzt von</th></tr></thead>
              <tbody>
              <?php foreach($history as $row): ?>
                <tr>
                  <td><strong><?=e($row['representative_first'].' '.$row['representative_last'])?></strong></td>
                  <td><?=e(date('d.m.Y H:i',strtotime($row['started_at'])))?></td>
                  <td><?= $row['ended_at'] ? e(date('d.m.Y H:i',strtotime($row['ended_at']))) : '<span class="badge text-bg-success">aktiv</span>' ?></td>
                  <td><?=e($row['assigned_first'].' '.$row['assigned_last'])?></td>
                </tr>
              <?php endforeach;?>
              </tbody>
            </table>
          </div>
        <?php endif;?>
      </div>
    </section>
  </div>
</div>
<?php require __DIR__.'/includes/footer.php'; ?>
