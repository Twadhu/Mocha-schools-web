(function(){
  // Translations
  if(window.AppCore){ AppCore.registerTranslations({ ar:{ teachers:'المعلمون', addTeacher:'إضافة معلم جديد', edit:'تعديل', delete:'حذف', noTeachers:'لا يوجد معلمون لهذه المدرسة. حاول إضافة معلم جديد لبدء القائمة.', subjects:'المواد', classes:'الصفوف', status:'الحالة الوظيفية', specialization:'التخصص', save:'حفظ التغييرات', cancel:'إلغاء', basicSubjects:'+ المواد الأساسية' }, en:{ teachers:'Teachers', addTeacher:'Add Teacher', edit:'Edit', delete:'Delete', noTeachers:'No teachers yet. Add a teacher to start.', subjects:'Subjects', classes:'Classes', status:'Status', specialization:'Specialization', save:'Save Changes', cancel:'Cancel', basicSubjects:'+ Basic subjects' } }); }
  function t(ar,en){ return (window.App?.currentLang||'ar')==='ar'?ar:en; }

  // Seed data for Alnoor (s3)
  const initialAlNoorTeachers = [
    { id:1, name:'أنور محمد احمد جاموس', status:'ثابت', specialization:'قرآن', subjects:['إدارة'], classes:['كل الصفوف'] },
    { id:2, name:'يحي ابراهيم محمد عوض', status:'ثابت', specialization:'انجليزي', subjects:['وكالة','انجليزي'], classes:['ثانوية'] },
    { id:3, name:'عبده محمد محمد يحيى غويث', status:'ثابت', specialization:'جغرافيا', subjects:['اجتماعيات','جغرافيا'], classes:['تاسع','عاشر'] },
    { id:4, name:'علي ثابت إبراهيم مكي', status:'ثابت', specialization:'لغة عربية', subjects:['لغة عربية'], classes:['سابع','ثامن'] },
    { id:5, name:'عادل محمد عبده قائد', status:'ثابت', specialization:'علوم', subjects:['علوم'], classes:['سادس'] },
    { id:6, name:'أسمهان محمد علي', status:'ثابت', specialization:'رياضيات', subjects:['رياضيات'], classes:['رابع','خامس'] },
    { id:7, name:'ابتسام علي احمد', status:'ثابت', specialization:'عام', subjects:['مواد أساسية'], classes:['ثالث'] },
    { id:8, name:'حنان محمد احمد', status:'ثابت', specialization:'عام', subjects:['مواد أساسية'], classes:['ثاني'] },
    { id:9, name:'شروق عبد الله محمد', status:'ثابت', specialization:'عام', subjects:['مواد أساسية'], classes:['أول'] },
    { id:10, name:'عز الدين علي احمد', status:'ثابت', specialization:'فيزياء', subjects:['فيزياء'], classes:['ثانوي'] },
    { id:11, name:'عبد الله سعيد محمد عكيش', status:'ثابت', specialization:'علوم', subjects:['علوم'], classes:['اساسي'] },
    { id:12, name:'عبده عبد الله حسن عثمان', status:'ثابت', specialization:'عام', subjects:['اجتماعيات'], classes:['4','5'] },
    { id:13, name:'محمد احمد عبده حائك', status:'ثابت', specialization:'قرآن', subjects:['قرآن كريم'], classes:['ثانوي'] },
    { id:14, name:'عبده محمدإبراهيم عبده', status:'ثابت', specialization:'رياضيات', subjects:['رياضيات'], classes:['7','8','9'] },
    { id:15, name:'محمد غالب قريش احمد', status:'ثابت', specialization:'إنجليزي', subjects:['انجليزي'], classes:['7','8','9'] },
    { id:16, name:'محمد إبراهيم محمد صالح المساوى', status:'ثابت', specialization:'عام', subjects:['إسلامية','قرآن'], classes:['4','5','6'] },
    { id:17, name:'سهام حسن محمد سالم', status:'ثابت', specialization:'عام', subjects:['مواد أساسية'], classes:['3'] },
    { id:18, name:'لمياء صالح قائد عبدلله قليهط', status:'متطوع', specialization:'حاسوب', subjects:['حاسوب'], classes:['أول'] },
    { id:19, name:'جميل محمد صالح منه', status:'متطوع', specialization:'قرآن', subjects:['قرآن'], classes:['7','8','9'] },
    { id:20, name:'وائل عبد الله سليمان سالم', status:'متطوع', specialization:'شريعة', subjects:['إسلامية'], classes:['سابع','ثامن'] },
    { id:21, name:'عواضة عبد الله علي منه', status:'متطوع', specialization:'عام', subjects:['مواد أساسية'], classes:['سادس'] },
    { id:22, name:'انتصار محمد علي', status:'متطوع', specialization:'عام', subjects:['مواد أساسية'], classes:['خامس'] },
    { id:23, name:'راوية احمد علي', status:'متطوع', specialization:'عام', subjects:['مواد أساسية'], classes:['رابع'] },
    { id:24, name:'هدى محمد يحيى', status:'متطوع', specialization:'عام', subjects:['مواد أساسية'], classes:['ثالث ب'] },
    { id:25, name:'ايمان عبده غانم', status:'متطوع', specialization:'عام', subjects:['مواد أساسية'], classes:['ثاني ب'] },
    { id:26, name:'رقية ابراهيم قائد', status:'متطوع', specialization:'عام', subjects:['مواد أساسية'], classes:['أول ب'] },
    { id:27, name:'تسنيم يحيى إبراهيم مكعبب', status:'متطوع', specialization:'ثانوية عامة', subjects:['مواد أساسية'], classes:['ثاني ج'] },
    { id:28, name:'أسماء محمد عبد الله داس', status:'متطوع', specialization:'ثانوية عامة', subjects:['مواد أساسية'], classes:['أول ج'] },
    { id:29, name:'سعيد محمد قائد قليهط', status:'متطوع', specialization:'بدون', subjects:['حارس'], classes:[] },
  ];

  function getSid(){ const p=new URLSearchParams(location.search); return p.get('sid') || sessionStorage.getItem('schoolId') || 's3'; }
  function getSchoolName(){ try{ const cu=JSON.parse(localStorage.getItem('currentUser')||'{}'); return cu.school_name||''; }catch{ return ''; } }
  function storageKey(sid){ return `mokha_teachers_${sid}`; }
  function loadLocal(sid){ try{ const s=localStorage.getItem(storageKey(sid)); return s? JSON.parse(s): null; }catch{ return null; } }
  function saveLocal(sid, rows){ try{ localStorage.setItem(storageKey(sid), JSON.stringify(rows||[])); }catch{} }

  function chip(text){ return `<span class="chip" style="display:inline-flex;align-items:center;background:#e0f2fe;color:#0c4a6e;border-radius:9999px;padding:2px 10px;font-size:12px;font-weight:600;margin:0 4px 4px 0">${mkApi.escapeHtml(text)}</span>`; }

  function buildModal(){
    const id='teacherModalWrap'; document.getElementById(id)?.remove();
    const wrap=document.createElement('div'); wrap.id=id; wrap.className='modal-overlay';
    wrap.innerHTML = `
      <div class="modal-content" style="max-width:720px">
        <div class="modal-header"><h2 class="modal-title" id="tModalTitle">${t('إضافة معلم جديد','Add Teacher')}</h2><button class="btn btn-outline" data-act="close">${t('إلغاء','Cancel')}</button></div>
        <div class="modal-body">
          <form id="teacherForm" class="form-grid" style="grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px">
            <input type="hidden" name="id"/>
            <div class="form-group"><label>${t('اسم المعلم','Teacher name')}</label><input name="name" required placeholder="${t('مثال: عبد الله سعيد','e.g. Abdullah Said')}"></div>
            <div class="form-group"><label>${t('الحالة الوظيفية','Status')}</label>
              <select name="status"><option value="ثابت">ثابت</option><option value="متطوع">متطوع</option><option value="متعاقد">متعاقد</option></select>
            </div>
            <div class="form-group" style="grid-column:1 / -1"><label>${t('التخصص','Specialization')}</label><input name="specialization" placeholder="${t('مثال: رياضيات، لغة عربية','e.g. Math, Arabic')}"></div>
            <div class="form-group" style="grid-column:1 / -1">
              <div style="display:flex;justify-content:space-between;align-items:center">
                <label>${t('المواد (افصل بفاصلة أو Enter)','Subjects (comma/Enter)')}</label>
                <button type="button" id="btnBasicSubs" class="btn btn-sm">${t('+ المواد الأساسية','+ Basic subjects')}</button>
              </div>
              <div id="subjectTags" class="tags"></div>
              <input type="text" id="subjectInput" placeholder="${t('أضف مادة...','Add subject...')}"/>
            </div>
            <div class="form-group" style="grid-column:1 / -1">
              <label>${t('الصفوف (افصل بفاصلة أو Enter)','Classes (comma/Enter)')}</label>
              <div id="classTags" class="tags"></div>
              <input type="text" id="classInput" placeholder="${t('أضف صف...','Add class...')}"/>
            </div>
          </form>
        </div>
        <div class="modal-footer"><button class="btn btn-outline" data-act="close">${t('إلغاء','Cancel')}</button><button class="btn btn-primary" id="btnSaveTeacher">${t('حفظ التغييرات','Save Changes')}</button></div>
      </div>`;
    document.body.appendChild(wrap);
    setTimeout(()=>wrap.classList.add('visible'),10);
    return wrap;
  }

  function makeTagEditor(containerEl, inputEl, initial){
    const tags = new Set(Array.isArray(initial)?initial:[]);
    function render(){ containerEl.querySelectorAll('.chip').forEach(el=>el.remove()); [...tags].forEach(v=>{ const span=document.createElement('span'); span.className='chip'; span.style.cssText='display:inline-flex;align-items:center;background:#e0f2fe;color:#0c4a6e;border-radius:9999px;padding:2px 10px;font-size:12px;font-weight:600;margin:0 4px 4px 0'; span.textContent=v; const rm=document.createElement('button'); rm.type='button'; rm.textContent='×'; rm.style.cssText='margin-right:6px;color:#38bdf8;background:none;border:0;cursor:pointer'; rm.addEventListener('click',(e)=>{e.stopPropagation(); tags.delete(v); render();}); span.prepend(rm); containerEl.appendChild(span); }); }
    function add(val){ const txt=(val||'').trim(); if(!txt) return; if(!tags.has(txt)){ tags.add(txt); render(); } inputEl.value=''; }
    inputEl.addEventListener('keydown',e=>{ if(e.key==='Enter' || e.key===','){ e.preventDefault(); add(inputEl.value); } }); inputEl.addEventListener('blur',()=>add(inputEl.value));
    render();
    return { get:()=>[...tags], addMany:(arr)=>{ (arr||[]).forEach(v=>add(v)); } };
  }

  window.initTeachers = async function(host){
    const root= host || document.getElementById('pageContent');
    if(!root) return;
    root.innerHTML = `
      <div class="page-header">
        <div><h1 class="page-title">${t('المعلمون','Teachers')}</h1><p class="page-subtitle">${t('عرض، إضافة، وتعديل بيانات المعلمين.','View, add, and edit teachers')}</p></div>
        <div class="quick-actions"><button class="btn btn-primary" id="btnAddTeacher"><i class="fas fa-plus"></i> ${t('إضافة معلم جديد','Add Teacher')}</button></div>
      </div>
      <section class="card"><div class="card-body">
        <div id="importHint" style="display:none;margin-bottom:12px;padding:10px;border:1px dashed #93c5fd;background:#eff6ff;border-radius:8px;color:#1e3a8a"></div>
        <div id="teachersGrid" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:16px"></div>
        <p id="noTeachers" class="placeholder-cell" style="display:none;text-align:center;margin-top:16px">${t('لا يوجد معلمون لهذه المدرسة. حاول إضافة معلم جديد لبدء القائمة.','No teachers yet for this school. Add one to start.')}</p>
      </div></section>`;

    const grid = document.getElementById('teachersGrid');
    const noMsg = document.getElementById('noTeachers');
    const sid = getSid();

    // Try server list first and track availability (ok response regardless of count)
    let serverRows = [];
    let serverAvailable = false;
    try{
      const data = await mkApi.apiJson('api.php?action=teachers');
      if(data?.ok){ serverAvailable = true; serverRows = Array.isArray(data.teachers)? data.teachers: []; }
    }catch{ serverAvailable = false; }

    // Preload subjects map (name -> id) if server is available to support assignments
    let subjectsByName = new Map();
    async function loadSubjectsMap(){
      if(!serverAvailable) return;
      try{
        const d = await mkApi.apiJson('api.php?action=subjects');
        if(d?.ok && Array.isArray(d.subjects)){
          subjectsByName = new Map(d.subjects.map(s=>[String(s.name).trim(), parseInt(s.id,10)]));
        }
      }catch{}
    }
    await loadSubjectsMap();

    async function maybeImportAlNoorSeed(){
      const sidVal = getSid();
      if(!serverAvailable) return false;
      if(sidVal !== 's3') return false;
      if((serverRows||[]).length > 0) return false;
      // avoid repeated import
      const flagKey = 'mokha_teachers_imported_'+sidVal;
      if(localStorage.getItem(flagKey)==='1') return false;
      try{
        for(let i=0;i<initialAlNoorTeachers.length;i++){
          const st = initialAlNoorTeachers[i];
          const subjectIds = await ensureSubjectIdsByNames(st.subjects);
          // Generate placeholder email (unique-ish)
          const email = `alnoor.t${i+1}@school.local`;
          const nameParts = (st.name||'').trim().split(/\s+/);
          const first = nameParts[0]||'-';
          const last = nameParts.length>1 ? nameParts.slice(1).join(' ') : '-';
          const payload = { first_name:first, last_name:last, email, phone:null, specialization: st.specialization||null, subjects: subjectIds, classes: st.classes||[] };
          const resp = await mkApi.apiJson('api.php?action=teachers', { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify(payload) });
          if(!resp?.ok){ throw new Error(resp?.message||'seed-import-failed'); }
        }
        localStorage.setItem(flagKey,'1');
        return true;
      }catch(err){
        // If import failed, don't set the flag; fall back to local list
        console.warn('Seed import failed:', err);
        AppCore?.showToast(t('تعذر استيراد بيانات معلمي مدرسة النور للسيرفر','Failed to import Alnoor teachers to server'),'error');
        return false;
      }
    }

    async function ensureSubjectIdsByNames(names){
      if(!serverAvailable) return [];
      const out = [];
      for(const nmRaw of (names||[])){
        const nm = String(nmRaw||'').trim(); if(!nm) continue;
        let id = subjectsByName.get(nm);
        if(!id){
          try{
            const resp = await mkApi.apiJson('api.php?action=subjects', { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({ name: nm }) });
            if(resp?.ok && resp.id){ id = parseInt(resp.id,10); subjectsByName.set(nm, id); }
          }catch{}
        }
        if(id) out.push(id);
      }
      return Array.from(new Set(out));
    }

    // Local model unify {id,name,status,specialization,subjects[],classes[], email?, phone?}
    function normalizeServer(rows){
      return (rows||[]).map(r=>({ id:r.id, name:`${r.first_name||''} ${r.last_name||''}`.trim(), email:r.email||'', phone:r.phone||'', status: (r.status==='disabled')?'غير نشط':'ثابت', specialization: r.specialization||'', subjects: (r.subjects||[]).map(s=>s.name), classes: r.classes||[] }));
    }

    let teachers = [];
    if(serverAvailable){
      // If this is Alnoor (s3) and server has no teachers yet, try to import seed then reload; otherwise fallback to local seed for display-only
  let usedFallback = false;
      if(getSid()==='s3' && (serverRows||[]).length===0){
        // Show explicit import hint/button
        const hint = document.getElementById('importHint');
        if(hint){
          hint.style.display='block';
          hint.innerHTML = `
            <div style="display:flex;align-items:center;gap:10px;justify-content:space-between;flex-wrap:wrap">
              <div>
                <strong>${t('مدرسة النور (s3):','Alnoor (s3):')}</strong>
                <span>${t('لا توجد بيانات معلمين على الخادم لهذه المدرسة. يمكنك استيراد القائمة الافتراضية الآن.','No teachers on server for this school yet. You can import the default list now.')}</span>
              </div>
              <div style="display:flex;gap:8px">
                <button class="btn" id="btnImportAlnoor">${t('استيراد معلمي النور للخادم','Import Alnoor teachers to server')}</button>
              </div>
            </div>`;
          hint.querySelector('#btnImportAlnoor')?.addEventListener('click', async ()=>{
            try{
              AppCore?.showToast?.(t('جاري الاستيراد...','Importing...'),'info');
              const ok = await maybeImportAlNoorSeed();
              if(ok){
                AppCore?.showToast?.(t('تم الاستيراد بنجاح','Imported successfully'),'success');
                try{ const data = await mkApi.apiJson('api.php?action=teachers'); if(data?.ok){ serverRows = data.teachers||[]; } }catch{}
                const list = normalizeServer(serverRows||[]);
                if(list.length){
                  teachers = list;
                  // Imported to server: switch to server mode
                  usedFallback = false;
                  render();
                  hint.style.display='none';
                }
              } else {
                AppCore?.showToast?.(t('تعذر الاستيراد، تحقق من صلاحيات الدخول أو فعّل وضع التطوير المحلي','Import failed, check auth or enable local dev bypass'),'error');
              }
            }catch(e){ AppCore?.showToast?.(e.message||t('فشل الاستيراد','Import failed'),'error'); }
          });
        }
        const imported = await maybeImportAlNoorSeed();
        if(imported){
          try{ const data = await mkApi.apiJson('api.php?action=teachers'); if(data?.ok){ serverRows = data.teachers||[]; } }catch{}
        }
        if((serverRows||[]).length===0){
          // fallback to local seeded view so the UI is not empty
          teachers = initialAlNoorTeachers;
          usedFallback = true;
        }
      }
      if(!usedFallback){ teachers = normalizeServer(serverRows); }
    } else {
      let local = loadLocal(sid);
      if(!local && sid==='s3'){ local = initialAlNoorTeachers; saveLocal(sid, local); }
      teachers = local || [];
    }

    function render(){
      grid.innerHTML='';
      if(!teachers.length){ noMsg.style.display='block'; return; } else { noMsg.style.display='none'; }
      teachers.forEach(ti=>{
        const card=document.createElement('div'); card.className='teacher-card'; card.style.cssText='background:#fff;border:1px solid var(--border-color,#e5e7eb);border-radius:12px;box-shadow:0 1px 2px rgba(0,0,0,.04);display:flex;flex-direction:column;overflow:hidden;';
        const statusClass = ti.status==='ثابت'? 'background:#dcfce7;color:#166534' : (ti.status==='متطوع' ? 'background:#fef3c7;color:#92400e' : 'background:#e0f2fe;color:#0c4a6e');
        card.innerHTML = `
          <div class="card-body" style="padding:14px;flex:1">
            <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:8px;">
              <h3 style="font-size:16px;font-weight:700;margin:0;color:#111827">${mkApi.escapeHtml(ti.name)}</h3>
              <span style="${statusClass};font-size:11px;font-weight:700;border-radius:9999px;padding:2px 8px;white-space:nowrap">${mkApi.escapeHtml(ti.status)}</span>
            </div>
            <p style="color:#6b7280;font-size:13px;margin:6px 0 10px 0">${mkApi.escapeHtml(ti.specialization||'غير محدد')}</p>
            <div style="margin-bottom:8px"><div style="font-size:11px;color:#9ca3af;margin-bottom:4px">${t('المواد','Subjects')}</div>
              <div>${(ti.subjects||[]).length? ti.subjects.map(chip).join(''): '<span style="font-size:12px;color:#9ca3af">'+t('لا يوجد','None')+'</span>'}</div>
            </div>
            <div><div style="font-size:11px;color:#9ca3af;margin-bottom:4px">${t('الصفوف','Classes')}</div>
              <div>${(ti.classes||[]).length? ti.classes.map(chip).join(''): '<span style="font-size:12px;color:#9ca3af">'+t('لا يوجد','None')+'</span>'}</div>
            </div>
          </div>
          <div style="background:#f9fafb;border-top:1px solid var(--border-color,#e5e7eb);padding:8px 10px;display:flex;justify-content:flex-end;gap:8px">
            <button class="btn btn-sm" data-act="edit" data-id="${ti.id}"><i class="fas fa-edit"></i> ${t('تعديل','Edit')}</button>
            <button class="btn btn-sm" data-act="delete" data-id="${ti.id}"><i class="fas fa-trash"></i> ${t('حذف','Delete')}</button>
          </div>`;
        grid.appendChild(card);
      });
      grid.querySelectorAll('button[data-act]').forEach(b=>b.addEventListener('click', async e=>{
        const act=e.currentTarget.dataset.act; const id=parseInt(e.currentTarget.dataset.id,10);
        if(act==='edit'){ const item=teachers.find(x=>x.id===id); if(item) openEdit(item); }
        if(act==='delete'){
          if(confirm(t('هل أنت متأكد من رغبتك في حذف هذا المعلم؟','Are you sure to delete?'))){
            if(serverAvailable && !usedFallback){
              try{ const d=await mkApi.apiJson(`api.php?action=teacher_delete&id=${id}`, { method:'DELETE' }); if(!d.ok) throw new Error(d.message||'Failed'); AppCore?.showToast(t('تم الحذف','Deleted'),'success'); }catch(err){ AppCore?.showToast(err.message||t('فشل الحذف','Delete failed'),'error'); return; }
              // Reload from server after delete
              try{ const data = await mkApi.apiJson('api.php?action=teachers'); if(data?.ok){ teachers = normalizeServer(data.teachers||[]); render(); } }catch{}
            } else {
              teachers = teachers.filter(x=>x.id!==id); saveLocal(sid, teachers); render(); AppCore?.showToast(t('تم الحذف','Deleted'),'success');
            }
          }
        }
      }));
    }

    function openEdit(item){
      const modal=buildModal();
      const form=modal.querySelector('#teacherForm');
      const saveBtn=modal.querySelector('#btnSaveTeacher');
      const subjTags=modal.querySelector('#subjectTags');
      const subjInput=modal.querySelector('#subjectInput');
      const clsTags=modal.querySelector('#classTags');
      const clsInput=modal.querySelector('#classInput');
      const title=modal.querySelector('#tModalTitle');

      // Inject email/phone fields if not present (light touch)
      if(!form.querySelector('input[name="email"]')){
        const emailDiv=document.createElement('div'); emailDiv.className='form-group'; emailDiv.innerHTML=`<label>${t('البريد الإلكتروني','Email')}</label><input type="email" name="email" placeholder="name@example.com">`;
        const phoneDiv=document.createElement('div'); phoneDiv.className='form-group'; phoneDiv.innerHTML=`<label>${t('الهاتف','Phone')}</label><input name="phone" placeholder="07xxxxxxxx">`;
        // Insert after status select
        const statusDiv=form.querySelector('select[name="status"]').closest('.form-group');
        statusDiv.insertAdjacentElement('afterend', phoneDiv);
        statusDiv.insertAdjacentElement('afterend', emailDiv);
      }

      title.textContent = item? t('تعديل بيانات المعلم','Edit Teacher'): t('إضافة معلم جديد','Add Teacher');
      form.name.value=item?.name||'';
      form.status.value=item?.status||'ثابت';
      form.specialization.value=item?.specialization||'';
      form.id.value=item?.id||'';
      form.email.value=item?.email||'';
      form.phone.value=item?.phone||'';

      const subj=makeTagEditor(subjTags, subjInput, item?.subjects||[]);
      const cls=makeTagEditor(clsTags, clsInput, item?.classes||[]);
      modal.querySelector('#btnBasicSubs').addEventListener('click',()=>subj.addMany(['قرآن كريم','لغة عربية','رياضيات','علوم','تربية إسلامية','اجتماعيات']));
      modal.querySelectorAll('[data-act="close"]').forEach(x=>x.addEventListener('click',()=>{ modal.classList.remove('visible'); setTimeout(()=>modal.remove(),250); }));

      function splitName(full){ const p=(full||'').trim().split(/\s+/); if(p.length===0) return {first:'',last:''}; if(p.length===1) return {first:p[0], last:'-'}; return {first:p[0], last:p.slice(1).join(' ')}; }
      function mapStatusToApi(s){ return (s==='غير نشط' || s==='inactive') ? 'inactive' : 'active'; }

      saveBtn.addEventListener('click', async ()=>{
        const data={
          id: form.id.value? parseInt(form.id.value,10): null,
          name: form.name.value.trim(),
          status: form.status.value,
          specialization: form.specialization.value.trim(),
          subjects: subj.get(),
          classes: cls.get(),
          email: (form.email.value||'').trim(),
          phone: (form.phone.value||'').trim()
        };
        if(!data.name){ AppCore?.showToast(t('يرجى إدخال اسم المعلم','Enter teacher name'),'error'); return; }

        if(serverAvailable && !usedFallback){
          // Ensure subjects ids by name
          const subjectIds = await ensureSubjectIdsByNames(data.subjects);
          try{
            if(data.id){
              // Update existing
              const nl = splitName(data.name);
              const payload = { first_name:nl.first, last_name:nl.last, email:data.email||undefined, phone:data.phone||undefined, specialization:data.specialization||undefined, status: mapStatusToApi(data.status), subjects: subjectIds, classes: data.classes };
              const resp = await mkApi.apiJson(`api.php?action=teacher_update&id=${data.id}`, { method:'PUT', headers:{'Content-Type':'application/json'}, body: JSON.stringify(payload) });
              if(!resp?.ok) throw new Error(resp.message||'Failed to update');
              AppCore?.showToast(t('تم الحفظ','Saved'),'success');
            }else{
              // Create new: email is required by API
              if(!data.email){ AppCore?.showToast(t('الرجاء إدخال البريد الإلكتروني','Please enter email'),'error'); return; }
              const nl = splitName(data.name);
              const payload = { first_name:nl.first, last_name:nl.last, email:data.email, phone:data.phone||undefined, specialization:data.specialization||undefined, subjects: subjectIds, classes: data.classes };
              const resp = await mkApi.apiJson('api.php?action=teachers', { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify(payload) });
              if(!resp?.ok){ throw new Error(resp.message||'Failed to create'); }
              AppCore?.showToast(t('تمت الإضافة','Created'),'success');
            }
            // Reload list from server
            try{ const d = await mkApi.apiJson('admin.php?path=teachers'); if(d?.ok){ teachers = normalizeServer(d.teachers||[]); render(); } }catch{}
          }catch(err){ AppCore?.showToast(err.message||t('حدث خطأ','Error'),'error'); return; }
        } else {
          // Local fallback (legacy)
          const idVal = data.id || Date.now();
          const record = { id:idVal, name:data.name, status:data.status, specialization:data.specialization, subjects:data.subjects, classes:data.classes, email:data.email, phone:data.phone };
          if(data.id){ const idx=teachers.findIndex(x=>x.id===idVal); if(idx>-1) teachers[idx]=record; }
          else { teachers.unshift(record); }
          saveLocal(sid, teachers);
          render();
        }
        modal.classList.remove('visible'); setTimeout(()=>modal.remove(),250);
      });
    }

    document.getElementById('btnAddTeacher').addEventListener('click',()=>openEdit(null));
    render();
  };
  // Compatibility wrapper
  if(!window.App) window.App = {};
  window.App.renderTeachersPage = async function(container){ await window.initTeachers(container instanceof HTMLElement ? container : undefined); };
})();
