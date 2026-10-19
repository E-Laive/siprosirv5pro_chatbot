(() => {
  const tableStates = new WeakMap();
  const filterTable = (table) => {
    let state = tableStates.get(table);
    if (!state) {
      state = { page: 1, size: 4, sort: '', direction: 'asc', rows: [...table.querySelectorAll('tbody tr')].map((element) => ({ element, search: element.dataset.search || element.textContent, status: element.dataset.status || '', role: element.dataset.role || '', values: JSON.parse(element.dataset.values || '{}') })) };
      tableStates.set(table, state);
    }
    const card = table.closest('.content-card');
    const search = (card.querySelector('[data-table-search]')?.value || '').trim().toLowerCase();
    const status = card.querySelector('[data-table-status]')?.value || '';
    const role = card.querySelector('[data-table-role]')?.value || '';
    state.size = Number(card.querySelector('[data-page-size]')?.value || 4);
    const resultPage = window.KerangkaUIModel.pageRows(state.rows, { ...state, search, status, role });
    state.page = resultPage.page;
    state.rows.forEach((row) => { row.element.hidden = !resultPage.rows.includes(row); });
    resultPage.rows.forEach((row) => table.tBodies[0].appendChild(row.element));
    const visible = resultPage.total;
    if (table.dataset.dataset === 'transaksi') {
      const total = resultPage.matchedRows.reduce((sum, row) => sum + Number(row.values.total), 0);
      card.querySelector('[data-transaction-total]').textContent = window.KerangkaUIModel.money(total);
      card.querySelector('[data-transaction-count]').textContent = `${visible} dokumen`;
      card.querySelector('[data-transaction-average]').textContent = window.KerangkaUIModel.money(visible ? Math.round(total / visible) : 0);
    }
    const result = card.querySelector('[data-table-result]');
    if (result) result.textContent = visible ? `Menampilkan ${resultPage.start}–${resultPage.end} dari ${visible} data` : 'Tidak ada data yang sesuai';
    const pageLabel = card.querySelector('[data-page-label]');
    if (pageLabel) pageLabel.textContent = `${resultPage.page} / ${resultPage.pages}`;
    card.querySelectorAll('[data-page-step]').forEach((button) => { button.disabled = Number(button.dataset.pageStep) < 0 ? resultPage.page <= 1 : resultPage.page >= resultPage.pages; });
    const empty = card.querySelector('[data-table-empty]');
    if (empty) empty.hidden = visible !== 0;
    card.querySelectorAll('[data-export]').forEach((link) => {
      const url = new URL(link.href);
      for (const [key, value] of Object.entries({ q: search, status, role })) {
        if (value) url.searchParams.set(key, value); else url.searchParams.delete(key);
      }
      link.href = url.href;
    });
    if (table.querySelector('[data-select-row]')) updateSelection(table);
  };

  const updateSelection = (table) => {
    const rows = [...table.querySelectorAll('[data-select-row]')];
    const visibleRows = rows.filter((row) => !row.closest('tr').hidden);
    const count = rows.filter((row) => row.checked).length;
    const all = table.querySelector('[data-select-all]');
    const selectedVisible = visibleRows.filter((row) => row.checked).length;
    if (all) { all.checked = visibleRows.length > 0 && selectedVisible === visibleRows.length; all.indeterminate = selectedVisible > 0 && selectedVisible < visibleRows.length; all.disabled = !visibleRows.length; }
    table.closest('.content-card').querySelector('[data-selection-result]').textContent = count ? `${count} pelanggan dipilih` : 'Belum ada pelanggan dipilih';
    table.closest('.content-card').querySelectorAll('[data-bulk-action]').forEach((button) => { button.disabled = !count; });
  };


  window.KerangkaTables = { states: tableStates, filter: filterTable, updateSelection };
})();
