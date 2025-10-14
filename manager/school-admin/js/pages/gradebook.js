(function(){
  // Register translations
  if(window.AppCore){ AppCore.registerTranslations({
    ar:{ gradebook:'دفتر الدرجات', gradebookSubtitle:'إدخال الدرجات وتجميعها حسب المادة', subject:'المادة', gradeLevel:'الصف', section:'الشعبة', term:'الفصل', load:'تحميل', saveAll:'حفظ الكل', exportPdf:'تصدير PDF', student:'الطالب', firstScore:'المحصلة', finalScore:'النهائي', total:'المجموع', status:'الحالة', pass:'نجاح', fail:'رسوب', year:'السنة', noData:'لا يوجد بيانات', pickFirst:'حدد المادة والصف', locked:'مقفلة', approved:'معتمدة', lock:'إقفال', unlock:'فتح', approve:'اعتماد', unapprove:'إلغاء الاعتماد' },
    en:{ gradebook:'Gradebook', gradebookSubtitle:'Enter and aggregate grades per subject', subject:'Subject', gradeLevel:'Grade', section:'Section', term:'Term', load:'Load', saveAll:'Save All', exportPdf:'Export PDF', student:'Student', firstScore:'First', finalScore:'Final', total:'Total', status:'Status', pass:'Pass', fail:'Fail', year:'Year', noData:'No data', pickFirst:'Select subject and grade', locked:'Locked', approved:'Approved', lock:'Lock', unlock:'Unlock', approve:'Approve', unapprove:'Unapprove' }
  }); }
  function t(a,b){ return (window.App?.currentLang||'ar')==='ar'?a:b; }

  const Limits = { max_first:20, max_final:30, pass_threshold:50 };
  async function fetchLimits(){ try{ const d=await mkApi.apiJson('admin.php?path=score-limits'); if(d?.ok){ Object.assign(Limits,{max_first:d.max_first,max_final:d.max_final,pass_threshold:d.pass_threshold}); } }catch(e){} }
  async function listSubjects(){ const d=await mkApi.apiJson('admin.php?path=subjects'); if(!d.ok) return []; return d.subjects||[]; }
  async function listBySubject(grade, section, subject, term, year){ const qs=new URLSearchParams({grade,section,subject,term,year}); const d=await mkApi.apiJson('admin.php?path=subject-results&'+qs.toString()); if(!d.ok) throw new Error(d.message||'load-failed'); return d.items||[]; }
  async function fetchLockState(grade, section, subject, term, year){ const qs=new URLSearchParams({grade,section,subject,term,year}); const d=await mkApi.apiJson('admin.php?path=results/lock-state&'+qs.toString()); if(!d.ok) return {locked:false, approved:false}; return { locked: !!d.locked, approved: !!d.approved };
  }
  async function toggleLock(grade, section, subject, term, year, want){ const d=await mkApi.apiJson('admin.php?path=results/lock', { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({ grade, section, subject, term, year, lock: !!want }) }); if(!d.ok) throw new Error(d.message||'lock-failed'); }
  async function toggleApprove(grade, section, subject, term, year, want){ const d=await mkApi.apiJson('admin.php?path=results/approve', { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({ grade, section, subject, term, year, approve: !!want }) }); if(!d.ok) throw new Error(d.message||'approve-failed'); }
  async function upsert(student_id, subject, term, year, first, final){ const d=await mkApi.apiJson('admin.php?path=results',{method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify({student_id,subject,term,year,first,final})}); if(!d.ok) throw new Error(d.message||'save-failed'); }

  function buildUI(root){
    root.innerHTML = `
      <div class="page-header">
        <div><h1 class="page-title">${t('دفتر الدرجات','Gradebook')}</h1><p class="page-subtitle">${t('إدخال الدرجات وتجميعها حسب المادة','Enter and aggregate grades per subject')}</p></div>
        <div class="quick-actions">
          <button id="gbExportPdf" class="btn btn-outline"><i class="fas fa-file-pdf"></i> ${t('تصدير PDF','Export PDF')}</button>
          <button id="gbSaveAll" class="btn btn-primary"><i class="fas fa-save"></i> ${t('حفظ الكل','Save All')}</button>
        </div>
      </div>
      <div class="card"><div class="card-body">
        <div class="form-grid" style="grid-template-columns:repeat(auto-fit,minmax(180px,1fr))">
          <div class="form-group"><label>${t('المادة','Subject')}</label><select id="gbSubject"></select></div>
          <div class="form-group"><label>${t('الصف','Grade')}</label><select id="gbGrade">${Array.from({length:12},(_,i)=>`<option value="${i+1}">${i+1}</option>`).join('')}</select></div>
          <div class="form-group"><label>${t('الشعبة','Section')}</label><input id="gbSection" type="text" placeholder="A"/></div>
          <div class="form-group"><label>${t('الفصل','Term')}</label><select id="gbTerm"><option value="first">${t('الأول','First')}</option><option value="second">${t('الثاني','Second')}</option></select></div>
          <div class="form-group"><label>${t('السنة','Year')}</label><input id="gbYear" type="text" value="${new Date().getFullYear()}"/></div>
          <div class="form-group"><label>&nbsp;</label><button id="gbLoad" class="btn btn-outline"><i class="fas fa-download"></i> ${t('تحميل','Load')}</button></div>
        </div>
      </div></div>
      <div class="card"><div class="card-body" style="display:flex;justify-content:space-between;align-items:center;gap:12px">
        <div id="gbLockState" class="muted">—</div>
        <div style="display:flex;gap:8px">
          <button id="gbToggleLock" class="btn btn-outline"><i class="fas fa-lock"></i> <span id="gbLockBtnText">${t('إقفال','Lock')}</span></button>
          <button id="gbToggleApprove" class="btn btn-outline"><i class="fas fa-badge-check"></i> <span id="gbApproveBtnText">${t('اعتماد','Approve')}</span></button>
        </div>
      </div></div>
      <div class="card"><div class="table-container">
        <table style="width:100%;border-collapse:collapse">
          <thead><tr>
            <th>#</th><th>${t('الطالب','Student')}</th>
            <th id="thFirst">${t('المحصلة','First')} (${Limits.max_first})</th>
            <th id="thFinal">${t('النهائي','Final')} (${Limits.max_final})</th>
            <th>${t('المجموع','Total')}</th>
            <th>${t('الحالة','Status')}</th>
          </tr></thead>
          <tbody id="gbTbody"><tr><td colspan="6" class="placeholder-cell">${t('اختر المادة والصف ثم تحميل','Pick subject/grade then Load')}</td></tr></tbody>
        </table>
      </div></div>
    `;
  }

  // Accept optional host element for compatibility mounting
  window.initGradebook = async function(hostParam){
    const host = hostParam || document.getElementById('pageContent');
    host.innerHTML = '<div id="gradebookPage"></div>';
    const root = document.getElementById('gradebookPage');
    await fetchLimits();
    buildUI(root);
    // update headers with limits
    root.querySelector('#thFirst').textContent = `${t('المحصلة','First')} (${Limits.max_first})`;
    root.querySelector('#thFinal').textContent = `${t('النهائي','Final')} (${Limits.max_final})`;

    const els = {
      subj: root.querySelector('#gbSubject'), grade: root.querySelector('#gbGrade'), section: root.querySelector('#gbSection'), term: root.querySelector('#gbTerm'), year: root.querySelector('#gbYear'),
      load: root.querySelector('#gbLoad'), saveAll: root.querySelector('#gbSaveAll'), exportPdf: root.querySelector('#gbExportPdf'), tbody: root.querySelector('#gbTbody'),
      lockState: root.querySelector('#gbLockState'), lockBtn: root.querySelector('#gbToggleLock'), lockBtnText: root.querySelector('#gbLockBtnText'), approveBtn: root.querySelector('#gbToggleApprove'), approveBtnText: root.querySelector('#gbApproveBtnText')
    };
    // populate subjects
    const subs = await listSubjects();
    els.subj.innerHTML = subs.map(s=>`<option value="${mkApi.escapeHtml(s.name)}">${mkApi.escapeHtml(s.name)}</option>`).join('');

    const state = { rows:[], meta:{}, lock:{locked:false, approved:false} };
    async function doLoad(){
      const subject = els.subj.value; const grade=els.grade.value; const section=(els.section.value||'').trim(); const term=els.term.value; const year=String(els.year.value||new Date().getFullYear());
      if(!subject||!grade){ els.tbody.innerHTML = `<tr><td colspan="6" class="placeholder-cell">${t('حدد المادة والصف','Select subject and grade')}</td></tr>`; return; }
      els.tbody.innerHTML = `<tr><td colspan="6" class="placeholder-cell">${t('جاري التحميل','Loading...')}</td></tr>`;
      try{
        const items = await listBySubject(grade, section, subject, term, year);
        state.meta = { subject, grade, section, term, year };
        state.lock = await fetchLockState(grade, section, subject, term, year);
        state.rows = items.map((it,i)=>({ id:it.id, name:it.name, first:it.first||0, final:it.final||0, dirty:false }));
        if(!state.rows.length){ els.tbody.innerHTML = `<tr><td colspan="6" class="placeholder-cell">${t('لا يوجد بيانات','No data')}</td></tr>`; return; }
        els.tbody.innerHTML = state.rows.map((r,i)=>{
          const total=(Number(r.first)||0)+(Number(r.final)||0); const passMin=Math.ceil((Limits.max_first+Limits.max_final)*Limits.pass_threshold/100);
          const isFail = total < passMin;
          return `<tr data-id="${r.id}" class="${isFail?'row-fail':''}">
            <td>${i+1}</td>
            <td style="text-align:right">${mkApi.escapeHtml(r.name)}</td>
            <td><input type="number" class="gb-first" min="0" max="${Limits.max_first}" value="${r.first}" ${state.lock.locked||state.lock.approved?'disabled':''}></td>
            <td><input type="number" class="gb-final" min="0" max="${Limits.max_final}" value="${r.final}" ${state.lock.locked||state.lock.approved?'disabled':''}></td>
            <td class="gb-total">${total}</td>
            <td class="gb-status">${isFail?t('راسب','Fail'):t('ناجح','Pass')}</td>
          </tr>`;
        }).join('');
        // update lock UI
        renderLockUI();
      }catch(e){ els.tbody.innerHTML = `<tr><td colspan="6" class="placeholder-cell" style="color:#c00">${mkApi.escapeHtml(e.message)}</td></tr>`; }
    }

    els.load.addEventListener('click', doLoad);
    function clamp(val, min, max){ const n = isNaN(val)?0:val; return Math.max(min, Math.min(max, n)); }
    els.tbody.addEventListener('input', (ev)=>{
      const tr = ev.target.closest('tr'); if(!tr) return; const id=parseInt(tr.dataset.id);
      const row = state.rows.find(x=>x.id===id); if(!row) return;
      if(state.lock.locked || state.lock.approved) return; // editing disabled
      if(ev.target.classList.contains('gb-first')){ row.first = clamp(parseInt(ev.target.value)||0, 0, Limits.max_first); ev.target.value = row.first; }
      if(ev.target.classList.contains('gb-final')){ row.final = clamp(parseInt(ev.target.value)||0, 0, Limits.max_final); ev.target.value = row.final; }
      row.dirty = true;
      const total=(row.first||0)+(row.final||0); const passMin=Math.ceil((Limits.max_first+Limits.max_final)*Limits.pass_threshold/100);
      tr.querySelector('.gb-total').textContent = total;
      tr.querySelector('.gb-status').textContent = total<passMin? t('راسب','Fail') : t('ناجح','Pass');
      tr.classList.toggle('row-fail', total<passMin);
    });

    els.saveAll.addEventListener('click', async ()=>{
      if(state.lock.locked || state.lock.approved){ return AppCore && AppCore.showToast(t('هذه المادة مقفلة أو معتمدة','Locked or approved'),'error'); }
      const dirty = state.rows.filter(r=>r.dirty);
      if(!dirty.length){ if(window.AppCore){ AppCore.showToast(t('لا تغييرات','No changes'),'info'); } return; }
      els.saveAll.disabled = true;
      let ok=0, fail=0;
      for(const r of dirty){
        try{ await upsert(r.id, state.meta.subject, state.meta.term, state.meta.year, r.first, r.final); r.dirty=false; ok++; }
        catch(e){ fail++; }
      }
      els.saveAll.disabled = false;
      if(window.AppCore){ AppCore.showToast(t('تم: ','Done: ')+ok+ ' / '+ dirty.length + (fail? ' - '+t('أخفق','Failed')+': '+fail:''), fail? 'error':'success'); }
    });

    async function ensurePdfLibs(){
      if(!window.AppCore) return false;
      await AppCore.ensureLib('jspdf', ()=>window.jspdf, 'https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js');
      await AppCore.ensureLib('jspdf-autotable', ()=>window.jspdf?.jsPDF?.autoTable, 'https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.23/jspdf.plugin.autotable.min.js');
      return !!(window.jspdf && window.jspdf.jsPDF && window.jspdf.jsPDF.autoTable);
    }

    els.exportPdf.addEventListener('click', async ()=>{
      if(!state.rows.length){ return AppCore && AppCore.showToast(t('لا يوجد بيانات','No data'),'info'); }
      const ok = await ensurePdfLibs(); if(!ok){ return AppCore && AppCore.showToast('PDF libs not ready','error'); }
      const { jsPDF } = window.jspdf;
      const doc = new jsPDF('p','pt');
      const title = t('دفتر الدرجات','Gradebook');
  const metaLine = `${t('المادة','Subject')}: ${state.meta.subject}  |  ${t('الصف','Grade')}: ${state.meta.grade}${state.meta.section?` - ${t('الشعبة','Section')}: ${state.meta.section}`:''}  |  ${t('الفصل','Term')}: ${state.meta.term==='first'?t('الأول','First'):t('الثاني','Second')}  |  ${t('السنة','Year')}: ${state.meta.year}`;
      doc.setFontSize(14); doc.text(title, 40, 40);
      doc.setFontSize(10); doc.text(metaLine, 40, 58);
      const passMin=Math.ceil((Limits.max_first+Limits.max_final)*Limits.pass_threshold/100);
      const head=[[t('#','#'), t('الطالب','Student'), `${t('المحصلة','First')} (${Limits.max_first})`, `${t('النهائي','Final')} (${Limits.max_final})`, t('المجموع','Total'), t('الحالة','Status')]];
      const body = state.rows.map((r,i)=>{
        const total=(Number(r.first)||0)+(Number(r.final)||0); const status = total<passMin? t('راسب','Fail'):t('ناجح','Pass');
        return [String(i+1), r.name, String(r.first||0), String(r.final||0), String(total), status];
      });
      doc.autoTable({ head, body, startY: 70, styles:{ fontSize:10 }, headStyles:{ fillColor:[59,130,246] } });
      const fname = `gradebook-${state.meta.subject}-g${state.meta.grade}${state.meta.section?('-s'+state.meta.section):''}-${state.meta.term}-${state.meta.year}.pdf`;
      doc.save(fname);
    });

    function renderLockUI(){
      const st = state.lock; if(!els.lockState) return;
      els.lockState.innerHTML = `${t('الحالة:','Status:')} ${st.locked? `<span class="badge danger">${t('مقفلة','Locked')}</span>`:`<span class="badge">${t('مفتوحة','Open')}</span>`}  •  ${st.approved? `<span class="badge success">${t('معتمدة','Approved')}</span>`:`<span class="badge">${t('غير معتمدة','Not approved')}</span>`}`;
      els.lockBtnText.textContent = st.locked ? t('فتح','Unlock') : t('إقفال','Lock');
      els.approveBtnText.textContent = st.approved ? t('إلغاء الاعتماد','Unapprove') : t('اعتماد','Approve');
      // Disable editing controls accordingly
      els.saveAll.disabled = !!(st.locked || st.approved);
      // Disable inputs already handled when rendering rows
    }
    els.lockBtn.addEventListener('click', async ()=>{
      const m=state.meta; if(!m.subject||!m.grade){ return AppCore&&AppCore.showToast(t('اختر مادة وصف أولاً','Pick subject & grade first'),'info'); }
      try{ await toggleLock(m.grade, m.section||'', m.subject, m.term, m.year, !state.lock.locked); state.lock.locked = !state.lock.locked; renderLockUI(); AppCore&&AppCore.showToast(t('تم التحديث','Updated'),'success'); await doLoad(); }catch(e){ AppCore&&AppCore.showToast(e.message||'lock-failed','error'); }
    });
    els.approveBtn.addEventListener('click', async ()=>{
      const m=state.meta; if(!m.subject||!m.grade){ return AppCore&&AppCore.showToast(t('اختر مادة وصف أولاً','Pick subject & grade first'),'info'); }
      try{ await toggleApprove(m.grade, m.section||'', m.subject, m.term, m.year, !state.lock.approved); state.lock.approved = !state.lock.approved; renderLockUI(); AppCore&&AppCore.showToast(t('تم التحديث','Updated'),'success'); await doLoad(); }catch(e){ AppCore&&AppCore.showToast(e.message||'approve-failed','error'); }
    });
  };
  // Compatibility wrapper so external code can call App.renderGradebookPage(container)
  if(!window.App) window.App = {};
  window.App.renderGradebookPage = async function(container){
    // If a specific container is provided, mount within it
    await window.initGradebook(container instanceof HTMLElement ? container : undefined);
  };
})();
