const CACHE='sm-static-beta-0.7.1';
const STATIC=[
  '/offline.html',
  '/assets/css/app.css',
  '/assets/js/app.js',
  '/assets/icons/icon-192.png',
  '/assets/icons/icon-512.png',
  '/assets/brand/strahlemaennkes-logo.png',
  '/favicon.ico',
  '/manifest.webmanifest'
];
self.addEventListener('install',event=>{
  event.waitUntil(
    caches.open(CACHE).then(cache =>
      Promise.all(STATIC.map(url => fetch(new Request(url,{cache:'reload'})).then(response => {
        if(response.ok) return cache.put(url,response);
      })))
    )
  );
  self.skipWaiting();
});
self.addEventListener('activate',event=>{event.waitUntil(caches.keys().then(keys=>Promise.all(keys.filter(k=>k!==CACHE).map(k=>caches.delete(k)))));self.clients.claim();});
self.addEventListener('fetch',event=>{
  const req=event.request;
  if(req.method!=='GET') return;
  const url=new URL(req.url);
  if(req.mode==='navigate'){
    event.respondWith(fetch(req).catch(()=>caches.match('/offline.html')));
    return;
  }
  if(url.origin===location.origin && (url.pathname.startsWith('/assets/') || url.pathname==='/manifest.webmanifest')){
    event.respondWith(caches.match(req).then(cached=>cached||fetch(req).then(res=>{const copy=res.clone();caches.open(CACHE).then(c=>c.put(req,copy));return res;})));
  }
});
self.addEventListener('push',event=>{
  let data={title:'Strahlemännkes',body:'Es gibt eine neue Mitteilung.',url:'/dashboard.php',tag:'strahlemaennkes'};
  try{data={...data,...event.data.json()};}catch(e){}
  event.waitUntil(self.registration.showNotification(data.title,{
    body:data.body,
    icon:'/assets/icons/icon-192.png',
    badge:'/assets/icons/badge-96.png',
    tag:data.tag||'strahlemaennkes',
    renotify:true,
    data:{url:data.url}
  }));
});
self.addEventListener('notificationclick',event=>{
  event.notification.close();
  const target=event.notification.data?.url||'/member-drink.php';
  event.waitUntil(clients.matchAll({type:'window',includeUncontrolled:true}).then(list=>{
    for(const client of list){if('focus' in client){client.navigate(target);return client.focus();}}
    return clients.openWindow(target);
  }));
});
