// Global app configuration
(function(){
  // Compute API base relative to this site folder under XAMPP
  // We assume this folder lives at /Schools-sites/front-mocha-schools-website/
  // and the PHP API is at /Schools-sites/api/
  function resolveApiBase(){
    try {
      const href = location.pathname.replace(/\\+/g,'/');
      const marker = '/Schools-sites/front-mocha-schools-website/';
      if(href.includes(marker)){
        // go up one level to Schools-sites then into api
        const prefix = href.split(marker)[0] + '/Schools-sites/api/';
        return prefix.replace(/\\+/g,'/');
      }
    } catch {}
    // fallback: root /api/
    return '/api/';
  }

  window.APP_CONFIG = {
    API_BASE: resolveApiBase(),
    getStartedUrl: '/public/portal/signup.html?ref=home',
    // 3 schools + 'other'
    schoolsFallback: [
      { id: 1, name: 'مدرسة الفقيد محمد عبدالله السراجي' },
      { id: 2, name: 'مدرسة الشهيد اللقية' },
      { id: 3, name: 'مدرسة النور بالثوباني' },
      { id: 'other', name: 'أخرى' }
    ],
    // mapping for endpoints per school/role
    schoolApiMap: {
      1: { student: 'account-requests.php',           teacher: 'teacher-requests.php' },
      2: { student: 'schools/2/account-requests.php', teacher: 'schools/2/teacher-requests.php' },
      3: { student: 'schools/3/account-requests.php', teacher: 'schools/3/teacher-requests.php' }
    }
  };
})();
