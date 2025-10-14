function buildApiUrl(path){
  const base = (window.APP_CONFIG && APP_CONFIG.API_BASE) || '/api/';
  const cleanBase = base.replace(/\/+/g,'/').replace(/\/?$/,'/');
  const cleanPath = String(path).replace(/^\/+/, '').replace(/^api\//,'');
  return cleanBase + cleanPath;
}

let newsItems = [];

async function fetchFrontpage(){
  try {
    const schoolIds = [1,2,3];
    const all = [];
    await Promise.all(schoolIds.map(async id=>{
      const r = await fetch(buildApiUrl(`activities.php?school_id=${id}&limit=4`));
      const d = await r.json();
      if(d && d.ok && Array.isArray(d.activities)){
        all.push(...d.activities.map(a=>({...a, school_id:id})));
      }
    }));
    all.sort((a,b)=> new Date(b.created_at)- new Date(a.created_at));
    newsItems = all.slice(0,12).map(a=>({
      titleAr: a.title,
      titleEn: a.title,
      date: a.created_at,
      typeAr: a.media_type==='news'?'خبر':'نشاط',
      typeEn: a.media_type==='news'?'News':'Activity',
      briefAr: a.description||'',
      briefEn: a.description||'',
      media: a.media_url,
      mediaType: a.media_type
    }));
    renderNews();
  } catch(e){ console.error('frontpage fetch failed', e); }
}

function renderNews(){
  const grid = document.getElementById('newsGrid'); if(!grid) return;
  grid.innerHTML='';
  if(!newsItems.length){
    grid.innerHTML = `<p style='text-align:center;color:var(--text-light)'>${currentLanguage==='ar'?'لا توجد أنشطة':'No activities yet'}</p>`;
    return;
  }
  newsItems.forEach(n=>{
    const card = document.createElement('div'); card.className='news-card';
    const isActivity = (n.typeAr === 'نشاط');
    const mediaHtml = n.media
      ? (n.mediaType==='video'
        ? `<video src='${n.media}' style='width:100%;border-radius:8px;margin-bottom:8px' controls></video>`
        : `<img src='${n.media}' style='width:100%;border-radius:8px;margin-bottom:8px' alt='media'>`)
      : '';
    card.innerHTML = `
      ${mediaHtml}
      <div style="display:flex;gap:10px;align-items:center;margin-bottom:8px">
        <span style="font-size:12px;color:#6b7280">${new Date(n.date).toLocaleDateString(currentLanguage==='ar'?'ar':'en')}</span>
        <span class="badge" style="margin-inline-start:auto;background:${isActivity?'#dcfce7':'#e0e7ff'};color:${isActivity?'#166534':'#1e3a8a'}">${currentLanguage==='ar'?n.typeAr:n.typeEn}</span>
      </div>
      <h3 style="color:var(--primary);font-size:18px;margin-bottom:6px">${currentLanguage==='ar'?n.titleAr:n.titleEn}</h3>
      <p style="color:var(--text-light);font-size:14px">${currentLanguage==='ar'?n.briefAr:n.briefEn}</p>`;
    grid.appendChild(card);
  });
}

// Scholarships datasource and helpers
const scholarships = [
  // Top 6 (default homepage cards)
  { name:'منحة تركيا الحكومية', nameEn:'Turkey Government Scholarship', country:'Turkey', countryAr:'تركيا', level:'university', levelAr:'جامعة', levels:['bachelor','master','phd'], funding:'full', description:'منح ممولة بالكامل للبكالوريوس والماجستير والدكتوراه', descriptionEn:'Fully funded for Bachelor, Master, PhD', link:'https://www.turkiyeburslari.gov.tr', pageLink:'scholarship-turkey.html' },
  { name:'منحة وقف الديانة التركية', nameEn:'Diyanet Scholarship', country:'Turkey', countryAr:'تركيا', level:'secondary', levelAr:'ثانوية', levels:['secondary','bachelor'], funding:'full', description:'تمويل شامل (سكن، إعاشة، رسوم، أنشطة) للثانوي والبكالوريوس الشرعي', descriptionEn:'Fully funded (housing, meals, tuition, activities)', pageLink:'scholarship-diyanet.html' },
  { name:'منحة الحكومة الرومانية', nameEn:'Romanian Government Scholarship', country:'Romania', countryAr:'رومانيا', level:'university', levelAr:'جامعة', levels:['bachelor','master','phd'], funding:'full', description:'إعفاء ورسوم وسكن وبدل شهري (باستثناء التخصصات الطبية)', descriptionEn:'Covers tuition, housing, stipend (excludes medical fields)', pageLink:'scholarship-romania.html' },
  { name:'منحة كازاخستان الحكومية 2025', nameEn:'Kazakhstan Government 2025', country:'Kazakhstan', countryAr:'كازاخستان', level:'university', levelAr:'جامعة', levels:['bachelor','master','phd'], funding:'full', description:'حوالي 550 مقعداً مع إعفاء ورسوم ومعيشة جزئية', descriptionEn:'~550 seats with tuition waiver and stipend', pageLink:'scholarship-kazakhstan.html' },
  { name:'منح التبادل الثقافي اليمنية', nameEn:'Yemeni Cultural Exchange', country:'Yemen', countryAr:'اليمن', level:'secondary', levelAr:'ثانوية', levels:['bachelor','master','phd'], funding:'partial', description:'مقاعد تبادل ثقافي عبر وزارة التعليم العالي (امتحان تنافسي)', descriptionEn:'Government cultural exchange seats (competitive exam)', pageLink:'scholarship-cultural-exchange.html' },
  { name:'ادرس في السعودية', nameEn:'Study in Saudi', country:'Saudi', countryAr:'السعودية', level:'university', levelAr:'جامعة', levels:['bachelor','master','phd'], funding:'full', description:'بوابة رسمية للتقديم على الجامعات السعودية (منح كاملة/جزئية وتمويل ذاتي)', descriptionEn:'Official portal for Saudi universities (full/partial/self-funded)', link:'https://my.gov.sa/en/services/18650', pageLink:'portal-study-in-saudi.html' },

  // More
  { name:'ادرس في العراق', nameEn:'Study in Iraq', country:'Iraq', countryAr:'العراق', level:'university', levelAr:'جامعة', levels:['bachelor','master','phd'], funding:'partial', description:'منصة وطنية للقبول الدولي (منح كاملة/جزئية ومقاعد خاصة)', descriptionEn:'National portal for international admissions (full/partial/special seats)', link:'https://studyiniraq.scrd-gate.gov.iq/home', pageLink:'scholarship-study-in-iraq.html' },
  { name:'منح الصين – CSC الفئة B', nameEn:'China CSC Type B – University Program', country:'China', countryAr:'الصين', level:'university', levelAr:'جامعة', levels:['bachelor','master','phd'], funding:'full', description:'التقديم عبر الجامعة ضمن بوابة CSC (Type B)', descriptionEn:'Apply via the university on CSC portal (Type B)', link:'https://studyinchina.csc.edu.cn/#/login', pageLink:'scholarship-china-type-b.html' },
  { name:'ملف تجميعي للجامعات والمنح', nameEn:'Universities & Scholarships Summary', country:'Global', countryAr:'عام', level:'university', levelAr:'جامعة', levels:['bachelor','master','phd'], funding:'partial', description:'ملخص للفئات (CSC/MIS وغيره) وروابط منصات مختارة', descriptionEn:'Summary of categories (CSC/MIS etc.) and selected platforms', pageLink:'scholarships-universities-summary.html' },
  { name:'منح الصين', nameEn:'China Scholarships', country:'China', countryAr:'الصين', level:'university', levelAr:'جامعة', levels:['bachelor','master','phd'], funding:'full', description:'منح في تخصصات متعددة مع سنة لغة', descriptionEn:'Multiple fields with language year', link:'https://www.campuschina.org' },
  { name:'منح المغرب', nameEn:'Morocco Scholarships', country:'Morocco', countryAr:'المغرب', level:'university', levelAr:'جامعة', levels:['bachelor','master','phd'], funding:'partial', description:'إعفاء من الرسوم في الجامعات الحكومية', descriptionEn:'Tuition waiver in public universities' },
  { name:'منح الجزائر', nameEn:'Algeria Scholarships', country:'Algeria', countryAr:'الجزائر', level:'university', levelAr:'جامعة', levels:['bachelor','master'], funding:'partial', description:'مقاعد مجانية (رسوم) لتخصصات إنسانية وتقنية', descriptionEn:'Free seats (tuition) for humanities & tech' }
];
// expose for other modules
window.SCHOLARSHIPS = scholarships;

function displayScholarships(list){
  const container = document.getElementById('scholarshipResults');
  if(!container) return;
  container.innerHTML='';
  if(!list.length){
    container.innerHTML = `<p style='text-align:center;color:var(--text-light)'>${currentLanguage==='ar'?'لا توجد نتائج':'No results found'}</p>`;
    return;
  }
  list.forEach(s=>{
    const card=document.createElement('div'); card.className='scholarship-card';
    const title = currentLanguage==='ar'?s.name:s.nameEn;
    const countryTxt = currentLanguage==='ar'?s.countryAr:s.country;
    const levelTxt = currentLanguage==='ar'?s.levelAr:s.level;
    const desc = currentLanguage==='ar'?s.description:s.descriptionEn;
  const moreBtn = s.pageLink?`<a href='${s.pageLink}' class='apply-btn' style='background:var(--primary);margin-${currentLanguage==='ar'?'right':'left'}:8px' target='_blank' rel='noopener'>${currentLanguage==='ar'?'معرفة أكثر':'Details'}</a>`:'';
  const applyBtn = s.link?`<a href='${s.link}' target='_blank' rel='noopener' class='apply-btn' style='background:var(--success)'>${currentLanguage==='ar'?'التقديم المباشر':'Apply'}</a>`:'';
    card.innerHTML = `
      <h3>${title}</h3>
      <div class='country'>${countryTxt} • ${levelTxt} • ${(currentLanguage==='ar'?(s.funding==='full'?'تمويل كامل':'جزئي'):(s.funding==='full'?'Full':'Partial'))}</div>
      <p class='description'>${desc}</p>
      <div class='flex-wrap-gap'>${applyBtn}${moreBtn}</div>`;
    container.appendChild(card);
  });
}

function searchScholarships(e){
  e.preventDefault();
  const country = document.getElementById('country').value;
  const level = document.getElementById('level').value;
  const funding = document.getElementById('funding').value;
  const field = (document.getElementById('field').value||'').toLowerCase();
  let filtered = scholarships.filter(s=>{
    if(country && s.country !== country) return false;
    if(level && !(s.level===level || (Array.isArray(s.levels) && s.levels.includes(level)))) return false;
    if(funding && s.funding !== funding) return false;
    if(field && !( (s.description+" "+(s.descriptionEn||'')).toLowerCase().includes(field) || (s.name+" "+s.nameEn).toLowerCase().includes(field))) return false;
    return true;
  });
  displayScholarships(filtered);
}

window.buildApiUrl = buildApiUrl;
window.fetchFrontpage = fetchFrontpage;
window.searchScholarships = searchScholarships;
window.displayScholarships = displayScholarships;
