export function initDialog() {
  const modal = document.getElementById('dialog');

  if (!modal) {
    return;
  }

  const openModal = function () {
    modal.showModal();
  };
  const closeModal = function () {
    modal.close();
  };

  // modal on [data-action="confirm-deletion"] or .delete
  const deleteLinks = document.querySelectorAll(
    '[data-action="confirm-deletion"], .delete',
  );

  deleteLinks.forEach(link => {
    link.addEventListener('click', e => {
      const deleteHref = link.getAttribute('href');
      const message = modal.querySelector('.dialog-message');
      e.preventDefault();
      if (link.dataset.confirm) {
        message.textContent = link.dataset.confirm;
      }
      openModal();
      const buttonSubmit = modal.querySelector('[data-action="modal-submit"]');
      const buttonCancel = modal.querySelector('[data-action="modal-cancel"]');
      buttonSubmit.addEventListener('click', e => {
        window.location.href = deleteHref;
      });
      buttonCancel.addEventListener('click', e => {
        e.stopPropagation();
        closeModal();
      });
    });
  });
}
