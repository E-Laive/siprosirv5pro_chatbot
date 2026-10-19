(() => {
  const readJSON = async (response) => {
    const data = await response.json();
    if (!data || typeof data !== 'object' || Array.isArray(data)) throw new Error('Respons server tidak valid.');
    if (data.redirect) {
      const target = new URL(data.redirect, location.href);
      if (target.origin !== location.origin) throw new Error('Tujuan pengalihan tidak valid.');
    }
    if (typeof data.csrf_token === 'string' && /^[a-f0-9]{32,128}$/i.test(data.csrf_token)) {
      const name = document.querySelector('meta[name="csrf-token-name"]')?.content || 'csrf_test_name';
      for (const input of document.querySelectorAll('input[type="hidden"]')) if (input.name === name) input.value = data.csrf_token;
      const meta = document.querySelector('meta[name="csrf-token-value"]'); if (meta) meta.content = data.csrf_token;
    }
    return data;
  };
  window.KerangkaHTTP = { readJSON };
})();
