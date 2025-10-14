const trackEl = ()=>document.getElementById('carouselTrack');
const dotsWrap = ()=>document.getElementById('dots');
const viewport = ()=>document.getElementById('carouselViewport');
let slideIndex = 0, timer, startX = 0, isDown=false, vidAdvanceTimer=null, autoStarted=false;

function buildDots(){
  const total = trackEl().children.length; dotsWrap().innerHTML='';
  for(let i=0;i<Math.max(1,total);i++){
    const d=document.createElement('div');
    d.className='dot'+(i===0?' active':'');
    if(total>1){ d.addEventListener('click',()=>{setSlide(i); restartAuto();}); }
    dotsWrap().appendChild(d);
  }
}
function setSlide(i){
  const total = trackEl().children.length; slideIndex = (i+total)%total;
  trackEl().style.transform = `translateX(-${slideIndex*100}%)`;
  [...dotsWrap().children].forEach((d,idx)=>d.classList.toggle('active', idx===slideIndex));
  if(slideIndex===0){
    const v = document.getElementById('carouselVideo1'); if(v){ try{ v.play(); }catch{} }
    if(autoStarted){
      stopAuto();
      if(vidAdvanceTimer) clearTimeout(vidAdvanceTimer);
      vidAdvanceTimer = setTimeout(()=>{ if(slideIndex===0){ setSlide(1); restartAuto(); } }, 8000);
    }
  } else { if(vidAdvanceTimer){ clearTimeout(vidAdvanceTimer); vidAdvanceTimer=null; } }
}
function next(){ setSlide(slideIndex+1); }
function prev(){ setSlide(slideIndex-1); }
function startAuto(){ stopAuto(); const total = trackEl().children.length; if(total>1){ timer=setInterval(()=> next(), 3000); } }
function stopAuto(){ if(timer) clearInterval(timer); }
function restartAuto(){ stopAuto(); startAuto(); }

document.addEventListener('click',(e)=>{
  const total = trackEl().children.length;
  if(e.target.closest('#btnNext') && total>1){ next(); restartAuto(); }
  if(e.target.closest('#btnPrev') && total>1){ prev(); restartAuto(); }
});
function onStart(e){ isDown=true; startX=(e.touches?e.touches[0].clientX:e.clientX); stopAuto(); }
function onMove(e){ if(!isDown) return; const x=(e.touches?e.touches[0].clientX:e.clientX)-startX; trackEl().style.transform=`translateX(calc(-${slideIndex*100}% + ${x}px))`; }
function onEnd(e){ if(!isDown) return; isDown=false; const endX=(e.changedTouches?e.changedTouches[0].clientX:e.clientX); const dx=endX-startX; if(Math.abs(dx)>60){ dx<0?next():prev(); } else { setSlide(slideIndex); } startAuto(); }

document.addEventListener('keydown',(e)=>{ if(e.key==='ArrowLeft'){ document.dir==='rtl'?next():prev(); restartAuto(); } if(e.key==='ArrowRight'){ document.dir==='rtl'?prev():next(); restartAuto(); } });

function initCarousel(){
  buildDots(); setSlide(0);
  const v = document.getElementById('carouselVideo1');
  if(v){
    v.setAttribute('playsinline',''); v.setAttribute('webkit-playsinline','');
    const scheduleAdvance = (ms)=>{ if(vidAdvanceTimer) clearTimeout(vidAdvanceTimer); vidAdvanceTimer = setTimeout(()=>{ if(slideIndex===0){ setSlide(1); if(!autoStarted){ startAuto(); autoStarted=true; } } }, ms); };
    v.addEventListener('loadedmetadata', ()=>{ const ms=Math.min(((v.duration||8)*1000),15000); scheduleAdvance(ms); }, {once:true});
    scheduleAdvance(10000);
    v.addEventListener('error', ()=>{ if(!autoStarted){ startAuto(); autoStarted=true; } }, {once:true});
  } else { startAuto(); autoStarted=true; }

  const el = viewport();
  const total = trackEl().children.length;
  if(total>1){
    el.addEventListener('mouseenter',stopAuto); el.addEventListener('mouseleave',startAuto);
    el.addEventListener('touchstart',onStart,{passive:true}); el.addEventListener('touchmove',onMove,{passive:true}); el.addEventListener('touchend',onEnd);
    el.addEventListener('mousedown',onStart); el.addEventListener('mousemove',onMove); el.addEventListener('mouseup',onEnd); el.addEventListener('mouseleave',()=>{isDown=false});
  } else {
    const prevBtn = document.getElementById('btnPrev');
    const nextBtn = document.getElementById('btnNext');
    if(prevBtn) prevBtn.style.display='none';
    if(nextBtn) nextBtn.style.display='none';
  }
}

window.initCarousel = initCarousel;
