export function initNavMainToggle() {  
  const mobileNavBtn = document.querySelector('.nav-main-toggle');

  if (!mobileNavBtn) {
    return;
  }

  mobileNavBtn.setAttribute('aria-expanded', 'false');

  mobileNavBtn.addEventListener('click', () => {
    const navMain = mobileNavBtn.nextElementSibling;
    mobileNavBtn.classList.toggle('active');
    mobileNavBtn.classList.contains('active')
      ? mobileNavBtn.setAttribute('aria-expanded', 'true')
      : mobileNavBtn.setAttribute('aria-expanded', 'false');
    navMain.classList.toggle('is-open');
  });
}
