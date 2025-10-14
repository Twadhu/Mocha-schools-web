// app-core.js - shared SPA utilities (toast, modal, dynamic scripts, translations)
(function(){
  const AppCore = {};
  AppCore._loadedScripts = new Set();

  AppCore.loadExternalScript = function(url){
    if(AppCore._loadedScripts.has(url)) return Promise.resolve();
    return new Promise((resolve,reject)=>{
      const s=document.createElement('script');
      s.src=url; s.async=true; s.onload=()=>{AppCore._loadedScripts.add(url); resolve();};
      s.onerror=()=>reject(new Error('Failed to load '+url));
      document.head.appendChild(s);
    });
  };

  AppCore.ensureLib = async function(key, testFn, url){
    if(testFn()) return; await AppCore.loadExternalScript(url); if(!testFn()) throw new Error('Library '+key+' failed to load');
  };

  AppCore.registerTranslations = function(bundle){
    if(!window.App || !App.translations) return;
    for(const lang of Object.keys(bundle)){
      App.translations[lang] = Object.assign(App.translations[lang]||{}, bundle[lang]);
    }
    // Re-apply current language so new keys reflect immediately
    if(typeof App.setLanguage==='function') App.setLanguage(App.currentLang);
  };

  AppCore.showToast = function(msg,type='info',timeout=3000){
    let host = document.getElementById('snackbarHost');
    if(!host){ host=document.createElement('div'); host.id='snackbarHost'; document.body.appendChild(host); }
    const el=document.createElement('div');
    el.className='snack snack-'+(type==='error'?'error': type==='success'?'success':'info');
    el.textContent = msg;
    host.appendChild(el);
    requestAnimationFrame(()=>el.classList.add('in'));
    setTimeout(()=>{ el.classList.add('out'); setTimeout(()=>el.remove(),420); }, timeout);
  };

  AppCore.openModal = function(html){
    const wrap=document.createElement('div');
    wrap.className='modal';
    wrap.innerHTML=html;
    document.body.appendChild(wrap);
    requestAnimationFrame(()=>wrap.classList.remove('modal-hidden'));
    return wrap;
  };
  AppCore.closeModal = function(node){ if(!node) return; node.classList.add('modal-hidden'); setTimeout(()=>node.remove(),300); };

  window.AppCore = AppCore;
})();
