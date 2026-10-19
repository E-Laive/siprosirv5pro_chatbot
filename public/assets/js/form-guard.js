(() => {
  const baselines = new WeakMap();
  let pendingConfirm = null;
  const fields = (form) => [...form.elements].filter((e) => e.name && !['submit', 'button', 'hidden'].includes(e.type));
  const values = (form) => Object.fromEntries(fields(form).map((e) => [e.name, e.type === 'checkbox' ? e.checked : e.type === 'file' ? [...e.files].map((f) => `${f.name}:${f.size}:${f.lastModified}`).join('|') : e.multiple ? [...e.selectedOptions].map((o) => o.value) : e.value]));
  const markClean = (form) => baselines.set(form, JSON.stringify(values(form)));
  const dirtyForms = () => [...document.querySelectorAll('[data-dirty-form]')].filter((form) => baselines.has(form) && baselines.get(form) !== JSON.stringify(values(form)));
  const clearDirty = () => dirtyForms().forEach(markClean);
  const hideModal = (element) => new Promise((resolve) => {
    if (!element?.classList.contains('show')) { resolve(); return; }
    element.addEventListener('hidden.bs.modal', resolve, { once: true });
    bootstrap.Modal.getInstance(element)?.hide();
  });
  const hideDrawer = (element) => new Promise((resolve) => {
    const instance = element && bootstrap.Offcanvas.getInstance(element);
    if (!instance || (!element.classList.contains('show') && !element.classList.contains('showing') && element.getAttribute('aria-modal') !== 'true')) { resolve(); return; }
    const hide = () => instance.hide();
    element.addEventListener('hidden.bs.offcanvas', () => { element.removeEventListener('shown.bs.offcanvas', hide); resolve(); }, { once: true });
    if (element.classList.contains('showing')) element.addEventListener('shown.bs.offcanvas', hide, { once: true });
    else hide();
  });
  const confirm = async ({ title = 'Buang perubahan?', message = 'Isian belum disimpan. Perubahan akan hilang jika Anda melanjutkan.', accept = 'Buang perubahan', cancel = 'Lanjut menyunting' } = {}) => {
    if (pendingConfirm) return pendingConfirm;
    await hideModal(document.querySelector('.modal.show:not(#workspaceConfirmModal)'));
    const element = document.getElementById('workspaceConfirmModal');
    const opener = document.activeElement;
    element.querySelector('#workspaceConfirmTitle').textContent = title;
    element.querySelector('#workspaceConfirmMessage').textContent = message;
    element.querySelector('[data-workspace-accept]').textContent = accept;
    element.querySelector('[data-workspace-cancel]').textContent = cancel;
    pendingConfirm = new Promise((resolve) => {
      let accepted = false;
      const yes = () => { accepted = true; bootstrap.Modal.getInstance(element).hide(); };
      const no = () => bootstrap.Modal.getInstance(element).hide();
      const yesButton = element.querySelector('[data-workspace-accept]');
      const noButton = element.querySelector('[data-workspace-cancel]');
      yesButton.addEventListener('click', yes);
      noButton.addEventListener('click', no);
      element.addEventListener('hidden.bs.modal', () => {
        yesButton.removeEventListener('click', yes); noButton.removeEventListener('click', no);
        pendingConfirm = null;
        if (opener?.isConnected) opener.focus({ preventScroll: true });
        resolve(accepted);
      }, { once: true });
      element.addEventListener('shown.bs.modal', () => noButton.focus(), { once: true });
      bootstrap.Modal.getOrCreateInstance(element).show();
    });
    return pendingConfirm;
  };
  const beforeNavigate = async () => {
    await hideModal(document.getElementById('menuSearchModal'));
    await hideDrawer(document.querySelector('.offcanvas.show,.offcanvas.showing'));
    if (!dirtyForms().length) return true;
    if (!await confirm()) return false;
    clearDirty();
    return true;
  };

  window.KerangkaFormGuard = { fields, values, markClean, dirtyForms, beforeNavigate, confirm, hideDrawer };
})();
