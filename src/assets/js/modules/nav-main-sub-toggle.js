export function initNavMainSubToggle() {
  const dropdownToggleButton = document.querySelectorAll(
    '.nav-main-sub-toggle',
  );

  if (!dropdownToggleButton.length) {
    return;
  }

  dropdownToggleButton.forEach(button => {
    const isActive = button.classList.contains('active');
    button.setAttribute('aria-expanded', isActive ? 'true' : 'false');
  });
  dropdownToggleButton.forEach(button => {
    button.addEventListener('click', function () {
      this.classList.toggle('active');
      const expanded = this.classList.contains('active');
      this.setAttribute('aria-expanded', expanded ? 'true' : 'false');
      this.nextElementSibling.classList.toggle('open');
    });
  });
}
