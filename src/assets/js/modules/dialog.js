/**
 * Confirmation dialog for destructive actions (progressive enhancement).
 *
 * Links with [data-action="confirm-deletion"] (or the class .delete) open the #dialog modal:
 *
 * - `<a href="confirmation-page" data-action="confirm-deletion" data-form="main form" data-confirm="Really?">`
 *   The href is a server-side confirmation page containing the POST form. On confirm the page is fetched, the form
 *   (CSS selector in data-form) is taken from it and submitted by POST; afterwards the browser goes to the page the
 *   server redirected to. If anything fails, the browser navigates to the href (the confirmation page).
 * - Without data-form, a confirmation navigates to the href (GET, legacy).
 *
 * Without JavaScript, or without <dialog> support, the links simply open their href.
 */
export function initDialog() {
  const modal = document.getElementById('dialog');
  const message = modal?.querySelector('.dialog-message');
  const buttonSubmit = modal?.querySelector('[data-action="modal-submit"]');
  const buttonCancel = modal?.querySelector('[data-action="modal-cancel"]');

  if (
    !modal ||
    !message ||
    !buttonSubmit ||
    !buttonCancel ||
    typeof modal.showModal !== 'function'
  ) {
    return;
  }

  const defaultMessage = message.textContent;
  let currentLink = null;

  const navigate = url => {
    window.location.href = url;
  };

  const submitConfirmationForm = async link => {
    const href = new URL(link.href, window.location.href).href;
    try {
      const page = await fetch(href, { credentials: 'same-origin' });
      if (!page.ok) {
        return navigate(href);
      }
      const doc = new DOMParser().parseFromString(
        await page.text(),
        'text/html',
      );
      const form = doc.querySelector(link.dataset.form);
      if (!(form instanceof HTMLFormElement)) {
        return navigate(href);
      }
      const body = new FormData(form);
      const submitButton = form.querySelector('[type="submit"][name]');
      if (submitButton) {
        body.append(submitButton.name, submitButton.value);
      }
      const response = await fetch(
        new URL(form.getAttribute('action') ?? '', page.url).href,
        { method: 'POST', body, credentials: 'same-origin' },
      );
      // Success is a redirect; a re-rendered page means an error (e.g. an invalid CSRF token)
      if (!response.ok || !response.redirected) {
        return navigate(href);
      }
      navigate(response.url);
    } catch (error) {
      navigate(href);
    }
  };

  buttonSubmit.addEventListener('click', () => {
    const link = currentLink;
    modal.close();
    if (!link) {
      return;
    }
    if (link.dataset.form) {
      submitConfirmationForm(link);
    } else {
      navigate(link.getAttribute('href'));
    }
  });
  buttonCancel.addEventListener('click', e => {
    e.stopPropagation();
    modal.close();
  });
  modal.addEventListener('close', () => {
    currentLink = null;
  });

  document
    .querySelectorAll('[data-action="confirm-deletion"], .delete')
    .forEach(link => {
      link.addEventListener('click', e => {
        e.preventDefault();
        currentLink = link;
        message.textContent = link.dataset.confirm || defaultMessage;
        modal.showModal();
      });
    });
}
