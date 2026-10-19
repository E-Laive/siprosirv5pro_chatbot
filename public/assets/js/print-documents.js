(() => {
  const init = (root) => {
    const checked = root.querySelector('[data-print-format]:checked');
    if (!checked) return;
    const format = checked.value;
    root.querySelectorAll('[data-document-preview]').forEach((e) => { e.hidden = e.dataset.documentPreview !== format; });
    for (const [selector, pdf] of [['[data-print-preview]', false], ['[data-print-pdf]', true]]) {
      const link = root.querySelector(selector);
      const url = new URL(link.href); url.pathname = url.pathname.replace(/\/dokumen\/[^/]+(?:\/pdf)?$/, `/dokumen/${format}${pdf ? '/pdf' : ''}`); link.href = url.href;
    }
  };
  document.addEventListener('change', (event) => { if (event.target.matches('[data-print-format]')) init(document.getElementById('spaPageContent')); });
  document.addEventListener('click', (event) => { if (event.target.closest('[data-print-document]')) window.print(); });
  window.KerangkaPrint = { init };
  const root = document.getElementById('spaPageContent'); if (root) init(root);
})();
