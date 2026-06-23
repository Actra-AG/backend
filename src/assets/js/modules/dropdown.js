// src/js/modules/dropdown.js
export function initDropdown() {
  const dropdowns = document.querySelectorAll('[data-dropdown=\"container\"]');

  if (!dropdowns.length) {
    return;
  }

  function closeAllDropdowns(exceptToggle = null) {
    dropdowns.forEach(dropdown => {
      const toggle = dropdown.querySelector('[data-dropdown="toggle"]');
      const content = toggle.nextElementSibling;

      if (toggle !== exceptToggle && toggle.classList.contains('expanded')) {
        toggle.classList.remove('expanded');
        toggle.setAttribute('aria-expanded', 'false');

        if (content) {
          content.classList.remove('open');
        }
      }
    });
  }

  dropdowns.forEach(dropdown => {
    const dropdownToggle = dropdown.querySelector('[data-dropdown="toggle"]');
    const dropdownContent = dropdownToggle.nextElementSibling;

    dropdownToggle.addEventListener('click', function (event) {
      event.stopPropagation();
      const isCurrentlyOpen = dropdownToggle.classList.contains('expanded');

      closeAllDropdowns(dropdownToggle);

      const isOpen = !isCurrentlyOpen;
      dropdownToggle.classList.toggle('expanded', isOpen);
      dropdownToggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');

      const container = document.querySelector('main#content');
      const containerRect = container.getBoundingClientRect();
      dropdownContent.classList.add('open');

      if (dropdownContent) {
        dropdownContent.classList.add('open');
        dropdownContent.classList.remove(
          'dropdown-content-top-left',
          'dropdown-content-top-right',
          'dropdown-content-bottom-left',
          'dropdown-content-bottom-right',
        );

        const rect = dropdownContent.getBoundingClientRect();
        const btnRect = dropdownToggle.getBoundingClientRect();
        const spaceTop = btnRect.top;
        const spaceBottom = window.innerHeight - btnRect.bottom;
        const spaceLeft = btnRect.left - containerRect.left;
        const spaceRight = containerRect.right - btnRect.right;

        let vertical =
          spaceBottom >= rect.height || spaceBottom >= spaceTop
            ? 'bottom'
            : 'top';

        let horizontal =
          spaceRight >= rect.width || spaceRight >= spaceLeft
            ? 'right'
            : 'left';

        dropdownContent.classList.add(
          `dropdown-content-${vertical}-${horizontal}`,
        );

        if (!isOpen) {
          dropdownContent.classList.remove('open');
        }
      }
    });

    if (dropdownContent) {
      dropdownContent.addEventListener('click', event => {
        event.stopPropagation();
      });
    }
  });

  window.addEventListener('click', () => {
    closeAllDropdowns();
  });

  document.addEventListener('keyup', e => {
    if (e.key === 'Escape') {
      closeAllDropdowns();
    }
  });
}
