<?php
require_once __DIR__.'/includes/app.php';
$u=require_login();
$isAdmin=has_role('admin');

if($_SERVER['REQUEST_METHOD']==='POST'){
    if(!$isAdmin){ http_response_code(403); exit('Nur Administratoren dürfen Mitglieder bearbeiten.'); }
    verify_csrf();
    $action=$_POST['action']??'';

    if(in_array($action,['create','edit'],true)){
        $id=(int)($_POST['id']??0);
        $username=trim($_POST['username']??'');
        $first=trim($_POST['first_name']??'');
        $last=trim($_POST['last_name']??'');
        $email=trim($_POST['email']??'');
        $role=in_array($_POST['role']??'', ['member','spiess','admin'],true)?$_POST['role']:'member';
        $treasurer=!empty($_POST['is_treasurer']) ? 1 : 0;
        $leader=!empty($_POST['is_leader']) ? 1 : 0;
        $secretary=!empty($_POST['is_secretary']) ? 1 : 0;
        $password=$_POST['password']??'';

        if(strlen($username)<3||$first===''||$last===''||($email!==''&&!filter_var($email,FILTER_VALIDATE_EMAIL))){
            flash('danger','Bitte die Angaben prüfen.'); redirect('/members.php');
        }
        if($action==='create' && $password===''){
            flash('danger','Bitte ein Startpasswort vergeben.');
            redirect('/members.php');
        }

        try{
            if($action==='create'){
                $pdo->prepare("INSERT INTO users(username,email,password_hash,first_name,last_name,role,is_treasurer,is_leader,is_secretary,must_change_password) VALUES(?,?,?,?,?,?,?,?,?,1)")
                    ->execute([$username,$email?:null,password_hash($password,PASSWORD_DEFAULT),$first,$last,$role,$treasurer,$leader,$secretary]);
                flash('success','Mitglied angelegt.');
            }else{
                // Der aktuell eingeloggte Admin darf sich nicht versehentlich selbst die Admin-Rolle entziehen.
                if($id===$u['id']) $role='admin';

                if($password!==''){
                    $pdo->prepare("UPDATE users SET username=?,email=?,first_name=?,last_name=?,role=?,is_treasurer=?,is_leader=?,is_secretary=?,password_hash=?,must_change_password=1,updated_at=CURRENT_TIMESTAMP WHERE id=?")
                        ->execute([$username,$email?:null,$first,$last,$role,$treasurer,$leader,$secretary,password_hash($password,PASSWORD_DEFAULT),$id]);
                    $pdo->prepare("DELETE FROM remember_tokens WHERE user_id=?")->execute([$id]);
                }else{
                    $pdo->prepare("UPDATE users SET username=?,email=?,first_name=?,last_name=?,role=?,is_treasurer=?,is_leader=?,is_secretary=?,updated_at=CURRENT_TIMESTAMP WHERE id=?")
                        ->execute([$username,$email?:null,$first,$last,$role,$treasurer,$leader,$secretary,$id]);
                }

                if($id===$u['id']){
                    $_SESSION['user']['username']=$username;
                    $_SESSION['user']['first_name']=$first;
                    $_SESSION['user']['last_name']=$last;
                    $_SESSION['user']['role']='admin';
                    $_SESSION['user']['is_treasurer']=$treasurer;
                    $_SESSION['user']['is_leader']=$leader;
                    $_SESSION['user']['is_secretary']=$secretary;
                    if($password!=='') $_SESSION['user']['must_change_password']=1;
                }
                flash('success','Mitglied bearbeitet.');
            }
        }catch(Throwable $e){
            flash('danger','Speichern fehlgeschlagen. Benutzername oder E-Mail sind möglicherweise bereits vergeben.');
        }
        redirect('/members.php');
    }

    if($action==='toggle_registration'){
        $enabled=!empty($_POST['enabled']);
        set_app_setting($pdo,'registration_enabled',$enabled ? '1' : '0',(int)$u['id']);
        flash('success',$enabled
            ? 'Selbstregistrierung wurde aktiviert.'
            : 'Selbstregistrierung wurde deaktiviert.');
        redirect('/members.php');
    }

    if($action==='toggle'){
        $id=(int)$_POST['id'];
        if($id===$u['id']){
            flash('warning','Den eigenen Admin-Account kannst du nicht deaktivieren.');
            redirect('/members.php');
        }
        $pdo->prepare('UPDATE users SET is_active=1-is_active WHERE id=?')->execute([$id]);
        flash('success','Benutzerstatus geändert.');
        redirect('/members.php');
    }
}

$users=$pdo->query('SELECT id,username,email,first_name,last_name,role,is_treasurer,is_leader,is_secretary,must_change_password,is_active,created_at FROM users ORDER BY last_name,first_name')->fetchAll();
$registrationEnabled=$isAdmin ? public_registration_enabled($pdo) : false;
$pageTitle='Mitglieder';
require __DIR__.'/includes/header.php';
?>
<div class="container dashboard-shell"><div class="row g-4">
<?php require __DIR__.'/includes/sidebar.php';?>
<section class="col-lg-9">
  <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <div>
      <div class="eyebrow">Zugmitglieder</div>
      <h1 class="section-title h2 mb-1">Mitglieder</h1>
      <p class="text-secondary mb-0"><?= $isAdmin ? 'Mitglieder ansehen und verwalten.' : 'Übersicht der im Zug angelegten Mitglieder.' ?></p>
    </div>
    <?php if($isAdmin): ?>
      <button class="btn btn-brand" data-bs-toggle="modal" data-bs-target="#userModal" data-mode="create"><i class="bi bi-person-plus me-1"></i> Mitglied anlegen</button>
    <?php endif; ?>
  </div>

  <?php if($isAdmin): ?>
    <div class="panel p-4 mb-4">
      <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div>
          <div class="eyebrow">Registrierung</div>
          <h2 class="h5 fw-bold mb-1">Selbstregistrierung neuer Mitglieder</h2>
          <p class="text-secondary mb-0">Wenn aktiviert, erscheint auf der Login-Seite ein Registrierungslink. Neue Accounts werden ausschließlich als normale Mitglieder angelegt.</p>
        </div>
        <form method="post" class="d-flex align-items-center gap-3">
          <input type="hidden" name="csrf" value="<?=e(csrf_token())?>">
          <input type="hidden" name="action" value="toggle_registration">
          <div class="form-check form-switch fs-5 mb-0">
            <input class="form-check-input" type="checkbox" role="switch" name="enabled" value="1" id="registrationEnabled" <?=$registrationEnabled?'checked':''?> onchange="this.form.submit()">
            <label class="form-check-label fs-6" for="registrationEnabled"><?=$registrationEnabled?'Aktiv':'Aus'?></label>
          </div>
        </form>
      </div>
      <?php if($registrationEnabled): ?>
        <div class="alert alert-success mt-3 mb-0 py-2"><i class="bi bi-person-plus me-1"></i> Registrierung ist aktuell geöffnet.</div>
      <?php else: ?>
        <div class="alert alert-secondary mt-3 mb-0 py-2">Registrierung ist aktuell geschlossen.</div>
      <?php endif;?>
    </div>
  <?php endif; ?>

  <div class="panel p-4">
    <?php if(!$isAdmin): ?>
      <div class="row g-3">
        <?php foreach($users as $m): ?>
          <div class="col-sm-6 col-xl-4">
            <div class="member-card h-100">
              <div class="d-flex align-items-start justify-content-between gap-2">
                <div>
                  <strong class="d-block"><?=e($m['first_name'].' '.$m['last_name'])?></strong>
                  <div class="small text-secondary mt-1"><?=e(role_label($m['role']))?></div>
                </div>
                <span class="member-status-dot <?=$m['is_active']?'active':'inactive'?>" title="<?=$m['is_active']?'Aktiv':'Inaktiv'?>"></span>
              </div>
              <?php if((int)$m['is_treasurer'] || (int)$m['is_leader'] || (int)$m['is_secretary']): ?>
                <div class="mt-3 d-flex flex-wrap gap-1">
                  <?php if((int)$m['is_treasurer']): ?><span class="badge text-bg-success">Kassierer</span><?php endif; ?>
                  <?php if((int)$m['is_leader']): ?><span class="badge text-bg-info">Zugführer</span><?php endif; ?>
                  <?php if((int)$m['is_secretary']): ?><span class="badge text-bg-warning">Schriftführer</span><?php endif; ?>
                </div>
              <?php endif; ?>
            </div>
          </div>
        <?php endforeach;?>
      </div>
      <div class="small text-secondary mt-3">Login-Namen und E-Mail-Adressen sind nur für Administratoren sichtbar.</div>
    <?php else: ?>
      <div class="table-responsive"><table class="table align-middle mb-0">
        <thead><tr><th>Name</th><th>Login</th><th>E-Mail</th><th>Rolle</th><th>Ämter</th><th>Status</th><th class="text-end">Aktion</th></tr></thead>
        <tbody>
        <?php foreach($users as $m):?>
          <tr>
            <td><strong><?=e($m['first_name'].' '.$m['last_name'])?></strong></td>
            <td><?=e($m['username'])?></td>
            <td><?=e($m['email']?:'–')?></td>
            <td><?=e(role_label($m['role']))?></td>
            <td><div class="d-flex flex-wrap gap-1">
              <?php if((int)$m['is_treasurer']): ?><span class="badge text-bg-success">Kassierer</span><?php endif; ?>
              <?php if((int)$m['is_leader']): ?><span class="badge text-bg-info">Zugführer</span><?php endif; ?>
                  <?php if((int)$m['is_secretary']): ?><span class="badge text-bg-warning">Schriftführer</span><?php endif; ?>
              <?php if(!((int)$m['is_treasurer']) && !(int)$m['is_leader']): ?>–<?php endif; ?>
            </div></td>
            <td>
              <?=$m['is_active']?'<span class="badge text-bg-success">Aktiv</span>':'<span class="badge text-bg-secondary">Inaktiv</span>'?>
              <?php if((int)$m['must_change_password']): ?><div class="mt-1"><span class="badge text-bg-warning">Passwortwechsel ausstehend</span></div><?php endif;?>
            </td>
            <td class="text-end"><div class="d-inline-flex gap-1">
              <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#userModal" data-mode="edit"
                data-id="<?=(int)$m['id']?>" data-username="<?=e($m['username'])?>" data-email="<?=e($m['email']??'')?>"
                data-first="<?=e($m['first_name'])?>" data-last="<?=e($m['last_name'])?>" data-role="<?=e($m['role'])?>"
                data-treasurer="<?=(int)$m['is_treasurer']?>" data-leader="<?=(int)$m['is_leader']?>" data-secretary="<?=(int)$m['is_secretary']?>">Bearbeiten</button>
              <?php if($m['id']!=$u['id']):?>
                <form method="post"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?=$m['id']?>">
                  <button class="btn btn-sm <?=$m['is_active']?'btn-outline-danger':'btn-outline-success'?>"><?=$m['is_active']?'Deaktivieren':'Aktivieren'?></button>
                </form>
              <?php endif;?>
            </div></td>
          </tr>
        <?php endforeach;?>
        </tbody>
      </table></div>
    <?php endif;?>
  </div>
</section></div></div>

<?php if($isAdmin): ?>
<div class="modal fade" id="userModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><form method="post" class="modal-content">
  <div class="modal-header"><h2 class="modal-title fs-5" id="userModalTitle">Mitglied anlegen</h2><button class="btn-close" type="button" data-bs-dismiss="modal"></button></div>
  <div class="modal-body">
    <input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" id="userAction" value="create"><input type="hidden" name="id" id="userId">
    <div class="row g-3">
      <div class="col-6"><label class="form-label">Vorname</label><input id="userFirst" name="first_name" class="form-control" required></div>
      <div class="col-6"><label class="form-label">Nachname</label><input id="userLast" name="last_name" class="form-control" required></div>
      <div class="col-12"><label class="form-label">Benutzername</label><input id="userUsername" name="username" class="form-control" required></div>
      <div class="col-12"><label class="form-label">E-Mail</label><input id="userEmail" type="email" name="email" class="form-control"></div>
      <div class="col-12"><label class="form-label">Start-/Resetpasswort <span id="passwordOptional" class="text-secondary small"></span></label><input type="password" name="password" id="userPassword" class="form-control" autocomplete="new-password"><div class="form-text">Als Administrator kannst du dieses Passwort frei vergeben. Es gilt nur als Start-/Resetpasswort; der Benutzer muss beim nächsten Login ein eigenes Passwort nach der normalen Kennwortrichtlinie festlegen.</div></div>
      <div class="col-12"><label class="form-label">Rolle</label><select id="userRole" name="role" class="form-select"><option value="member">Mitglied</option><option value="spiess">Spieß / Moderator</option><option value="admin">Administrator</option></select></div>
      <div class="col-12"><div class="form-check"><input class="form-check-input" type="checkbox" name="is_treasurer" value="1" id="userTreasurer"><label class="form-check-label" for="userTreasurer">Kassenrecht / Kassierer</label></div><div class="form-text">Kann Kontostand und Monatsbeitrag in der Kasse ändern. Administratoren dürfen die Kasse unabhängig davon ebenfalls bearbeiten.</div></div><div class="col-12"><div class="form-check"><input class="form-check-input" type="checkbox" name="is_leader" value="1" id="userLeader"><label class="form-check-label" for="userLeader">Zugführer</label></div><div class="form-text">Darf Stammtische verwalten und die Chronik bearbeiten.</div></div><div class="col-12"><div class="form-check"><input class="form-check-input" type="checkbox" name="is_secretary" value="1" id="userSecretary"><label class="form-check-label" for="userSecretary">Schriftführer</label></div><div class="form-text">Darf Stammtische und Protokolle bearbeiten sowie abgeschlossene Protokolle wieder öffnen.</div></div>
    </div>
  </div>
  <div class="modal-footer"><button class="btn btn-brand">Speichern</button></div>
</form></div></div>
<script>
document.getElementById('userModal').addEventListener('show.bs.modal',e=>{
  const b=e.relatedTarget,edit=b?.dataset.mode==='edit';
  document.getElementById('userModalTitle').textContent=edit?'Mitglied bearbeiten':'Mitglied anlegen';
  document.getElementById('userAction').value=edit?'edit':'create';
  document.getElementById('userId').value=edit?b.dataset.id:'';
  document.getElementById('userFirst').value=edit?b.dataset.first:'';
  document.getElementById('userLast').value=edit?b.dataset.last:'';
  document.getElementById('userUsername').value=edit?b.dataset.username:'';
  document.getElementById('userEmail').value=edit?b.dataset.email:'';
  document.getElementById('userRole').value=edit?b.dataset.role:'member';
  document.getElementById('userTreasurer').checked=edit&&b.dataset.treasurer==='1';
  document.getElementById('userLeader').checked=edit&&b.dataset.leader==='1';
  document.getElementById('userSecretary').checked=edit&&b.dataset.secretary==='1';
  document.getElementById('userPassword').required=!edit;
  document.getElementById('userPassword').value='';
  document.getElementById('passwordOptional').textContent=edit?'(leer lassen = unverändert)':'(wird beim ersten Login geändert)';
});
</script>
<?php endif;?>
<?php require __DIR__.'/includes/footer.php';?>
