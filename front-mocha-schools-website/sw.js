self.addEventListener('install', (event) => {
  event.waitUntil((async ()=>{
    const cache = await caches.open('mocha-shell-v1');
    await cache.addAll([
      './',
      './index.html',
      './assets/css/styles.css',
      './assets/js/config.js',
      './assets/js/i18n.js',
      './assets/js/api.js',
      './assets/js/carousel.js',
      './assets/js/forms.js',
      './assets/js/main.js'
    ]);
  })());
});

self.addEventListener('fetch', (event) => {
  event.respondWith((async ()=>{
    try { return await fetch(event.request); } catch(e){
      const cached = await caches.match(event.request);
      if(cached) return cached;
      throw e;
    }
  })());
});
