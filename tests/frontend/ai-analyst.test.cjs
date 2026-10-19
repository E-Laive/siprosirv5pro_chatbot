const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

test('POS SQL reference schema exists and contains core business tables', () => {
  const ddlPath = path.join(__dirname, '../../app/Database/Reference/dbsiprosir_online_v5_x2_struktur.sql');
  assert.equal(fs.existsSync(ddlPath), true);
  const ddl = fs.readFileSync(ddlPath, 'utf8');
  for (const table of [
    'tbltransaksipenjualan',
    'tbltransaksipenjualan_detail',
    'tbldatabarang',
    'tbldatabarang_stok',
    'tbldata_member',
    'tblreturpenjualan',
    'tblpiutangpenjualan',
    'tbltransaksipembelian',
    'tbldata_supiller',
    'tblbank_ledger',
  ]) {
    assert.equal(ddl.includes(`CREATE TABLE \`${table}\``), true, `Missing table ${table}`);
  }
});

test('ai-chat.js renders markdown safely and escapes HTML injection', () => {
  const source = fs.readFileSync(path.join(__dirname, '../../public/assets/js/ai-chat.js'), 'utf8');
  const context = {
    window: {},
    document: {
      addEventListener: () => {},
      getElementById: () => null,
      querySelector: () => null,
    },
    CSS: { escape: (s) => s },
  };
  vm.runInNewContext(source, context);
  assert.equal(typeof context.window.KerangkaAiChat, 'object');
  const rendered = context.window.KerangkaAiChat.renderMarkdownText(
    'Halo **SIPROSIR** dengan `grand_total` <script>alert(1)</script>\n- Item satu\n- Item dua'
  );
  assert.equal(rendered.includes('<strong>SIPROSIR</strong>'), true);
  assert.equal(rendered.includes('<code>grand_total</code>'), true);
  assert.equal(rendered.includes('<script>'), false);
  assert.equal(rendered.includes('<ul><li>Item satu</li><li>Item dua</li></ul>'), true);
});
