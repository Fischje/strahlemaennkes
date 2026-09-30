<?php
require_once __DIR__.'/includes/app.php';
$u=require_chronicle_editor();

if($_SERVER['REQUEST_METHOD']==='POST'){
    verify_csrf();
    $action=$_POST['action']??'';

    if(in_array($action,['create','edit'],true)){
        $id=(int)($_POST['id']??0);
        $year=(int)($_POST['year']??0);
        if($year<1900 || $year>2200){
            flash('danger','Bitte ein gültiges Jahr eingeben.');
            redirect('/chronik-admin.php');
        }

        $values=[
            trim($_POST['zugkoenig']??''),
            trim($_POST['zugfuehrer']??''),
            trim($_POST['zugspiess']??''),
            trim($_POST['kassierer']??''),
            trim($_POST['schriftfuehrer']??''),
            trim($_POST['erster_offizier']??''),
            trim($_POST['zweiter_offizier']??''),
            trim($_POST['notes']??''),
        ];
        $published=!empty($_POST['published'])?1:0;

        try{
            if($action==='create'){
                $pdo->prepare("INSERT INTO chronicle_years(year,zugkoenig,zugfuehrer,zugspiess,kassierer,schriftfuehrer,erster_offizier,zweiter_offizier,notes,published) VALUES(?,?,?,?,?,?,?,?,?,?)")
                    ->execute([$year,...$values,$published]);
                flash('success','Chronik-Jahrgang angelegt.');
            }else{
                $pdo->prepare("UPDATE chronicle_years SET year=?,zugkoenig=?,zugfuehrer=?,zugspiess=?,kassierer=?,schriftfuehrer=?,erster_offizier=?,zweiter_offizier=?,notes=?,published=? WHERE id=?")
                    ->execute([$year,...$values,$published,$id]);
                flash('success','Chronik-Jahrgang aktualisiert.');
            }
        }catch(Throwable $e){
            flash('danger','Der Jahrgang konnte nicht gespeichert werden. Das Jahr ist möglicherweise bereits vorhanden.');
        }
        redirect('/chronik-admin.php');
    }

    if($action==='delete'){
        $id=(int)($_POST['id']??0);
        $pdo->prepare("DELETE FROM chronicle_years WHERE id=?")->execute([$id]);
        flash('success','Chronik-Jahrgang gelöscht.');
        redirect('/chronik-admin.php');
    }
}

$years=$pdo->query("SELECT * FROM chronicle_years ORDER BY year DESC")->fetchAll();
$pageTitle='Chronik verwalten';
require __DIR__.'/includes/header.php';
?>
<div class="container dashboard-shell"><div class="row g-4">
<?php require __DIR__.'/includes/sidebar.php';?>
<section class="col-lg-9">
  <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <div>
      <div class="eyebrow">Chronik · Administration</div>
      <h1 class="section-title h2 mb-1">Chronik verwalten</h1>
      <p class="text-secondary mb-0">Veröffentlichte Jahrgänge erscheinen sofort auf der öffentlichen Chronik.</p>
    </div>
    <div class="d-flex flex-wrap gap-2">
      <a class="btn btn-outline-secondary" href="/public/chronik.php"><i class="bi bi-eye me-1"></i> Öffentliche Ansicht</a>
      <button class="btn btn-brand" data-bs-toggle="modal" data-bs-target="#chronikModal" data-mode="create"><i class="bi bi-plus-lg me-1"></i> Jahrgang anlegen</button>
    </div>
  </div>

  <div class="panel p-4">
    <?php if(!$years): ?>
      <div class="text-secondary py-4 text-center">Noch keine Jahrgänge angelegt.</div>
    <?php else: ?>
    <div class="table-responsive"><table class="table align-middle mb-0">
      <thead><tr><th>Jahr</th><th>Zugkönig</th><th>Zugführer</th><th>Status</th><th class="text-end">Aktionen</th></tr></thead>
      <tbody><?php foreach($years as $y):?>
        <tr>
          <td><strong><?=e((string)$y['year'])?></strong></td>
          <td><?=e($y['zugkoenig']?:'–')?></td>
          <td><?=e($y['zugfuehrer']?:'–')?></td>
          <td><?=$y['published']?'<span class="badge text-bg-success">Öffentlich</span>':'<span class="badge text-bg-secondary">Entwurf</span>'?></td>
          <td class="text-end"><div class="d-inline-flex flex-wrap gap-1">
            <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#chronikModal"
              data-mode="edit" data-id="<?=(int)$y['id']?>" data-year="<?=(int)$y['year']?>"
              data-zugkoenig="<?=e($y['zugkoenig']??'')?>" data-zugfuehrer="<?=e($y['zugfuehrer']??'')?>"
              data-zugspiess="<?=e($y['zugspiess']??'')?>" data-kassierer="<?=e($y['kassierer']??'')?>" data-schriftfuehrer="<?=e($y['schriftfuehrer']??'')?>"
              data-erster="<?=e($y['erster_offizier']??'')?>" data-zweiter="<?=e($y['zweiter_offizier']??'')?>"
              data-notes="<?=e($y['notes']??'')?>" data-published="<?=(int)$y['published']?>">Bearbeiten</button>
            <form method="post" onsubmit="return confirm('Diesen Chronik-Jahrgang wirklich löschen?');">
              <input type="hidden" name="csrf" value="<?=e(csrf_token())?>">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="id" value="<?=(int)$y['id']?>">
              <button class="btn btn-sm btn-outline-danger">Löschen</button>
            </form>
          </div></td>
        </tr>
      <?php endforeach;?></tbody>
    </table></div>
    <?php endif;?>
  </div>
</section></div></div>

<div class="modal fade" id="chronikModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered modal-lg"><form method="post" class="modal-content">
  <div class="modal-header"><h2 class="modal-title fs-5" id="chronikModalTitle">Jahrgang anlegen</h2><button class="btn-close" type="button" data-bs-dismiss="modal"></button></div>
  <div class="modal-body">
    <input type="hidden" name="csrf" value="<?=e(csrf_token())?>">
    <input type="hidden" name="action" id="chronikAction" value="create">
    <input type="hidden" name="id" id="chronikId">
    <div class="row g-3">
      <div class="col-12 col-md-4"><label class="form-label">Jahr</label><input type="number" min="1900" max="2200" class="form-control" name="year" id="chronikYear" required></div>
      <div class="col-md-6"><label class="form-label">Zugkönig</label><input class="form-control" name="zugkoenig" id="chronikZugkoenig"></div>
      <div class="col-md-6"><label class="form-label">Zugführer</label><input class="form-control" name="zugfuehrer" id="chronikZugfuehrer"></div>
      <div class="col-md-6"><label class="form-label">Zugspieß</label><input class="form-control" name="zugspiess" id="chronikZugspiess"></div>
      <div class="col-md-6"><label class="form-label">Kassierer</label><input class="form-control" name="kassierer" id="chronikKassierer"></div>
      <div class="col-md-6"><label class="form-label">Schriftführer</label><input class="form-control" name="schriftfuehrer" id="chronikSchriftfuehrer"></div>
      <div class="col-md-6"><label class="form-label">1. Offizier</label><input class="form-control" name="erster_offizier" id="chronikErster"></div>
      <div class="col-md-6"><label class="form-label">2. Offizier</label><input class="form-control" name="zweiter_offizier" id="chronikZweiter"></div>
      <div class="col-12"><label class="form-label">Notizen / Besonderheiten</label><textarea class="form-control" name="notes" id="chronikNotes" rows="4"></textarea></div>
      <div class="col-12"><div class="form-check"><input class="form-check-input" type="checkbox" name="published" value="1" id="chronikPublished" checked><label class="form-check-label" for="chronikPublished">Öffentlich anzeigen</label></div></div>
    </div>
  </div>
  <div class="modal-footer"><button class="btn btn-brand">Speichern</button></div>
</form></div></div>
<script>
document.getElementById('chronikModal').addEventListener('show.bs.modal',e=>{
  const b=e.relatedTarget,edit=b?.dataset.mode==='edit';
  document.getElementById('chronikModalTitle').textContent=edit?'Jahrgang bearbeiten':'Jahrgang anlegen';
  document.getElementById('chronikAction').value=edit?'edit':'create';
  document.getElementById('chronikId').value=edit?b.dataset.id:'';
  document.getElementById('chronikYear').value=edit?b.dataset.year:new Date().getFullYear();
  document.getElementById('chronikZugkoenig').value=edit?b.dataset.zugkoenig:'';
  document.getElementById('chronikZugfuehrer').value=edit?b.dataset.zugfuehrer:'';
  document.getElementById('chronikZugspiess').value=edit?b.dataset.zugspiess:'';
  document.getElementById('chronikKassierer').value=edit?b.dataset.kassierer:'';
  document.getElementById('chronikSchriftfuehrer').value=edit?b.dataset.schriftfuehrer:'';
  document.getElementById('chronikErster').value=edit?b.dataset.erster:'';
  document.getElementById('chronikZweiter').value=edit?b.dataset.zweiter:'';
  document.getElementById('chronikNotes').value=edit?b.dataset.notes:'';
  document.getElementById('chronikPublished').checked=!edit||b.dataset.published==='1';
});
</script>
<?php require __DIR__.'/includes/footer.php';?>
