(function(){
  if(window.AppCore){ AppCore.registerTranslations({
    ar:{ addSubject:'إضافة مادة', subjectsSubtitle:'إدارة المواد الدراسية', subjects:'المواد الدراسية', name:'الاسم', code:'الكود', actions:'الإجراءات', edit:'تعديل', del:'حذف', save:'حفظ', cancel:'إلغاء', newSubject:'مادة جديدة', updateSubject:'تحديث مادة', deleteConfirm:'هل تريد حذف هذه المادة؟', noData:'لا يوجد بيانات', loading:'جاري التحميل' },
    en:{ addSubject:'Add Subject', subjectsSubtitle:'Manage academic subjects', subjects:'Subjects', name:'Name', code:'Code', actions:'Actions', edit:'Edit', del:'Delete', save:'Save', cancel:'Cancel', newSubject:'New Subject', updateSubject:'Update Subject', deleteConfirm:'Delete this subject?', noData:'No data', loading:'Loading' }
  }); }

  function t(ar,en){ return (window.App?.currentLang||'ar')==='ar'?ar:en; }
  async function apiList(){ const d=await mkApi.apiJson('admin.php?path=subjects'); if(!d.ok) throw new Error(d.message||'Failed'); return d.subjects||[]; }
  async function apiCreate(name){ const d=await mkApi.apiJson('admin.php?path=subjects',{method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify({name})}); if(!d.ok) throw new Error(d.message||'Failed'); return d; }
  async function apiUpdate(id,name){ const d=await mkApi.apiJson('admin.php?path=subjects/'+id,{method:'PUT', headers:{'Content-Type':'application/json'}, body:JSON.stringify({name})}); if(!d.ok) throw new Error(d.message||'Failed'); return d; }
  async function apiDelete(id){ const res=await mkApi.apiFetch('admin.php?path=subjects/'+id,{method:'DELETE'}); const d=await res.json().catch(()=>({ok:false})); if(!d.ok) throw new Error(d.message||'Failed'); return d; }

  function ensureMarkup(host){
    const root = host || document.getElementById('pageContent'); if(!root) return { tbody:null, addBtn:null };
    let tbody = root.querySelector('#subjectsList'); let addBtn = root.querySelector('#btnAddSubject');
    if(tbody && addBtn) return { tbody, addBtn };
    root.innerHTML = `
      <div class="page-header">
        <div><h1 class="page-title">${t('المواد الدراسية','Subjects')}</h1><p class="page-subtitle">${t('إدارة المواد الدراسية','Manage academic subjects')}</p></div>
        <div><button class="btn btn-primary" id="btnAddSubject"><i class="fas fa-plus"></i> ${t('إضافة مادة','Add Subject')}</button></div>
      </div>
      <div class="card">
        <div class="card-body">
          <div class="toolbar" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin-bottom:8px">
            <input type="search" id="subjectsSearch" class="form-control" style="max-width:280px" placeholder="${t('بحث...','Search...')}">
            <label class="muted" style="display:flex;align-items:center;gap:6px">${t('حجم الصفحة','Page size')}
              <select id="subjectsPageSize" class="form-control" style="width:88px">
                <option value="10">10</option>
                <option value="25">25</option>
                <option value="50">50</option>
                <option value="100">100</option>
              </select>
            </label>
            <span id="subjectsCounter" class="muted"></span>
          </div>
          <div class="table-container">
            <table class="table modern-table"><thead><tr><th>${t('الاسم','Name')}</th><th>${t('الكود','Code')}</th><th style="text-align:center">${t('الإجراءات','Actions')}</th></tr></thead>
            <tbody id="subjectsList"></tbody></table>
          </div>
          <div class="pagination" id="subjectsPager" style="display:flex;gap:6px;justify-content:center;margin-top:8px"></div>
        </div>
      </div>`;
    return { tbody: root.querySelector('#subjectsList'), addBtn: root.querySelector('#btnAddSubject') };
  }

  function openModal(initial){
    const wrap=document.createElement('div'); wrap.className='modal-overlay';
    const title = initial? t('تحديث مادة','Update Subject'): t('مادة جديدة','New Subject');
    const val = initial?.name||'';
    wrap.innerHTML = `
      <div class="modal-content" style="max-width:420px">
        <div class="modal-header"><h3 class="modal-title">${title}</h3></div>
        <form id="subjectForm" class="modal-body">
          <div class="form-group"><label>${t('الاسم','Name')}</label><input name="name" value="${mkApi.escapeHtml(val)}" required></div>
        </form>
        <div class="modal-footer"><button class="btn btn-outline" data-act="cancel">${t('إلغاء','Cancel')}</button><button class="btn btn-primary" id="btnSaveSubj">${t('حفظ','Save')}</button></div>
      </div>`;
    document.body.appendChild(wrap); setTimeout(()=>wrap.classList.add('visible'),10);
    return { wrap, form: wrap.querySelector('#subjectForm'), saveBtn: wrap.querySelector('#btnSaveSubj') };
  }

  function closeModal(wrap){ if(!wrap) return; wrap.classList.remove('visible'); setTimeout(()=>wrap.remove(),250); }

  // Accept optional host for compatibility mounting
  window.initSubjects = async function(host){
    const { tbody, addBtn } = ensureMarkup(host); if(!tbody || !addBtn) return;
  const root = tbody.closest('.card');
    const searchEl = root.querySelector('#subjectsSearch');
    const counterEl = root.querySelector('#subjectsCounter');
    const pagerEl = root.querySelector('#subjectsPager');
  const pageSizeEl = root.querySelector('#subjectsPageSize');
  let all=[], view=[], page=1, pageSize=parseInt(localStorage.getItem('subjectsPageSize')||'10',10)||10;
  if(pageSizeEl){ pageSizeEl.value=String(pageSize); }

    function updateCounter(){ if(!counterEl) return; const total=view.length; const shown=Math.min(total, page*pageSize); counterEl.textContent = total? `${t('المعروض','Shown')}: ${shown} / ${total}` : ''; }
    function renderRows(rows){
      tbody.innerHTML = rows.length? rows.map(r=>`
        <tr data-id="${r.id}">
          <td>${mkApi.escapeHtml(r.name)}</td>
          <td>-</td>
          <td style="text-align:center;white-space:nowrap">
            <button class="btn btn-sm" data-act="edit"><i class="fas fa-edit"></i> ${t('تعديل','Edit')}</button>
            <button class="btn btn-sm" data-act="delete" style="margin-inline-start:6px"><i class="fas fa-trash"></i> ${t('حذف','Delete')}</button>
          </td>
        </tr>`).join('') : `<tr><td colspan='3' class='placeholder-cell'>${t('لا يوجد بيانات','No data')}</td></tr>`;

      // wire actions
      tbody.querySelectorAll('button[data-act]')?.forEach(btn=>{
        btn.addEventListener('click', async (ev)=>{
          const tr = ev.currentTarget.closest('tr'); const id = tr && tr.dataset.id; if(!id) return;
          if(ev.currentTarget.dataset.act==='edit'){
            const existing = tr.children[0].textContent.trim();
            const { wrap, form, saveBtn } = openModal({ name: existing });
            wrap.querySelector('[data-act="cancel"]').addEventListener('click',()=>closeModal(wrap));
            saveBtn.addEventListener('click', async ()=>{
              const fd = new FormData(form); const name = String(fd.get('name')||'').trim(); if(!name) return;
              try{ await apiUpdate(id, name); AppCore?.showToast?.(t('تم الحفظ','Saved'),'success'); await refresh(); closeModal(wrap); }
              catch(e){ AppCore?.showToast?.(e.message,'error'); }
            });
          } else if(ev.currentTarget.dataset.act==='delete'){
            if(!confirm(t('هل تريد حذف هذه المادة؟','Delete this subject?'))) return;
            try{ await apiDelete(id); AppCore?.showToast?.(t('تم الحذف','Deleted'),'success'); await refresh(); }
            catch(e){ AppCore?.showToast?.(e.message,'error'); }
          }
        });
      });
    }
    function renderPager(){ if(!pagerEl) return; const pages = Math.max(1, Math.ceil(view.length / pageSize)); let html='';
      const mkBtn=(p,lab,dis=false,pri=false)=>`<button class="btn ${pri?'btn-primary':'btn-outline'}" ${dis?'disabled':''} data-page="${p}">${lab}</button>`;
      html += mkBtn(1,'«', page===1);
      html += mkBtn(Math.max(1,page-1),'‹', page===1);
      for(let i=1;i<=pages;i++){
        if(i===page) html+= mkBtn(i, String(i), true, true);
        else if(i<=2 || i>pages-2 || Math.abs(i-page)<=1) html += mkBtn(i, String(i));
        else if(!html.endsWith('…')) html += `<span class="muted">…</span>`;
      }
      html += mkBtn(Math.min(pages,page+1),'›', page===pages);
      html += mkBtn(pages,'»', page===pages);
      pagerEl.innerHTML = html; pagerEl.style.display = pages>1? 'flex':'none';
      pagerEl.querySelectorAll('button[data-page]')?.forEach(b=> b.addEventListener('click', ()=>{ const p=Number(b.dataset.page); if(!isNaN(p)){ page=p; renderPage(); }}));
    }
    function renderPage(){ const start=(page-1)*pageSize; const rows=view.slice(start,start+pageSize); renderRows(rows); renderPager(); updateCounter(); }
    function applyFilter(){ const q=(searchEl?.value||'').trim().toLowerCase(); view = q? all.filter(s=> (s.name||'').toLowerCase().includes(q)) : [...all]; page=1; renderPage(); }

    async function refresh(){
      tbody.innerHTML=`<tr><td colspan='3' class='placeholder-cell'>${t('جاري التحميل','Loading')}...</td></tr>`;
      try{ all = await apiList(); view=[...all]; if(!all.length){ tbody.innerHTML = `<tr><td colspan='3' class='placeholder-cell'>${t('لا يوجد بيانات','No data')}</td></tr>`; if(counterEl) counterEl.textContent=''; if(pagerEl) pagerEl.style.display='none'; return; } page=1; renderPage(); }
      catch(e){ tbody.innerHTML = `<tr><td colspan='3' class='placeholder-cell error'>${mkApi.escapeHtml(e.message)}</td></tr>`; }
    }

    if(!addBtn.__wired){
      addBtn.__wired = true;
      addBtn.addEventListener('click', ()=>{
        const { wrap, form, saveBtn } = openModal(null);
        wrap.querySelector('[data-act="cancel"]').addEventListener('click',()=>closeModal(wrap));
        saveBtn.addEventListener('click', async ()=>{
          const fd = new FormData(form); const name = String(fd.get('name')||'').trim(); if(!name) return;
          try{ await apiCreate(name); AppCore?.showToast?.(t('تمت الإضافة','Added'),'success'); await refresh(); closeModal(wrap); }
          catch(e){ AppCore?.showToast?.(e.message,'error'); }
        });
      });
    }
    searchEl?.addEventListener('input', ()=> applyFilter());
    pageSizeEl?.addEventListener('change', ()=>{ const v=parseInt(pageSizeEl.value,10)||10; pageSize=v; localStorage.setItem('subjectsPageSize', String(v)); page=1; renderPage(); });
    await refresh();
  };

  // Compatibility wrapper
  if(!window.App) window.App={};
  window.App.renderSubjectsPage = async function(container){ await window.initSubjects(container instanceof HTMLElement ? container : undefined); };
})();
