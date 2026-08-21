
/* Initialize fullPage.js where applicable */
(function(){
  var fpEl = document.querySelector('#fullpage');
  if (fpEl && window.fullpage) {
    new fullpage('#fullpage', {
      autoScrolling: true,
      scrollBar: false,
      navigation: true,
      scrollingSpeed: 650,
      licenseKey: 'OPEN-SOURCE-GPLV3-DEV',
      // subtle parallax-like shift
      onLeave: function(origin, destination, direction){
        const outgoing = origin.item.querySelector('.post-card');
        const incoming = destination.item.querySelector('.post-card');
        if (outgoing) outgoing.style.transform = 'translateY(' + (direction === "down" ? -30 : 30) + 'px) scale(0.98)';
        if (incoming) incoming.style.transform = 'translateY(0px) scale(1)';
        setTimeout(()=>{
          if (outgoing) outgoing.style.transform = '';
        }, 350);
      }
    });
  }
})();

/* Read-more modal */
function bindReadMore(scope=document){
  scope.querySelectorAll('[data-read-more]').forEach(btn => {
    if (btn.dataset.bound) return;
    btn.dataset.bound = "1";
    btn.addEventListener('click', () => {
      const fullText = btn.closest('.post-card').querySelector('.post-text').dataset.full || btn.closest('.post-card').querySelector('.post-text').innerText;
      const modal = document.createElement('div');
      modal.className = 'modal';
      modal.innerHTML = `
        <div class="modal-content">
          <button class="modal-close" aria-label="Закрыть">✕</button>
          <div style="white-space: pre-wrap; line-height: 1.5; font-size: 15px;">${fullText}</div>
        </div>`;
      document.body.appendChild(modal);
      modal.querySelector('.modal-close').onclick = () => modal.remove();
      modal.addEventListener('click', (e)=>{ if(e.target === modal) modal.remove(); });
    });
  });
}
bindReadMore();

/* Hide header/footer on scroll direction */
(function(){
  const header = document.querySelector('.app-header');
  const footer = document.querySelector('.app-footer');
  let lastY = window.scrollY, ticking = false;

  function onScroll(y){
    const goingDown = y > lastY;
    lastY = y;
    if (header) header.classList.toggle('hidden-bar', goingDown);
    if (footer) footer.classList.toggle('hidden-bar-bottom', goingDown);
  }

  // fullPage has its own scroll — listen to wheel/touch
  if (document.querySelector('#fullpage')) {
    let wheelTimeout;
    window.addEventListener('wheel', (e)=>{
      clearTimeout(wheelTimeout);
      onScroll(lastY + (e.deltaY>0?10:-10));
      wheelTimeout = setTimeout(()=>{
        if (header) header.classList.remove('hidden-bar');
        if (footer) footer.classList.remove('hidden-bar-bottom');
      }, 350);
    }, {passive:true});
    // touch
    let startY = 0;
    window.addEventListener('touchstart', (e)=>{ startY = e.touches[0].clientY; }, {passive:true});
    window.addEventListener('touchmove', (e)=>{
      const dy = startY - e.touches[0].clientY;
      onScroll(lastY + (dy>0?10:-10));
    }, {passive:true});
  } else {
    // normal pages
    window.addEventListener('scroll', ()=>{
      if (!ticking){
        window.requestAnimationFrame(()=>{
          onScroll(window.scrollY);
          ticking = false;
        });
        ticking = true;
      }
    }, {passive:true});
  }
})();

/* Lightweight router for footer buttons (if used as buttons) */
document.querySelectorAll('[data-nav]').forEach(el=>{
  el.addEventListener('click', ()=>{
    location.href = el.getAttribute('data-nav');
  });
});
