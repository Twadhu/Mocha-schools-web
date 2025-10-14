(function(){
  if(window.AppCore){ AppCore.registerTranslations({ ar:{ addResult:'إضافة نتيجة', gradesSubtitle:'عرض وإدارة درجات الطلاب', firstScore:'درجة أولى', finalScore:'درجة نهائية', subject:'المادة', term:'الفصل', student:'الطالب', save:'حفظ', gradeLevel:'الصف' }, en:{ addResult:'Add Result', gradesSubtitle:'View & manage student grades', firstScore:'First', finalScore:'Final', subject:'Subject', term:'Term', student:'Student', save:'Save', gradeLevel:'Grade'} }); }
  function t(a,b){ return (window.App?.currentLang||'ar')==='ar'?a:b; }
  async function fetchScoreLimits(){ const d=await mkApi.apiJson('admin.php?path=score-limits'); return d; }
  async function fetchRoster(grade,term,year){ const qs=new URLSearchParams({grade,term,year}); const d=await mkApi.apiJson('admin.php?path=results&'+qs.toString()); if(!d.ok) throw new Error(d.message||'failed'); return d.items||[]; }
  async function upsert(student_id, subject, term, year, first, final){ const d=await mkApi.apiJson('admin.php?path=results',{method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify({student_id,subject,term,year,first,final})}); if(!d.ok) throw new Error(d.message||'failed'); }
  window.initGrades = async function(){
    // Basic interactive form
    const gradeSel=document.getElementById('gradesGradeSelect');
    const subjSel=document.getElementById('gradesSubjectSelect');
    const termSel=document.getElementById('gradesTermSelect');
    ['','1','2','3','4','5','6','7','8','9','10','11','12'].forEach(g=>{ if(g) gradeSel.insertAdjacentHTML('beforeend',`<option value='${g}'>${g}</option>`); });
    subjSel.innerHTML='<option value="math">Math</option><option value="arabic">Arabic</option>';
    termSel.innerHTML='<option value="first">'+t('الأول','First')+'</option><option value="second">'+t('الثاني','Second')+'</option>';
    const tbody=document.getElementById('gradesList');
    async function load(){ const grade=gradeSel.value; const term=termSel.value; const subj=subjSel.value; if(!grade){ tbody.innerHTML='<tr><td colspan="4" class="placeholder-cell">'+t('اختر صفاً','Select a grade')+'</td></tr>'; return; } tbody.innerHTML='<tr><td colspan="4" class="placeholder-cell">'+t('جاري التحميل','Loading...')+'</td></tr>'; try{ const items=await fetchRoster(grade,term,new Date().getFullYear()); if(!items.length){ tbody.innerHTML='<tr><td colspan="4" class="placeholder-cell">'+t('لا يوجد بيانات','No data')+'</td></tr>'; return; } tbody.innerHTML=items.map(it=>{ const subjRow = (it.subjects||[]).find(s=>s.name===subj)||{first:0,final:0}; return `<tr data-id='${it.id}'><td>${it.name}</td><td>${subj}</td><td>${term}</td><td><input type='number' class='firstInp' style='width:60px' value='${subjRow.first}' min='0'> / <input type='number' class='finalInp' style='width:60px' value='${subjRow.final}' min='0'></td></tr>`; }).join(''); }catch(e){ tbody.innerHTML='<tr><td colspan="4" class="placeholder-cell" style="color:#c00">'+e.message+'</td></tr>'; } }
    gradeSel.addEventListener('change',load); termSel.addEventListener('change',load); subjSel.addEventListener('change',load);
    document.getElementById('btnAddResult').addEventListener('click', async ()=>{
      const grade=gradeSel.value; if(!grade) return AppCore.showToast(t('اختر صفاً أولاً','Pick grade first'),'error');
      const term=termSel.value; const subj=subjSel.value; const year=new Date().getFullYear();
      // Save all visible rows
      const rows=[...tbody.querySelectorAll('tr[data-id]')];
      for(const r of rows){ const sid=parseInt(r.dataset.id); const first=parseInt(r.querySelector('.firstInp').value)||0; const final=parseInt(r.querySelector('.finalInp').value)||0; try{ await upsert(sid,subj,term,year,first,final); }catch(e){ AppCore.showToast(t('فشل حفظ طالب','Failed student')+':'+sid,'error'); } }
      AppCore.showToast(t('تم الحفظ','Saved'),'success');
      load();
    });
  };
})();
