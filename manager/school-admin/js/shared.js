// Shared helpers for school-admin pages
(function(){
  function buildApiUrl(path){
    try{
      let p = String(path||'');
      // absolute URL: return as-is
      if(/^https?:\/\//i.test(p)) return p;
      // remove leading slashes and duplicate api/
      p = p.replace(/^\/+/, '').replace(/^api\//i, '');
      // Compute base like the SPA shell: anchor to '/Schools-sites/' if present
      const loc = window.location;
      const parts = (loc.pathname||'/').split('/').filter(Boolean);
      const idx = parts.indexOf('Schools-sites');
      let base = '';
      if(idx !== -1){
        base = '/' + parts.slice(0, idx+1).join('/') + '/api/';
      } else {
        // Fallback to relative-to-current directory
        const dir = (loc.pathname||'/').replace(/[^\/]+$/,'');
        base = dir + (dir.endsWith('/')?'':'/') + 'api/';
      }
      return base + p;
    }catch(e){ return '/Schools-sites/api/'+String(path||'').replace(/^\/+/,'').replace(/^api\//i,''); }
  }
  async function apiFetch(path, opts={}){
    // Fallback: try sessionStorage token if not yet persisted
    let token = localStorage.getItem('authToken');
    if(!token){ try { token = sessionStorage.getItem('authToken'); if(token) localStorage.setItem('authToken', token); } catch(_){ } }
    const headers = Object.assign({ 'Content-Type':'application/json' }, opts.headers||{}, token? {'Authorization':'Bearer '+token} : {});
    // Localhost dev bypass headers (opt-in via localStorage 'devBypass'==='1')
    try{
      const host = window.location.hostname || '';
      const isLocal = host === 'localhost' || host === '127.0.0.1' || /^192\.168\./.test(host) || /^10\./.test(host) || /^172\.(1[6-9]|2[0-9]|3[0-1])\./.test(host);
      const wantBypass = isLocal && localStorage.getItem('devBypass') === '1';
      if(wantBypass){
        headers['X-Dev-Bypass'] = '1';
        // derive school id from URL ?sid=, currentUser, or sessionStorage
        let sid = null;
        try { sid = new URLSearchParams(window.location.search).get('sid'); } catch(_){ }
        if(!sid){ try{ const u = JSON.parse(localStorage.getItem('currentUser')||'null'); sid = (u&& (u.school_id||u.id)) ? (u.school_id||u.id) : null; }catch(_){} }
        if(!sid){ try{ sid = sessionStorage.getItem('schoolId'); }catch(_){} }
        if(sid){ headers['X-School-Id'] = String(sid); }
      }
    }catch(_){ }
    const url = /^https?:\/\//i.test(String(path||'')) ? path : buildApiUrl(path);
    const res = await fetch(url, Object.assign({}, opts, { headers }));
    if(res.status===401){
      const dev = localStorage.getItem('devBypass')==='1';
      if(!dev){ alert('انتهت الجلسة، يرجى تسجيل الدخول'); }
      location.href='../school-login.html';
      throw new Error('unauthorized');
    }
    return res;
  }
  async function apiJson(path, opts={}){
    const res = await apiFetch(path, opts);
    try { return await res.json(); } catch(_) { return {}; }
  }
  function escapeHtml(s){ return String(s||'').replace(/[&<>"']/g, m=>({"&":"&amp;","<":"&lt;",">":"&gt;","\"":"&quot;","'":"&#39;"}[m])); }
  window.mkApi = { buildApiUrl, apiFetch, apiJson, escapeHtml };

  // Sidebar collapse toggle wiring (idempotent)
  document.addEventListener('DOMContentLoaded', function(){
    try{
      const key = 'sidebarCollapsed';
      // Apply persisted state once
      const persisted = localStorage.getItem(key);
      if(persisted === '1'){ document.body.classList.add('collapsed-sidebar'); }

      // Wire toggler if present
      const toggler = document.querySelector('.menu-toggler');
      if(toggler && !toggler.__wired){
        toggler.__wired = true;
        toggler.addEventListener('click', function(){
          const willCollapse = !document.body.classList.contains('collapsed-sidebar');
          document.body.classList.toggle('collapsed-sidebar');
          localStorage.setItem(key, willCollapse ? '1' : '0');
        });
      }
    }catch(e){ /* no-op */ }
  });
})();
