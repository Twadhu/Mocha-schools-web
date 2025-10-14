let currentLanguage = localStorage.getItem('lang') || 'ar';

function toggleLanguage(){
  currentLanguage = currentLanguage === 'ar' ? 'en' : 'ar';
  localStorage.setItem('lang', currentLanguage);
  document.documentElement.lang = currentLanguage;
  document.documentElement.dir  = currentLanguage === 'ar' ? 'rtl' : 'ltr';
  updateLanguage();
}

function updateLanguage(){
  const nodes = document.querySelectorAll('[data-ar][data-en]');
  nodes.forEach(el => { el.textContent = el.getAttribute(`data-${currentLanguage}`); });
  const langText = document.getElementById('lang-text');
  if(langText){ langText.textContent = currentLanguage === 'ar' ? 'EN' : 'AR'; }

  const fieldInput = document.getElementById('field');
  if(fieldInput){
    fieldInput.placeholder = currentLanguage === 'ar'
      ? 'مثال: طب، هندسة، علوم'
      : 'Example: Medicine, Engineering, Science';
  }

  const gradeSel = document.getElementById('reqGrade');
  const gradNote = document.getElementById('graduateNote');
  if(gradeSel && gradNote){
    gradNote.style.display = (gradeSel.value === 'graduate' || gradeSel.value === 'other') ? 'block' : 'none';
  }
}

document.addEventListener('DOMContentLoaded', ()=>{
  document.documentElement.lang = currentLanguage;
  document.documentElement.dir  = currentLanguage === 'ar' ? 'rtl' : 'ltr';
  updateLanguage();
});
