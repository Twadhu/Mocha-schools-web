// وظائف: طباعة/مشاركة + إبراز رابط الفهرس النشط
(function(){
  const btnPrint = document.getElementById('btnPrint');
  if(btnPrint){ btnPrint.addEventListener('click', ()=> window.print()); }

  const btnShare = document.getElementById('btnShare');
  if(btnShare){
    btnShare.addEventListener('click', async ()=>{
      const shareData = {
        title: 'منحة وقف الديانة التركية 2025 | مدارس المخا',
        text: 'المواعيد، الشروط، المزايا، وخطوات التقديم.',
        url: location.href
      };
      try{
        if(navigator.share){ await navigator.share(shareData); }
        else{
          await navigator.clipboard.writeText(location.href);
          btnShare.textContent = 'تم نسخ الرابط ✅';
          setTimeout(()=> btnShare.innerHTML = '<i class="fa-solid fa-share-nodes"></i> مشاركة', 1600);
        }
      }catch(_){}
    });
  }

  // تفعيل رابط الفهرس عند التمرير
  const tocLinks = document.querySelectorAll('.toc nav a[href^="#"]');
  const sections = [...tocLinks].map(a => document.querySelector(a.getAttribute('href'))).filter(Boolean);

  const onScroll = () =>{
    const y = window.scrollY + 120;
    let activeIndex = -1;
    sections.forEach((sec, i)=>{ if(sec.offsetTop <= y) activeIndex = i; });
    tocLinks.forEach((a, i)=> a.classList.toggle('active', i === activeIndex));
  };
  document.addEventListener('scroll', onScroll, {passive:true});
  onScroll();
})();
