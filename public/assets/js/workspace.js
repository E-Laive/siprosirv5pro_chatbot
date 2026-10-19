(() => {
  const model = window.KerangkaUIModel;
  const root = () => document.getElementById('spaPageContent');
  const notify = (...args) => window.KerangkaCatalog.notify(...args);
  const timers = new Map();
  const { fields, values, markClean, dirtyForms, beforeNavigate, confirm, hideDrawer } = window.KerangkaFormGuard;
  const fillForm = (form, data) => fields(form).forEach((field) => {
    if (!(field.name in data) || field.type === 'file') return;
    if (field.type === 'checkbox') field.checked = !!data[field.name];
    else field.value = String(data[field.name] ?? '');
  });
  const draftKey = (form) => window.KerangkaDrafts.key(form.dataset.editSku || 'new');
  const syncDraftVisibility = (form) => {
    let hasDraft = false;
    try { hasDraft = Boolean(sessionStorage.getItem(draftKey(form))); } catch (_) {}
    const loadButton = form.querySelector('[data-load-draft]');
    if (loadButton) loadButton.hidden = !hasDraft;
    const banner = form.querySelector('[data-draft-banner]');
    if (banner) banner.hidden = !hasDraft;
  };
  const setProductStatusChip = (container, text, tone = 'neutral') => {
    const chip = container?.querySelector('[data-product-status]');
    if (!chip) return;
    chip.textContent = text;
    chip.className = `status-chip ${tone}`;
  };
  const productResult = (form, data, draft) => {
    const container = root();
    container.querySelector('[data-product-result]').hidden = false;
    container.querySelectorAll('[data-result]').forEach((e) => { e.textContent = ['cost', 'price'].includes(e.dataset.result) ? model.money(Number(data[e.dataset.result] || 0)) : data[e.dataset.result]; });
    container.querySelector('[data-product-result-note]').textContent = draft ? 'Draft contoh disimpan di sesi tab ini. Data daftar tidak berubah.' : 'Produk lolos validasi. Ini hasil simulasi; data daftar dan database tidak berubah.';
    setProductStatusChip(container, draft ? 'Draft tersimpan' : 'Simulasi selesai', draft ? 'info' : 'success');
  };
  const checkForm = (form) => {
    fields(form).forEach((e) => {
      if (['text', 'search'].includes(e.type) && e.minLength > 0) { e.value = e.value.trim(); e.setCustomValidity(e.value.length && e.value.length < e.minLength ? `Isi minimal ${e.minLength} karakter.` : ''); }
      e.setAttribute('aria-invalid', String(!e.validity.valid));
    });
    const valid = form.checkValidity();
    form.classList.add('was-validated');
    const summary = form.querySelector('[data-form-errors]');
    if (summary) { summary.hidden = valid; summary.textContent = valid ? '' : 'Periksa kolom wajib dan nilai yang ditandai sebelum melanjutkan.'; }
    if (!valid) fields(form).find((e) => !e.validity.valid)?.focus();
    return valid;
  };
  const init = (container) => {
    container.querySelectorAll('button:not([aria-label])').forEach((button) => { if (!button.textContent.trim()) button.setAttribute('aria-label', 'Pilihan lainnya'); });
    container.querySelectorAll('[data-product-form]').forEach((form) => {
      const sku = new URL(location.href).searchParams.get('sku');
      const item = JSON.parse(form.dataset.products).find((p) => p.sku === sku);
      if (item) { fillForm(form, { ...item, unit: 'Pcs', cost: 0 }); form.dataset.editSku = sku; container.querySelector('[data-product-form-title]').textContent = `Edit ${item.name}`; productResult(form, values(form), false); setProductStatusChip(container, 'Data contoh', 'neutral'); container.querySelector('[data-product-result-note]').textContent = 'Data produk contoh dimuat dari daftar. Ubah isian untuk mencoba editor.'; }
      syncDraftVisibility(form);
    });
    container.querySelectorAll('[data-column-options]').forEach((options) => {
      if (options.childElementCount) return;
      const table = options.closest('.content-card').querySelector('[data-catalog-table]');
      [...table.tHead.rows[0].cells].forEach((cell, index) => {
        if (!cell.dataset.sortColumn) return;
        const label = document.createElement('label'); label.className = 'form-check';
        const input = document.createElement('input'); input.type = 'checkbox'; input.checked = true; input.className = 'form-check-input'; input.dataset.columnIndex = index;
        const text = document.createElement('span'); text.className = 'form-check-label'; text.textContent = cell.querySelector('button').textContent.replace('↕', '').trim();
        label.append(input, text); options.append(label);
      });
    });
    container.querySelectorAll('.progress-bar').forEach((bar) => { bar.setAttribute('role', 'progressbar'); bar.setAttribute('aria-valuemin', '0'); bar.setAttribute('aria-valuemax', '100'); bar.setAttribute('aria-valuenow', String(Number.parseFloat(bar.style.width) || 0)); bar.setAttribute('aria-label', bar.closest('label')?.querySelector('span')?.textContent.replace(/\d+%/, '').trim() || 'Progres contoh'); });
    container.querySelectorAll('.spinner-border[role="status"],.spinner-grow[role="status"]').forEach((spinner) => spinner.setAttribute('aria-label', 'Sedang memuat contoh'));
    // Static showcase controls explicitly report a simulated action instead of silently doing nothing.
    container.querySelectorAll('button').forEach((button) => {
      if (button.disabled || button.closest('form') || button.closest('[data-ai-workspace]') || button.hasAttribute('data-bs-toggle') || button.hasAttribute('data-bs-dismiss') || button.matches('[data-demo-action],[data-progress-demo],[data-show-toast],[data-alert-toast],[data-alert-confirm],[data-alert-cancel],[data-alert-accept],[data-modal-demo-confirm],[data-sort-key],[data-page-step],[data-bulk-action],[data-product-cancel],[data-load-draft],[data-discard-draft],[data-quantity-step],[data-kit-pagination],[data-table-retry],[data-table-reset],[data-file-remove],[data-recovery],[data-retry-demo],[data-print-document]')) return;
      button.dataset.demoAction = button.getAttribute('aria-label') || button.textContent.trim();
    });
    container.querySelectorAll('a[href="#"]').forEach((link) => { if (!link.closest('.type-sample')) link.dataset.demoAction = link.textContent.trim(); });
    window.KerangkaAdvancedFields.init(container);
    window.KerangkaPrint?.init(container);
    window.KerangkaAiChat?.init(container);
    // Record the baseline after formatting and default values have been applied.
    container.querySelectorAll('[data-dirty-form], [data-modal-demo-form]').forEach((form) => { form.setAttribute('data-dirty-form', ''); markClean(form); });
  };
  const dispose = async (container) => {
    for (const element of container.querySelectorAll('.offcanvas')) { await hideDrawer(element); bootstrap.Offcanvas.getInstance(element)?.dispose(); }
    window.KerangkaAdvancedFields.dispose(container);
    for (const [key, timer] of timers) if (container.contains(key)) { clearTimeout(timer); timers.delete(key); }
  };
  const syncProductFormDirtyBadge = (input) => {
    const form = input.form || input.closest?.('form');
    if (!form?.matches('[data-product-form]')) return;
    const isDirty = dirtyForms().includes(form);
    if (isDirty) setProductStatusChip(root(), 'Perubahan belum disimpan', 'warning');
    else setProductStatusChip(root(), form.dataset.editSku ? 'Data contoh' : 'Belum disimpan', 'neutral');
  };
  document.addEventListener('input', (event) => {
    const input = event.target;
    if (input.name && input.minLength > 0) input.setCustomValidity('');
    syncProductFormDirtyBadge(input);
  });
  document.addEventListener('change', (event) => {
    const input = event.target;
    syncProductFormDirtyBadge(input);
    if (input.matches('[data-column-index]')) {
      const table = input.closest('.content-card').querySelector('[data-catalog-table]');
      const options = [...input.closest('[data-column-options]').querySelectorAll('input')];
      if (!options.some((e) => e.checked)) { input.checked = true; notify('warning', 'Sisakan minimal satu kolom data.'); return; }
      [...table.rows].forEach((row) => { row.cells[Number(input.dataset.columnIndex)].hidden = !input.checked; });
    }
    if (input.matches('[data-table-state]')) {
      const card = input.closest('.content-card'), message = card.querySelector('[data-table-state-message]');
      const unavailable = input.value !== 'ready';
      card.querySelector('.table-responsive').hidden = unavailable;
      card.querySelector('.kit-table-footer').hidden = unavailable;
      card.querySelector('[data-table-empty]').hidden = unavailable || [...card.querySelectorAll('tbody tr')].some((r) => !r.hidden);
      message.hidden = !unavailable; message.replaceChildren();
      if (unavailable) {
        const copy = document.createElement('p'); copy.textContent = input.value === 'error' ? 'Contoh: data gagal dimuat. Isian filter tetap dipertahankan.' : 'Contoh: sedang memuat data…'; message.append(copy);
        if (input.value === 'error') { const button = document.createElement('button'); button.type = 'button'; button.className = 'btn btn-primary'; button.dataset.tableRetry = ''; button.textContent = 'Coba Lagi'; message.append(button); }
      }
    }

  });
  document.addEventListener('click', async (event) => {
    const target = event.target.closest('button');
    if (!target) return;
    if (target.matches('[data-demo-choice]')) {
      target.closest('[role="group"]').querySelectorAll('[data-demo-choice]').forEach((button) => { button.classList.toggle('active', button === target); button.setAttribute('aria-pressed', String(button === target)); });
    }
    if (target.matches('[data-quantity-step]')) {
      const input = target.closest('.quantity-group').querySelector('input');
      input.value = Math.min(9999, Math.max(0, (Number(input.value) || 0) + Number(target.dataset.quantityStep)));
      input.dispatchEvent(new Event('input', { bubbles: true }));
    }
    if (target.matches('[data-product-cancel]')) {
      const cancelUrl = target.dataset.cancelUrl;
      if (cancelUrl && window.KerangkaApp?.navigate) {
        await window.KerangkaApp.navigate(cancelUrl);
      } else if (await beforeNavigate()) {
        root().querySelector('a.spa-link')?.click();
      }
    }
    if (target.matches('[data-load-draft]')) {
      const form = target.closest('form');
      try {
        const draft = JSON.parse(sessionStorage.getItem(draftKey(form)));
        if (draft) {
          fillForm(form, draft);
          markClean(form);
          setProductStatusChip(root(), 'Draft dimuat', 'info');
          form.querySelector('[data-draft-status]').textContent = 'Draft sesi dimuat. Lanjutkan menyunting atau simpan produk.';
        }
      } catch (_) { notify('warning', 'Draft tidak dapat dibaca. Isi kembali form.'); }
    }
    if (target.matches('[data-discard-draft]')) {
      const form = target.closest('form');
      try { sessionStorage.removeItem(draftKey(form)); } catch (_) {}
      syncDraftVisibility(form);
      form.querySelector('[data-draft-status]').textContent = 'Draft sesi telah dihapus.';
      notify('info', 'Draft sesi pada tab ini telah dihapus.', 'Draft diabaikan');
    }
    if (target.matches('[data-kit-pagination]')) {
      const demo = target.closest('[data-pagination-example]'); const page = Number(target.dataset.kitPagination);
      demo.querySelectorAll('[data-kit-pagination]').forEach((b) => { b.setAttribute('aria-current', b === target ? 'page' : 'false'); b.classList.toggle('active', b === target); });
      demo.querySelector('[data-pagination-copy]').textContent = `Halaman ${page}: menampilkan ${page === 1 ? '1–10' : page === 2 ? '11–20' : '21–24'} dari 24 data contoh.`;
    }
    if (target.matches('[data-table-retry]')) {
      const select = target.closest('.content-card').querySelector('[data-table-state]'); select.value = 'ready'; select.dispatchEvent(new Event('change', { bubbles: true }));
    }

    if (target.matches('[data-recovery]')) {
      const panel = target.closest('.content-card').querySelector('[data-recovery-panel]');
      const messages = { empty: ['Belum ada data', 'Tambahkan data pertama untuk mulai menggunakan modul.'], noresults: ['Tidak ada hasil', 'Ubah kata pencarian atau hapus filter.'], error: ['Data gagal dimuat', 'Isian filter tetap tersedia. Coba muat ulang contoh.'], offline: ['Anda sedang offline', 'Sambungkan koneksi untuk melanjutkan. Jangan tutup form yang belum disimpan.'], session: ['Sesi telah berakhir', 'Masuk kembali melalui halaman login.'], forbidden: ['Akses tidak tersedia', 'Peran contoh ini tidak memiliki izin. Hubungi administrator.'], conflict: ['Data sudah berubah', 'Bandingkan perubahan sebelum menyimpan ulang.'] };
      const state = target.dataset.recovery, copy = messages[state];
      panel.querySelector('h3').textContent = copy[0]; panel.querySelector('p').textContent = copy[1];
      panel.querySelector('[data-retry-demo]').hidden = !['error', 'offline', 'conflict'].includes(state);
      panel.querySelector('a').hidden = state !== 'session';
      panel.querySelector('[data-recovery-result]').textContent = '';
    }
    if (target.matches('[data-retry-demo]')) {
      const panel = target.closest('[data-recovery-panel]'); target.disabled = true; panel.setAttribute('aria-busy', 'true');
      panel.querySelector('[data-recovery-result]').textContent = 'Memuat ulang contoh…';
      const timer = setTimeout(() => { target.disabled = false; panel.removeAttribute('aria-busy'); panel.querySelector('h3').textContent = 'Contoh berhasil dimuat'; panel.querySelector('p').textContent = 'Permintaan ulang selesai disimulasikan. Tidak ada koneksi server yang diubah.'; panel.querySelector('[data-recovery-result]').textContent = ''; timers.delete(panel); }, 600);
      timers.set(panel, timer);
    }
  });
  document.addEventListener('submit', (event) => {
    const form = event.target;
    if (!form.matches('[data-product-form],[data-kit-form]')) return;
    event.preventDefault();
    const draft = event.submitter?.value === 'draft';
    if (!draft && !checkForm(form)) return;
    if (form.matches('[data-product-form]')) {
      const data = values(form);
      if (draft) {
        try {
          sessionStorage.setItem(draftKey(form), JSON.stringify(data));
          syncDraftVisibility(form);
          form.querySelector('[data-draft-status]').textContent = 'Draft tersimpan di sesi tab ini. Form belum perlu lengkap.';
        } catch (_) { notify('error', 'Penyimpanan sesi tidak tersedia. Isian tetap ada pada form.'); return; }
      } else {
        try { sessionStorage.removeItem(draftKey(form)); } catch (_) {}
        syncDraftVisibility(form);
        form.querySelector('[data-draft-status]').textContent = 'Validasi selesai. Tidak ada data operasional disimpan.';
      }
      productResult(form, data, draft); markClean(form);
      notify('info', draft ? 'Draft tersimpan di sesi tab ini.' : 'Hasil simulasi produk tersedia di ringkasan.', draft ? 'Draft contoh' : 'Simulasi selesai');
    } else {
      markClean(form);
      form.querySelector('[data-kit-result]').textContent = 'Semua isian lolos validasi. Penyimpanan contoh selesai disimulasikan.';
      if (form.matches('[data-note-form]')) {
        root().querySelector('[data-note-display]').textContent = form.elements.namedItem('note').value.trim() || 'Belum ada catatan.';
        bootstrap.Modal.getInstance(form.closest('.modal'))?.hide();
        notify('info', 'Catatan contoh diperbarui pada halaman ini.');
      }
    }
  });
  const updateNetwork = () => { document.getElementById('workspaceOffline').hidden = navigator.onLine; };
  addEventListener('online', updateNetwork); addEventListener('offline', updateNetwork); updateNetwork();
  addEventListener('beforeunload', (event) => { if (dirtyForms().length) { event.preventDefault(); event.returnValue = ''; } });
  document.addEventListener('submit', async (event) => {
    if (!event.target.matches('form[data-no-spa]')) return;
    event.preventDefault();
    if (!await beforeNavigate()) return;
    window.KerangkaDrafts.clear();
    HTMLFormElement.prototype.submit.call(event.target);
  }, true);
  window.KerangkaWorkspace = { init, dispose, markClean, beforeNavigate, confirm, dirtyForms };
  init(root());
})();
