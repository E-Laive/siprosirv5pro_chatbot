(() => {
  const prefix = 'kerangka-draft-v2-';
  const owner = document.body.dataset.userId;
  const current = prefix + owner + '-';
  const clear = () => {
    try { for (let i = sessionStorage.length - 1; i >= 0; i--) { const key = sessionStorage.key(i); if (key.startsWith(prefix) || key.startsWith('kerangka-example-product-draft-')) sessionStorage.removeItem(key); } } catch (_) {}
  };
  try {
    for (let i = sessionStorage.length - 1; i >= 0; i--) {
      const key = sessionStorage.key(i);
      if (key.startsWith('kerangka-example-product-draft-') || (key.startsWith(prefix) && !key.startsWith(current))) sessionStorage.removeItem(key);
    }
  } catch (_) {}
  window.KerangkaDrafts = { key: (sku) => current + sku, clear };
})();
