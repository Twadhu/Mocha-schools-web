// Deprecated shim: manager/school-admin/js/schedule.js
// This legacy file is replaced by the SPA module at js/pages/schedule.js.
// If this file is included directly, delegate to SpaLoader to keep behavior consistent.
(function(){
  try{
    console.warn('[DEPRECATED] Use js/pages/schedule.js via SpaLoader. Delegating to SPA module...');
    function delegate(){
      if(window.SpaLoader && typeof window.SpaLoader.loadAndInit==='function'){
        return window.SpaLoader.loadAndInit('schedule');
      }
      var s = document.createElement('script');
      s.src = 'js/pages/schedule.js'; s.async = true;
      s.onload = function(){ if(typeof window.initSchedule==='function') window.initSchedule(); };
      s.onerror = function(){ console.error('Failed to load schedule page module'); };
      document.head.appendChild(s);
    }
    if(document.readyState==='loading'){
      document.addEventListener('DOMContentLoaded', delegate, { once:true });
    } else {
      delegate();
    }
  }catch(e){ console.error('schedule shim failed', e); }
})();
