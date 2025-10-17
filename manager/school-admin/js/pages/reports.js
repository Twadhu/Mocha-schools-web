(function(){
  if(window.AppCore){ AppCore.registerTranslations({
    ar:{ reports:'التقارير', reportsSubtitle:'تصدير تقارير CSV/PDF موحدة', attendance:'الحضور', from:'من تاريخ', to:'إلى تاريخ', load:'تحميل', exportCsv:'تصدير CSV', exportPdf:'تصدير PDF', noData:'لا يوجد بيانات', date:'التاريخ', student:'الطالب', status:'الحالة' },
    en:{ reports:'Reports', reportsSubtitle:'Unified CSV/PDF exports', attendance:'Attendance', from:'From', to:'To', load:'Load', exportCsv:'Export CSV', exportPdf:'Export PDF', noData:'No data', date:'Date', student:'Student', status:'Status' }
  }); }
  const t=(a,b)=> (window.App?.currentLang||'ar')==='ar'?a:b;

  function buildUI(root){
    root.innerHTML = `
      <div class="page-header"><div><h1 class="page-title">${t('التقارير','Reports')}</h1><p class="page-subtitle">${t('تصدير تقارير CSV/PDF موحدة','Unified CSV/PDF exports')}</p></div></div>
      <div class="card"><div class="card-body">
        <h3 style="margin-top:0">${t('الحضور','Attendance')}</h3>
        <div class="form-grid" style="grid-template-columns:repeat(auto-fit,minmax(180px,1fr));align-items:end">
          <div class="form-group"><label>${t('من تاريخ','From')}</label><input type="date" id="repFrom" /></div>
          <div class="form-group"><label>${t('إلى تاريخ','To')}</label><input type="date" id="repTo"></div>
          <div style="display:flex;gap:8px;flex-wrap:wrap"><button class="btn btn-outline" id="repLoad">${t('تحميل','Load')}</button>
          <button class="btn btn-primary" id="repCsv"><i class="fas fa-file-csv"></i> ${t('تصدير CSV','Export CSV')}</button>
          <button class="btn btn-outline" id="repPdf"><i class="fas fa-file-pdf"></i> ${t('تصدير PDF','Export PDF')}</button></div>
        </div>
        <div class="table-container" style="margin-top:12px"><table style="width:100%">
          <thead><tr><th>${t('التاريخ','Date')}</th><th>${t('الطالب','Student')}</th><th>${t('الحالة','Status')}</th></tr></thead>
          <tbody id="repAttBody"><tr><td colspan="3" class="placeholder-cell">${t('لا يوجد بيانات','No data')}</td></tr></tbody>
        </table></div>
      </div></div>`;
  }

  async function fetchAttendance(from,to){
    const qs = new URLSearchParams(); if(from) qs.set('from', from); if(to) qs.set('to', to);
    const d = await mkApi.apiJson('attendance.php?'+qs.toString()); if(!d?.ok) throw new Error(d?.message||'failed'); return d.items||[];
    const d = await mkApi.apiJson('api.php?action=attendance&'+qs.toString()); if(!d?.ok) throw new Error(d?.message||'failed'); return d.items||[];
  }

  window.initReports = async function(host){
    const root = host || document.getElementById('pageContent'); if(!root) return; buildUI(root);
    const els = { from: root.querySelector('#repFrom'), to: root.querySelector('#repTo'), load: root.querySelector('#repLoad'), csv: root.querySelector('#repCsv'), pdf: root.querySelector('#repPdf'), tbody: root.querySelector('#repAttBody') };
    let cachedRows = [];
    async function load(){ els.tbody.innerHTML = `<tr><td colspan="3" class="placeholder-cell">${t('جاري التحميل','Loading...')}</td></tr>`; try{ const rows=await fetchAttendance(els.from.value||'', els.to.value||''); cachedRows = rows; if(!rows.length){ els.tbody.innerHTML=`<tr><td colspan="3" class="placeholder-cell">${t('لا يوجد بيانات','No data')}</td></tr>`; return; } els.tbody.innerHTML = rows.map(r=>{ const name = r.student_name || ((r.first_name||'')+' '+(r.last_name||'')); return `<tr><td>${mkApi.escapeHtml(r.att_date||'')}</td><td>${mkApi.escapeHtml(name||'')}</td><td>${mkApi.escapeHtml(r.status||'')}</td></tr>`; }).join(''); }catch(e){ els.tbody.innerHTML = `<tr><td colspan="3" class="placeholder-cell error">${mkApi.escapeHtml(e.message)}</td></tr>`; } }
    els.load.addEventListener('click', load);
      els.csv.addEventListener('click', async ()=>{
      if(cachedRows.length){
        const header = [t('التاريخ','Date'), t('الطالب','Student'), t('الحالة','Status')];
        const lines = [header.join(',')].concat(cachedRows.map(r=>{
          const name = r.student_name || ((r.first_name||'')+' '+(r.last_name||''));
          const cells = [r.att_date||'', name||'', r.status||''];
          return cells.map(x=> '"'+String(x).replaceAll('"','""')+'"').join(',');
        }));
        const blob = new Blob(["\ufeff"+lines.join('\r\n')], {type:'text/csv;charset=utf-8;'});
        const a = document.createElement('a');
        a.href = URL.createObjectURL(blob);
        a.download = `attendance-${(els.from.value||'all')}-${(els.to.value||'all')}.csv`;
        document.body.appendChild(a); a.click(); setTimeout(()=>{ URL.revokeObjectURL(a.href); a.remove(); }, 200);
      } else {
          // Fallback: request CSV via teacher API if needed (not ideal for manager). Prefer loading table then export client-side.
          AppCore?.showToast?.(t('حمّل البيانات أولاً ثم صدّر CSV','Load data first then export CSV'),'info');
      }
    });
    async function ensurePdfLibs(){ if(!window.AppCore) return false; await AppCore.ensureLib('jspdf', ()=>window.jspdf, 'https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js'); await AppCore.ensureLib('jspdf-autotable', ()=>window.jspdf?.jsPDF?.autoTable, 'https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.23/jspdf.plugin.autotable.min.js'); return !!(window.jspdf && window.jspdf.jsPDF && window.jspdf.jsPDF.autoTable); }
    els.pdf.addEventListener('click', async ()=>{
      if(!cachedRows.length){ AppCore?.showToast?.(t('لا يوجد بيانات','No data'),'info'); return; }
      const ok = await ensurePdfLibs(); if(!ok){ AppCore?.showToast?.('PDF libs not ready','error'); return; }
      const { jsPDF } = window.jspdf; const doc = new jsPDF('p','pt');
      const title = t('تقرير الحضور','Attendance Report');
      const sub = `${t('من تاريخ','From')}: ${els.from.value||'-'}   ${t('إلى تاريخ','To')}: ${els.to.value||'-'}`;
      doc.setFontSize(14); doc.text(title, 40, 40); doc.setFontSize(10); doc.text(sub, 40, 58);
      const head = [[t('التاريخ','Date'), t('الطالب','Student'), t('الحالة','Status')]];
      const body = cachedRows.map(r=>{ const name = r.student_name || ((r.first_name||'')+' '+(r.last_name||'')); return [String(r.att_date||''), String(name||''), String(r.status||'')]; });
      doc.autoTable({ head, body, startY: 70, styles:{ fontSize:10 }, headStyles:{ fillColor:[59,130,246] } });
      const fname = `attendance-${(els.from.value||'all')}-${(els.to.value||'all')}.pdf`;
      doc.save(fname);
    });
  };
  if(!window.App) window.App={}; window.App.renderReportsPage = async function(container){ await window.initReports(container instanceof HTMLElement ? container : undefined); };
  // Extend UI: list directorate report requests + quick submit
  window.initReportsEx = async function(container){
    await window.initReports(container);
    const host = container || document.getElementById('pageContent'); if(!host) return;
    const wrap = document.createElement('div'); wrap.className='card';
    wrap.innerHTML = `
      <div class="card-body">
        <h3 style="margin-top:0">${t('طلبات التقارير من المديرية','Directorate Report Requests')}</h3>
        <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:end">
          <button class="btn btn-outline" id="btnLoadReqs">${t('تحديث','Refresh')}</button>
          <input type="number" id="reqIdInput" placeholder="${t('رقم الطلب','Request ID')}" style="max-width:160px" />
          <input type="number" id="schoolIdInput" placeholder="${t('رقم المدرسة','School ID')}" style="max-width:160px" />
          <button class="btn btn-primary" id="btnSubmitReq">${t('إرسال تسليم','Submit Response')}</button>
        </div>
        <div class="table-container" style="margin-top:12px">
          <table style="width:100%"><thead><tr><th>#</th><th>${t('النوع','Type')}</th><th>${t('المعاملات','Params')}</th><th>${t('التاريخ','Date')}</th></tr></thead>
          <tbody id="reqsBody"><tr><td colspan="4" class="placeholder-cell">${t('لا يوجد بيانات','No data')}</td></tr></tbody></table>
        </div>
        <div id="reqMsg" class="muted" style="margin-top:8px"></div>
      </div>`;
    host.appendChild(wrap);
    const els = {
      load: wrap.querySelector('#btnLoadReqs'),
      tbody: wrap.querySelector('#reqsBody'),
      reqId: wrap.querySelector('#reqIdInput'),
      schoolId: wrap.querySelector('#schoolIdInput'),
      submit: wrap.querySelector('#btnSubmitReq'),
      msg: wrap.querySelector('#reqMsg')
    };
    async function loadReqs(){
      els.tbody.innerHTML = `<tr><td colspan="4" class="placeholder-cell">${t('جاري التحميل','Loading...')}</td></tr>`;
      try{
        const d = await mkApi.apiJson('api.php?action=report_requests');
        const rows = Array.isArray(d?.requests) ? d.requests : [];
        if(!rows.length){ els.tbody.innerHTML = `<tr><td colspan="4" class="placeholder-cell">${t('لا يوجد بيانات','No data')}</td></tr>`; return; }
        els.tbody.innerHTML = rows.map(r=>`<tr><td>${r.id}</td><td>${mkApi.escapeHtml(r.type||'')}</td><td><code>${mkApi.escapeHtml(typeof r.params==='string'?r.params: JSON.stringify(r.params||{}))}</code></td><td>${mkApi.escapeHtml(r.created_at||'')}</td></tr>`).join('');
      }catch(e){ els.tbody.innerHTML = `<tr><td colspan="4" class="placeholder-cell error">${mkApi.escapeHtml(e.message||'error')}</td></tr>`; }
    }
    async function submitReq(){
      const rid = parseInt(els.reqId.value||'0',10); const sid = parseInt(els.schoolId.value||'0',10);
      if(!rid || !sid){ els.msg.textContent = t('املأ رقم الطلب ورقم المدرسة','Fill request and school IDs'); return; }
      els.msg.textContent = '...';
      try{
        const payload = { request_id: rid, school_id: sid, data: { note: 'submitted via manager panel' } };
        const r = await mkApi.apiJson('api.php?action=report_submit', { method:'POST', body: JSON.stringify(payload) });
        if(!r?.ok) throw new Error(r?.message||'failed');
        els.msg.textContent = t('تم الإرسال','Submitted');
      }catch(e){ els.msg.textContent = e.message||'error'; }
    }
    els.load.addEventListener('click', loadReqs);
    els.submit.addEventListener('click', submitReq);
    loadReqs();
  };
  // Render extended reports page when available
  if(!window.App) window.App={}; window.App.renderReportsPage = async function(container){ await window.initReportsEx(container instanceof HTMLElement ? container : undefined); };
})();
