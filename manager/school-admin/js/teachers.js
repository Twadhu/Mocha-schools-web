// Deprecated shim: manager/school-admin/js/teachers.js
// This legacy file is replaced by the SPA module at js/pages/teachers.js.
// If this file is included directly, we forward to the SPA loader to avoid duplication.
(function(){
  try{
    console.warn('[DEPRECATED] Use js/pages/teachers.js via SpaLoader. Delegating to SPA module...');
    function delegate(){
      if(window.SpaLoader && typeof window.SpaLoader.loadAndInit==='function'){
        return window.SpaLoader.loadAndInit('teachers');
      }
      // Fallback: dynamically load the page script then call its init
      var s = document.createElement('script');
      s.src = 'js/pages/teachers.js'; s.async = true;
      s.onload = function(){ if(typeof window.initTeachers==='function') window.initTeachers(); };
      s.onerror = function(){ console.error('Failed to load teachers page module'); };
      document.head.appendChild(s);
    }
    if(document.readyState==='loading'){
      document.addEventListener('DOMContentLoaded', delegate, { once:true });
    } else {
      delegate();
    }
  }catch(e){ console.error('teachers shim failed', e); }
})();
