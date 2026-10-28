/**
 * Confirmation dialog for destructive actions (progressive enhancement).
 *
 * Links with [data-action="confirm-deletion"] (or the class .delete) open the #dialog modal:
 *
 * - `<a href="confirmation-page" data-action="confirm-deletion" data-form="main form" data-confirm="Really?">`
 *   The href is a server-side confirmation page containing the POST form. On confirm the page is fetched, the form
 *   (CSS selector in data-form) is taken from it and submitted natively by POST, so the browser loads the page the
 *   server redirects to exactly once (pages that show a value only once, e.g. a new API key, rely on it). If fetching
 *   the confirmation page or finding its form fails, the browser navigates to the href (the confirmation page).
 * - Without data-form, a confirmation navigates to the href (GET, legacy).
 * - data-confirm sets the message, data-confirm-label the text of the confirm button (e.g. "Yes, generate") while the
 *   dialog is open. Without them, the texts of the #dialog template are used.
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
  const defaultSubmitLabel = buttonSubmit.textContent;
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
      // Native submit instead of fetch: a fetch follows the redirect and loads the target page once in the
      // background, which uses up values shown only once (e.g. a new API key) before the browser shows the page
      const submitForm = document.createElement('form');
      submitForm.method = 'post';
      submitForm.action = new URL(
        form.getAttribute('action') ?? '',
        page.url,
      ).href;
      submitForm.hidden = true;
      const body = new FormData(form);
      const submitButton = form.querySelector('[type="submit"][name]');
      if (submitButton) {
        body.append(submitButton.name, submitButton.value);
      }
      body.forEach((value, name) => {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = name;
        input.value = value;
        submitForm.append(input);
      });
      document.body.append(submitForm);
      submitForm.submit();
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
    buttonSubmit.textContent = defaultSubmitLabel;
  });

  document
    .querySelectorAll('[data-action="confirm-deletion"], .delete')
    .forEach(link => {
      link.addEventListener('click', e => {
        e.preventDefault();
        currentLink = link;
        message.textContent = link.dataset.confirm || defaultMessage;
        buttonSubmit.textContent =
          link.dataset.confirmLabel || defaultSubmitLabel;
        modal.showModal();
      });
    });
}
