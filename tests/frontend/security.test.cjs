const test = require('node:test');
const assert = require('node:assert/strict');
const vm = require('node:vm');
const fs = require('node:fs');

test('draft namespace removes other-account/legacy drafts and preserves preferences', () => {
  const entries = new Map([['kerangka-draft-v2-7-new','other'],['kerangka-draft-v2-9-new','mine'],['kerangka-example-product-draft-new','legacy'],['kerangka-theme-config-v1','theme']]);
  const storage = { get length(){return entries.size;}, key:i=>[...entries.keys()][i],removeItem:k=>entries.delete(k) };
  const context = {window:{},document:{body:{dataset:{userId:'9'}}},sessionStorage:storage};
  vm.runInNewContext(fs.readFileSync('public/assets/js/draft-store.js','utf8'),context);
  assert.equal(context.window.KerangkaDrafts.key('PRD-001'),'kerangka-draft-v2-9-PRD-001');
  assert.equal(entries.has('kerangka-draft-v2-7-new'),false);
  assert.equal(entries.has('kerangka-example-product-draft-new'),false);
  assert.equal(entries.get('kerangka-draft-v2-9-new'),'mine');
  context.window.KerangkaDrafts.clear();
  assert.deepEqual([...entries],[['kerangka-theme-config-v1','theme']]);
});

test('response contract updates CSRF consistently and rejects cross-origin redirect', async () => {
  const inputs=[{name:'csrf_test_name',value:'old'},{name:'other',value:'keep'}];
  const meta={content:'old'};
  const context={window:{},URL,location:{href:'https://kerangkaweb.test/dashboard',origin:'https://kerangkaweb.test'},document:{querySelector:s=>s.includes('token-name')?{content:'csrf_test_name'}:meta,querySelectorAll:()=>inputs}};
  vm.runInNewContext(fs.readFileSync('public/assets/js/http.js','utf8'),context);
  const token='a'.repeat(32);
  await context.window.KerangkaHTTP.readJSON({json:async()=>({success:true,csrf_token:token,redirect:'/dashboard'})});
  assert.equal(inputs[0].value,token); assert.equal(meta.content,token); assert.equal(inputs[1].value,'keep');
  await assert.rejects(()=>context.window.KerangkaHTTP.readJSON({json:async()=>({redirect:'https://example.invalid/'})}),/pengalihan/);
  await assert.rejects(()=>context.window.KerangkaHTTP.readJSON({json:async()=>([])}),/tidak valid/);
});
