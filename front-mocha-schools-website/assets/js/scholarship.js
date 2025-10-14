// وظائف بسيطة: مشاركة / طباعة / سلوكيات UX صغيرة
(function(){
  const btnPrint = document.getElementById('btnPrint');
  if(btnPrint){ btnPrint.addEventListener('click', ()=> window.print()); }

  const btnShare = document.getElementById('btnShare');
  if(btnShare){
    btnShare.addEventListener('click', async ()=>{
      const shareData = {
        title: 'منح التبادل الثقافي لليمنيين | مدارس المخا',
        text: 'كل ما تحتاج معرفته عن منح التبادل الثقافي: الدول، الخطوات، والنصائح.',
        url: location.href
      };
      try{
        if(navigator.share){ await navigator.share(shareData); }
        else {
          await navigator.clipboard.writeText(location.href);
          btnShare.textContent = 'تم نسخ الرابط ✅';
          setTimeout(()=> btnShare.innerHTML = '<i class="fa-solid fa-share-nodes"></i> مشاركة', 1600);
        }
      }catch(_){}
    });
  }

  // تفعيل رابط الفهرس الحالي بناء على التمرير (تحسين بسيط)
  const tocLinks = document.querySelectorAll('.toc nav a[href^="#"]');
  const sections = [...tocLinks].map(a => document.querySelector(a.getAttribute('href'))).filter(Boolean);

  const onScroll = () =>{
    const y = window.scrollY + 120; // تعويض الهيدر
    let activeIndex = -1;
    sections.forEach((sec, i)=>{
      if(sec.offsetTop <= y) activeIndex = i;
    });
    tocLinks.forEach((a, i)=> a.classList.toggle('active', i === activeIndex));
  };
  document.addEventListener('scroll', onScroll, {passive:true});
  onScroll();

  // سنة الفوتر
  const y = document.getElementById('y'); if(y) y.textContent = new Date().getFullYear();
})();
