export function initResponsiveTable() {  
  const tables = document.querySelectorAll('.table');

  if (!tables.length) {
    return;
  }

  tables.forEach(table => {
    const scrollWrapper = document.createElement('div');
    scrollWrapper.classList.add('scrollable');

    const scrollWrapperInner = document.createElement('div');
    scrollWrapperInner.classList.add('scrollable-inner');

    table.parentNode.insertBefore(scrollWrapper, table);
    scrollWrapper.appendChild(scrollWrapperInner);
    scrollWrapperInner.appendChild(table);

    const scrollHint = document.createElement('div');
    scrollHint.classList.add('scroll-hint');
    scrollHint.textContent =
      'Horizontal Scrollen um mehr zu sehen (Shift+Scrollrad)';
    scrollHint.setAttribute('aria-hidden', 'true');

    const updateShadows = () => {
      const scrollLeft = scrollWrapperInner.scrollLeft;
      const maxScrollLeft =
        scrollWrapperInner.scrollWidth - scrollWrapperInner.clientWidth;

      if (
        !scrollWrapper.previousElementSibling ||
        !scrollWrapper.previousElementSibling.classList.contains('scroll-hint')
      ) {
        scrollWrapper.parentNode.insertBefore(scrollHint, scrollWrapper);
      }

      if (maxScrollLeft <= 0) {
        scrollWrapper.classList.remove(
          'has-scroll',
          'has-scroll-right',
          'has-scroll-left',
        );
        scrollHint.style.display = 'none';
      } else {
        scrollWrapper.classList.add('has-scroll');
        scrollHint.style.display = 'block';

        if (scrollLeft === 0) {
          scrollWrapper.classList.add('has-scroll-right');
          scrollWrapper.classList.remove('has-scroll-left');
        } else if (scrollLeft >= maxScrollLeft) {
          scrollWrapper.classList.add('has-scroll-left');
          scrollWrapper.classList.remove('has-scroll-right');
        } else {
          scrollWrapper.classList.add('has-scroll-left', 'has-scroll-right');
        }
      }
    };
    updateShadows();

    scrollWrapperInner.addEventListener('scroll', () => {
      updateShadows();
    });

    window.addEventListener('resize', updateShadows);
  });
}
