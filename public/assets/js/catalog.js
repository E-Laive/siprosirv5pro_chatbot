(() => {
  let confirmOpener = null;
  const showAlertToast = (...args) => window.KerangkaNotifications.notify(...args);
  const { states: tableStates, filter: filterTable, updateSelection } = window.KerangkaTables;
  const renderChart = (chart) => {
    const period = chart.querySelector('[data-chart-period]').value;
    const series = JSON.parse(chart.dataset.datasets)[period];
    const svg = chart.querySelector('[data-chart-svg]');
    svg.setAttribute('aria-label', `Omzet contoh ${period} hari dalam juta rupiah. Alternatif data tersedia di bawah grafik.`);
    const points = series.values.map((value, index) => `${56 + index * 540 / (series.values.length - 1)},${230 - value * 3}`);
    svg.querySelector('polyline').setAttribute('points', points.join(' '));
    svg.querySelector('[data-chart-area]').setAttribute('d', `M${points.join(' L')} L596,230 L56,230 Z`);
    const labels = svg.querySelector('.chart-labels');
    labels.replaceChildren();
    const label = (x, y, text) => {
      const node = document.createElementNS('http://www.w3.org/2000/svg', 'text');
      node.setAttribute('x', x); node.setAttribute('y', y); node.textContent = text; labels.appendChild(node);
    };
    [0, 20, 40, 60].forEach((value) => label(30, 235 - value * 3, value));
    series.labels.forEach((text, index) => {
      if (period === '7' || index === 0 || index === 29 || (index + 1) % 5 === 0) label(56 + index * 540 / (series.values.length - 1), 260, text);
    });
    const list = chart.querySelector('[data-chart-data]');
    list.replaceChildren(...series.values.map((value, index) => {
      const item = document.createElement('li'); item.textContent = `${series.labels[index]}: ${value} juta rupiah`; return item;
    }));
  };
  const init = (root) => {
    root.querySelectorAll('[data-bs-toggle="tooltip"]').forEach((element) => bootstrap.Tooltip.getOrCreateInstance(element, { trigger: 'hover focus' }));
    root.querySelectorAll('[data-catalog-table]').forEach(filterTable);
    root.querySelectorAll('[data-sales-chart]').forEach(renderChart);
    root.querySelectorAll('.btn-close:not([aria-label])').forEach((button) => button.setAttribute('aria-label', 'Tutup'));
    root.querySelectorAll('.sample-form input, .sample-form select, .sample-form textarea').forEach((field, index) => {
      const feedback = field.closest('label')?.querySelector('.invalid-feedback, .form-text');
      if (!feedback) return;
      feedback.id ||= `catalog-field-help-${index}`;
      field.setAttribute('aria-describedby', feedback.id);
    });
    root.querySelectorAll('button.icon-button:not([aria-label])').forEach((button) => {
      button.setAttribute('aria-label', 'Contoh aksi edit');
      button.dataset.demoAction = 'Edit';
    });
    window.KerangkaWorkspace?.init(root);
  };
  const dispose = async (root) => {
    await window.KerangkaWorkspace?.dispose(root);
    window.KerangkaNotifications.dispose();
    root.querySelectorAll('[data-bs-toggle="tooltip"]').forEach((element) => bootstrap.Tooltip.getInstance(element)?.dispose());
    for (const [selector, component] of [['.toast', bootstrap.Toast], ['[data-bs-toggle="tab"]', bootstrap.Tab], ['[data-bs-toggle="dropdown"]', bootstrap.Dropdown], ['.collapse', bootstrap.Collapse]]) {
      root.querySelectorAll(selector).forEach((element) => component.getInstance(element)?.dispose());
    }
    for (const element of root.querySelectorAll('.modal')) {
      const modal = bootstrap.Modal.getInstance(element);
      if (!modal) continue;
      if (element.classList.contains('show') || element.getAttribute('aria-modal') === 'true') {
        await new Promise((resolve) => {
          const hideWhenReady = () => modal.hide();
          element.addEventListener('shown.bs.modal', hideWhenReady, { once: true });
          element.addEventListener('hidden.bs.modal', () => {
            element.removeEventListener('shown.bs.modal', hideWhenReady);
            resolve();
          }, { once: true });
          modal.hide();
        });
      }
      if (bootstrap.Modal.getInstance(element) === modal) modal.dispose();
    }
  };
  window.KerangkaCatalog = { init, dispose, notify: showAlertToast };

  document.addEventListener('input', (event) => {
    if (event.target.matches('[data-modal-demo-form] input[name="category_name"]')) {
      event.target.setCustomValidity('');
      if (event.target.closest('form').classList.contains('was-validated')) event.target.setAttribute('aria-invalid', String(!event.target.validity.valid));
    }
    if (event.target.matches('[data-table-search]')) {
      const table = event.target.closest('.content-card')?.querySelector('[data-catalog-table]');
      if (table) { tableStates.get(table).page = 1; filterTable(table); }
    }
  });
  document.addEventListener('change', (event) => {
    if (event.target.matches('[data-chart-period]')) renderChart(event.target.closest('[data-sales-chart]'));
    if (event.target.matches('[data-table-status], [data-table-role], [data-page-size]')) {
      const table = event.target.closest('.content-card')?.querySelector('[data-catalog-table]');
      if (table) { tableStates.get(table).page = 1; filterTable(table); }
    }
    if (event.target.matches('[data-select-all], [data-select-row]')) {
      const table = event.target.closest('table');
      if (event.target.matches('[data-select-all]')) table.querySelectorAll('[data-select-row]').forEach((row) => { if (!row.closest('tr').hidden) row.checked = event.target.checked; });
      updateSelection(table);
    }
  });
  document.addEventListener('click', async (event) => {
    const exportLink = event.target.closest('a[data-export], a[data-print-pdf], a[data-ai-export]');
    if (exportLink && !event.defaultPrevented && event.button === 0 && !event.metaKey && !event.ctrlKey && !event.shiftKey) {
      event.preventDefault();
      if (exportLink.dataset.busy === '1') return;
      exportLink.dataset.busy = '1';
      exportLink.setAttribute('aria-busy', 'true');
      try {
        const response = await fetch(exportLink.href, {
          headers: { 'X-Requested-With': 'XMLHttpRequest' },
          cache: 'no-store',
        });
        const contentType = response.headers.get('Content-Type') || '';
        if (response.status === 401) {
          let payload = null;
          try { payload = await response.json(); } catch (_) {}
          try {
            sessionStorage.setItem('kerangka-auth-notice', payload?.message || 'Sesi Anda telah berakhir. Silakan masuk kembali.');
            sessionStorage.setItem('kerangka-intended-url', location.href);
          } catch (_) {}
          if (payload?.redirect) location.assign(payload.redirect);
          return;
        }
        if (!response.ok || contentType.includes('application/json')) {
          let payload = null;
          try { payload = await response.json(); } catch (_) {}
          showAlertToast('error', payload?.message || 'Dokumen ekspor belum dapat diunduh saat ini.', 'Ekspor belum berhasil');
          return;
        }
        const blob = await response.blob();
        const disposition = response.headers.get('Content-Disposition') || '';
        const match = disposition.match(/filename="?([^";]+)"?/i);
        const filename = match ? match[1] : (exportLink.getAttribute('download') || 'dokumen-ekspor');
        const objectUrl = URL.createObjectURL(blob);
        const tempLink = document.createElement('a');
        tempLink.href = objectUrl;
        tempLink.download = filename;
        document.body.appendChild(tempLink);
        tempLink.click();
        tempLink.remove();
        setTimeout(() => URL.revokeObjectURL(objectUrl), 1500);
      } catch (_) {
        showAlertToast('error', 'Koneksi terputus saat mengunduh dokumen. Periksa jaringan lalu coba kembali.', 'Unduhan gagal');
      } finally {
        delete exportLink.dataset.busy;
        exportLink.removeAttribute('aria-busy');
      }
      return;
    }

    const resetBtn = event.target.closest('[data-table-reset]');
    if (resetBtn) {
      const card = resetBtn.closest('.content-card');
      const table = card?.querySelector('[data-catalog-table]');
      if (card && table) {
        card.querySelectorAll('[data-table-search], [data-table-status], [data-table-role]').forEach((input) => { input.value = ''; });
        const state = tableStates.get(table);
        if (state) state.page = 1;
        filterTable(table);
        card.querySelector('[data-table-search]')?.focus();
      }
      return;
    }

    const sorter = event.target.closest('[data-sort-key]');
    const pager = event.target.closest('[data-page-step]');
    if (sorter || pager) {
      const table = (sorter || pager).closest('.content-card').querySelector('[data-catalog-table]');
      const state = tableStates.get(table);
      if (sorter) {
        state.direction = state.sort === sorter.dataset.sortKey && state.direction === 'asc' ? 'desc' : 'asc';
        state.sort = sorter.dataset.sortKey; state.page = 1;
        table.querySelectorAll('[data-sort-column]').forEach((heading) => {
          const active = heading.dataset.sortColumn === state.sort;
          heading.setAttribute('aria-sort', active ? (state.direction === 'asc' ? 'ascending' : 'descending') : 'none');
          heading.querySelector('button span').textContent = active ? (state.direction === 'asc' ? '↑' : '↓') : '↕';
        });
      } else state.page += Number(pager.dataset.pageStep);
      filterTable(table);
    }
    const bulk = event.target.closest('[data-bulk-action]');
    if (bulk) {
      const card = bulk.closest('.content-card');
      const selected = [...card.querySelectorAll('[data-select-row]:checked')];
      showAlertToast('info', `${selected.length} pelanggan: ${selected.map((e) => e.closest('tr').querySelector('.modern-person strong').textContent).join(', ')}. Aksi massal disimulasikan.`, 'Tindak lanjut pelanggan');
    }
    if (event.target.closest('#spaPageContent a[href="#"]')) event.preventDefault();
    const progressButton = event.target.closest('[data-progress-demo]');
    if (progressButton) {
      const card = progressButton.closest('.content-card');
      const bar = card.querySelector('[data-progress-bar]');
      const next = bar.style.width === '72%' ? 94 : 72;
      bar.style.width = `${next}%`;
      bar.setAttribute('aria-valuenow', next);
      card.querySelector('[data-progress-label]').textContent = `${next}%`;
    }
    if (event.target.closest('[data-show-toast]')) {
      const toast = document.querySelector('.catalog-toast');
      if (toast) bootstrap.Toast.getOrCreateInstance(toast).show();
    }
    const toastButton = event.target.closest('[data-alert-toast]');
    if (toastButton) showAlertToast(toastButton.dataset.alertToast);
    const demoButton = event.target.closest('[data-demo-action]');
    if (demoButton) showAlertToast('info', 'Aksi ini merupakan contoh tampilan. Data aplikasi tidak diubah.', `Contoh: ${demoButton.dataset.demoAction}`);
    const modalDemoConfirm = event.target.closest('[data-modal-demo-confirm]');
    if (modalDemoConfirm) {
      bootstrap.Modal.getInstance(modalDemoConfirm.closest('.modal'))?.hide();
      showAlertToast('info', 'Konfirmasi nonaktifkan produk selesai disimulasikan. Data produk tidak diubah.', 'Simulasi konfirmasi');
    }
    const opener = event.target.closest('[data-alert-confirm]');
    if (opener) {
      confirmOpener = opener;
      bootstrap.Modal.getOrCreateInstance(document.getElementById('catalogAlertConfirm')).show(opener);
    }
    if (event.target.closest('[data-alert-cancel], [data-alert-accept]')) {
      bootstrap.Modal.getInstance(document.getElementById('catalogAlertConfirm'))?.hide();
      if (event.target.closest('[data-alert-accept]')) showAlertToast('success', 'Simulasi konfirmasi selesai. Tidak ada data yang dihapus.', 'Contoh konfirmasi');
    }
  });
  document.addEventListener('shown.bs.modal', (event) => {
    event.target.querySelector('[data-modal-initial-focus], [data-alert-cancel]')?.focus();
  });
  document.addEventListener('hidden.bs.modal', (event) => {
    if (event.target.id === 'catalogAlertConfirm' && confirmOpener?.isConnected) confirmOpener.focus();
  });
  document.addEventListener('submit', (event) => {
    if (event.target.matches('[data-kit-form], [data-product-form]')) return;
    if (event.target.matches('[data-modal-demo-form]')) {
      event.preventDefault();
      const form = event.target;
      const name = form.elements.namedItem('category_name');
      name.value = name.value.trim();
      name.setCustomValidity(name.value.length < name.minLength ? 'Masukkan nama kategori minimal tiga karakter.' : '');
      form.classList.add('was-validated');
      form.querySelectorAll('input, select, textarea').forEach((field) => field.setAttribute('aria-invalid', String(!field.validity.valid)));
      if (!form.checkValidity()) { form.querySelector(':invalid')?.focus(); return; }
      bootstrap.Modal.getInstance(form.closest('.modal'))?.hide();
      window.KerangkaWorkspace?.markClean(form);
      showAlertToast('info', `Kategori “${name.value}” berhasil diperiksa. Penyimpanan disimulasikan; data aplikasi tidak diubah.`, 'Simulasi form kategori');
      return;
    }
    if (!event.target.matches('.sample-form')) return;
    event.preventDefault();
    if (event.target.id === 'catalogValidationForm') {
      event.target.classList.add('was-validated');
      event.target.querySelectorAll('input, select, textarea').forEach((field) => field.setAttribute('aria-invalid', String(!field.validity.valid)));
      if (!event.target.checkValidity()) { event.target.querySelector(':invalid')?.focus(); return; }
    }
    showAlertToast('info', 'Form contoh berhasil diperiksa. Data aplikasi tidak diubah.', 'Simulasi form');
  });
  document.addEventListener('reset', (event) => {
    if (event.target.id === 'catalogValidationForm') setTimeout(() => {
      event.target.classList.remove('was-validated');
      event.target.querySelectorAll('[aria-invalid]').forEach((field) => field.removeAttribute('aria-invalid'));
    }, 0);
  });
  init(document.getElementById('spaPageContent'));
})();
