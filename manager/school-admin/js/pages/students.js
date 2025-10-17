// Students SPA module extracted from standalone students.html
(function(){
  if(!window.mkApi){ console.warn('mkApi not ready when students.js loaded'); }
  // Register module-specific translations lazily
  if(window.AppCore){
    AppCore.registerTranslations({
      ar:{ fullName:'الاسم الكامل', gradeLabel:'الصف', dob:'تاريخ الميلاد', actions:'الإجراءات', roster:'سجل الصف', addStudent:'إضافة طالب', newStudent:'طالب جديد', editStudent:'تعديل طالب', studentCard:'بطاقة الطالب', generateAccess:'توليد / تحديث رمز الوصول', downloadPdf:'تحميل PDF', code:'الرمز', exp:'انتهاء', nameLabel:'الاسم', tempPassword:'كلمة المرور المؤقتة' },
      en:{ fullName:'Full Name', gradeLabel:'Grade', dob:'Date of Birth', actions:'Actions', roster:'Roster', addStudent:'Add Student', newStudent:'New Student', editStudent:'Edit Student', studentCard:'Student Card', generateAccess:'Generate / Refresh Access Code', downloadPdf:'Download PDF', code:'Code', exp:'Exp', nameLabel:'Name', tempPassword:'Temporary Password' }
    });
  }

  class StudentManagerApp {
    constructor(root){
      this.root = root;
      this.state = { students:[], total:0, page:1, pageSize:10, filters:{ grade:'', search:'' }, editingStudent:null };
      this.renderSkeleton();
      this.cacheElements();
      this.bindEventListeners();
      this.fetchAndRender();
    }
    t(ar,en){ return (App?.currentLang||'ar')==='ar'?ar:en; }
    renderSkeleton(){
      this.root.innerHTML = `
        <section class="card">
          <div class="card-body">
            <div class="toolbar" id="studentsToolbar">
              <div class="form-group">
                <label for="gradeFilter">${this.t('فلترة حسب الصف','Filter by Grade')}</label>
                <select id="gradeFilter">
                  <option value="">${this.t('جميع الصفوف','All Grades')}</option>
                  ${Array.from({length:12},(_,i)=>`<option value="${i+1}">${this.t(['الأول','الثاني','الثالث','الرابع','الخامس','السادس','السابع','الثامن','التاسع','أول ثانوي','ثاني ثانوي','ثالث ثانوي'][i],[`Grade ${i+1}`,`Grade ${i+1}`][0])}</option>`).join('')}
                </select>
              </div>
              <div class="form-group">
                <label for="searchInput">${this.t('بحث بالاسم','Search Name')}</label>
                <input id="searchInput" type="text" placeholder="${this.t('اكتب اسم الطالب...','Type student name...')}">
              </div>
              <div class="form-group">
                <button class="btn btn-outline" id="rosterBtn"><i class="fas fa-list-alt"></i> ${this.t('سجل الصف','Roster')}</button>
              </div>
              <div class="form-group">
                <button class="btn btn-primary" id="addBtn"><i class="fas fa-plus"></i> ${this.t('إضافة طالب جديد','Add Student')}</button>
              </div>
            </div>
          </div>
        </section>
        <section class="card">
          <div class="card-body">
            <div class="table-wrap">
              <table id="studentsTable">
                <thead>
                  <tr>
                    <th>${this.t('الاسم الكامل','Full Name')}</th>
                    <th>${this.t('الصف','Grade')}</th>
                    <th>${this.t('تاريخ الميلاد','DOB')}</th>
                    <th>${this.t('الإجراءات','Actions')}</th>
                  </tr>
                </thead>
                <tbody></tbody>
              </table>
            </div>
            <div class="pagination">
              <div id="pageInfo"></div>
              <div>
                <button class="btn" id="prevPage" disabled>${this.t('السابق','Prev')}</button>
                <button class="btn" id="nextPage" disabled>${this.t('التالي','Next')}</button>
              </div>
            </div>
          </div>
        </section>
        <div id="toast" class="toast"></div>
      `;
    }
    cacheElements(){
      this.els = {
        tableBody: this.root.querySelector('#studentsTable tbody'),
        pageInfo: this.root.querySelector('#pageInfo'),
        prevPageBtn: this.root.querySelector('#prevPage'),
        nextPageBtn: this.root.querySelector('#nextPage'),
        addBtn: this.root.querySelector('#addBtn'),
        rosterBtn: this.root.querySelector('#rosterBtn'),
        gradeFilter: this.root.querySelector('#gradeFilter'),
        searchInput: this.root.querySelector('#searchInput'),
        toast: this.root.querySelector('#toast'),
      };
    }
    async api(action,payload){
      switch(action){
        case 'GET_STUDENTS':{
          const { grade, search, page, pageSize } = payload;
            const qs = new URLSearchParams();
            if(grade) qs.set('grade', grade);
            if(search) qs.set('search', search);
            qs.set('page', page); qs.set('pageSize', pageSize);
            const data = await mkApi.apiJson(`api.php?action=students&${qs.toString()}`);
            if(!data.ok) throw new Error(data.message||'فشل التحميل');
            const students = (data.students||[]).map(r=>({
              id:r.id, firstName:r.first_name||r.firstName||'', fatherName:'', grandfatherName:'', lastName:r.last_name||r.lastName||'', grade:r.grade_level||r.grade||'', dob:r.dob||'', email:r.email||'', school:''
            }));
            return { students, total: data.total || students.length };
        }
        case 'CREATE_STUDENT':{
          const body={ first_name:payload.firstName, last_name:payload.lastName, email:payload.email, grade_level:payload.grade, dob:payload.dob||null, status:'active'};
          const res = await mkApi.apiJson('api.php?action=students',{method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify(body)});
          if(!res.ok) throw new Error(res.message||'تعذر الإضافة'); return res;
        }
        case 'UPDATE_STUDENT':{
          const body={ first_name:payload.firstName, last_name:payload.lastName, grade_level:payload.grade, dob:payload.dob||null, status:'active'};
          const res = await mkApi.apiJson(`api.php?action=students_update&id=${encodeURIComponent(payload.id)}`,{method:'PUT', headers:{'Content-Type':'application/json'}, body:JSON.stringify(body)});
          if(!res.ok) throw new Error(res.message||'تعذر التحديث'); return res;
        }
        case 'DELETE_STUDENT':{
          const resRaw = await mkApi.apiFetch(`api.php?action=students_delete&id=${encodeURIComponent(payload.id)}`, { method:'DELETE' });
          const res = await resRaw.json().catch(()=>({ok:false}));
          if(!res.ok) throw new Error(res.message||'تعذر الحذف'); return res;
        }
        default: throw new Error('إجراء غير معروف');
      }
    }
    bindEventListeners(){
      this.els.addBtn.addEventListener('click',()=>this.openStudentModal());
      this.els.rosterBtn.addEventListener('click',()=>this.openRosterModal());
      this.els.gradeFilter.addEventListener('change',()=>this.handleFilterChange());
      this.els.searchInput.addEventListener('input', this.debounce(()=>this.handleFilterChange(),300));
      this.els.prevPageBtn.addEventListener('click',()=>this.changePage(-1));
      this.els.nextPageBtn.addEventListener('click',()=>this.changePage(1));
    }
    async fetchAndRender(){
      try{
        const {students,total}= await this.api('GET_STUDENTS',{...this.state.filters,page:this.state.page,pageSize:this.state.pageSize});
        this.state.students = students; this.state.total = total;
        this.renderTable(); this.renderPagination();
      }catch(e){ this.showToast('خطأ: '+e.message,'error'); }
    }
    renderTable(){
      const tb = this.els.tableBody; tb.innerHTML='';
      if(this.state.students.length===0){ tb.innerHTML = `<tr><td colspan="4" style="text-align:center">${this.t('لا يوجد طلاب','No Students')}</td></tr>`; return; }
      this.state.students.forEach(st=>{
        const fullName = `${st.firstName} ${st.fatherName} ${st.grandfatherName} ${st.lastName}`;
        const tr = document.createElement('tr');
        tr.innerHTML = `
          <td>${fullName}</td>
          <td>${this.mapGrade(st.grade)}</td>
          <td>${st.dob||'-'}</td>
          <td>
            <button class="btn btn-sm" data-action="edit" data-id="${st.id}"><i class="fas fa-edit"></i></button>
            <button class="btn btn-sm" data-action="card" data-id="${st.id}"><i class="fas fa-id-card"></i></button>
            <button class="btn btn-sm" data-action="delete" data-id="${st.id}"><i class="fas fa-trash"></i></button>
          </td>`;
        tb.appendChild(tr);
      });
      tb.querySelectorAll('button').forEach(b=>b.addEventListener('click',e=>{ const act=e.currentTarget.dataset.action; const id=parseInt(e.currentTarget.dataset.id); this.handleTableAction(act,id); }));
    }
    renderPagination(){
      const {page,pageSize,total}=this.state; const totalPages = Math.ceil(total / pageSize)||1;
      this.els.pageInfo.textContent = `${this.t('صفحة','Page')} ${page} / ${totalPages} (${this.t('إجمالي','Total')} ${total})`;
      this.els.prevPageBtn.disabled = page===1; this.els.nextPageBtn.disabled = page>=totalPages;
    }
    handleFilterChange(){ this.state.page=1; this.state.filters.grade=this.els.gradeFilter.value; this.state.filters.search=this.els.searchInput.value; this.fetchAndRender(); }
    changePage(dir){ this.state.page += dir; this.fetchAndRender(); }
    handleTableAction(action,id){ const st=this.state.students.find(s=>s.id===id); if(!st) return; if(action==='edit') this.openStudentModal(st); else if(action==='card') this.openCardModal(st); else if(action==='delete'){ if(confirm(this.t('تأكيد الحذف؟','Delete?'))){ this.api('DELETE_STUDENT',{id}).then(()=>{this.showToast(this.t('تم الحذف','Deleted')); this.fetchAndRender();}).catch(er=>this.showToast(er.message,'error')); } } }
    openStudentModal(student=null){
      this.state.editingStudent = student; const modalId='studentModal'; document.getElementById(modalId)?.remove();
      const gradeOptions = Array.from(this.els.gradeFilter.options).map(o=>`<option value="${o.value}">${o.textContent}</option>`).join('');
      const wrap = document.createElement('div'); wrap.id=modalId; wrap.className='modal-overlay';
      wrap.innerHTML = `
        <div class="modal-content">
          <div class="modal-header"><h2 class="modal-title">${student?this.t('تعديل طالب','Edit Student'):this.t('طالب جديد','New Student')}</h2></div>
          <form id="studentForm" class="modal-body">
            <div class="toolbar" style="gap:12px;">
              <div class="form-group"><label>${this.t('الاسم الأول','First Name')}</label><input name="firstName" value="${student?.firstName||''}" required></div>
              <div class="form-group"><label>${this.t('اللقب','Last Name')}</label><input name="lastName" value="${student?.lastName||''}" required></div>
              <div class="form-group"><label>${this.t('البريد الإلكتروني','Email')}</label><input type="email" name="email" value="${student?.email||''}" required></div>
              <div class="form-group"><label>${this.t('تاريخ الميلاد','DOB')}</label><input type="date" name="dob" value="${student?.dob||''}"></div>
              <div class="form-group"><label>${this.t('الصف','Grade')}</label><select name="grade" required>${gradeOptions}</select></div>
            </div>
          </form>
          <div class="modal-footer">
            <button class="btn btn-outline" data-action="close">${this.t('إلغاء','Cancel')}</button>
            <button class="btn btn-primary" id="saveStudentBtn">${this.t('حفظ','Save')}</button>
          </div>
        </div>`;
      document.body.appendChild(wrap);
      if(student) wrap.querySelector('select[name="grade"]').value = student.grade;
      wrap.querySelector('#saveStudentBtn').addEventListener('click',()=>this.handleSaveStudent());
      wrap.querySelector('[data-action="close"]').addEventListener('click',()=>this.closeModal(modalId));
      setTimeout(()=>wrap.classList.add('visible'),10);
    }
    async handleSaveStudent(){
      const form=document.getElementById('studentForm'); const fd=new FormData(form); const payload=Object.fromEntries(fd.entries());
      try{
        if(this.state.editingStudent){ await this.api('UPDATE_STUDENT',{id:this.state.editingStudent.id,...payload}); this.showToast(this.t('تم التحديث','Updated')); }
        else { const res= await this.api('CREATE_STUDENT',payload); if(res.tempPassword){ alert(this.t('كلمة المرور المؤقتة: ','Temp Password: ')+res.tempPassword); } this.showToast(this.t('تمت الإضافة','Added')); }
        this.closeModal('studentModal'); this.fetchAndRender();
      }catch(e){ this.showToast(e.message,'error'); }
    }
    openCardModal(student){
      const modalId='cardModal'; document.getElementById(modalId)?.remove();
      const wrap=document.createElement('div'); wrap.id=modalId; wrap.className='modal-overlay';
      wrap.innerHTML = `
        <div class="modal-content" style="max-width:450px;">
          <div class="modal-header"><h2 class="modal-title">${this.t('بطاقة الطالب','Student Card')}</h2></div>
          <div class="modal-body">
            <div class="id-card-preview" id="cardPreviewContainer">
              <div class="id-card-header"><h3>${student.school||''}</h3></div>
              <div class="id-card-body" style="display:flex;gap:16px;align-items:center;">
                <div class="id-card-info" style="flex:1;">
                  <p><strong>${this.t('الاسم','Name')}:</strong> ${student.firstName} ${student.fatherName} ${student.grandfatherName} ${student.lastName}</p>
                  <p><strong>${this.t('تاريخ الميلاد','DOB')}:</strong> ${student.dob||'-'}</p>
                  <p><strong>${this.t('الصف','Grade')}:</strong> ${this.mapGrade(student.grade)}</p>
                </div>
                <div class="id-card-qr" id="qrCodeContainer" style="width:100px;height:100px;"></div>
              </div>
              <div style="margin-top:12px;">
                <button id="btnGenAccess" class="btn btn-sm" style="background:#2563eb;color:#fff;border:0;padding:6px 10px;border-radius:6px;cursor:pointer">${this.t('توليد / تحديث رمز الوصول','Generate / Refresh Access Code')}</button>
                <span id="accessMeta" style="font-size:12px;color:#555"></span>
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button class="btn btn-outline" data-action="close">${this.t('إغلاق','Close')}</button>
            <button class="btn btn-primary" id="downloadCardBtn">${this.t('تحميل PDF','Download PDF')}</button>
          </div>
        </div>`;
      document.body.appendChild(wrap);
      setTimeout(()=>wrap.classList.add('visible'),10);
      let currentPayload = JSON.stringify({ studentId: student.id });
      const qrContainer = wrap.querySelector('#qrCodeContainer');
      const drawQR = (text)=>{ if(!qrContainer) return; qrContainer.innerHTML=''; if(window.QRCode){ new QRCode(qrContainer,{ text, width:100,height:100 }); } };
      this.ensureQrLibs().then(()=>drawQR(currentPayload));
      wrap.querySelector('#btnGenAccess').addEventListener('click', async ()=>{
        try{
          const data = await mkApi.apiJson(`api.php?action=student_access_code&studentId=${encodeURIComponent(student.id)}`);
          if(!data.ok) throw new Error(data.message||'فشل توليد الرمز');
          currentPayload = data.payload || currentPayload; drawQR(currentPayload);
          const exp = data.exp ? new Date(data.exp*1000).toLocaleString(App.currentLang==='ar'?'ar-EG':'en-US') : '';
          wrap.querySelector('#accessMeta').textContent = `${this.t('الرمز','Code')}: ${(data.code||'')} (${this.t('انتهاء','Exp')} ${exp})`;
        }catch(err){ alert(err.message); }
      });
      wrap.querySelector('#downloadCardBtn').addEventListener('click',()=>this.downloadCardAsPDF(student,currentPayload));
      wrap.querySelector('[data-action="close"]').addEventListener('click',()=>this.closeModal(modalId));
    }
    async ensureQrLibs(){
      if(!window.AppCore) return; // fallback
      await AppCore.ensureLib('qrcode', ()=>window.QRCode, 'https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js');
      if(!window.jspdf){ await AppCore.ensureLib('jspdf', ()=>window.jspdf, 'https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js'); }
      if(!window.jspdf?.jsPDF?.autoTable){ await AppCore.ensureLib('jspdf-autotable', ()=>window.jspdf?.jsPDF?.autoTable, 'https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.23/jspdf.plugin.autotable.min.js'); }
    }
    downloadCardAsPDF(student,qrPayload){
      if(!window.jspdf){ this.showToast(this.t('مكتبات PDF غير جاهزة','PDF libs not ready'),'error'); return; }
      const { jsPDF } = window.jspdf; const doc = new jsPDF({ unit:'mm', format:[85.6,54] });
      const temp=document.createElement('div'); new QRCode(temp,{ text:qrPayload, width:256,height:256 });
      const canvas=temp.querySelector('canvas'); const dataUrl=canvas?canvas.toDataURL('image/png'):null;
      doc.setFontSize(10); doc.text(student.school||'', 43, 10, {align:'center'});
      if(dataUrl) doc.addImage(dataUrl,'PNG',10,15,25,25);
      doc.setFontSize(8);
      const fullName = `${student.firstName} ${student.fatherName} ${student.grandfatherName} ${student.lastName}`;
      doc.text(this.t('الاسم:','Name:')+fullName, 80,20,{align:'right'});
      doc.text(this.t('تاريخ الميلاد:','DOB:')+(student.dob||'-'), 80,28,{align:'right'});
      doc.text(this.t('الصف:','Grade:')+this.mapGrade(student.grade), 80,36,{align:'right'});
      doc.save(`student-card-${student.id}.pdf`);
    }
    openRosterModal(){ const grade=this.els.gradeFilter.value; if(!grade){ this.showToast(this.t('اختر صفاً','Select a grade'),'error'); return; } alert(this.t('قريباً: سجل الصف','Roster soon')); }
    closeModal(id){ const m=document.getElementById(id); if(m){ m.classList.remove('visible'); setTimeout(()=>m.remove(),300);} }
  showToast(msg,type='success'){ if(window.AppCore){ AppCore.showToast(msg, type==='error'?'error':(type==='success'?'success':'info')); return; } const t=this.els.toast; t.textContent=msg; t.style.backgroundColor= type==='error'?'#dc2626':'#16a34a'; t.classList.add('show'); setTimeout(()=>t.classList.remove('show'),3000); }
    debounce(fn,delay){ let to; return (...a)=>{ clearTimeout(to); to=setTimeout(()=>fn.apply(this,a),delay);}; }
    mapGrade(g){ const ar=['الأول','الثاني','الثالث','الرابع','الخامس','السادس','السابع','الثامن','التاسع','أول ثانوي','ثاني ثانوي','ثالث ثانوي']; return ar[(parseInt(g,10)||0)-1]||g||'-'; }
  }

  // Accept optional host for compatibility mounting
  window.initStudents = function(host){
    const container = host || document.getElementById('pageContent');
    if(!container) return;
    container.innerHTML = '<div id="studentsPage"></div>';
    const mount = container.querySelector('#studentsPage');
    new StudentManagerApp(mount);
  };
  // Compatibility wrapper so external code can call App.renderStudentsPage(container)
  if(!window.App) window.App = {};
  window.App.renderStudentsPage = function(container){
    return window.initStudents(container instanceof HTMLElement ? container : undefined);
  };
})();
