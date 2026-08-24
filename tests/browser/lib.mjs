// Shared helpers for the browser-driven CRUD suites.
import { chromium } from 'playwright';

export const BASE = 'http://127.0.0.1:8000';

export const results = [];
export function record(module, id, status, note) {
  results.push({ module, id, status, note });
  const tag = { PASS: 'PASS', FAIL: 'FAIL', SKIP: 'SKIP' }[status] || status;
  console.log(`${tag.padEnd(4)} ${id.padEnd(14)} ${note}`);
}

export async function launch() {
  return chromium.launch();
}

export async function session(browser, email) {
  const ctx = await browser.newContext();
  const page = await ctx.newPage();
  // `php artisan serve` melayani satu request pada satu waktu; aset statis bisa
  // memakan beberapa detik saat halaman admin dibuka beruntun.
  page.setDefaultNavigationTimeout(120000);
  page.setDefaultTimeout(120000);
  await page.goto(`${BASE}/admin/login`);
  await page.fill('input[name="email"]', email);
  await page.fill('input[name="password"]', 'password');
  await Promise.all([page.waitForNavigation(), page.click('button[type="submit"]')]);
  return { ctx, page };
}

// CSRF token straight off a rendered page.
export async function token(page, url) {
  const resp = await page.goto(url);
  if (!resp.ok()) return { status: resp.status(), token: null };
  const t = await page.evaluate(() =>
    document.querySelector('input[name="_token"]')?.value ||
    document.querySelector('meta[name="csrf-token"]')?.content || null);
  return { status: resp.status(), token: t };
}

// Backpack list rows arrive over AJAX into #crudTable.
export async function rowCount(page, entity) {
  await page.goto(`${BASE}/admin/${entity}`);
  try {
    await page.waitForFunction(
      () => document.querySelectorAll('#crudTable tbody tr').length > 0, { timeout: 20000 });
  } catch { return { rows: -1, info: '' }; }
  return page.evaluate(() => {
    const rows = [...document.querySelectorAll('#crudTable tbody tr')];
    const info = document.querySelector('.dataTables_info')?.innerText.replace(/\s+/g, ' ').trim() || '';
    const empty = rows.length === 1 && /no data|tidak ada|empty/i.test(rows[0].innerText);
    return { rows: empty ? 0 : rows.length, info };
  });
}

// Total from the DataTables info line — survives pagination.
export function totalFromInfo(info) {
  const m = info.match(/dari\s+([\d.,]+)\s+masukan/i) || info.match(/of\s+([\d.,]+)\s+entries/i);
  return m ? parseInt(m[1].replace(/[.,]/g, ''), 10) : null;
}

// Form POST through the page's own cookie jar.
export async function post(page, url, data) {
  const body = new URLSearchParams(data).toString();
  const r = await page.request.post(url, {
    headers: { 'content-type': 'application/x-www-form-urlencoded' },
    data: body,
    maxRedirects: 0,
    failOnStatusCode: false,
  });
  return { status: r.status(), location: (r.headers()['location'] || '').replace(BASE, ''), body: await r.text().catch(() => '') };
}

export async function del(page, url, csrf) {
  const r = await page.request.delete(url, {
    headers: { 'x-csrf-token': csrf, 'x-requested-with': 'XMLHttpRequest' },
    maxRedirects: 0,
    failOnStatusCode: false,
  });
  return { status: r.status(), body: await r.text().catch(() => '') };
}

export function titleOf(html) {
  const m = html.match(/<title>([^<]*)<\/title>/);
  return m ? m[1].replace(/&#039;/g, "'").replace(/&quot;/g, '"').slice(0, 110) : '';
}

// Classify a create/update POST outcome.
export function classify(res, entity) {
  if (res.status === 500) return { kind: '500', detail: titleOf(res.body) };
  if (res.status === 302) {
    if (res.location.includes('/create') || res.location.includes('/edit')) return { kind: 'rejected', detail: res.location };
    if (res.location.includes('/login')) return { kind: 'unauth', detail: res.location };
    return { kind: 'saved', detail: res.location };
  }
  if (res.status === 403) return { kind: 'forbidden', detail: '403' };
  if (res.status === 404) return { kind: 'notfound', detail: '404' };
  return { kind: String(res.status), detail: res.location || '' };
}

export function summary() {
  const byModule = {};
  for (const r of results) (byModule[r.module] ||= []).push(r);
  const fails = results.filter(r => r.status === 'FAIL');
  console.log(`\n${'='.repeat(78)}`);
  console.log(`${results.filter(r => r.status === 'PASS').length} PASS / ${fails.length} FAIL / ${results.filter(r => r.status === 'SKIP').length} SKIP`);
  console.log('='.repeat(78));
  for (const [m, rs] of Object.entries(byModule)) {
    const f = rs.filter(r => r.status === 'FAIL').length;
    console.log(`  ${m.padEnd(24)} ${rs.length - f}/${rs.length} lulus${f ? `  ← ${f} GAGAL` : ''}`);
  }
  if (fails.length) {
    console.log(`\nKEGAGALAN:`);
    for (const f of fails) console.log(`  [${f.module}] ${f.id} — ${f.note}`);
  }
  return { results, byModule, fails };
}
