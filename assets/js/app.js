window.SM = window.SM || {};
SM.urlBase64ToUint8Array = function(base64String){const padding='='.repeat((4-base64String.length%4)%4);const base64=(base64String+padding).replace(/-/g,'+').replace(/_/g,'/');const raw=atob(base64);return Uint8Array.from([...raw].map(c=>c.charCodeAt(0)));};

SM.enablePush = async function(publicKey){
  if(!('serviceWorker' in navigator) || !('PushManager' in window)) throw new Error('Browser unterstützt Web-Push nicht.');
  if(!publicKey) throw new Error('Push ist auf dem Server noch nicht vollständig konfiguriert.');
  const reg=await navigator.serviceWorker.ready;
  const permission=await Notification.requestPermission();
  if(permission!=='granted') throw new Error('Benachrichtigungen wurden nicht erlaubt.');
  let sub=await reg.pushManager.getSubscription();
  if(!sub) sub=await reg.pushManager.subscribe({userVisibleOnly:true,applicationServerKey:SM.urlBase64ToUint8Array(publicKey)});
  const res=await fetch('/push-subscribe.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(sub),cache:'no-store'});
  let responseData={};
  try{responseData=await res.json();}catch(e){}
  if(!res.ok || responseData.ok===false) throw new Error(responseData.error || ('Push-Abo konnte nicht gespeichert werden (HTTP '+res.status+').'));
  return true;
};

SM.getPushStatus = async function(){
  if(!('serviceWorker' in navigator) || !('PushManager' in window) || !('Notification' in window)){
    return {supported:false, permission:'unsupported', subscribed:false, subscription:null};
  }
  const reg=await navigator.serviceWorker.ready;
  const subscription=await reg.pushManager.getSubscription();
  return {
    supported:true,
    permission:Notification.permission,
    subscribed:!!subscription,
    subscription:subscription
  };
};

SM.disablePush = async function(){
  if(!('serviceWorker' in navigator) || !('PushManager' in window)) throw new Error('Browser unterstützt Web-Push nicht.');
  const reg=await navigator.serviceWorker.ready;
  const sub=await reg.pushManager.getSubscription();
  const endpoint=sub?.endpoint || '';
  if(sub){
    const ok=await sub.unsubscribe();
    if(!ok) throw new Error('Das Push-Abo konnte im Browser nicht deaktiviert werden.');
  }
  const res=await fetch('/push-unsubscribe.php',{
    method:'POST',
    headers:{'Content-Type':'application/json'},
    body:JSON.stringify({endpoint:endpoint}),
    cache:'no-store'
  });
  let responseData={};
  try{responseData=await res.json();}catch(e){}
  if(!res.ok || responseData.ok===false) throw new Error(responseData.error || ('Push-Abo konnte serverseitig nicht entfernt werden (HTTP '+res.status+').'));
  return true;
};



SM.refreshActiveRoundWidgets = async function(){
  const widgets=[...document.querySelectorAll('[data-active-round-widget]')];
  if(!widgets.length) return;
  try{
    const res=await fetch('/api/my-active-round.php',{cache:'no-store'});
    if(!res.ok) return;
    const data=await res.json();

    widgets.forEach(widget=>{
      if(!data.active){
        widget.hidden=true;
        return;
      }
      const detail=widget.querySelector('.round-widget-detail');
      if(detail) detail.textContent='1× Runde · '+data.reason;
      widget.hidden=false;
    });
  }catch(e){}
};

SM.isIOS = /iphone|ipad|ipod/i.test(navigator.userAgent);
SM.isStandalone = window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;
SM.deferredInstallPrompt = null;

window.addEventListener('beforeinstallprompt', (event) => {
  event.preventDefault();
  SM.deferredInstallPrompt = event;
  document.querySelectorAll('[data-pwa-install]').forEach(el => el.hidden = false);
});
window.addEventListener('appinstalled', () => {
  SM.deferredInstallPrompt = null;
  document.querySelectorAll('[data-pwa-install]').forEach(el => el.hidden = true);
});

SM.installPwa = async function(){
  if(SM.isStandalone) return {installed:true};
  if(SM.deferredInstallPrompt){
    SM.deferredInstallPrompt.prompt();
    const result = await SM.deferredInstallPrompt.userChoice;
    if(result.outcome === 'accepted') SM.deferredInstallPrompt = null;
    return {installed: result.outcome === 'accepted'};
  }
  if(SM.isIOS){
    alert('Auf dem iPhone: unten auf „Teilen“ tippen und anschließend „Zum Home-Bildschirm“ wählen.');
    return {installed:false};
  }
  alert('Falls kein Installationsdialog erscheint: Öffne das Browser-Menü und wähle „App installieren“ oder „Zum Startbildschirm hinzufügen“.');
  return {installed:false};
};

SM.setOnlineState = function(){
  document.querySelectorAll('[data-online-indicator]').forEach(el => el.classList.toggle('offline', !navigator.onLine));
};

SM.enableWakeLock = async function(button){
  if(!('wakeLock' in navigator)) { alert('Dein Browser unterstützt das Wachhalten des Displays nicht.'); return; }
  try {
    if(SM.wakeLock){ await SM.wakeLock.release(); SM.wakeLock=null; button.classList.remove('active'); button.innerHTML='<i class="bi bi-lightbulb me-1"></i> Display wach'; return; }
    SM.wakeLock=await navigator.wakeLock.request('screen');
    button.classList.add('active'); button.innerHTML='<i class="bi bi-lightbulb-fill me-1"></i> Display bleibt wach';
    SM.wakeLock.addEventListener('release',()=>{button.classList.remove('active');});
  } catch(e){ alert('Display konnte nicht wach gehalten werden.'); }
};



/* Mobile/PWA Pull-to-Refresh */
SM.initPullToRefresh = function(){
  if(!('ontouchstart' in window) && navigator.maxTouchPoints<1) return;

  const indicator=document.createElement('div');
  indicator.className='pull-refresh-indicator';
  indicator.setAttribute('aria-hidden','true');
  indicator.innerHTML='<div class="pull-refresh-pill"><i class="bi bi-arrow-down"></i><span>Zum Aktualisieren ziehen</span></div>';
  document.body.appendChild(indicator);

  let startY=0;
  let pulling=false;
  let ready=false;
  let startedOnInteractive=false;
  const threshold=78;
  const maxPull=125;

  const isInteractiveTarget=el=>{
    if(!el || !(el instanceof Element)) return false;
    return !!el.closest('input, textarea, select, button, [contenteditable="true"], .wysiwyg-editor, .modal, .offcanvas');
  };

  const atTop=()=>{
    const y=window.scrollY || document.documentElement.scrollTop || document.body.scrollTop || 0;
    return y<=0;
  };

  const reset=()=>{
    pulling=false;
    ready=false;
    indicator.classList.remove('show','ready','refreshing');
    indicator.style.setProperty('--pull-distance','0px');
    const icon=indicator.querySelector('i');
    const label=indicator.querySelector('span');
    if(icon) icon.className='bi bi-arrow-down';
    if(label) label.textContent='Zum Aktualisieren ziehen';
  };

  window.addEventListener('touchstart',e=>{
    if(e.touches.length!==1) return;
    startedOnInteractive=isInteractiveTarget(e.target);
    if(startedOnInteractive || !atTop()) return;
    startY=e.touches[0].clientY;
    pulling=true;
    ready=false;
  },{passive:true});

  window.addEventListener('touchmove',e=>{
    if(!pulling || startedOnInteractive || e.touches.length!==1) return;

    const raw=e.touches[0].clientY-startY;
    if(raw<=0 || !atTop()){
      reset();
      return;
    }

    const distance=Math.min(maxPull,raw*0.55);
    indicator.style.setProperty('--pull-distance',distance+'px');
    indicator.classList.add('show');

    ready=raw>=threshold;
    indicator.classList.toggle('ready',ready);

    const icon=indicator.querySelector('i');
    const label=indicator.querySelector('span');
    if(ready){
      if(icon) icon.className='bi bi-arrow-up';
      if(label) label.textContent='Loslassen zum Aktualisieren';
    }else{
      if(icon) icon.className='bi bi-arrow-down';
      if(label) label.textContent='Zum Aktualisieren ziehen';
    }
  },{passive:true});

  window.addEventListener('touchend',()=>{
    if(!pulling) return;

    if(ready){
      pulling=false;
      indicator.classList.add('show','refreshing');
      indicator.classList.remove('ready');
      indicator.style.setProperty('--pull-distance','58px');
      const icon=indicator.querySelector('i');
      const label=indicator.querySelector('span');
      if(icon) icon.className='bi bi-arrow-clockwise pull-refresh-spin';
      if(label) label.textContent='Wird aktualisiert …';
      setTimeout(()=>window.location.reload(),180);
      return;
    }

    reset();
  },{passive:true});

  window.addEventListener('touchcancel',reset,{passive:true});
};

document.addEventListener('DOMContentLoaded', () => {
  SM.initPullToRefresh();
  if('serviceWorker' in navigator) {
    let reloading=false;
    navigator.serviceWorker.addEventListener('controllerchange',()=>{
      if(reloading) return; reloading=true;
      const key='sm-reloaded-'+(window.SM_APP_VERSION||'current');
      if(!sessionStorage.getItem(key)){ sessionStorage.setItem(key,'1'); window.location.reload(); }
    });
    navigator.serviceWorker.register('/service-worker.js?v='+encodeURIComponent(window.SM_APP_VERSION||Date.now()),{updateViaCache:'none'}).then(reg=>reg.update()).catch(()=>{});
  }
  SM.setOnlineState();
  window.addEventListener('online', SM.setOnlineState);
  window.addEventListener('offline', SM.setOnlineState);
  document.querySelectorAll('[data-pwa-install]').forEach(btn => {
    btn.hidden = SM.isStandalone;
    btn.addEventListener('click', SM.installPwa);
  });
  document.querySelectorAll('[data-wake-lock]').forEach(btn => btn.addEventListener('click',()=>SM.enableWakeLock(btn)));
  SM.refreshActiveRoundWidgets();
  if(document.querySelector('[data-active-round-widget]')) setInterval(SM.refreshActiveRoundWidgets,5000);
});
