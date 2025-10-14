(function(){
  // Translations
  if(window.AppCore){ AppCore.registerTranslations({
    ar:{ scheduleSubtitle:'إدارة الجداول الدراسية', load:'تحميل', semester:'الفصل', save:'حفظ', day:'اليوم', period:'الحصة', teacher:'المعلم',
        grade:'الصف', section:'الشعبة', includeSat:'إظهار السبت', print:'طباعة', allSchedules:'جداول الصفوف', teacherSchedules:'جداول المعلمين',
        editPeriodTitle:'تحرير الحصة', subject:'المادة', selectSubject:'اختر المادة', selectTeacher:'اختر المعلم',
        copyDay:'نسخ اليوم', pasteDay:'لصق', clearDay:'مسح اليوم', deletePeriod:'حذف الحصة',
        noConflict:'لا يوجد تعارض زمني', hasConflict:'هذا المعلم مُشغَل في نفس الموعد', pickGrade:'اختر صفاً', loading:'جاري التحميل', saved:'تم الحفظ', failed:'تعذر التنفيذ' },
    en:{ scheduleSubtitle:'Manage class schedules', load:'Load', semester:'Semester', save:'Save', day:'Day', period:'Period', teacher:'Teacher',
        grade:'Grade', section:'Section', includeSat:'Include Saturday', print:'Print', allSchedules:'All Classes', teacherSchedules:'Teacher Schedules',
        editPeriodTitle:'Edit Period', subject:'Subject', selectSubject:'Select subject', selectTeacher:'Select teacher',
        copyDay:'Copy Day', pasteDay:'Paste', clearDay:'Clear Day', deletePeriod:'Delete Period',
        noConflict:'No time conflict', hasConflict:'Teacher is busy in same slot', pickGrade:'Pick grade', loading:'Loading...', saved:'Saved', failed:'Failed' }
  }); }
  function t(ar,en){ return (App?.currentLang||'ar')==='ar'?ar:en; }

  // State and helpers
  const apiDay = { saturday:'Sat', sunday:'Sun', monday:'Mon', tuesday:'Tue', wednesday:'Wed', thursday:'Thu' };
  const uiDay  = { Sat:'saturday', Sun:'sunday', Mon:'monday', Tue:'tuesday', Wed:'wednesday', Thu:'thursday' };
  const arDay  = { saturday:'السبت', sunday:'الأحد', monday:'الاثنين', tuesday:'الثلاثاء', wednesday:'الأربعاء', thursday:'الخميس' };
  const baseDays = ['sunday','monday','tuesday','wednesday','thursday'];
  const state = { includeSat:false, currentSchedule:{}, subjects:[], teachers:[], busyCache:new Map(), selected:{day:'', period:0} };
  const days = ()=> state.includeSat ? ['saturday', ...baseDays] : baseDays.slice();

  function $(s){ return document.querySelector(s); }
  function $all(s){ return Array.from(document.querySelectorAll(s)); }
  function toast(msg, type='info'){ AppCore?.showToast?.(msg, type); }
  async function safe(fn){ try{ return await fn(); }catch(e){ console.error(e); return null; } }

  async function loadSubjects(){ const d = await safe(()=> mkApi.apiJson('admin.php?path=subjects')); if(d?.ok){ state.subjects = d.subjects||[]; } }
  async function loadTeachers(){ const d = await safe(()=> mkApi.apiJson('admin.php?path=teachers&status=active')); if(d?.ok){ state.teachers = d.teachers||[]; } }

  function buildUI(host){
    const root = host || document.getElementById('pageContent');
    root.innerHTML = `
      <div class="page-header"><div><h1 class="page-title">${t('الجدول الدراسي','Schedule')}</h1><p class="page-subtitle">${t('إدارة الجداول الدراسية','Manage class schedules')}</p></div></div>
      <div class="card"><div class="card-body">
        <div class="form-grid" style="grid-template-columns:repeat(auto-fit,minmax(160px,1fr));align-items:end">
          <div class="form-group"><label>${t('الصف','Grade')}</label><select id="gradeSelect">${Array.from({length:12},(_,i)=>`<option value="${i+1}">${i+1}</option>`).join('')}</select></div>
          <div class="form-group"><label>${t('الشعبة','Section')}</label><input id="sectionSelect" placeholder="A"></div>
          <div class="form-group"><label>${t('الفصل','Semester')}</label><select id="semesterSelect"><option value="1">1</option><option value="2">2</option></select></div>
          <div class="form-group"><label>&nbsp;</label><label style="display:flex;gap:8px;align-items:center"><input type="checkbox" id="toggleSat"/> ${t('إظهار السبت','Include Saturday')}</label></div>
          <div style="display:flex;gap:8px;flex-wrap:wrap">
            <button class="btn btn-outline" id="btnLoad">${t('تحميل','Load')}</button>
            <button class="btn btn-primary" id="btnSave">${t('حفظ','Save')}</button>
            <button class="btn btn-outline" id="btnPrint">${t('طباعة','Print')}</button>
            <button class="btn btn-outline" id="btnAll">${t('جداول الصفوف','All Classes')}</button>
            <button class="btn btn-outline" id="btnTeachers">${t('جداول المعلمين','Teacher Schedules')}</button>
          </div>
        </div>
        <div class="table-container" style="margin-top:12px">
          <table class="schedule-table"><thead id="scheduleHead"></thead><tbody id="scheduleBody"></tbody></table>
        </div>
      </div></div>
      <div class="card hidden" id="allSchedulesCard"><div class="card-body"><div id="allSchedulesHint" class="muted" style="margin-bottom:8px"></div><div id="allClassSchedules"></div></div></div>

      <div id="periodModal" class="modal modal-hidden" role="dialog" aria-modal="true">
        <div class="modal-content">
          <div class="modal-header"><h3 id="periodModalTitle">${t('تحرير الحصة','Edit Period')}</h3><button class="close-btn" id="closePeriod">&times;</button></div>
          <form id="periodForm" class="modal-body">
            <div class="form-grid">
              <div class="form-group"><label>${t('المادة','Subject')}</label><select id="subjectSelect"><option value="">${t('اختر المادة','Select subject')}</option></select></div>
              <div class="form-group"><label>${t('المعلم','Teacher')}</label><select id="teacherSelect"><option value="">${t('اختر المعلم','Select teacher')}</option></select></div>
            </div>
            <div id="conflictZone" style="margin-top:8px"></div>
            <div class="modal-footer" style="display:flex;justify-content:space-between;gap:8px">
              <div style="display:flex;gap:8px">
                <button type="button" class="btn btn-outline" id="btnCopyDay">${t('نسخ اليوم','Copy Day')}</button>
                <button type="button" class="btn btn-outline" id="btnPasteDay">${t('لصق','Paste')}</button>
                <button type="button" class="btn btn-outline" id="btnClearDay">${t('مسح اليوم','Clear Day')}</button>
              </div>
              <div style="display:flex;gap:8px">
                <button type="button" class="btn btn-outline" id="cancelPeriod">${t('إلغاء','Cancel')}</button>
                <button type="button" class="btn btn-outline" id="btnDeletePeriod">${t('حذف الحصة','Delete Period')}</button>
                <button type="submit" class="btn btn-primary">${t('حفظ','Save')}</button>
              </div>
            </div>
          </form>
        </div>
      </div>
    `;
  }

  function renderTable(){
    const thead = $('#scheduleHead'); const tbody=$('#scheduleBody');
    const dlist = days();
    thead.innerHTML = `<tr><th>${t('الحصة/اليوم','Period/Day')}</th>${dlist.map(d=>`<th>${arDay[d]||d}</th>`).join('')}</tr>`;
    const rows = Array.from({length:7},(_,i)=> i+1);
    tbody.innerHTML = rows.map(p=>`<tr><td>${t('الحصة','P')} ${p}</td>${dlist.map(d=>`<td><div class="schedule-cell" data-day="${d}" data-period="${p}"></div></td>`).join('')}</tr>`).join('');
    tbody.addEventListener('click', (e)=>{ const cell=e.target.closest('.schedule-cell'); if(!cell) return; editPeriod(cell.dataset.day, Number(cell.dataset.period)); });
    attachCellDnD();
    refreshCells();
  }

  function refreshCells(){
    $all('.schedule-cell').forEach(c=>{ c.innerHTML=''; c.classList.remove('has-class'); });
    Object.entries(state.currentSchedule).forEach(([key, it])=>{
      const [d,p] = key.split('_'); const cell = document.querySelector(`.schedule-cell[data-day="${d}"][data-period="${p}"]`);
      if(cell && (it.subject||it.subject_name)){
        const subj = mkApi.escapeHtml(it.subject_name||it.subject||'');
        const tn = (it.teacher_name||'').trim();
        cell.innerHTML = `<div class="period-info"><div class="subject-name">${subj}</div>${tn?`<div class="teacher-name" style="color:#64748b;font-size:12px">${mkApi.escapeHtml(tn)}</div>`:''}</div>`;
        cell.classList.add('has-class');
      }
    });
  }

  function attachCellDnD(){
    $all('.schedule-cell').forEach(cell=>{
      cell.addEventListener('dragstart', e=>{ e.dataTransfer.setData('text/plain', JSON.stringify({ from:`${cell.dataset.day}_${cell.dataset.period}` })); });
      cell.setAttribute('draggable','true');
      cell.addEventListener('dragover', e=>{ e.preventDefault(); cell.classList.add('drag-over'); });
      cell.addEventListener('dragleave', ()=> cell.classList.remove('drag-over'));
      cell.addEventListener('drop', async e=>{ e.preventDefault(); cell.classList.remove('drag-over'); const data=JSON.parse(e.dataTransfer.getData('text/plain')||'{}'); if(!data.from) return; const to=`${cell.dataset.day}_${cell.dataset.period}`; await movePeriod(data.from, to); });
    });
  }

  async function movePeriod(fromKey, toKey){ if(fromKey===toKey) return; const src=state.currentSchedule[fromKey]; if(!src) return; state.currentSchedule[toKey] = { ...src }; delete state.currentSchedule[fromKey]; refreshCells(); await saveSnapshot(true); }

  async function loadSchedule(){
    const grade=$('#gradeSelect').value; const section=$('#sectionSelect').value.trim(); const semester=$('#semesterSelect').value;
    if(!grade){ toast(t('اختر صفاً','Pick grade'),'error'); return; }
    const qs = new URLSearchParams({grade, semester}); if(section) qs.set('section', section);
    const d = await safe(()=> mkApi.apiJson('admin.php?path=schedule&'+qs.toString()));
    state.currentSchedule = {};
    if(d?.ok && Array.isArray(d.items)){
      d.items.forEach(r=>{ const dayKey = uiDay[r.day] || String(r.day||'').toLowerCase(); const key=`${dayKey}_${r.period}`; state.currentSchedule[key] = { subject:r.subject||'', subject_name:r.subject||'', teacher_id:r.teacher_id||null, teacher_name: r.teacher_name||'' }; });
    }
    refreshCells();
  }

  async function saveSnapshot(silent=false){
    const grade=$('#gradeSelect').value; const section=($('#sectionSelect').value.trim()||null); const semester=$('#semesterSelect').value;
    const entries=[]; for(let p=1;p<=7;p++){ for(const d of days()){ const key=`${d}_${p}`; const it=state.currentSchedule[key]; if(it && (it.subject||it.subject_name)){ entries.push({ day: apiDay[d]||d, period:p, subject: it.subject||it.subject_name, teacher_id: it.teacher_id||null }); } } }
    const r = await safe(()=> mkApi.apiJson('admin.php?path=schedule', { method:'PUT', headers:{'Content-Type':'application/json'}, body: JSON.stringify({ grade, section, semester, entries }) }));
    if(r?.ok){ if(!silent) toast(t('تم الحفظ','Saved'),'success'); } else if(!silent){ toast(t('تعذر التنفيذ','Failed'),'error'); }
  }

  function updateSubjectsSelect(){ const el=$('#subjectSelect'); el.innerHTML = `<option value="">${t('اختر المادة','Select subject')}</option>${state.subjects.map(s=>`<option value="${s.name}">${mkApi.escapeHtml(s.name)}</option>`).join('')}`; }
  function updateTeachersSelect(subjectName){ const el=$('#teacherSelect'); let eligible = state.teachers.filter(t=> (t.subjects||[]).some(s=> String(s.name)===String(subjectName))); if(!eligible.length) eligible = state.teachers.slice(); el.innerHTML = `<option value="">${t('اختر المعلم','Select teacher')}</option>${eligible.map(ti=>`<option value="${ti.id}">${mkApi.escapeHtml(`${ti.first_name||''} ${ti.last_name||''}`.trim())}</option>`).join('')}`; }

  function editPeriod(day, period){
    state.selected.day = day; state.selected.period = period;
    $('#periodModalTitle').textContent = `${t('تحرير الحصة','Edit Period')} ${period} - ${arDay[day]||day}`;
    const cur = state.currentSchedule[`${day}_${period}`];
    updateSubjectsSelect();
    $('#subjectSelect').value = cur?.subject || cur?.subject_name || '';
    updateTeachersSelect($('#subjectSelect').value);
    $('#teacherSelect').value = cur?.teacher_id || '';
    $('#conflictZone').innerHTML = '';
    $('#periodModal').classList.remove('modal-hidden');
  }
  function closePeriod(){ $('#periodModal').classList.add('modal-hidden'); $('#periodForm').reset(); }

  async function hydrateBusy(teacherId, semester){ if(!teacherId) return; const key=String(teacherId); if(state.busyCache.has(key)) return; const d=await safe(()=> mkApi.apiJson(`admin.php?path=teacher-schedule/${encodeURIComponent(teacherId)}&semester=${encodeURIComponent(semester)}`)); const map={}; if(d?.ok && Array.isArray(d.items)){ d.items.forEach(it=>{ map[`${uiDay[it.day]||String(it.day).toLowerCase()}_${it.period}`]=true; }); } state.busyCache.set(key,map); }
  async function previewConflicts(){ const tid=Number($('#teacherSelect').value||0); const sem=$('#semesterSelect').value; const zone=$('#conflictZone'); zone.innerHTML=''; if(!tid) return; await hydrateBusy(tid, sem); const busy=state.busyCache.get(String(tid))||{}; const k=`${state.selected.day}_${state.selected.period}`; zone.innerHTML = busy[k] ? `<div class="badge danger"><span class="dot danger"></span> ${t('هذا المعلم مُشغَل في نفس الموعد','Teacher is busy in same slot')}</div>` : `<div class="badge ok"><span class="dot ok"></span> ${t('لا يوجد تعارض زمني','No time conflict')}</div>`; }

  async function handleSubmit(e){ e.preventDefault(); const subj=$('#subjectSelect').value; const tid = $('#teacherSelect').value ? Number($('#teacherSelect').value) : null; const key=`${state.selected.day}_${state.selected.period}`; if(subj){ const name = state.subjects.find(s=>String(s.name)===String(subj))?.name || subj; const tRow = tid? state.teachers.find(t=> Number(t.id)===tid): null; state.currentSchedule[key] = { subject:subj, subject_name:name, teacher_id: tid, teacher_name: tRow? `${tRow.first_name||''} ${tRow.last_name||''}`.trim(): '' }; } else { delete state.currentSchedule[key]; }
    refreshCells(); await saveSnapshot(); closePeriod(); }
  async function deleteSelected(){ const key=`${state.selected.day}_${state.selected.period}`; delete state.currentSchedule[key]; refreshCells(); await saveSnapshot(); closePeriod(); }

  let copyBuffer = null;
  function copyDay(day){ const out={}; for(let p=1;p<=7;p++){ const k=`${day}_${p}`; if(state.currentSchedule[k]) out[p] = { ...state.currentSchedule[k] }; } copyBuffer = out; toast(t('تم النسخ','Copied'),'success'); }
  async function pasteDay(day){ if(!copyBuffer) return; for(let p=1;p<=7;p++){ const src=copyBuffer[p]; const k=`${day}_${p}`; if(src){ state.currentSchedule[k] = { ...src }; } else { delete state.currentSchedule[k]; } } refreshCells(); await saveSnapshot(); }
  async function clearDay(day){ for(let p=1;p<=7;p++){ const k=`${day}_${p}`; if(state.currentSchedule[k]) delete state.currentSchedule[k]; } refreshCells(); await saveSnapshot(); }

  async function printSchedule(){ const grade=$('#gradeSelect').value; const section=$('#sectionSelect').value.trim(); const semester=$('#semesterSelect').value; const w=window.open('', '_blank'); if(!w){ alert('اسمح بالنوافذ المنبثقة للطباعة'); return; } const cols = days(); const head = cols.map(d=>`<th>${arDay[d]||d}</th>`).join(''); const body = Array.from({length:7},(_,i)=>{ const p=i+1; const tds = cols.map(d=>{ const it=state.currentSchedule[`${d}_${p}`]; const subj = it?(it.subject_name||it.subject||''):''; const tName = (it&&it.teacher_name)? `<div style="color:#555;font-size:12px">${it.teacher_name}</div>`:''; return `<td>${subj}${tName}</td>`; }).join(''); return `<tr><td>${t('الحصة','P')} ${p}</td>${tds}</tr>`; }).join(''); const html = `<html lang="ar" dir="rtl"><head><meta charset="utf-8"><title>جدول ${grade}${section?(' - '+section):''} فصل ${semester}</title><style>body{font-family:Tahoma,Arial,sans-serif;margin:20px} table{border-collapse:collapse;width:100%} th,td{border:1px solid #ddd;padding:8px;text-align:center} th{background:#f5f5f5}</style></head><body><h2>الجدول الدراسي - الصف ${grade}${section?(' - الشعبة '+section):''} - الفصل ${semester}</h2><table><thead><tr><th>الحصة/اليوم</th>${head}</tr></thead><tbody>${body}</tbody></table></body></html>`; w.document.write(html); w.document.close(); w.focus(); w.print(); }

  async function loadAllClassSchedules(){ const container=$('#allClassSchedules'); const card=$('#allSchedulesCard'); const sem=$('#semesterSelect').value; container.innerHTML=''; card.classList.remove('hidden'); $('#allSchedulesHint').textContent='يعرض من السبت إلى الخميس بسبع حصص لكل يوم.'; const dayCols=['Sat','Sun','Mon','Tue','Wed','Thu']; const dayTitle={Sat:'السبت',Sun:'الأحد',Mon:'الاثنين',Tue:'الثلاثاء',Wed:'الأربعاء',Thu:'الخميس'}; const grades=['1','2','3','4','5','6','7','8','9','10','11','12']; for(const g of grades){ const d=await safe(()=> mkApi.apiJson(`admin.php?path=schedule&grade=${encodeURIComponent(g)}&semester=${encodeURIComponent(sem)}`)); const items=d?.ok && Array.isArray(d.items) ? d.items : []; const sections=Array.from(new Set(items.map(it => (it.section==null||it.section==='') ? '' : String(it.section)))).sort((a,b)=>a.localeCompare(b,'ar')); if(sections.length===0) sections.push(''); for(const sec of sections){ const grid={}; for(let p=1;p<=7;p++){ grid[p]={}; dayCols.forEach(dc=> grid[p][dc]=''); } items.forEach(it=>{ if(((it.section==null||it.section==='')?'':String(it.section))!==sec) return; if(dayCols.includes(it.day)){ const txt = `${it.subject||''}${it.teacher_name?`<div class="muted">${it.teacher_name}</div>`:''}`; grid[it.period||0][it.day]=txt; } }); const table=document.createElement('table'); table.className='schedule-table'; const caption = sec ? `الصف: ${g} - الشعبة ${sec}` : `الصف: ${g}`; table.innerHTML = `<thead><tr><th>${caption}</th>${dayCols.map(dk=>`<th>${dayTitle[dk]}</th>`).join('')}</tr></thead><tbody>${Array.from({length:7},(_,i)=>{ const p=i+1; return `<tr><td>الحصة ${p}</td>${dayCols.map(dk=>`<td>${(grid[p][dk]||'')}</td>`).join('')}</tr>`; }).join('')}</tbody>`; const wrap=document.createElement('div'); wrap.className='mb-16'; wrap.appendChild(table); container.appendChild(wrap); } } }

  async function showTeacherSchedules(){ if(!state.teachers.length){ await loadTeachers(); } const sem=$('#semesterSelect').value; const list=document.createElement('div'); list.className='mt-16'; list.innerHTML=''; for(const t of state.teachers){ const d=await safe(()=> mkApi.apiJson(`admin.php?path=teacher-schedule/${encodeURIComponent(t.id)}&semester=${encodeURIComponent(sem)}`)); const items=d?.ok && Array.isArray(d.items) ? d.items : []; const grid={}; const dayCols=['Sat','Sun','Mon','Tue','Wed','Thu']; for(let p=1;p<=7;p++){ grid[p]={}; dayCols.forEach(dc=> grid[p][dc]=''); } items.forEach(it=>{ if(dayCols.includes(it.day)){ const txt = `${it.subject||''}${it.grade?`<div class=\"muted\">${it.grade}${it.section?(' / '+it.section):''}</div>`:''}`; grid[it.period||0][it.day]=txt; }}); const table=document.createElement('table'); table.className='schedule-table'; table.innerHTML = `<thead><tr><th>${mkApi.escapeHtml(`${t.first_name||''} ${t.last_name||''}`.trim())}</th>${dayCols.map(dk=>`<th>${{Sat:'السبت',Sun:'الأحد',Mon:'الاثنين',Tue:'الثلاثاء',Wed:'الأربعاء',Thu:'الخميس'}[dk]}</th>`).join('')}</tr></thead><tbody>${Array.from({length:7},(_,i)=>{ const p=i+1; return `<tr><td>الحصة ${p}</td>${dayCols.map(dk=>`<td>${(grid[p][dk]||'')}</td>`).join('')}</tr>`; }).join('')}</tbody>`; const wrap=document.createElement('div'); wrap.className='mb-24'; const h=document.createElement('h3'); h.textContent = `جدول المعلم: ${mkApi.escapeHtml(`${t.first_name||''} ${t.last_name||''}`.trim())}`; wrap.appendChild(h); wrap.appendChild(table); list.appendChild(wrap); }
    const card=$('#allSchedulesCard'); const hint=$('#allSchedulesHint'); const container=$('#allClassSchedules'); container.innerHTML=''; container.appendChild(list); hint.textContent='جداول مخصصة لكل معلم.'; card.classList.remove('hidden'); }

  // Accept optional host for compatibility mounting
  window.initSchedule = async function(hostParam){
    buildUI(hostParam);
    await Promise.all([ loadSubjects(), loadTeachers() ]);
    renderTable();
    // Wire controls
    $('#toggleSat').addEventListener('change', (e)=>{ state.includeSat = !!e.target.checked; renderTable(); });
    $('#btnLoad').addEventListener('click', loadSchedule);
    $('#btnSave').addEventListener('click', ()=> saveSnapshot());
    $('#btnPrint').addEventListener('click', (e)=>{ e.preventDefault(); printSchedule(); });
    $('#btnAll').addEventListener('click', (e)=>{ e.preventDefault(); loadAllClassSchedules(); });
    $('#btnTeachers').addEventListener('click', (e)=>{ e.preventDefault(); showTeacherSchedules(); });
    $('#closePeriod').addEventListener('click', closePeriod);
    $('#cancelPeriod').addEventListener('click', closePeriod);
    $('#periodForm').addEventListener('submit', handleSubmit);
    $('#teacherSelect').addEventListener('change', previewConflicts);
    $('#btnCopyDay').addEventListener('click', (e)=>{ e.preventDefault(); copyDay(state.selected.day); });
    $('#btnPasteDay').addEventListener('click', async (e)=>{ e.preventDefault(); await pasteDay(state.selected.day); });
    $('#btnClearDay').addEventListener('click', async (e)=>{ e.preventDefault(); await clearDay(state.selected.day); });
    $('#btnDeletePeriod').addEventListener('click', async (e)=>{ e.preventDefault(); await deleteSelected(); });
    // Auto load first time
    await loadSchedule();
  };
  // Compatibility wrapper so external code can call App.renderSchedulePage(container)
  if(!window.App) window.App = {};
  window.App.renderSchedulePage = async function(container){
    await window.initSchedule(container instanceof HTMLElement ? container : undefined);
  };
})();
