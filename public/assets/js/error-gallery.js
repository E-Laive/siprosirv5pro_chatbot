(() => {
  document.addEventListener('change', (event) => {
    const input = event.target;
    if (!input.matches('[data-error-gallery-form] input[name="code"]')) return;
    const gallery = input.closest('.error-gallery');
    gallery.querySelectorAll('[data-error-preview]').forEach((preview) => {
      preview.hidden = preview.dataset.errorPreview !== input.value;
    });
    gallery.querySelector('[data-error-live-status]').textContent = `Pratinjau ${input.value} dipilih`;
  });
})();
