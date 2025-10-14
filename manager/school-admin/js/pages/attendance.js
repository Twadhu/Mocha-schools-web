(function(){
  if(window.AppCore){ AppCore.registerTranslations({ ar:{ attendance:'الحضور' }, en:{ attendance:'Attendance'} }); }
  function t(a,b){ return (App?.currentLang||'ar')==='ar'?a:b; }
  async function fetchAttendance(params){ const qs=new URLSearchParams(params); const d=await mkApi.apiJson('admin.php?path=attendance&'+qs.toString()); if(!d.ok) throw new Error(d.message||'failed'); return d.items||[]; }
  window.initAttendance = function(host){
    const tpl = host || document.getElementById('pageContent');
    if(!tpl) return;
    let cardBody = tpl.querySelector('.card .card-body');
    if(!cardBody){ tpl.innerHTML = `<div class="card"><div class="card-body"></div></div>`; cardBody = tpl.querySelector('.card .card-body'); }
    cardBody.innerHTML = `<div style='display:flex;gap:8px;flex-wrap:wrap;margin-bottom:12px'>
      <input type='date' id='attDate'>
      <select id='attGrade'><option value=''>${t('الصف','Grade')}</option>${Array.from({length:12},(_,i)=>`<option value='${i+1}'>${i+1}</option>`).join('')}</select>
      <button class='btn btn-outline' id='btnLoadAtt'>${t('تحميل','Load')}</button>
    </div>
    <div class='table-container'><table style='width:100%'><thead><tr><th>${t('التاريخ','Date')}</th><th>${t('الطالب','Student')}</th><th>${t('الصف','Grade')}</th><th>${t('الحالة','Status')}</th></tr></thead><tbody id='attTbody'><tr><td colspan='4' style='text-align:center'>${t('انتظر','Wait')}...</td></tr></tbody></table></div>`;
    const tbody=cardBody.querySelector('#attTbody');
    async function load(){ const date=cardBody.querySelector('#attDate').value; const grade=cardBody.querySelector('#attGrade').value; tbody.innerHTML='<tr><td colspan=4 style="text-align:center">'+t('جاري التحميل','Loading...')+'</td></tr>'; try{ const rows=await fetchAttendance({date,grade}); if(!rows.length){ tbody.innerHTML='<tr><td colspan=4 style="text-align:center">'+t('لا يوجد بيانات','No data')+'</td></tr>'; return; } tbody.innerHTML=rows.map(r=>`<tr><td>${r.att_date}</td><td>${r.student_name}</td><td>${r.grade_level}</td><td>${r.status}</td></tr>`).join(''); }catch(e){ tbody.innerHTML='<tr><td colspan=4 style="text-align:center;color:#c00">'+e.message+'</td></tr>'; } }
    cardBody.querySelector('#btnLoadAtt').addEventListener('click',load);
  };
  // Compatibility wrapper
  if(!window.App) window.App = {};
  window.App.renderAttendancePage = async function(container){ await window.initAttendance(container instanceof HTMLElement ? container : undefined); };
})();
