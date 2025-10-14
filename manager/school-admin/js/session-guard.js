(function guard(){
  try {
    var ss = window.sessionStorage;
    var token = ss.getItem('authToken');
    var sid = ss.getItem('schoolId');
    var utype = ss.getItem('userType');
    if (window.__MK_SPA_HOST === true) {
      if (!token || !sid || utype !== 'school') { throw new Error('no-session'); }
      var ls = window.localStorage;
      if (!ls.getItem('authToken')) ls.setItem('authToken', token);
      if (!ls.getItem('currentUser')) ls.setItem('currentUser', JSON.stringify({ id: 'school:'+sid, school_id: sid, role: 'school-admin' }));
      return;
    }
    var qs = new URLSearchParams(window.location.search);
    var urlSid = qs.get('sid');
    if (!token || !sid || !urlSid || urlSid !== sid || utype !== 'school') {
      window.location.replace('../school-login.html');
      return;
    }
    var ls = window.localStorage;
    if (!ls.getItem('authToken')) ls.setItem('authToken', token);
    if (!ls.getItem('currentUser')) ls.setItem('currentUser', JSON.stringify({ id: 'school:'+sid, school_id: sid, role: 'school-admin' }));
  } catch(e){
    try { window.location.replace('../school-login.html'); } catch(_) {}
  }
})();