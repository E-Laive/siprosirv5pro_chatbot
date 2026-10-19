const test = require('node:test');
const assert = require('node:assert/strict');
const vm = require('node:vm');
const fs = require('node:fs');
const path = require('node:path');
const source = fs.readFileSync(path.join(__dirname, '../../public/assets/js/theme-init.js'), 'utf8');

function runtime(saved = null) {
  const root = { dataset: {}, style: {} };
  const elements = Object.fromEntries(['.sidebar', '.topbar', '.app-footer'].map(key => [key, { dataset: {}, style: {} }]));
  const listeners = new Map();
  const storage = new Map(saved ? [['kerangka-theme-config-v1', saved]] : []);
  let osChange;
  const preference = { matches: false, addEventListener: (_, callback) => { osChange = callback; } };
  const context = {
    document: { documentElement: root, querySelectorAll: selector => [elements[selector]], addEventListener: (_, callback) => callback() },
    localStorage: { getItem: key => storage.get(key) || null, setItem: (key, value) => storage.set(key, value), removeItem: key => storage.delete(key) },
    matchMedia: () => preference,
    window: {},
    CustomEvent: class { constructor(type, options) { this.type = type; this.detail = options.detail; } },
    addEventListener: (type, callback) => listeners.set(type, [...(listeners.get(type) || []), callback]),
    dispatchEvent: event => (listeners.get(event.type) || []).forEach(callback => callback(event)),
  };
  vm.runInNewContext(source, context);
  return { context, root, elements, storage, manager: context.window.KerangkaTheme, osChange: dark => { preference.matches = dark; osChange(); } };
}

test('custom preference updates Bootstrap regions before theme observers run', () => {
  const run = runtime();
  let observedMode;
  run.context.addEventListener('kerangka:theme-changed', () => { observedMode = run.manager.getConfig().mode; });
  run.manager.save({ mode: 'custom', themes: { sidebar: 'light', topbar: 'light', content: 'dark', footer: 'dark' } });
  assert.equal(observedMode, 'custom');
  assert.equal(run.root.dataset.bsTheme, 'dark');
  assert.equal(run.elements['.sidebar'].dataset.bsTheme, 'light');
  assert.equal(run.elements['.topbar'].dataset.bsTheme, 'light');
  assert.equal(run.elements['.app-footer'].dataset.bsTheme, 'dark');
  assert.equal(JSON.parse(run.storage.get('kerangka-theme-config-v1')).mode, 'custom');
});

test('system changes follow OS while custom selection is preserved', () => {
  const run = runtime();
  run.osChange(true);
  assert.equal(run.root.dataset.bsTheme, 'dark');
  run.manager.save({ mode: 'custom', themes: { content: 'light' } });
  run.osChange(false);
  run.osChange(true);
  assert.equal(run.root.dataset.bsTheme, 'light');
  run.manager.reset();
  assert.equal(run.root.dataset.themeMode, 'system');
  assert.equal(run.root.dataset.bsTheme, 'dark');
});

test('malformed saved preference falls back to system without breaking startup', () => {
  const run = runtime('{broken-json');
  assert.equal(run.root.dataset.themeMode, 'system');
  assert.equal(run.root.dataset.bsTheme, 'light');
});
