<?php
require_once __DIR__.'/includes/app.php';
$u=require_login();
$active=active_round_for_user($pdo,(int)$u['id']);

$pageTitle='Aktuelle Runde holen';
$bodyClass='theme-light-theke';
require __DIR__.'/includes/header.php';
?>
<div class="container theke-page" style="max-width:1100px">
  <div class="theke-toolbar">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
      <div>
        <div class="eyebrow">Live-Bestellung</div>
        <h1 class="h3 section-title mb-0">Aktuelle Runde holen</h1>
      </div>
      <div class="d-flex flex-wrap gap-2">
        <a class="btn btn-outline-secondary" href="/member-drink.php"><i class="bi bi-cup-straw me-1"></i> Mein Getränk ändern</a>
        <button class="btn btn-soft" data-wake-lock><i class="bi bi-lightbulb me-1"></i> Display wach</button>
        <button id="refreshBtn" class="btn btn-dark"><i class="bi bi-arrow-clockwise me-1"></i> Aktualisieren</button>
      </div>
    </div>
  </div>

  <?php if(!$active): ?>
    <div class="theke-empty mt-4">
      <div class="display-5 mb-2">✅</div>
      <h2 class="h4">Aktuell keine Runde zum Holen</h2>
      <p class="text-secondary">Der Auftrag wurde bereits als gegeben markiert oder vom Spieß zurückgestellt.</p>
      <a class="btn btn-dark" href="/dashboard.php">Zur Übersicht</a>
    </div>
  <?php else: ?>
    <div class="runner-banner p-3 p-md-4 mb-3" id="runnerBanner">
      <div class="small text-secondary">Dein Auftrag</div>
      <div class="h4 fw-bold mb-1">1× Runde holen</div>
      <div><?=e($active['reason'])?></div>
      <div class="small text-secondary mt-1">Die Getränkeliste darunter ist live und kann sich bis zur Bestellung noch ändern.</div>
    </div>

    <div class="theke-summary p-3 p-md-4 mb-3">
      <div class="row g-3 align-items-center">
        <div class="col-6 col-md-3"><div class="small text-secondary">Getränke gewählt</div><div class="stat-number" id="chosenCount">–</div></div>
        <div class="col-6 col-md-3"><div class="small text-secondary">Derzeit nichts</div><div class="stat-number" id="noneCount">–</div></div>
        <div class="col-md-6">
          <div class="small text-secondary">Live-Stand</div>
          <div class="fw-bold"><span class="online-dot d-inline-block me-2" data-online-indicator></span><span id="updateTime">wird geladen …</span></div>
          <div class="small text-secondary">Aktualisiert automatisch alle 5 Sekunden</div>
        </div>
      </div>
    </div>

    <div id="orderGrid" class="row g-3"></div>
    <div id="orderEmpty" class="theke-empty d-none">
      <div class="display-5 mb-2">🥤</div>
      <h2 class="h5">Noch keine Getränke ausgewählt</h2>
      <p class="text-secondary mb-0">Die Liste aktualisiert sich automatisch.</p>
    </div>

    <div class="runner-live-note mt-4">
      <i class="bi bi-arrow-repeat me-2"></i>
      Änderungen der Mitglieder werden automatisch übernommen. Entscheidend ist der Stand, den du an der Theke siehst.
    </div>
  <?php endif;?>
</div>

<?php if($active): ?>
<script>
const grid=document.getElementById('orderGrid');
const empty=document.getElementById('orderEmpty');
let stopped=false;

function esc(s){return String(s).replace(/[&<>'"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[c]));}


function formatLocalTime(value){
  const d=new Date(value);
  if(Number.isNaN(d.getTime())) return String(value||'');
  return new Intl.DateTimeFormat('de-DE',{
    hour:'2-digit',minute:'2-digit',second:'2-digit',hour12:false
  }).format(d);
}

async function checkAssignment(){
  const res=await fetch('/api/my-active-round.php',{cache:'no-store'});
  if(!res.ok) return true;
  const data=await res.json();
  if(!data.active){
    stopped=true;
    document.getElementById('runnerBanner').innerHTML='<div class="h4 fw-bold mb-1">✅ Runde beendet</div><div>Der Spieß hat die Runde als gegeben markiert oder zurückgestellt.</div><a class="btn btn-dark mt-3" href="/dashboard.php">Zur Übersicht</a>';
    grid.innerHTML='';
    empty.classList.add('d-none');
    document.getElementById('updateTime').textContent='Auftrag beendet';
    return false;
  }
  return true;
}

async function loadStatus(){
  if(stopped) return;
  const btn=document.getElementById('refreshBtn');
  btn.disabled=true;
  try{
    const stillActive=await checkAssignment();
    if(!stillActive) return;
    const res=await fetch('/api/live-order.php',{cache:'no-store'});
    if(!res.ok) throw new Error();
    const data=await res.json();
    document.getElementById('chosenCount').textContent=data.chosen;
    document.getElementById('noneCount').textContent=data.none;
    document.getElementById('updateTime').textContent='Stand '+formatLocalTime(data.updated_at)+' Uhr';
    grid.innerHTML='';
    empty.classList.toggle('d-none',data.drinks.length>0);

    data.drinks.forEach(d=>{
      const members=d.members.map(m=>`<span class="member-pill">${esc(m)}</span>`).join('');
      const col=document.createElement('div');
      col.className='col-12 col-sm-6 col-xl-4';
      col.innerHTML=`<article class="order-card h-100 p-3 p-md-4">
        <div class="d-flex align-items-start justify-content-between gap-3">
          <div><div class="order-emoji">${esc(d.emoji||'🥤')}</div><div class="order-name mt-2">${esc(d.name)}</div></div>
          <div class="order-count">${d.qty}</div>
        </div>
        <details class="mt-3"><summary class="small text-secondary">Wer bestellt das?</summary><div class="mt-2">${members}</div></details>
      </article>`;
      grid.appendChild(col);
    });
  }catch(e){
    document.getElementById('updateTime').textContent='Aktualisierung fehlgeschlagen';
  }finally{
    btn.disabled=false;
  }
}

document.getElementById('refreshBtn').addEventListener('click',loadStatus);
loadStatus();
setInterval(loadStatus,5000);
</script>
<?php endif;?>
<?php require __DIR__.'/includes/footer.php';?>
