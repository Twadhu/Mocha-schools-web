// Deprecated shim: manager/school-admin/js/gradebook.js
// Gradebook is now provided via SPA page module js/pages/gradebook.js. This shim delegates to keep backward compatibility.
(function(){
  try{
    console.warn('[DEPRECATED] Use js/pages/gradebook.js via SpaLoader. Delegating to SPA module...');
    function delegate(){
      if(window.SpaLoader && typeof window.SpaLoader.loadAndInit==='function'){
        return window.SpaLoader.loadAndInit('gradebook');
      }
      var s = document.createElement('script');
      s.src = 'js/pages/gradebook.js'; s.async = true;
      s.onload = function(){ if(typeof window.initGradebook==='function') window.initGradebook(); };
      s.onerror = function(){ console.error('Failed to load gradebook page module'); };
      document.head.appendChild(s);
    }
    if(document.readyState==='loading'){
      document.addEventListener('DOMContentLoaded', delegate, { once:true });
    } else {
      delegate();
    }
  }catch(e){ console.error('gradebook shim failed', e); }
})();