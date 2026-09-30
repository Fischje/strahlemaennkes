<?php
$config=require __DIR__.'/../config.php';
?>
<div class="panel p-4 mt-4" id="pushStatusCard">
  <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
    <div>
      <div class="small text-secondary">Push-Benachrichtigungen auf diesem Gerät</div>
      <div class="fw-bold mt-1" id="pushStatusText">Status wird geprüft …</div>
      <div class="small text-secondary mt-1" id="pushStatusHint"></div>
    </div>
    <button class="btn btn-outline-secondary" type="button" id="pushToggleBtn" disabled>
      <i class="bi bi-bell me-1"></i> Wird geladen …
    </button>
  </div>
</div>
<script>
document.addEventListener('DOMContentLoaded',()=>{
  const btn=document.getElementById('pushToggleBtn');
  const text=document.getElementById('pushStatusText');
  const hint=document.getElementById('pushStatusHint');
  if(!btn || !text || !hint) return;

  const publicKey=<?=json_encode($config['push']['public_key'] ?? '')?>;

  async function refreshPushStatus(){
    try{
      const s=await SM.getPushStatus();
      btn.disabled=false;
      btn.classList.remove('btn-brand','btn-outline-danger','btn-outline-secondary');

      if(!s.supported){
        text.textContent='Auf diesem Gerät nicht unterstützt';
        hint.textContent='Dieser Browser unterstützt keine Web-Push-Benachrichtigungen.';
        btn.innerHTML='<i class="bi bi-bell-slash me-1"></i> Nicht verfügbar';
        btn.classList.add('btn-outline-secondary');
        btn.disabled=true;
        return;
      }

      if(s.permission==='denied'){
        text.textContent='Benachrichtigungen sind blockiert';
        hint.textContent='Bitte Benachrichtigungen in den Browser-/App-Einstellungen wieder erlauben.';
        btn.innerHTML='<i class="bi bi-bell-slash me-1"></i> Blockiert';
        btn.classList.add('btn-outline-danger');
        btn.disabled=true;
        return;
      }

      if(s.subscribed){
        text.textContent='Push-Benachrichtigungen sind aktiviert';
        hint.textContent='Dieses Gerät kann Getränkeerinnerungen und „Runde holen“-Hinweise empfangen.';
        btn.innerHTML='<i class="bi bi-bell-slash me-1"></i> Push deaktivieren';
        btn.classList.add('btn-outline-danger');
        btn.dataset.mode='disable';
      }else{
        text.textContent='Push-Benachrichtigungen sind nicht aktiviert';
        hint.textContent='Aktiviere Push für Getränkeerinnerungen und „Runde holen“-Hinweise.';
        btn.innerHTML='<i class="bi bi-bell me-1"></i> Push aktivieren';
        btn.classList.add('btn-brand');
        btn.dataset.mode='enable';
      }
    }catch(e){
      text.textContent='Status konnte nicht geprüft werden';
      hint.textContent=e.message;
      btn.disabled=true;
    }
  }

  btn.addEventListener('click',async()=>{
    btn.disabled=true;
    try{
      if(btn.dataset.mode==='disable'){
        await SM.disablePush();
      }else{
        await SM.enablePush(publicKey);
      }
      await refreshPushStatus();
    }catch(e){
      alert(e.message);
      await refreshPushStatus();
    }
  });

  refreshPushStatus();
});
</script>
