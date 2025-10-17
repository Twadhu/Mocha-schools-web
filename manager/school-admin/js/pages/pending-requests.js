(function(){
  // Translations
  if(window.AppCore){ AppCore.registerTranslations({
    ar:{ requestsSubtitle:'طلبات التسجيل قيد المعالجة', name:'الاسم', email:'البريد', role:'الدور', student:'طالب', teacher:'معلم', grade:'الصف', gender:'الجنس', createdAt:'تاريخ الطلب', actions:'الإجراءات', approve:'موافقة', reject:'رفض', loading:'جاري التحميل', noData:'لا يوجد بيانات', error:'خطأ', refreshed:'تم التحديث', approved:'تمت الموافقة', rejected:'تم الرفض', confirmReject:'تأكيد رفض الطلب؟', refresh:'تحديث' },
    en:{ requestsSubtitle:'Pending registration requests', name:'Name', email:'Email', role:'Role', student:'Student', teacher:'Teacher', grade:'Grade', gender:'Gender', createdAt:'Submitted At', actions:'Actions', approve:'Approve', reject:'Reject', loading:'Loading', noData:'No data', error:'Error', refreshed:'Refreshed', approved:'Approved', rejected:'Rejected', confirmReject:'Confirm rejecting this request?', refresh:'Refresh' }
  }); }
  function t(a,b){ return (window.App?.currentLang||'ar')==='ar'?a:b; }

  function getSid(){
    // Derive current school id from URL, session, or currentUser
    try{ const qs=new URLSearchParams(location.search); const sid=qs.get('sid'); if(sid) return sid; }catch(_){ }
    try{ const cur=JSON.parse(localStorage.getItem('currentUser')||'null'); if(cur && (cur.school_id||cur.id)) return String(cur.school_id||cur.id); }catch(_){ }
    try{ const s=sessionStorage.getItem('schoolId'); if(s) return s; }catch(_){ }
    return null;
  }

  async function fetchRequests(){
    const sid = getSid();
    const qs = new URLSearchParams();
    if(sid) qs.set('school_id', sid);
    qs.set('status','pending');
  const data = await mkApi.apiJson('api.php?action=pending_requests&'+qs.toString());
    if(!data?.ok) throw new Error(data?.message||'load-failed');
    return data.requests||[];
  }

  async function decideRequest(id, decision){
  return await mkApi.apiJson('api.php?action=request_decision', { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({ id, decision }) });
  }

  function renderTable(container, rows){
    const thead = `
      <thead><tr>
        <th>#</th>
        <th>${t('الاسم','Name')}</th>
        <th>${t('البريد','Email')}</th>
        <th>${t('الدور','Role')}</th>
        <th>${t('الصف','Grade')}</th>
        <th>${t('الجنس','Gender')}</th>
        <th>${t('تاريخ الطلب','Submitted At')}</th>
        <th style="text-align:center">${t('الإجراءات','Actions')}</th>
      </tr></thead>`;
    const tbody = rows && rows.length ? rows.map((r,i)=>{
      const roleTxt = r.role==='teacher'? t('معلم','Teacher') : t('طالب','Student');
      const gradeTxt = r.grade_level ? String(r.grade_level) : '—';
      const genderTxt = r.gender ? (r.gender==='male'? (t('ذكر','Male')) : (t('أنثى','Female'))) : '—';
      const dateTxt = r.created_at ? new Date(r.created_at).toLocaleString() : '—';
      const fullName = mkApi.escapeHtml(`${r.first_name||''} ${r.last_name||''}`.trim());
      return `<tr data-id="${r.id}">
        <td>${i+1}</td>
        <td style="text-align:right">${fullName||'—'}</td>
        <td>${mkApi.escapeHtml(r.email||'—')}</td>
        <td>${roleTxt}</td>
        <td>${gradeTxt}</td>
        <td>${genderTxt}</td>
        <td>${mkApi.escapeHtml(dateTxt)}</td>
        <td style="text-align:center;white-space:nowrap">
          <button class="btn btn-primary btn-approve"><i class="fas fa-check"></i> ${t('موافقة','Approve')}</button>
          <button class="btn btn-outline btn-reject" style="margin-inline-start:6px"><i class="fas fa-times"></i> ${t('رفض','Reject')}</button>
        </td>
      </tr>`; }).join('') : `<tr><td colspan="8" class="placeholder-cell">${t('لا يوجد بيانات','No data')}</td></tr>`;
    container.innerHTML = `<div class='table-container'><table style='width:100%'>${thead}<tbody id='reqTbody'>${tbody}</tbody></table></div>`;
  }

  // Accept optional host element for compatibility mounting
  window.initPendingRequests = async function(hostParam){
    const pc = hostParam || document.getElementById('pageContent'); if(!pc) return;
    // If template provided a card, use its body; otherwise mount a fresh card
    let cardBody = pc.querySelector('.card .card-body');
    if(!cardBody){
      pc.innerHTML = `<div class="card"><div class="card-body"></div></div>`;
      cardBody = pc.querySelector('.card .card-body');
    }

    // Header quick actions (refresh)
    const header = pc.previousElementSibling?.classList.contains('page-header') ? pc.previousElementSibling : null;
    if(header && !header.querySelector('#btnReqRefresh')){
      const qa = header.querySelector('.quick-actions') || (function(){ const d=document.createElement('div'); d.className='quick-actions'; header.appendChild(d); return d; })();
      const btn = document.createElement('button'); btn.id='btnReqRefresh'; btn.className='btn btn-outline'; btn.innerHTML = `<i class="fas fa-rotate"></i> ${t('تحديث','Refresh')}`; qa.appendChild(btn);
      btn.addEventListener('click', async ()=>{ await loadList(); AppCore && AppCore.showToast(t('تم التحديث','Refreshed'),'success'); });
    }

    async function loadList(){
      cardBody.innerHTML = `<div class='table-container'><table style='width:100%'><thead><tr><th>${t('الاسم','Name')}</th><th>${t('البريد','Email')}</th><th>${t('الإجراءات','Actions')}</th></tr></thead><tbody><tr><td colspan='3' class='placeholder-cell'>${t('جاري التحميل','Loading')}...</td></tr></tbody></table></div>`;
      try{
        const rows = await fetchRequests();
        renderTable(cardBody, rows);
        // Wire actions
        cardBody.querySelectorAll('button.btn-approve').forEach(btn=>{
          btn.addEventListener('click', async (ev)=>{
            const tr = ev.target.closest('tr'); const id = tr && tr.dataset.id; if(!id) return;
            btn.disabled = true;
            try{
              const res = await decideRequest(id, 'approved');
              if(!res?.ok){ throw new Error(res?.message||'approve-failed'); }
              AppCore && AppCore.showToast(t('تمت الموافقة','Approved'),'success');
              // Optionally show temp password
              if(res.temp_password){ AppCore && AppCore.showToast(t('كلمة مرور مؤقتة: ','Temp password: ')+res.temp_password, 'info', 8000); }
              await loadList();
            }catch(e){ AppCore && AppCore.showToast((e&&e.message)||t('خطأ','Error'),'error'); btn.disabled=false; }
          });
        });
        cardBody.querySelectorAll('button.btn-reject').forEach(btn=>{
          btn.addEventListener('click', async (ev)=>{
            const tr = ev.target.closest('tr'); const id = tr && tr.dataset.id; if(!id) return;
            if(!confirm(t('تأكيد رفض الطلب؟','Confirm rejecting this request?'))) return;
            btn.disabled = true;
            try{
              const res = await decideRequest(id, 'rejected');
              if(!res?.ok){ throw new Error(res?.message||'reject-failed'); }
              AppCore && AppCore.showToast(t('تم الرفض','Rejected'),'success');
              await loadList();
            }catch(e){ AppCore && AppCore.showToast((e&&e.message)||t('خطأ','Error'),'error'); btn.disabled=false; }
          });
        });
      }catch(e){ cardBody.innerHTML = `<div class='placeholder-cell error'>${mkApi.escapeHtml(e.message||'error')}</div>`; }
    }

    await loadList();
  };
  // Compatibility wrapper so external code can call App.renderPendingRequestsPage(container)
  if(!window.App) window.App = {};
  window.App.renderPendingRequestsPage = async function(container){
    await window.initPendingRequests(container instanceof HTMLElement ? container : undefined);
  };
})();
