<?php
require_once __DIR__ . '/includes/app.php';
$u=require_role('spiess','admin');
$pageTitle='Thekenansicht'; require __DIR__.'/includes/header.php';
?>
<div class="container theke-page" style="max-width:1100px">
  <div class="theke-toolbar">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
      <div><div class="eyebrow">Spieß · Bestellmodus</div><h1 class="h3 section-title mb-0">Thekenansicht</h1></div>
      <div class="d-flex gap-2"><button class="btn btn-soft" data-wake-lock><i class="bi bi-lightbulb me-1"></i> Display wach</button><button id="refreshBtn" class="btn btn-dark"><i class="bi bi-arrow-clockwise me-1"></i> Aktualisieren</button></div>
    </div>
  </div>

  <div class="theke-summary p-3 p-md-4 mb-3"><div class="row g-3 align-items-center"><div class="col-6 col-md-3"><div class="small text-secondary">Getränke gewählt</div><div class="stat-number" id="chosenCount">–</div></div><div class="col-6 col-md-3"><div class="small text-secondary">Derzeit nichts</div><div class="stat-number" id="noneCount">–</div></div><div class="col-md-6"><div class="small text-secondary">Live-Stand</div><div class="fw-bold"><span class="online-dot d-inline-block me-2" data-online-indicator></span><span id="updateTime">wird geladen …</span></div><div class="small text-secondary">Aktualisiert automatisch alle 15 Sekunden</div></div></div></div>

  <div id="orderGrid" class="row g-3"></div>
  <div id="orderEmpty" class="theke-empty d-none"><div class="display-5 mb-2">🥤</div><h2 class="h5">Noch keine Getränke ausgewählt</h2><p class="text-secondary mb-0">Erinnere die Mitglieder oder warte auf ihre Auswahl.</p></div>

  <div class="panel p-3 p-md-4 mt-4"><div class="d-flex flex-wrap justify-content-between align-items-center gap-2"><div><strong>Vor der nächsten Veranstaltung alte Wünsche?</strong><div class="small text-secondary">Status-Reset und Erinnerungen findest du in der Getränkezentrale.</div></div><a class="btn btn-outline-danger" href="/drinks.php">Getränkezentrale</a></div></div>
</div>
<script>
const grid=document.getElementById('orderGrid'), empty=document.getElementById('orderEmpty');
function esc(s){return String(s).replace(/[&<>'"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[c]));}
async function loadStatus(){
 const btn=document.getElementById('refreshBtn'); btn.disabled=true;
 try{const res=await fetch('/api/drink-status.php',{cache:'no-store'}); if(!res.ok) throw new Error(); const data=await res.json();
 document.getElementById('chosenCount').textContent=data.chosen;document.getElementById('noneCount').textContent=data.none;document.getElementById('updateTime').textContent='Stand '+data.updated_at+' Uhr';
 grid.innerHTML=''; empty.classList.toggle('d-none',data.drinks.length>0);
 data.drinks.forEach(d=>{const members=d.members.map(m=>`<span class="member-pill">${esc(m)}</span>`).join('');const col=document.createElement('div');col.className='col-12 col-sm-6 col-xl-4';col.innerHTML=`<article class="order-card h-100 p-3 p-md-4"><div class="d-flex align-items-start justify-content-between gap-3"><div><div class="order-emoji">${esc(d.emoji||'🥤')}</div><div class="order-name mt-2">${esc(d.name)}</div></div><div class="order-count">${d.qty}</div></div><details class="mt-3"><summary class="small text-secondary">Wer bestellt das?</summary><div class="mt-2">${members}</div></details></article>`;grid.appendChild(col);});
 }catch(e){document.getElementById('updateTime').textContent='Aktualisierung fehlgeschlagen';}finally{btn.disabled=false;}
}
document.getElementById('refreshBtn').addEventListener('click',loadStatus);loadStatus();setInterval(loadStatus,15000);
</script>
<?php require __DIR__.'/includes/footer.php'; ?>
