// spa-loader.js - dynamic module loader for pages
(function(){
  if(!window.App) window.App = {};

  const registry = {
    students: { file: 'js/pages/students.js', depends: [] },
    teachers: { file: 'js/pages/teachers.js', depends: [] },
    subjects: { file: 'js/pages/subjects.js', depends: [] },
    schedule: { file: 'js/pages/schedule.js', depends: [] },
    grades: { file: 'js/pages/grades.js', depends: [] },
    activities: { file: 'js/pages/activities.js', depends: [] },
    reports: { file: 'js/pages/reports.js', depends: [] },
    settings: { file: 'js/pages/settings.js', depends: [] },
    'pending-requests': { file: 'js/pages/pending-requests.js', depends: [] },
    attendance: { file: 'js/pages/attendance.js', depends: [] },
    gradebook: { file: 'js/pages/gradebook.js', depends: [] },
  };

  async function ensureModule(page){
    const entry = registry[page];
    if(!entry) return;
    if(entry._loaded) return;
    // load dependencies sequentially (could be improved to parallel if needed)
    for(const dep of entry.depends){ await ensureModule(dep); }
    await loadScript(entry.file);
    entry._loaded = true;
  }

  function loadScript(src){
    return new Promise((resolve,reject)=>{
      // avoid duplicates or wait for an in-flight load
      const existing = document.querySelector('script[data-page-src="'+src+'"]');
      if(existing){
        if(existing.dataset.loaded==='1'){ return resolve(); }
        existing.addEventListener('load', ()=>resolve(), { once:true });
        existing.addEventListener('error', ()=>reject(new Error('Failed to load '+src)), { once:true });
        return;
      }
      const s=document.createElement('script');
      s.src=src; s.async=true; s.dataset.pageSrc = src; s.onload=()=>{ s.dataset.loaded='1'; resolve(); }; s.onerror=()=>reject(new Error('Failed to load '+src));
      document.head.appendChild(s);
    });
  }

  async function loadAndInit(page){
    await ensureModule(page);
    const initFn = 'init'+page.charAt(0).toUpperCase()+page.slice(1).replace(/-(\w)/g,(_,c)=>c.toUpperCase());
    if(typeof window[initFn]==='function'){
      await window[initFn]();
    }
  }

  window.SpaLoader = { loadAndInit };

  // Patch App.loadPage to defer module loading
  const originalLoadPage = App.loadPage;
  App.loadPage = async function(page){
    const container = document.getElementById('pageContent');
    container.innerHTML = `<div class="placeholder-cell">${App.t('loading')}...</div>`;
    const template = document.getElementById(`tpl-${page}`);
    if(template){ container.innerHTML = template.innerHTML; }
    try {
      await loadAndInit(page);
    } catch(e){
      console.error('Module load/init failed for', page, e);
      container.innerHTML = `<div class='placeholder-cell error'>${App.t('loadError')}</div>`;
    }
    App.setLanguage(App.currentLang);
  };
})();
