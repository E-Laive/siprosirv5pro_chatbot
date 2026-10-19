/* Pure data operations shared by the browser examples and regression tests. */
((root) => {
  const money = (value) => new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(value);
  const pageRows = (rows, { search = '', status = '', role = '', sort = '', direction = 'asc', page = 1, size = 4 } = {}) => {
    const term = search.trim().toLocaleLowerCase('id-ID');
    const filtered = rows.filter((row) => (!term || row.search.toLocaleLowerCase('id-ID').includes(term)) && (!status || row.status === status) && (!role || row.role === role));
    if (sort) filtered.sort((a, b) => {
      const av = a.values[sort], bv = b.values[sort];
      const result = typeof av === 'number' && typeof bv === 'number' ? av - bv : String(av).localeCompare(String(bv), 'id-ID', { numeric: true });
      return direction === 'desc' ? -result : result;
    });
    const limit = [2, 4].includes(Number(size)) ? Number(size) : 4;
    const pages = Math.max(1, Math.ceil(filtered.length / limit));
    const current = Math.min(Math.max(1, Number(page) || 1), pages);
    return { rows: filtered.slice((current - 1) * limit, current * limit), matchedRows: filtered, total: filtered.length, pages, page: current, start: filtered.length ? (current - 1) * limit + 1 : 0, end: Math.min(current * limit, filtered.length) };
  };
  const currencyDigits = (value) => String(value).replace(/\D/g, '').slice(0, 12);
  const validateImage = ({ type, size }) => !['image/png', 'image/jpeg', 'image/webp'].includes(type) ? 'Pilih gambar PNG, JPEG, atau WebP.' : size > 2 * 1024 * 1024 ? 'Ukuran gambar maksimal 2 MB.' : '';
  const api = { money, pageRows, currencyDigits, validateImage };
  if (typeof module !== 'undefined' && module.exports) module.exports = api;
  else root.KerangkaUIModel = api;
})(typeof window !== 'undefined' ? window : {});
