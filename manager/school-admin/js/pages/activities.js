(function(){
  // Translations
  if(window.AppCore){ AppCore.registerTranslations({
    ar:{
      activitiesTitle:'إدارة الأنشطة والفعاليات', activitiesSubtitle:'ارفع صورًا أو فيديوهات للأنشطة، وستظهر في صفحة أنشطة المدرسة.',
      uploadNewActivity:'رفع نشاط جديد', activityTitleLabel:'عنوان النشاط', activityDescriptionLabel:'وصف / ملخص', mediaFileLabel:'ملف الوسائط (صورة أو فيديو)',
      fileHint:'الصيغ المدعومة: MP4, WEBM, JPG, PNG, GIF (الحد الأقصى 100MB)', uploadNow:'رفع الآن', currentActivities:'الأنشطة الحالية', refresh:'تحديث',
      connectionError:'خطأ اتصال بالخادم', noActivities:'لا توجد أنشطة بعد.', pickFileFirst:'الرجاء اختيار ملف أولاً.', uploading:'جارٍ الرفع...', uploadSuccess:'تم الرفع بنجاح!',
      networkError:'فشل الاتصال بالشبكة.', serverError:'خطأ في الخادم', confirmDelete:'هل أنت متأكد من حذف هذا النشاط؟', deleteSuccess:'تم حذف النشاط بنجاح.'
    },
    en:{
      activitiesTitle:'Manage Activities & Events', activitiesSubtitle:'Upload photos or videos to appear on the school activities page.',
      uploadNewActivity:'Upload New Activity', activityTitleLabel:'Activity Title', activityDescriptionLabel:'Description / Summary', mediaFileLabel:'Media file (image or video)',
      fileHint:'Supported: MP4, WEBM, JPG, PNG, GIF (Max 100MB)', uploadNow:'Upload Now', currentActivities:'Current Activities', refresh:'Refresh',
      connectionError:'Connection error', noActivities:'No activities yet.', pickFileFirst:'Please select a file first.', uploading:'Uploading...', uploadSuccess:'Uploaded successfully!',
      networkError:'Network error.', serverError:'Server error', confirmDelete:'Are you sure to delete this activity?', deleteSuccess:'Activity deleted.'
    }
  }); }

  function t(a,b){ return (App?.currentLang||'ar')==='ar'?a:b; }

  function getSchoolKey(){
    try{ const urlSid = new URLSearchParams(location.search).get('sid'); if(urlSid) return urlSid; }catch(_){ }
    try{ const sid = sessionStorage.getItem('schoolId'); if(sid) return sid; }catch(_){ }
    try{ const u = JSON.parse(localStorage.getItem('currentUser')||'null'); if(u && (u.school_id||u.id)) return String(u.school_id||u.id); }catch(_){ }
    return 's1';
  }

  function buildPage(){
    return `
      <header class="page-header">
        <div>
          <h1 class="page-title">${t('إدارة الأنشطة والفعاليات','Manage Activities & Events')}</h1>
          <p class="page-subtitle">${t('ارفع صورًا أو فيديوهات للأنشطة، وستظهر في صفحة أنشطة المدرسة.','Upload photos or videos to appear on the school activities page.')}</p>
        </div>
      </header>
      <div class="card">
        <div class="card-header"><h3 class="card-title">${t('رفع نشاط جديد','Upload New Activity')}</h3></div>
        <div class="card-body">
          <form id="uploadForm" novalidate>
            <div class="form-grid">
              <div class="form-group" style="grid-column:1 / -1">
                <label for="title">${t('عنوان النشاط','Activity Title')}</label>
                <input type="text" id="title" required placeholder="${t('مثال: رحلة علمية','e.g. Science trip')}" />
              </div>
              <div class="form-group" style="grid-column:1 / -1">
                <label for="description">${t('وصف / ملخص','Description / Summary')}</label>
                <textarea id="description" placeholder="${t('أدخل وصفاً قصيراً للنشاط...','Short description...')}" rows="3"></textarea>
              </div>
              <div class="form-group" style="grid-column:1 / -1">
                <label for="file">${t('ملف الوسائط (صورة أو فيديو)','Media file (image or video)')}</label>
                <input type="file" id="file" accept="video/mp4,video/webm,image/*" required />
                <div class="hint">${t('الصيغ المدعومة: MP4, WEBM, JPG, PNG, GIF (الحد الأقصى 100MB)','Supported: MP4, WEBM, JPG, PNG, GIF (Max 100MB)')}</div>
              </div>
            </div>
            <div class="form-actions" style="display:flex;gap:8px;align-items:center;margin-top:8px">
              <div id="uploadStatus" class="upload-status"></div>
              <button type="submit" id="btnUpload" class="btn btn-primary"><i class="fas fa-upload"></i> ${t('رفع الآن','Upload Now')}</button>
            </div>
            <div id="progressWrap" class="progress-bar" style="display:none;margin-top:8px;height:6px;background:#eef2ff;border-radius:8px;overflow:hidden">
              <div id="progressBar" class="progress-bar-fill" style="height:100%;width:0;background:#3b82f6"></div>
            </div>
          </form>
        </div>
      </div>
      <div class="page-header" style="margin-top:24px">
        <div><h2 class="page-title">${t('الأنشطة الحالية','Current Activities')}</h2></div>
        <div class="quick-actions"><button id="btnRefresh" class="btn btn-outline"><i class="fas fa-sync"></i> ${t('تحديث','Refresh')}</button></div>
      </div>
      <div id="loader" class="loader" style="display:none"></div>
      <div id="activitiesGrid" class="cards-grid"></div>
    `;
  }

  function setBusy(busy, els){ els.loader.style.display = busy ? 'block' : 'none'; els.grid.style.display = busy ? 'none' : 'grid'; }
  function updateStatus(els, message, type){ els.status.textContent = message || ''; els.status.className = `upload-status ${type||''}`; }
  function setProgress(els, pct){ els.progressWrap.style.display='block'; els.progressBar.style.width = `${pct|0}%`; }

  async function fetchActivities(state, els){
    setBusy(true, els);
    try{
      const res = await mkApi.apiFetch(`activities.php?school_id=${encodeURIComponent(state.schoolKey)}`);
      const data = await res.json();
      state.activities = (data && data.ok && Array.isArray(data.activities)) ? data.activities : [];
      renderGrid(state, els);
    }catch(e){ console.error(e); els.grid.innerHTML = `<div class="placeholder-cell error">${t('خطأ اتصال بالخادم','Connection error')}</div>`; }
    finally{ setBusy(false, els); }
  }

  function renderGrid(state, els){
    if(!state.activities.length){ els.grid.innerHTML = `<div class="placeholder-cell">${t('لا توجد أنشطة بعد.','No activities yet.')}</div>`; return; }
    els.grid.innerHTML = state.activities.map(a=>{
      const isVideo = String(a.media_type)==='video';
      const mediaEl = isVideo ? `<video preload="metadata" src="${a.file_url||a.media_url}#t=0.5" aria-label="${mkApi.escapeHtml(a.title||'')}"></video>`
                               : `<img src="${a.file_url||a.media_url}" alt="${mkApi.escapeHtml(a.title||'')}" loading="lazy">`;
      const when = a.created_at ? new Date(a.created_at).toLocaleDateString(App.currentLang==='ar'?'ar-EG-u-nu-latn':'en-US') : '';
      return `
        <div class="card" data-id="${a.id}">
          <div class="card-media">${mediaEl}</div>
          <div class="card-body"><h4 class="card-title">${mkApi.escapeHtml(a.title||'')}</h4><p class="card-text">${mkApi.escapeHtml(a.description||'')}</p></div>
          <div class="card-footer"><span class="muted-text">${when}</span><div class="actions"><button class="btn btn-icon act-delete" title="${t('حذف','Delete')}"><i class="fas fa-trash"></i></button></div></div>
        </div>`;
    }).join('');
  }

  function wireUpload(state, els){
    els.form.addEventListener('submit', (e)=>{
      e.preventDefault(); updateStatus(els, '');
      const file = els.fileInput.files[0]; if(!file){ updateStatus(els, t('الرجاء اختيار ملف أولاً.','Please select a file first.'), 'error'); return; }
      els.btnUpload.disabled = true; updateStatus(els, t('جارٍ الرفع...','Uploading...'), 'info');
      const fd = new FormData();
      fd.append('title', (els.titleInput.value||'').trim());
      fd.append('description', (els.descInput.value||'').trim());
      fd.append('file', file);
      fd.append('school_key', state.schoolKey);
      const xhr = new XMLHttpRequest();
      xhr.open('POST', mkApi.buildApiUrl('activities.php'));
      const token = localStorage.getItem('authToken') || sessionStorage.getItem('authToken') || '';
      if(token){ xhr.setRequestHeader('Authorization', 'Bearer '+token); }
      xhr.upload.onprogress = (ev)=>{ if(ev.lengthComputable){ setProgress(els, Math.round((ev.loaded/ev.total)*100)); } };
      xhr.onload = ()=>{
        els.btnUpload.disabled=false; setProgress(els,0); els.progressWrap.style.display='none';
        if(xhr.status>=200 && xhr.status<300){ try{ const data=JSON.parse(xhr.responseText); if(data.ok){ updateStatus(els, t('تم الرفع بنجاح!','Uploaded successfully!'), 'success'); els.form.reset(); fetchActivities(state, els); } else { throw new Error(data.message||t('خطأ في الخادم','Server error')); } } catch(err){ updateStatus(els, err.message, 'error'); }
        } else { updateStatus(els, `${t('خطأ في الخادم','Server error')}: ${xhr.statusText||xhr.status}`, 'error'); }
      };
      xhr.onerror = ()=>{ els.btnUpload.disabled=false; updateStatus(els, t('فشل الاتصال بالشبكة.','Network error.'), 'error'); };
      xhr.send(fd);
    });
  }

  function wireDeletion(state, els){
    els.grid.addEventListener('click', async (ev)=>{
      const btn = ev.target.closest('.act-delete'); if(!btn) return; const card = btn.closest('.card'); const id = card?.dataset?.id; if(!id) return;
      if(!confirm(t('هل أنت متأكد من حذف هذا النشاط؟','Are you sure to delete this activity?'))) return;
      try{ const res = await mkApi.apiFetch(`activities.php?id=${encodeURIComponent(id)}`, { method:'DELETE' }); const data = await res.json(); if(data?.ok){ card.remove(); AppCore?.showToast?.(t('تم حذف النشاط بنجاح.','Activity deleted.'),'success'); } else { throw new Error(data?.message||t('خطأ في الخادم','Server error')); } }
      catch(err){ AppCore?.showToast?.(err.message||t('خطأ اتصال بالخادم','Connection error'),'error'); }
    });
  }

  window.initActivities = async function(host){
    const root = host || document.getElementById('pageContent');
    if(!root) return;
    root.innerHTML = buildPage();
    const state = { schoolKey: getSchoolKey(), activities: [] };
    const els = {
      grid: document.getElementById('activitiesGrid'), loader: document.getElementById('loader'), form: document.getElementById('uploadForm'),
      btnUpload: document.getElementById('btnUpload'), status: document.getElementById('uploadStatus'), progressWrap: document.getElementById('progressWrap'),
      progressBar: document.getElementById('progressBar'), btnRefresh: document.getElementById('btnRefresh'), fileInput: document.getElementById('file'),
      titleInput: document.getElementById('title'), descInput: document.getElementById('description')
    };
    wireUpload(state, els);
    wireDeletion(state, els);
    els.btnRefresh.addEventListener('click', ()=> fetchActivities(state, els));
    await fetchActivities(state, els);
    // Re-apply i18n
    try{ App.setLanguage(App.currentLang||'ar'); }catch(_){ }
  };
  // Compatibility wrapper so external code can call App.renderActivitiesPage(container)
  if(!window.App) window.App = {};
  window.App.renderActivitiesPage = async function(container){
    await window.initActivities(container instanceof HTMLElement ? container : undefined);
  };
})();
