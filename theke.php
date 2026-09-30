<?php
require_once __DIR__ . '/includes/app.php';
$u=require_login();
$canOperate=can_act_as_spiess($pdo,$u);
$pageTitle='Thekenansicht';
$bodyClass='theme-light-theke';
require __DIR__.'/includes/header.php';
?>
<div class="container theke-page" style="max-width:1100px">
  <div class="theke-toolbar">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
      <div><div class="eyebrow">Bestellmodus</div><h1 class="h3 section-title mb-0">Thekenansicht</h1></div>
      <div class="d-flex flex-wrap gap-2">
        <a class="btn btn-outline-secondary" href="/dashboard.php"><i class="bi bi-arrow-left me-1"></i> Übersicht</a>
        <?php if($canOperate):?><a class="btn btn-outline-secondary" href="/drinks.php"><i class="bi bi-sliders me-1"></i> Verwalten</a><?php if(sektbar_is_enabled($pdo)):?><a class="btn btn-danger" href="/sektbar.php"><i class="bi bi-stars me-1"></i>Sektbar</a><?php endif;?><?php endif;?>
        <button class="btn btn-soft" data-wake-lock><i class="bi bi-lightbulb me-1"></i> Display wach</button>
        <button id="refreshBtn" class="btn btn-dark"><i class="bi bi-arrow-clockwise me-1"></i> Aktualisieren</button>
      </div>
    </div>
  </div>
  <div class="theke-sharebar p-3 mb-3">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
      <div><strong>Bestellliste weitergeben</strong><div class="small text-secondary">Mitglieder werden beim Teilen nur mit Initialen angegeben (z. B. MP).</div></div>
      <div class="d-flex flex-wrap gap-2">
        <button class="btn btn-outline-dark" id="copyOrderBtn"><i class="bi bi-copy me-1"></i> Liste kopieren</button>
        <button class="btn btn-success" id="whatsappBtn"><i class="bi bi-whatsapp me-1"></i> WhatsApp</button>
        <button class="btn btn-outline-dark" id="printOrderBtn"><i class="bi bi-printer me-1"></i> Drucken</button>
      </div>
    </div>
    <div class="small mt-2" id="shareFeedback" aria-live="polite"></div>
  </div>


  <?php
  $guestDrinks=[]; $guests=[];
  if($canOperate){
    $guestDrinks=$pdo->query("SELECT id,name,emoji FROM drinks WHERE is_active=1 AND is_approved=1 ORDER BY sort_order,name")->fetchAll();
    $guests=$pdo->query("SELECT g.id,g.guest_name,d.name,d.emoji FROM guest_drink_orders g JOIN drinks d ON d.id=g.drink_id ORDER BY g.id DESC")->fetchAll();
  }
?>
  <div class="theke-summary p-3 p-md-4 mb-3">
    <div class="row g-3 align-items-center">
      <div class="col-6 col-md-3"><div class="small text-secondary">Getränke gewählt</div><div class="stat-number" id="chosenCount">–</div></div>
      <div class="col-6 col-md-3"><div class="small text-secondary">Derzeit nichts</div><div class="stat-number" id="noneCount">–</div></div>
      <div class="col-md-6"><div class="small text-secondary">Live-Stand</div><div class="fw-bold"><span class="online-dot d-inline-block me-2" data-online-indicator></span><span id="updateTime">wird geladen …</span></div><div class="small text-secondary">Aktualisiert automatisch alle 15 Sekunden</div></div>
    </div>
    <?php if($canOperate):?>
    <div class="theke-ops-details mt-3">
      <div class="small text-secondary">Noch ohne Getränk</div>
      <div id="noneMembers" class="small fw-semibold text-light">Wird geladen …</div>
      <?php if($guests):?><div class="small text-secondary mt-3 mb-2">Zusätzliche Gästebestellungen</div><div class="d-flex flex-wrap gap-2 guest-live-chips"><?php foreach($guests as $g):?><form method="post" action="/guest-drink.php"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?=(int)$g['id']?>"><button class="btn btn-sm btn-outline-danger"><?=e($g['guest_name'].' · '.($g['emoji']?:'🥤').' '.$g['name'])?> ×</button></form><?php endforeach;?></div><?php endif;?>
    </div>
    <?php endif;?>
  </div>

  <?php if($canOperate):
  ?>
  <div class="theke-sharebar p-3 mb-3">
    <strong>Fremde / Gäste</strong><div class="small text-secondary mb-2">Getränke für Personen ohne Mitgliedskonto zur Bestellliste hinzufügen.</div>
    <form method="post" action="/guest-drink.php" class="row g-2 align-items-end"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="add">
      <div class="col-md-5"><label class="form-label">Name / Bezeichnung</label><input class="form-control" name="guest_name" maxlength="80" required placeholder="z. B. Peter / Gast 1"></div>
      <div class="col-md-5"><label class="form-label">Getränk</label><select class="form-select" name="drink_id" required><?php foreach($guestDrinks as $d):?><option value="<?=(int)$d['id']?>"><?=e(($d['emoji']?:'🥤').' '.$d['name'])?></option><?php endforeach;?></select></div>
      <div class="col-md-2"><button class="btn btn-dark w-100">Hinzufügen</button></div>
    </form>
  </div>
  <?php endif;?>
  <div id="orderGrid" class="row g-3"></div>
  <div id="orderEmpty" class="theke-empty d-none"><div class="display-5 mb-2">🥤</div><h2 class="h5">Noch keine Getränke ausgewählt</h2><p class="text-secondary mb-0">Erinnere die Mitglieder oder warte auf ihre Auswahl.</p></div>

  <div class="panel p-3 p-md-4 mt-4"><div class="d-flex flex-wrap justify-content-between align-items-center gap-2"><div><strong>Vor der nächsten Veranstaltung alte Wünsche?</strong><div class="small text-secondary">Status-Reset und Erinnerungen findest du in der Getränkezentrale.</div></div><a class="btn btn-outline-danger" href="/drinks.php">Getränkezentrale</a></div></div>
</div>

<script>
const grid=document.getElementById('orderGrid');
const empty=document.getElementById('orderEmpty');
const shareFeedback=document.getElementById('shareFeedback');
let latestOrderData=null;

function esc(s){return String(s).replace(/[&<>'"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[c]));}

function buildOrderText(){
  if(!latestOrderData || !latestOrderData.drinks.length) return '';
  const lines=['Strahlemännkes – aktuelle Getränkebestellung','Stand: '+latestOrderData.updated_at+' Uhr',''];
  latestOrderData.drinks.forEach(d=>{
    const codes=(d.export_codes||[]).filter(Boolean).join(', ');
    lines.push(d.qty+'× '+(d.emoji||'')+' '+d.name+(codes?' – '+codes:''));
  });
  lines.push('');
  lines.push('Gesamt gewählt: '+latestOrderData.chosen);
  return lines.join('\n');
}


function printOrder(){
  if(!latestOrderData || !latestOrderData.drinks.length){
    shareFeedback.textContent='Aktuell gibt es keine Bestellung zum Drucken.';
    return;
  }
  const rows=latestOrderData.drinks.map(d=>`<tr><td>${esc(d.emoji||'🥤')} ${esc(d.name)}</td><td>${esc((d.export_codes||[]).join(', '))}</td><td>${Number(d.qty)||0}</td></tr>`).join('');
  const stamp=esc(formatLocalTime(latestOrderData.updated_at));
  const html=`<!doctype html><html lang="de"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Getränkebestellung</title><style>
  body{font-family:Arial,sans-serif;color:#111;margin:24px}h1{font-size:22px;margin:0 0 4px}.meta{color:#555;margin-bottom:18px}table{width:100%;border-collapse:collapse;font-size:18px}th,td{text-align:left;padding:10px 8px;border-bottom:1px solid #bbb}th:last-child,td:last-child{text-align:right;font-weight:700;width:80px}.privacy{margin-top:18px;font-size:12px;color:#666}@media print{body{margin:10mm}}</style></head><body><h1>Strahlemännkes – Getränkebestellung</h1><div class="meta">Stand: ${stamp} Uhr</div><table><thead><tr><th>Getränk</th><th>Kürzel</th><th>Anzahl</th></tr></thead><tbody>${rows}</tbody></table><div class="privacy">Mitglieder werden nur mit Initialen angegeben.</div><script>window.addEventListener('load',()=>{setTimeout(()=>window.print(),100)});<\/script></body></html>`;
  const w=window.open('','_blank');
  if(!w){shareFeedback.textContent='Druckansicht konnte nicht geöffnet werden. Bitte Pop-ups für diese Seite erlauben.';return;}
  w.document.open();w.document.write(html);w.document.close();
}


function formatLocalTime(value){
  const d=new Date(value);
  if(Number.isNaN(d.getTime())) return String(value||'');
  return new Intl.DateTimeFormat('de-DE',{
    hour:'2-digit',minute:'2-digit',second:'2-digit',hour12:false
  }).format(d);
}

async function copyText(text){
  if(!text) throw new Error('Aktuell gibt es keine Bestellung zum Kopieren.');
  if(navigator.clipboard && window.isSecureContext){
    await navigator.clipboard.writeText(text);
    return;
  }
  const ta=document.createElement('textarea');
  ta.value=text; ta.style.position='fixed'; ta.style.opacity='0';
  document.body.appendChild(ta); ta.select();
  const ok=document.execCommand('copy'); ta.remove();
  if(!ok) throw new Error('Kopieren wird von diesem Browser nicht unterstützt.');
}

async function loadStatus(){
  const btn=document.getElementById('refreshBtn'); btn.disabled=true;
  try{
    const res=await fetch('/api/drink-status.php',{cache:'no-store'});
    if(!res.ok) throw new Error();
    const data=await res.json();
    latestOrderData=data;
    document.getElementById('chosenCount').textContent=data.chosen;
    document.getElementById('noneCount').textContent=data.none;
    const noneMembers=document.getElementById('noneMembers');
    if(noneMembers){const names=data.none_members||[];noneMembers.textContent=names.length?names.join(', '):'Alle Mitglieder haben ein Getränk gewählt.';}
    document.getElementById('updateTime').textContent='Stand '+formatLocalTime(data.updated_at)+' Uhr';
    grid.innerHTML='';
    empty.classList.toggle('d-none',data.drinks.length>0);
    data.drinks.forEach(d=>{
      const members=d.members.map(m=>`<span class="member-pill">${esc(m)}</span>`).join('');
      const col=document.createElement('div');
      col.className='col-12 col-sm-6 col-xl-4';
      col.innerHTML=`<article class="order-card h-100 p-3 p-md-4"><div class="d-flex align-items-start justify-content-between gap-3"><div><div class="order-emoji">${esc(d.emoji||'🥤')}</div><div class="order-name mt-2">${esc(d.name)}</div></div><div class="order-count">${d.qty}</div></div><details class="mt-3"><summary class="small text-secondary">Wer bestellt das?</summary><div class="mt-2">${members}</div></details></article>`;
      grid.appendChild(col);
    });
  }catch(e){
    document.getElementById('updateTime').textContent='Aktualisierung fehlgeschlagen';
  }finally{btn.disabled=false;}
}

document.getElementById('copyOrderBtn').addEventListener('click',async()=>{
  try{
    await copyText(buildOrderText());
    shareFeedback.textContent='Bestellliste wurde kopiert.';
  }catch(e){shareFeedback.textContent=e.message;}
});

document.getElementById('whatsappBtn').addEventListener('click',()=>{
  const text=buildOrderText();
  if(!text){shareFeedback.textContent='Aktuell gibt es keine Bestellung zum Teilen.';return;}
  window.open('https://wa.me/?text='+encodeURIComponent(text),'_blank','noopener');
});

document.getElementById('printOrderBtn').addEventListener('click',printOrder);

document.getElementById('refreshBtn').addEventListener('click',loadStatus);
loadStatus();
setInterval(loadStatus,15000);
</script>
<?php require __DIR__.'/includes/footer.php';?>
