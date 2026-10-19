(() => {
  const model = window.KerangkaUIModel;
  const root = () => document.getElementById('spaPageContent');
  const imageUrls = new Map();
  const formatCurrency = (input) => {
    const digits = model.currencyDigits(input.value);
    input.dataset.rawValue = digits;
    input.value = digits ? Number(digits).toLocaleString('id-ID') : '';
    const result = input.closest('.field-group')?.querySelector('[data-currency-value]');
    if (result) result.textContent = digits ? `Nilai: ${model.money(Number(digits))}` : 'Masukkan nominal rupiah tanpa desimal.';
  };
  const validateDates = (form) => {
    const start = form.querySelector('[name="date_from"]'), end = form.querySelector('[name="date_to"]');
    if (!start || !end) return;
    end.min = start.value;
    end.setCustomValidity(start.value && end.value && end.value < start.value ? 'Tanggal akhir tidak boleh sebelum tanggal awal.' : '');
  };
  const updateRecipientCount = (container) => {
    const select = container.querySelector('[data-multiselect]');
    if (select) container.querySelector('[data-multiselect-summary]').textContent = `${select.selectedOptions.length} outlet dipilih: ${[...select.selectedOptions].map((o) => o.textContent).join(', ') || 'belum ada'}`;
  };

  document.addEventListener('input', (event) => {
    const input = event.target;
    if (input.matches('[data-currency]')) formatCurrency(input);
    if (input.closest('[data-kit-date-range]')) validateDates(input.closest('[data-kit-date-range]'));
  });
  document.addEventListener('change', (event) => {
    const input = event.target;
    if (input.matches('[data-multiselect]')) updateRecipientCount(root());
    if (input.matches('[data-image-upload]')) {
      const card = input.closest('[data-upload-example]'), image = card.querySelector('img'), status = card.querySelector('[data-upload-status]');
      if (imageUrls.has(input)) { URL.revokeObjectURL(imageUrls.get(input)); imageUrls.delete(input); }
      image.hidden = true; image.removeAttribute('src');
      const file = input.files[0];
      const error = file ? model.validateImage(file) : '';
      input.setCustomValidity(error); input.setAttribute('aria-invalid', String(!!error));
      status.textContent = error || (file ? `${file.name} — preview lokal, belum diunggah.` : 'Belum ada gambar.');
      card.querySelector('[data-file-remove]').disabled = !file;
      if (file && !error) {
        const url = URL.createObjectURL(file); imageUrls.set(input, url);
        image.onerror = () => { if (imageUrls.get(input) !== url) return; image.hidden = true; input.setCustomValidity('Berkas gambar tidak dapat dibaca. Pilih gambar lain.'); input.setAttribute('aria-invalid', 'true'); status.textContent = 'Berkas gambar tidak dapat dibaca. Pilih gambar lain.'; };
        image.src = url; image.hidden = false;
      }
    }
  });
  document.addEventListener('click', (event) => {
    const target = event.target.closest('button');
    if (!target) return;
    if (target.matches('[data-file-remove]')) {
      const input = target.closest('[data-upload-example]').querySelector('input'); input.value = ''; input.dispatchEvent(new Event('change', { bubbles: true }));
    }
  });
  const init = (container) => {
    container.querySelectorAll('[data-kit-date-range]').forEach(validateDates);
    container.querySelectorAll('[data-currency]').forEach(formatCurrency);
    updateRecipientCount(container);
  };
  const dispose = (container) => container.querySelectorAll('[data-image-upload]').forEach((input) => { if (imageUrls.has(input)) { URL.revokeObjectURL(imageUrls.get(input)); imageUrls.delete(input); } });
  window.KerangkaAdvancedFields = { init, dispose };
})();
