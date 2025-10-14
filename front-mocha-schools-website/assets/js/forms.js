const getStartedUrl = (window.APP_CONFIG && APP_CONFIG.getStartedUrl) || '/public/portal/signup.html?ref=home';

function openRequestModal(){
  const el = document.getElementById('accountRequestModal'); if(el){ el.style.display='flex'; }
  if(window.updateLanguage) updateLanguage();
}
function closeRequestModal(){
  const modal = document.getElementById('accountRequestModal'); if(modal){ modal.style.display='none'; }
  const form = document.getElementById('accountRequestForm'); if(form){ form.reset(); }
  const msg = document.getElementById('reqMsg'); if(msg){ msg.textContent=''; }
  toggleRoleFields();
}

function toggleRoleFields(){
  const role = (document.getElementById('reqRole')||{}).value;
  const gradeWrapper = document.getElementById('gradeWrapper');
  const genderWrapper = document.getElementById('genderWrapper');
  const isStudent = role === 'student';
  if(gradeWrapper) gradeWrapper.classList.toggle('hidden', !isStudent);
  if(genderWrapper) genderWrapper.classList.toggle('hidden', !isStudent);
}

async function populateSchools(){
  const sel = document.getElementById('reqSchool');
  if(!sel) return;
  const fallback = (APP_CONFIG && APP_CONFIG.schoolsFallback) || [];
  try {
    const res = await fetch(buildApiUrl('public/schools.php'));
    const data = await res.json();
    let list = [];
    if(res.ok && data && data.ok && Array.isArray(data.schools) && data.schools.length){
      const allowedIds = new Set([1,2,3]);
      list = data.schools.filter(s => allowedIds.has(Number(s.id)));
      list.push({id:'other', name:'أخرى'});
    } else {
      list = fallback;
    }
    sel.innerHTML = list.map(s=>`<option value="${s.id}">${s.name}</option>`).join('');
  } catch(e){
    sel.innerHTML = fallback.map(s=>`<option value="${s.id}">${s.name}</option>`).join('');
  }
}

function grantAccessWithoutSubmit(reqMsg){
  reqMsg.textContent = (currentLanguage==='ar')
    ? 'تم منحك وصولًا لميزات "ابدأ الآن" دون الحاجة لمراجعة الإدارة.'
    : 'You now have access to "Get Started" features without admin review.';
  reqMsg.style.color='var(--success)';
  setTimeout(()=>{ location.href = getStartedUrl; }, 1200);
}

async function submitAccountRequest(event){
  event.preventDefault();
  const form   = document.getElementById('accountRequestForm');
  const submit = form.querySelector('button[type="submit"]');
  const reqMsg = document.getElementById('reqMsg');

  reqMsg.textContent = currentLanguage==='ar'?'جاري الإرسال...':'Sending...';
  reqMsg.style.color = '#555';
  submit.disabled = true; const prev = submit.textContent; submit.textContent = currentLanguage==='ar'?'جارٍ الإرسال':'Sending';

  try {
    const role      = document.getElementById('reqRole').value;         // student | teacher
    const schoolVal = document.getElementById('reqSchool').value;       // 1 | 2 | 3 | 'other'
    if(!schoolVal){ throw new Error('NO_SCHOOL'); }

    const email = document.getElementById('reqEmail').value.trim();
    if(!/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(email)){ throw new Error('BAD_EMAIL'); }
    const first = document.getElementById('reqFirst').value.trim();
    const last  = document.getElementById('reqLast').value.trim();
    if(!first || !last){ throw new Error('NAME_REQUIRED'); }

    const body = { role, school_id: schoolVal, first_name:first, last_name:last, email };

    // 'other' school => do not send to backend
    if(String(schoolVal) === 'other'){
      try {
        const all = JSON.parse(localStorage.getItem('accountRequests')||'[]');
        all.push({...body, status:'granted_school_other', ts: Date.now()});
        localStorage.setItem('accountRequests', JSON.stringify(all));
      } catch(e){}
      grantAccessWithoutSubmit(reqMsg);
      return;
    }

    if(role === 'student'){
      const grade  = document.getElementById('reqGrade').value;
      const gender = document.getElementById('reqGender').value;
      body.grade_level = grade; body.gender = gender;

      if(grade === 'graduate' || grade === 'other'){
        try {
          const all = JSON.parse(localStorage.getItem('accountRequests')||'[]');
          all.push({...body, status:'granted_grade_exception', ts: Date.now()});
          localStorage.setItem('accountRequests', JSON.stringify(all));
        } catch(e){}
        grantAccessWithoutSubmit(reqMsg);
        return;
      }
    }

    // resolve endpoint map (1/2/3 only)
    const map = (APP_CONFIG && APP_CONFIG.schoolApiMap) || {};
  const endpoints = map[Number(schoolVal)];
  let endpoint = endpoints && endpoints[role];
  // Fallback: use central account-requests.php if per-school/role endpoint isn't configured
  if(!endpoint){ endpoint = 'account-requests.php'; }

    const res = await fetch(buildApiUrl(endpoint), {
      method: 'POST',
      headers: {'Content-Type':'application/json'},
      body: JSON.stringify(body)
    });

    let data = null; try { data = await res.json(); } catch{}
    if(res.ok && data && data.ok){
      reqMsg.textContent = currentLanguage==='ar'?'تم إرسال طلبك بنجاح وسيتم مراجعته':'Request sent successfully and will be reviewed';
      reqMsg.style.color = 'var(--success)';
      try {
        const all = JSON.parse(localStorage.getItem('accountRequests')||'[]');
        all.push({...body, status:'pending', ts: Date.now()});
        localStorage.setItem('accountRequests', JSON.stringify(all));
      } catch(e){}
      setTimeout(closeRequestModal, 2000);
    } else {
      const message = (data && data.message) || (res.status===404 ? (currentLanguage==='ar'?'غير موجود':'Not found') : (currentLanguage==='ar'?'فشل إرسال الطلب':'Failed to send'));
      reqMsg.textContent = message + (res.ok?'':` (HTTP ${res.status})`);
      reqMsg.style.color = 'var(--danger)';
    }
  } catch(e){
    console.error(e);
    let msg;
    if(e.message==='NO_SCHOOL')   msg = currentLanguage==='ar'?'اختر المدرسة':'Select a school';
    else if(e.message==='BAD_EMAIL') msg = currentLanguage==='ar'?'بريد إلكتروني غير صالح':'Invalid email';
    else if(e.message==='NAME_REQUIRED') msg = currentLanguage==='ar'?'الاسم مطلوب':'Name required';
    else if(e.message==='NO_ENDPOINT') msg = currentLanguage==='ar'?'لا يوجد مسار إعداد لهذه المدرسة':'No endpoint configured for this school';
    else msg = currentLanguage==='ar'?'حدث خطأ في الشبكة':'Network error';
    reqMsg.textContent = msg; reqMsg.style.color='var(--danger)';
  } finally {
    submit.disabled = false; submit.textContent = prev;
  }
}

window.openRequestModal = openRequestModal;
window.closeRequestModal = closeRequestModal;
window.toggleRoleFields  = toggleRoleFields;
window.submitAccountRequest = submitAccountRequest;
window.populateSchools = populateSchools;
