const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

class Element {
  constructor(tag = 'div') {
    this.tag = tag; this.children = []; this.dataset = {}; this.attributes = {}; this.listeners = {};
    const classes = new Set();
    this.classList = { add: v => classes.add(v), remove: v => classes.delete(v), contains: v => classes.has(v), toggle(v, force) { const active = force ?? !classes.has(v); if (active) classes.add(v); else classes.delete(v); return active; } };
    this.style = {}; this.value = ''; this.disabled = false; this.isConnected = true;
  }
  appendChild(node) { this.children.push(node); return node; }
  append(...nodes) { nodes.forEach(n => this.appendChild(n)); }
  prepend(node) { this.children.unshift(node); }
  replaceChildren(...nodes) { this.children = nodes; }
  setAttribute(key, val) { this.attributes[key] = val; }
  addEventListener(type, cb) { this.listeners[type] = cb; }
  querySelector(selector) { return this.querySelectorAll(selector)[0] || (selector === '[data-ai-copy]' ? new Element('button') : null); }
  querySelectorAll(selector) { return this.children.flatMap(walk).filter(n => selector.startsWith('.') ? n.className?.split(' ').includes(selector.slice(1)) : n.tag === selector); }
  getBoundingClientRect() { return { left: 0, width: this.clientWidth || 638 }; }
  contains() { return true; }
  focus() { this.focused = true; }
  scrollTo() {}
  remove() {}
}
const walk = n => [n, ...n.children.flatMap(walk)];
const flush = async () => { for (let i = 0; i < 8; i++) await Promise.resolve(); };

function setup() {
  const handlers = {};
  const nodes = Object.fromEntries(['btnSendAiMessage', 'aiEmptyState', 'aiMessageStream', 'aiMessageInput', 'aiThinkingIndicator', 'aiActiveTitle', 'aiActiveContextBadge', 'aiMessagesViewport', 'aiFollowupChips'].map(id => [id, new Element()]));
  const workspace = new Element(); workspace.dataset = { initialized: '1', activeUid: 'conv-original', endpointBase: '/asisten-ai' };
  workspace.querySelector = selector => nodes[selector.replace(/^#/, '')] || null;
  let resolveFetch; let request;
  const context = {
    document: { readyState: 'loading', addEventListener: (type, cb) => handlers[type] = cb, getElementById: id => id === 'aiWorkspace' ? workspace : nodes[id] || null, querySelector: () => null, createElement: tag => new Element(tag), createElementNS: (_, tag) => new Element(tag) },
    window: { KerangkaHTTP: { readJSON: async r => r.body }, KerangkaCatalog: { notify() {} } },
    CSS: { escape: x => x }, navigator: {}, location: { assign() {} }, URLSearchParams,
    fetch: async (url, options) => { request = { url, options }; return new Promise(resolve => resolveFetch = resolve); },
  };
  vm.runInNewContext(fs.readFileSync(require('node:path').join(__dirname, '../../public/assets/js/ai-chat.js'), 'utf8'), context);
  return { context, handlers, nodes, workspace, request: () => request, respond: body => resolveFetch({ ok: body.success, status: body.success ? 200 : 503, body }) };
}

test('late message response cannot overwrite a newly selected conversation', async () => {
  const s = setup(); s.nodes.aiMessageInput.value = 'Penjualan bulan lalu';
  await s.handlers.submit({ preventDefault() {}, target: { id: 'aiChatForm' } }); await flush();
  assert.equal(s.nodes.btnSendAiMessage.disabled, true);
  await s.handlers.click({ target: { closest: selector => selector === '#btnNewAiChat' ? {} : null } });
  assert.equal(s.workspace.dataset.activeUid, '');
  s.respond({ success: true, conversation_uid: 'conv-original', conversation_title: 'Old result' }); await flush();
  assert.equal(s.workspace.dataset.activeUid, '');
  assert.equal(s.nodes.aiMessageStream.children.length, 0);
  assert.equal(s.nodes.btnSendAiMessage.disabled, false);
});

test('network failure preserves the question and offers retry', async () => {
  const s = setup(); s.nodes.aiMessageInput.value = 'Pertanyaan yang tidak boleh hilang';
  await s.handlers.submit({ preventDefault() {}, target: { id: 'aiChatForm' } }); await flush();
  s.respond({ success: false, message: 'Koneksi gagal' }); await flush();
  assert.equal(s.nodes.aiMessageInput.value, 'Pertanyaan yang tidak boleh hilang');
  assert.equal(s.nodes.btnSendAiMessage.disabled, false);
  assert.ok(walk(s.nodes.aiMessageStream).some(n => n.tag === 'button' && n.textContent === 'Coba lagi'));
});

test('reasoning configuration errors preserve draft without an identical retry', async () => {
  for (const error_code of ['provider_reasoning_unsupported', 'provider_output_incomplete']) {
    const s = setup(); s.nodes.aiMessageInput.value = 'Pertanyaan saya';
    await s.handlers.submit({ preventDefault() {}, target: { id: 'aiChatForm' } }); await flush();
    s.respond({ success: false, error_code, summary: 'Pengaturan AI perlu diperiksa Administrator.' }); await flush();
    assert.equal(s.nodes.aiMessageInput.value, 'Pertanyaan saya');
    assert.equal(walk(s.nodes.aiMessageStream).some(n => n.tag === 'button' && n.textContent === 'Coba lagi'), false);
  }
});

function setupProviderForm() {
  const s = setup();
  const fields = { api_key: { value: 'key-in-form' }, reasoning_effort: { value: 'medium' } };
  const button = new Element('button');
  const form = { id: 'aiProviderForm', elements: { namedItem: name => fields[name] }, querySelector: () => button };
  const notices = []; let hidden = false;
  s.nodes.aiReasoningStatus = new Element(); s.nodes.aiProviderBadge = new Element(); s.nodes.aiProviderModal = new Element();
  s.context.FormData = class { constructor() { this.values = { reasoning_effort: fields.reasoning_effort.value }; } set(k, v) { this.values[k] = v; } };
  s.context.bootstrap = { Modal: { getInstance: () => ({ hide() { hidden = true; } }) } };
  s.context.window.KerangkaCatalog.notify = (...args) => notices.push(args);
  return { ...s, fields, form, button, notices, hidden: () => hidden };
}

test('provider form submits selected reasoning once and clears secret only after success', async () => {
  const s = setupProviderForm();
  const pending = s.handlers.submit({ preventDefault() {}, target: s.form }); await flush();
  const first = s.request(); assert.equal(first.options.body.values.reasoning_effort, 'medium');
  assert.equal(s.button.disabled, true);
  await s.handlers.submit({ preventDefault() {}, target: s.form }); assert.equal(s.request(), first);
  s.respond({ success: true, provider: { model_id: 'combo', configured: true, reasoning_capabilities: { effort_supported: true } } }); await pending;
  assert.equal(s.fields.api_key.value, ''); assert.equal(s.hidden(), true); assert.equal(s.button.disabled, false);
  assert.match(s.nodes.aiReasoningStatus.textContent, /mendukung/);
});

test('provider rejection and connection failure retain form fields and restore save button', async () => {
  const s = setupProviderForm();
  const pending = s.handlers.submit({ preventDefault() {}, target: s.form }); await flush();
  s.respond({ success: false, message: 'Model belum mendukung reasoning.' }); await pending;
  assert.equal(s.fields.reasoning_effort.value, 'medium'); assert.equal(s.fields.api_key.value, 'key-in-form');
  assert.equal(s.hidden(), false); assert.equal(s.button.disabled, false);
  assert.equal(s.notices[0][1], 'Model belum mendukung reasoning.');
  s.context.fetch = async () => { throw new Error('offline'); };
  await s.handlers.submit({ preventDefault() {}, target: s.form });
  assert.equal(s.hidden(), false); assert.equal(s.button.disabled, false); assert.equal(s.fields.api_key.value, 'key-in-form');
  assert.match(s.notices.at(-1)[1], /isian tetap tersedia/);
});

test('unsupported SQL explains the final rejection without encouraging identical retries', async () => {
  for (const error_code of ['analysis_not_supported', 'sql_validation_failed', 'sql_repair_scope_mismatch', 'business_filter_mismatch', 'business_member_mismatch', 'member_reference_invalid', 'provider_plan_mismatch']) {
    const s = setup(); s.nodes.aiMessageInput.value = 'Analisis September 2026';
    await s.handlers.submit({ preventDefault() {}, target: { id: 'aiChatForm' } }); await flush();
    s.respond({ success: false, error_code, summary: 'Metode alternatif sudah dicoba sekali.' }); await flush();
    assert.equal(s.nodes.aiMessageInput.value, 'Analisis September 2026');
    assert.equal(s.nodes.btnSendAiMessage.disabled, false);
    const nodes = walk(s.nodes.aiMessageStream);
    assert.ok(nodes.some(n => n.textContent === 'Metode alternatif sudah dicoba sekali.'));
    assert.equal(nodes.some(n => n.tag === 'button' && n.textContent === 'Coba lagi'), false);
  }
});

test('format buttons submit a structured reformat action', async () => {
  const s = setup();
  const button = { dataset: { aiReformat: 'table' } };
  await s.handlers.click({ target: { closest: selector => selector === '[data-ai-reformat]' ? button : null } }); await flush();
  const body = new URLSearchParams(s.request().options.body);
  assert.equal(body.get('action'), 'reformat'); assert.equal(body.get('format'), 'table');
  assert.equal(body.get('conversation_uid'), 'conv-original');
  s.respond({ success: false, message: 'Test complete' }); await flush();
});

test('conversation and context answers have only copy actions while reports keep exact references', () => {
  const s = setup();
  for (const kind of ['conversation', 'report_context']) {
    const msg = s.context.window.KerangkaAiChat.createMessageElement({ role: 'assistant', metadata: { response_kind: kind, actions: { table: false, chart: false, export: false } }, blocks: [{ type: 'text', content: 'Jawaban singkat.' }] });
    const actions = walk(msg).find(n => n.className === 'ai-msg-actions');
    assert.ok(actions.innerHTML.includes('data-ai-copy'));
    assert.equal(actions.innerHTML.includes('data-ai-reformat'), false);
  }
  const msg = s.context.window.KerangkaAiChat.createMessageElement({ role: 'assistant', metadata: { response_kind: 'analysis', report_ref: 'report-0123456789abcdef', actions: { table: true, chart: false, export: true } }, blocks: [{ type: 'text', content: 'Laporan.' }] });
  const actions = walk(msg).find(n => n.className === 'ai-msg-actions');
  assert.ok(actions.innerHTML.includes('data-ai-reformat="table"'));
  assert.equal(actions.innerHTML.includes('data-ai-reformat="chart"'), false);
  assert.ok(actions.innerHTML.includes('data-report-ref="report-0123456789abcdef"'));
});

test('production hides query diagnostics while development keeps them and failure warnings remain visible', () => {
  const s = setup();
  const message = {role:'assistant',blocks:[{type:'text',content:'Laporan.'}],metadata:{queries:[{sql:'SELECT id_produk FROM tbldatabarang',success:true,row_count:1}]}};
  const audit = m => walk(s.context.window.KerangkaAiChat.createMessageElement(m)).some(n=>n.className==='ai-sql-audit');
  s.workspace.dataset.showQueryAudit='0'; assert.equal(audit(message),false);
  s.workspace.dataset.showQueryAudit='1'; assert.equal(audit(message),true);
  assert.equal(audit({...message,metadata:{...message.metadata,show_query_audit:false}}),false);
  const incomplete = s.context.window.KerangkaAiChat.createMessageElement({...message,metadata:{show_query_audit:false,queries:[],query_failure_present:true}});
  assert.ok(walk(incomplete).some(n=>n.className?.startsWith('ai-block-warning')));
});

test('developer error trace shows generated SQL and rejection as plain text without claiming execution', () => {
  const s = setup(); s.workspace.dataset.showQueryAudit='1';
  const metadata = { response_kind:'error', analysis_status:'error', queries:[], sql_diagnostics:[{sql:'SELECT <unsafe>',executed:false,success:false,error_code:'business_grouping_mismatch',error:'Rincian harian belum sesuai <img>'}] };
  const trace = walk(s.context.window.KerangkaAiChat.createMessageElement({role:'assistant',blocks:[{type:'warning',content:'Gagal'}],metadata}));
  const pre = trace.find(n=>n.className==='ai-sql-code');
  assert.ok(pre.textContent.includes('Belum dieksekusi'));
  assert.ok(pre.textContent.includes('business_grouping_mismatch'));
  assert.ok(pre.textContent.includes('SELECT <unsafe>'));
  assert.ok(pre.textContent.includes('Rincian harian belum sesuai <img>'));
  assert.equal(pre.innerHTML || '', '');
  s.workspace.dataset.showQueryAudit='0';
  assert.equal(walk(s.context.window.KerangkaAiChat.createMessageElement({role:'assistant',blocks:[],metadata})).some(n=>n.className==='ai-sql-audit'),false);
});

test('business plan rejection shows bounded field diagnostics only in developer mode as plain text', () => {
  const s = setup(); s.workspace.dataset.showQueryAudit='1';
  const metadata={response_kind:'error',plan_diagnostics:{stage:'business_plan',issues:[{path:'plan.ranking.metric',rule:'<img onerror=alert(1)>'}],plan_shape:{metric:'product_units'}},queries:[]};
  const nodes=walk(s.context.window.KerangkaAiChat.createMessageElement({role:'assistant',blocks:[{type:'text',content:'Rencana belum sesuai'}],metadata}));
  const details=nodes.find(n=>n.className==='ai-sql-audit'); assert.ok(details); assert.equal(details.open,undefined);
  const pre=nodes.find(n=>n.className==='ai-sql-code');assert.ok(pre.textContent.includes('plan.ranking.metric'));assert.ok(pre.textContent.includes('<img'));assert.equal(pre.innerHTML||'','');
  s.workspace.dataset.showQueryAudit='0';
  assert.equal(walk(s.context.window.KerangkaAiChat.createMessageElement({role:'assistant',blocks:[],metadata})).some(n=>n.className==='ai-sql-audit'),false);
});

test('failed message displays developer SQL immediately and retains the draft without a blind retry', async () => {
  const s = setup(); s.workspace.dataset.showQueryAudit='1'; s.nodes.aiMessageInput.value='Laporan harian';
  await s.handlers.submit({preventDefault(){},target:{id:'aiChatForm'}}); await flush();
  s.respond({success:false,conversation_uid:'conv-original',message:'Rincian harian belum sesuai',error_code:'business_grouping_mismatch',blocks:[{type:'warning',content:'Rincian harian belum sesuai'}],metadata:{response_kind:'error',analysis_status:'error',queries:[],sql_diagnostics:[{sql:'SELECT MONTH(tanggal)',executed:false,error:'Rincian harian belum sesuai'}]}}); await flush();
  assert.ok(walk(s.nodes.aiMessageStream).some(n=>n.className==='ai-sql-code' && n.textContent.includes('SELECT MONTH(tanggal)')));
  assert.equal(s.nodes.aiMessageInput.value,'Laporan harian');
  assert.equal(walk(s.nodes.aiMessageStream).some(n=>n.textContent==='Coba lagi'),false);
});

test('scope rejection and row limit preserve editable draft without an identical retry action', async () => {
  for (const error_code of ['provider_route_mismatch','result_limit_exceeded']) {
    const s=setup();s.nodes.aiMessageInput.value='Laporan retur';
    await s.handlers.submit({preventDefault(){},target:{id:'aiChatForm'}});await flush();
    s.respond({success:false,conversation_uid:'conv-original',message:'Persempit atau perjelas cakupan laporan.',error_code,metadata:{response_kind:'error',queries:[]},blocks:[]});await flush();
    assert.equal(s.nodes.aiMessageInput.value,'Laporan retur');assert.equal(walk(s.nodes.aiMessageStream).some(n=>n.textContent==='Coba lagi'),false);
  }
});

test('old report button transmits its reference instead of using the newest report', async () => {
  const s = setup();
  const button = { dataset: { aiReformat: 'table', reportRef: 'report-0123456789abcdef' } };
  await s.handlers.click({ target: { closest: selector => selector === '[data-ai-reformat]' ? button : null } }); await flush();
  const body = new URLSearchParams(s.request().options.body);
  assert.equal(body.get('report_ref'), button.dataset.reportRef);
  assert.equal(body.get('format'), 'table');
  s.respond({ success: false, message: 'Test complete' }); await flush();
});

test('general reply hides export and followups even while the last report dataset is preserved', async () => {
  const s = setup();
  const toolbar = new Element(); s.nodes.aiExportToolbar = toolbar;
  s.nodes.aiMessageInput.value = 'halo';
  await s.handlers.submit({ preventDefault() {}, target: { id: 'aiChatForm' } }); await flush();
  s.respond({ success: true, conversation_uid: 'conv-original', summary: 'Halo!', blocks: [{ type: 'text', content: 'Halo!' }], metadata: { response_kind: 'conversation', actions: { table: false, chart: false, export: false } }, context: { domain: 'sales', last_rows: [{ amount: 100 }], dialogue: { response_kind: 'conversation' } } });
  await flush();
  assert.equal(toolbar.hidden, true);
  assert.equal(s.nodes.aiFollowupChips.children.length, 0);
  assert.equal(s.nodes.btnSendAiMessage.disabled, false);
});

test('empty results suppress old visual blocks, SQL audit, export and followups', async () => {
  const s = setup(); const toolbar = new Element(); s.nodes.aiExportToolbar = toolbar;
  const metadata = {response_kind:'analysis',empty_result:true,actions:{table:true,chart:true,export:true},queries:[{success:true,sql:'SELECT COUNT(id_transaksi)',row_count:1}]};
  const blocks = [{type:'text',content:'Tidak ditemukan transaksi.'},{type:'kpi',items:[{label:'Omzet',value:'Rp0'}]},{type:'table',columns:[],rows:[]},{type:'chart',labels:['1'],series:[{data:[0]}]},{type:'insight',items:['Catatan']}];
  const message = s.context.window.KerangkaAiChat.createMessageElement({role:'assistant',metadata,blocks});
  const all = walk(message);
  assert.equal(all.some(n=>n.className==='ai-result-detail'),false);
  assert.equal(all.some(n=>n.className==='ai-sql-audit'),false);
  assert.equal(all.some(n=>n.tag==='svg'),false);
  assert.equal(all.find(n=>n.className==='ai-msg-actions').innerHTML.includes('data-ai-reformat'),false);
  s.nodes.aiMessageInput.value='Laporan hari ini';
  await s.handlers.submit({preventDefault(){},target:{id:'aiChatForm'}}); await flush();
  s.respond({success:true,conversation_uid:'conv-original',summary:'Tidak ditemukan transaksi.',metadata,blocks,context:{domain:'sales',last_empty_result:true,last_rows:[{amount:0}],dialogue:{response_kind:'analysis'}}}); await flush();
  assert.equal(toolbar.hidden,true);
  assert.equal(s.nodes.aiFollowupChips.children.length,0);
});

test('result defaults to three primary numbers and closed optional details', () => {
  const s = setup();
  const msg = s.context.window.KerangkaAiChat.createMessageElement({ role: 'assistant', blocks: [
    { type: 'text', content: 'Ringkasan' },
    { type: 'kpi', items: [1, 2, 3, 4, 5].map(v => ({ label: 'Angka', value: v })) },
    { type: 'chart', labels: [], series: [] },
    { type: 'insight', items: ['Catatan metodologi'] },
  ] });
  const all = walk(msg);
  assert.equal(all.filter(n => n.className?.startsWith('ai-kpi-box ')).length, 3);
  const details = all.filter(n => n.className === 'ai-result-detail');
  assert.equal(details.length, 3); assert.ok(details.every(n => n.open === false));
});

test('requested graph opens and shows negative values below zero; zero stays zero', () => {
  const s = setup();
  const msg = s.context.window.KerangkaAiChat.createMessageElement({ role: 'assistant', metadata: { presentation: 'chart' }, blocks: [{ type: 'chart', labels: ['Turun', 'Naik', 'Nol'], series: [{ name: 'Nilai', data: [-100, 100, 0] }] }] });
  const all = walk(msg);
  assert.equal(all.find(n => n.className === 'ai-result-detail').open, true);
  const bars = all.filter(n => n.tag === 'rect');
  assert.equal(bars.length, 3);
  assert.ok(Number(bars[0].attributes.y) > Number(bars[1].attributes.y));
  assert.equal(Number(bars[0].attributes.height), Number(bars[1].attributes.height));
  assert.equal(Number(bars[2].attributes.height), 0);
  assert.ok(all.some(n => n.tag === 'text' && n.textContent === '-100'));
});


test('clarification keeps focus on the answer instead of result actions and followups', async () => {
  const s = setup(); s.nodes.aiMessageInput.value = 'Pembelian member A berapa?';
  await s.handlers.submit({ preventDefault() {}, target: { id: 'aiChatForm' } }); await flush();
  s.respond({ success: true, conversation_uid: 'conv-original', summary: 'Member yang dimaksud siapa?', blocks: [{ type: 'text', content: 'Member yang dimaksud siapa?' }], metadata: { intent: 'clarification_needed', analysis_status: 'clarification' }, context: { domain: 'sales', last_rows: [{ value: 10 }], pending_request: { slot: 'member' } } }); await flush();
  assert.equal(s.nodes.aiFollowupChips.children.length, 0);
  assert.equal(s.nodes.aiFollowupChips.hidden, true);
  const cards = walk(s.nodes.aiMessageStream);
  assert.ok(cards.some(n => n.innerHTML?.includes('data-ai-copy')));
  assert.equal(cards.some(n => n.innerHTML?.includes('data-ai-reformat')), false);
});

const chartMessage = (s, block) => s.context.window.KerangkaAiChat.createMessageElement({ role: 'assistant', metadata: { presentation: 'chart' }, blocks: [{ type: 'chart', ...block }] });

test('dense monthly chart keeps all points, compacts labels, and redraws at narrow width', () => {
  const s = setup(); const observers = [];
  s.context.ResizeObserver = class { constructor(callback) { this.callback = callback; observers.push(this); } observe() {} disconnect() { this.disconnected = true; } };
  const msg = chartMessage(s, { chart_type: 'line', labels: Array.from({ length: 12 }, (_, i) => `2026-${String(i + 1).padStart(2, '0')}`), series: [{ name: 'Omzet', data: Array.from({ length: 12 }, (_, i) => i * 100) }] });
  let all = walk(msg); const canvas = all.find(n => n.className === 'ai-chart-canvas');
  assert.equal(all.filter(n => n.tag === 'circle').length, 12);
  assert.ok(all.some(n => n.tag === 'text' && n.textContent === 'Jan'));
  assert.ok(all.some(n => n.tag === 'text' && n.textContent === 'Des'));
  assert.ok(all.filter(n => n.tag === 'text' && /^(Jan|Feb|Mar|Apr|Mei|Jun|Jul|Agu|Sep|Okt|Nov|Des)$/.test(n.textContent)).length < 12);
  canvas.clientWidth = 260; canvas.clientHeight = 198; observers[0].callback(); all = walk(msg);
  assert.equal(all.find(n => n.tag === 'svg').attributes.viewBox, '0 0 242 180');
  assert.equal(all.filter(n => n.tag === 'circle').length, 12);
  const labels = all.filter(n => n.tag === 'text' && /^(Jan|Feb|Mar|Apr|Mei|Jun|Jul|Agu|Sep|Okt|Nov|Des)$/.test(n.textContent));
  assert.equal(labels.length, 2);
  canvas.isConnected = false; observers[0].callback(); assert.equal(observers[0].disconnected, true);
});

test('chart touch and keyboard details preserve full dates and exact values without requests', () => {
  const s = setup();
  const msg = chartMessage(s, { chart_type: 'line', labels: ['2025-12', '2026-01'], series: [{ name: 'Omzet', data: [-1234567, 0] }] });
  const all = walk(msg); const svg = all.find(n => n.tag === 'svg'); const readout = all.find(n => n.className === 'ai-chart-readout');
  assert.ok(all.some(n => n.tag === 'text' && n.textContent === 'Des 25'));
  assert.ok(all.some(n => n.tag === 'text' && n.textContent === 'Jan 26'));
  svg.listeners.keydown({ key: 'Home', preventDefault() {} });
  assert.match(readout.textContent, /2025-12.*-Rp1\.234\.567/);
  svg.listeners.keydown({ key: 'End', preventDefault() {} }); assert.match(readout.textContent, /2026-01.*Rp0/);
  svg.listeners.pointerdown({ clientX: 80 }); assert.match(readout.textContent, /2025-12/);
  all.find(n => n.tag === 'circle').listeners.pointerenter(); assert.match(readout.textContent, /-Rp1\.234\.567/);
  assert.equal(s.request(), undefined);
});

test('missing chart values remain gaps while empty and zero datasets are distinguished', () => {
  const s = setup();
  const msg = chartMessage(s, { chart_type: 'line', labels: ['A', 'B', 'C', 'D', 'E'], series: [{ name: 'Transaksi', data: [1, 2, null, 0, 3] }] });
  const all = walk(msg);
  assert.equal(all.filter(n => n.tag === 'circle').length, 4);
  assert.equal(all.filter(n => n.tag === 'polyline').length, 2);
  const zeros = walk(chartMessage(s, { labels: ['A'], series: [{ name: 'Nilai', data: [0] }] }));
  assert.equal(Number(zeros.find(n => n.tag === 'rect').attributes.height), 0);
  const empty = walk(chartMessage(s, { labels: [], series: [] }));
  assert.equal(empty.find(n => n.textContent === 'Perbesar').disabled, true);
  assert.ok(empty.some(n => n.textContent === 'Data grafik belum tersedia.'));
  const missing = walk(chartMessage(s, { labels: ['A'], series: [{ data: [null] }] }));
  assert.equal(missing.some(n => n.tag === 'svg'), false);
});

test('expand reuses the dataset, preserves chart mode, and cleans up fullscreen and focus', () => {
  const s = setup(); const observers = []; let shown = 0;
  s.context.ResizeObserver = class { constructor(callback) { this.callback = callback; observers.push(this); } observe() {} disconnect() { this.disconnected = true; } };
  const modal = new Element(); const content = new Element(); const title = new Element(); const fullscreen = new Element('button'); const dialog = new Element();
  modal.querySelector = selector => ({ '#aiChartModalContent': content, '#aiChartModalTitle': title, '#aiChartFullscreen': fullscreen, '.modal-dialog': dialog }[selector]);
  s.nodes.aiChartModal = modal;
  s.context.window.bootstrap = { Modal: { getOrCreateInstance: (el, options) => { assert.equal(el, modal); assert.equal(options.backdrop, 'static'); return { show() { shown++; } }; } } };
  const block = { title: '<img src=x onerror=alert(1)>', chart_type: 'bar', labels: ['A', 'B'], series: [{ name: 'Omzet', data: [50, -10] }] };
  const original = JSON.stringify(block); const msg = chartMessage(s, block);
  const header = walk(msg).find(n => n.className === 'ai-block-toolbar');
  header.listeners.click({ target: { closest: () => ({ dataset: { chartSwitch: 'line' } }) } });
  const opener = walk(msg).find(n => n.textContent === 'Perbesar'); opener.listeners.click();
  assert.equal(shown, 1); assert.equal(title.textContent, block.title);
  assert.equal(walk(content).filter(n => n.tag === 'circle').length, 2);
  assert.equal(walk(content).some(n => n.textContent === 'Perbesar'), false);
  assert.equal(JSON.stringify(block), original); assert.equal(s.request(), undefined);
  fullscreen.listeners.click(); assert.equal(fullscreen.attributes['aria-pressed'], 'true'); assert.equal(dialog.classList.contains('modal-fullscreen'), true);
  modal.listeners['shown.bs.modal'](); assert.equal(walk(content).find(n => n.tag === 'svg').focused, true);
  modal.listeners['hidden.bs.modal'](); assert.equal(content.children.length, 0); assert.equal(opener.focused, true);
  assert.equal(observers[1].disconnected, true); assert.equal(dialog.classList.contains('modal-fullscreen'), false);
  opener.listeners.click(); assert.equal(shown, 2); assert.equal(s.request(), undefined);
});

test('daily comparison retains both 31-day series and missing cells without inventing zero', () => {
  const s = setup();
  const originalQuery = Element.prototype.querySelector;
  const originalQueries = Element.prototype.querySelectorAll;
  Element.prototype.querySelector = function (selector) {
    if (['input', '[data-ai-table-count]', '[data-ai-page-info]'].includes(selector)) {
      this.mockNodes ||= {}; return this.mockNodes[selector] ||= new Element();
    }
    return originalQuery.call(this, selector);
  };
  Element.prototype.querySelectorAll = function (selector) {
    if (selector === '[data-ai-page]') return [new Element('button'), new Element('button')];
    return originalQueries.call(this, selector);
  };
  try {
    const rows = Array.from({ length: 31 }, (_, i) => ({ day: i + 1, base: i === 0 ? 100 : 0, target: i === 30 ? null : 0, percent: i === 0 ? -100 : null }));
    const msg = s.context.window.KerangkaAiChat.createMessageElement({ role: 'assistant', metadata: { presentation: 'table' }, blocks: [
      { type: 'table', columns: [{ key: 'day', label: 'Tanggal', type: 'number' }, { key: 'base', label: 'Agustus', type: 'currency' }, { key: 'target', label: 'September', type: 'currency' }, { key: 'percent', label: 'Perubahan (%)', type: 'number' }], rows },
      { type: 'chart', chart_type: 'line', labels: rows.map(r => String(r.day)), series: [{ name: 'Agustus · omzet', data: rows.map(r => r.base) }, { name: 'September · omzet', data: rows.map(r => r.target) }] },
    ] });
    const all = walk(msg);
    const details = all.filter(n => n.className === 'ai-result-detail');
    assert.equal(details[0].open, true); assert.equal(details[1].open, false);
    const cells = all.filter(n => n.tag === 'td').map(n => n.textContent);
    assert.ok(cells.includes('—')); assert.ok(cells.includes('Rp0')); assert.ok(cells.includes('-100'));
    const footer = all.find(n => n.className === 'kit-table-footer mt-2 mb-0');
    for (let i = 0; i < 3; i++) footer.listeners.click({ target: { closest: () => ({ dataset: { aiPage: '1' } }) } });
    const lastRow = walk(msg).filter(n => n.tag === 'tbody')[0].children.at(-1);
    assert.deepEqual(lastRow.children.map(n => n.textContent), ['31', 'Rp0', '—', '—']);
    assert.equal(all.filter(n => n.tag === 'circle').length, 61);
    assert.equal(s.request(), undefined);
  } finally {
    Element.prototype.querySelector = originalQuery; Element.prototype.querySelectorAll = originalQueries;
  }
});
