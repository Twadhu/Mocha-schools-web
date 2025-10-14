(function(){
  if(window.AppCore){ AppCore.registerTranslations({ ar:{ settingsSubtitle:'إدارة الإعدادات', changePassword:'تغيير كلمة المرور' }, en:{ settingsSubtitle:'Manage settings', changePassword:'Change Password'} }); }

  function ensureMarkup(host){
    // If the host already has the form (from template), keep it. Otherwise, render a minimal form.
    const hasForm = (host||document).querySelector('#passwordChangeForm');
    if(hasForm) return;
    const container = host || document.getElementById('pageContent');
    if(!container) return;
    const t = (a,b)=> (window.App?.currentLang||'ar')==='ar'?a:b;
    container.innerHTML = `
      <div class="page-header"><div><h1 class="page-title">${t('الإعدادات','Settings')}</h1><p class="page-subtitle">${t('إدارة إعدادات النظام وحسابك','Manage system settings and your account')}</p></div></div>
      <div class="card" style="max-width:700px"><div class="card-header"><h3 class="card-title">${t('تغيير كلمة المرور','Change Password')}</h3></div>
        <div class="card-body">
          <form id="passwordChangeForm">
            <div class="form-grid" style="grid-template-columns:1fr">
              <div class="form-group"><label for="currentPassword">${t('كلمة المرور الحالية','Current Password')}</label><input type="password" id="currentPassword" required autocomplete="current-password"></div>
              <div class="form-group"><label for="newPassword">${t('كلمة المرور الجديدة','New Password')}</label><input type="password" id="newPassword" required autocomplete="new-password"></div>
              <div class="form-group"><label for="confirmPassword">${t('تأكيد كلمة المرور الجديدة','Confirm New Password')}</label><input type="password" id="confirmPassword" required autocomplete="new-password"></div>
            </div>
            <div class="form-actions" style="margin-top:12px;display:flex;gap:8px;align-items:center"><div id="pwMsg" class="form-message" style="min-height:1em"></div>
              <button type="submit" class="btn btn-primary"><i class="fas fa-key"></i> ${t('تحديث كلمة المرور','Update Password')}</button>
            </div>
          </form>
        </div>
      </div>`;
  }

  async function onSubmit(e){
    e.preventDefault();
    const cur=document.getElementById('currentPassword').value;
    const nw=document.getElementById('newPassword').value;
    const conf=document.getElementById('confirmPassword').value;
    const msg=document.getElementById('pwMsg');
    const t = (a,b)=> (window.App?.currentLang||'ar')==='ar'?a:b;
    if(nw!==conf){ msg.style.color='#c00'; msg.textContent=t('عدم تطابق','Mismatch'); return; }
    msg.style.color='inherit'; msg.textContent='...';
    try{
      const res=await mkApi.apiJson('admin.php?path=change-password',{method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify({current:cur, new:nw})});
      if(res.ok){ msg.style.color='green'; msg.textContent=t('تم التحديث','Updated'); e.target.reset(); AppCore?.showToast?.(t('تم التحديث بنجاح','Updated successfully'),'success'); }
      else { msg.style.color='#c00'; msg.textContent=res.message||'Error'; AppCore?.showToast?.(res.message||'Error','error'); }
    }catch(err){ msg.style.color='#c00'; msg.textContent=err.message; AppCore?.showToast?.(err.message,'error'); }
  }

  // Accept optional host for compatibility mounting
  window.initSettings = function(host){
    ensureMarkup(host);
    const form=(host||document).querySelector('#passwordChangeForm');
    if(!form) return; 
    // Avoid duplicate listeners when re-entering
    if(!form.__wired){ form.__wired=true; form.addEventListener('submit', onSubmit); }
  };
  // Compatibility wrapper so external code can call App.renderSettingsPage(container)
  if(!window.App) window.App = {};
  window.App.renderSettingsPage = async function(container){
    await window.initSettings(container instanceof HTMLElement ? container : undefined);
  };
})();
