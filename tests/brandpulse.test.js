'use strict';

const assert = require('node:assert/strict');
const { readFileSync } = require('node:fs');
const { test } = require('node:test');
const vm = require('node:vm');

// Exercise the shipped closure with a small browser fixture; no browser or
// third-party DOM dependency is required for refresh and storage regressions.
const source = readFileSync('public/js/brandpulse.js', 'utf8').replace(
  '  const boot = () => {',
  '  globalThis.actions = { loadCounters, setupCounterRefresh, requestCounterRefresh, setupAssetManagementLink, boot: () => boot() };\n  const boot = () => {'
);

function browser(fetch, { hidden = false, storage = null } = {}) {
  const timers = [];
  let scans = 0;
  let observer;
  const events = new Map();
  const ajaxEvents = new Map();
  const requests = [];
  const context = {
    document: {
      hidden, readyState: 'loading', contentType: 'text/html',
      documentElement: { nodeName: 'HTML' },
      currentScript: { src: 'https://example.test/glpi/plugins/brandpulse/js/brandpulse.js' },
      addEventListener(name, callback) { events.set(name, callback); },
      getElementById() { return null; },
      querySelector() { return null; },
      querySelectorAll() { scans += 1; return []; },
      body: { classList: { toggle() {} } },
    },
    window: {
      CFG_GLPI: { root_doc: '/glpi' }, localStorage: storage,
      location: { hash: '', href: 'https://example.test/glpi/front/ticket.php' }, console: { debug() {} },
      addEventListener(name, callback) { events.set(name, callback); },
      jQuery() { return { on(name, callback) { ajaxEvents.set(name, callback); } }; },
      setTimeout(callback, delay) { const timer = { callback, delay }; timers.push(timer); return timer; },
      clearTimeout(timer) { const index = timers.indexOf(timer); if (index >= 0) timers.splice(index, 1); },
      requestAnimationFrame(callback) { callback(); },
    },
    MutationObserver: class {
      constructor(callback) { observer = callback; }
      observe() {}
    },
    fetch: (...args) => { requests.push(args); return fetch(...args); },
    URLSearchParams, URL, AbortController,
  };
  vm.createContext(context);
  vm.runInContext(source, context);
  return {
    context, timers, requests, events, ajaxEvents,
    scans: () => scans, mutate: (changes) => observer(changes),
    async runTimer(delay) {
      const timer = timers.find((entry) => entry.delay === delay);
      assert.ok(timer, 'expected timer ' + delay);
      context.window.clearTimeout(timer);
      await timer.callback();
    },
  };
}

test('counter refresh survives network errors and HTTP failures', async () => {
  for (const fetch of [async () => { throw new Error('offline'); }, async () => ({ ok: false })]) {
    const fixture = browser(fetch);
    await fixture.context.actions.loadCounters();
    assert.equal(fixture.timers.length, 1);
    assert.ok(fixture.timers[0].delay >= 54000 && fixture.timers[0].delay <= 66000);
  }
});

test('hidden tabs defer requests and retain the refresh timer', async () => {
  let requests = 0;
  const fixture = browser(async () => { requests += 1; }, { hidden: true });
  await fixture.context.actions.loadCounters();
  assert.equal(requests, 0);
  assert.equal(fixture.timers.length, 1);
});

test('successful responses use the configured interval', async () => {
  const fixture = browser(async () => ({
    ok: true, json: async () => ({ enabled: false, refresh_interval: 30 }),
  }));
  await fixture.context.actions.loadCounters();
  assert.equal(fixture.timers.length, 1);
  assert.ok(fixture.timers[0].delay >= 27000 && fixture.timers[0].delay <= 33000);
});

test('blocked browser storage does not stop startup', () => {
  let requests = 0;
  const fixture = browser(async () => { requests += 1; return { ok: false }; }, {
    storage: {
      removeItem() { throw new Error('storage denied'); },
      getItem() { throw new Error('storage denied'); },
    },
  });
  assert.doesNotThrow(() => fixture.context.actions.boot());
  assert.equal(requests, 2);
});

test('asset observer ignores unrelated DOM additions', () => {
  const fixture = browser(async () => ({ ok: false }));
  fixture.context.actions.setupAssetManagementLink();
  assert.equal(fixture.scans(), 1);
  fixture.mutate([{ addedNodes: [{ nodeType: 1, matches: () => false, querySelector: () => null }] }]);
  assert.equal(fixture.scans(), 1);
  fixture.mutate([{ addedNodes: [{ nodeType: 1, matches: () => true }] }]);
  assert.equal(fixture.scans(), 2);
});


const disabledResponse = () => ({
  ok: true, json: async () => ({ enabled: false, refresh_interval: 30 }),
});

test('page entry starts counters even before a visible header is found', async () => {
  const fixture = browser(async () => disabledResponse());
  fixture.context.actions.boot();
  assert.ok(fixture.requests.some(([url]) => url.endsWith('/ajax/counters.php?fresh=1')));
});

test('visibility, focus and restored history request current counters', async () => {
  const fixture = browser(async () => disabledResponse());
  fixture.context.actions.setupCounterRefresh();
  fixture.events.get('visibilitychange')();
  fixture.events.get('focus')();
  fixture.events.get('pageshow')({ persisted: true });
  assert.equal(fixture.timers.length, 1);
  await fixture.runTimer(200);
  assert.ok(fixture.requests[0][0].endsWith('?fresh=1'));
  assert.equal(fixture.requests[0][1].cache, 'no-store');
});

test('native GLPI save and AJAX write events coalesce into a fresh request', async () => {
  const fixture = browser(async () => disabledResponse());
  fixture.context.actions.setupCounterRefresh();
  const ajax = fixture.ajaxEvents.get('ajaxSuccess.brandpulse');
  ajax(null, null, { type: 'GET', url: '/glpi/ajax/getDropdownValue.php' });
  ajax(null, null, { type: 'POST', url: 'https://other.test/update' });
  ajax(null, null, { type: 'POST', url: '/another-glpi/update' });
  assert.equal(fixture.timers.length, 0);
  fixture.ajaxEvents.get('glpi-ajax-controller-submit-success.brandpulse')();
  ajax(null, null, { type: 'POST', url: '/glpi/GenericAjaxCrud' });
  assert.equal(fixture.timers.length, 1);
  await fixture.runTimer(200);
  assert.equal(fixture.requests.length, 1);
  assert.ok(fixture.requests[0][0].endsWith('?fresh=1'));
});

test('a save during an active poll queues one fresh request without overlap', async () => {
  let finish;
  let calls = 0;
  const fixture = browser(async () => {
    calls += 1;
    if (calls === 1) return new Promise((resolve) => { finish = resolve; });
    return disabledResponse();
  });
  const polling = fixture.context.actions.loadCounters();
  await fixture.context.actions.loadCounters();
  fixture.context.actions.requestCounterRefresh();
  fixture.context.actions.requestCounterRefresh();
  assert.equal(calls, 1);
  finish(disabledResponse());
  await polling;
  await new Promise((resolve) => setImmediate(resolve));
  assert.equal(calls, 2);
  assert.ok(fixture.requests[1][0].endsWith('?fresh=1'));
  assert.equal(fixture.timers.length, 1);
});

test('a stuck request is aborted and polling resumes at its configured interval', async () => {
  let fail = false;
  const fixture = browser(async (url, options) => {
    if (!fail) return disabledResponse();
    return new Promise((resolve, reject) => {
      options.signal.addEventListener('abort', () => reject(new Error('timeout')));
    });
  });
  await fixture.context.actions.loadCounters();
  fail = true;
  const polling = fixture.context.actions.loadCounters();
  await fixture.runTimer(20000);
  await polling;
  assert.equal(fixture.timers.length, 1);
  assert.ok(fixture.timers[0].delay >= 27000 && fixture.timers[0].delay <= 33000);
});
