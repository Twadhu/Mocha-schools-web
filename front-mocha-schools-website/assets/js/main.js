function scrollToSection(id){ const el = document.getElementById(id); if(el) el.scrollIntoView({behavior:'smooth'}); }

document.addEventListener('DOMContentLoaded', async ()=>{
  // Mobile menu toggle
  document.addEventListener('click', (e)=>{
    const btn = e.target.closest('.mobile-menu-btn');
    if(btn){ document.body.classList.toggle('mobile-open'); }
    const link = e.target.closest('.nav-menu a');
    if(link){ document.body.classList.remove('mobile-open'); }
  });

  // Carousel
  if(window.initCarousel) initCarousel();

  // Schools
  if(window.populateSchools) populateSchools();

  // News
  if(window.fetchFrontpage) fetchFrontpage();

  // Year in footer
  const y = document.getElementById('y'); if(y) y.textContent = new Date().getFullYear();

  // Account request form
  const form = document.getElementById('accountRequestForm');
  if(form){
    form.addEventListener('submit', submitAccountRequest);
    const roleSel = document.getElementById('reqRole');
    if(roleSel){ roleSel.addEventListener('change', toggleRoleFields); }
    toggleRoleFields();
  }

  // Default scholarships view
  // Wire scholarships search + default featured list (top 6)
  const searchForm = document.getElementById('scholarshipSearchForm');
  if(searchForm && window.searchScholarships){
    searchForm.addEventListener('submit', window.searchScholarships);
  }
  if(window.displayScholarships && Array.isArray(window.SCHOLARSHIPS)){
    window.displayScholarships(window.SCHOLARSHIPS.slice(0,6));
  }

  // Service Worker
  if('serviceWorker' in navigator){
    try { navigator.serviceWorker.register('./sw.js'); } catch(e){ /* ignore */ }
  }
});

window.scrollToSection = scrollToSection;
