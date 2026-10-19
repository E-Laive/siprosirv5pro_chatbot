const test = require('node:test');
const assert = require('node:assert/strict');
const { pageRows, currencyDigits, validateImage } = require('../../public/assets/js/ui-model.js');
const rows = [
  { search: 'Kopi PRD-001', status: 'Aktif', role: '', values: { price: 68000 } },
  { search: 'Susu PRD-002', status: 'Menipis', role: '', values: { price: 21500 } },
  { search: 'Roti PRD-003', status: 'Aktif', role: '', values: { price: 17000 } },
  { search: 'Sirup PRD-004', status: 'Nonaktif', role: '', values: { price: 45000 } },
];
test('sorts numeric amounts across the whole dataset before pagination', () => {
  const page = pageRows(rows, { sort: 'price', size: 2, page: 2 });
  assert.deepEqual(page.rows.map(r => r.values.price), [45000, 68000]);
  assert.equal(page.pages, 2);
  assert.deepEqual(rows.map(r => r.values.price), [68000, 21500, 17000, 45000]);
});
test('combined filter clamps a stale page and distinguishes empty results', () => {
  const filtered = pageRows(rows, { search: ' SUSU ', status: 'Menipis', page: 2, size: 2 });
  assert.equal(filtered.page, 1); assert.equal(filtered.total, 1);
  const empty = pageRows(rows, { search: 'Susu', status: 'Aktif', page: 9 });
  assert.equal(empty.total, 0); assert.equal(empty.start, 0); assert.equal(empty.end, 0);
});
test('extracts and bounds nominal digits without accepting arbitrary text as a number', () => {
  assert.equal(currencyDigits('Rp 1.250.000'), '1250000');
  assert.equal(currencyDigits(''), '');
  assert.equal(currencyDigits('12345678901234567890').length, 12);
});
test('rejects unsupported images and oversize input; accepts the documented limit', () => {
  assert.notEqual(validateImage({ type: 'image/svg+xml', size: 100 }), '');
  assert.notEqual(validateImage({ type: 'image/jpeg', size: 2097153 }), '');
  assert.equal(validateImage({ type: 'image/png', size: 2097152 }), '');
});
