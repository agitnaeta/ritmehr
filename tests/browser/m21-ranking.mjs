// M21 — Ranking view: kandidat 1 lowongan tampil terurut 1..N by skor AI,
// nomor peringkat + medali, header statistik, klik Detail → drawer alasan.
// Run: node tests/browser/m21-ranking.mjs   (needs `php artisan serve` on :8000)
import { chromium } from 'playwright';
import { execSync } from 'node:child_process';

const BASE = 'http://127.0.0.1:8000';
const results = [];
const pass = (id, n) => { results.push(['PASS', id, n]); console.log(`PASS ${id} — ${n}`); };
const fail = (id, n) => { results.push(['FAIL', id, n]); console.log(`FAIL ${id} — ${n}`); };
const stamp = Date.now();
const seed = (action, ...args) =>
  execSync(`php tests/browser/_m21_helper.php ${action} ${args.join(' ')}`, { cwd: process.cwd() })
    .toString().trim().split('\n').pop().trim();

const openingId = seed('seed_opening', stamp);
// Seed a mixed field (deliberately out of insertion order):
const budi = seed('seed_scored', openingId, 'Budi', 91, 80);   // rank 1
const andi = seed('seed_scored', openingId, 'Andi', 82, 70);   // rank 3
const sari = seed('seed_scored', openingId, 'Sari', 87, 60);   // rank 2
const eko  = seed('seed_scored', openingId, 'Eko', 'null', 54); // rank 4 (vector only)
const zul  = seed('seed_scored', openingId, 'Zulfa', 'null', 'null'); // rank 5

const browser = await chromium.launch();
const ac = await browser.newContext();
const ap = await ac.newPage(); ap.setDefaultTimeout(60000);
const jsErr = [];
ap.on('pageerror', e => jsErr.push(e.message));

await ap.goto(`${BASE}/admin/login`);
await ap.fill('input[name="email"]', 'siti@demo.test');
await ap.fill('input[name="password"]', 'password');
await Promise.all([ap.waitForNavigation(), ap.click('button[type="submit"]')]);

await ap.goto(`${BASE}/admin/recruitment/ranking?job_opening_id=${openingId}`);
await ap.waitForTimeout(1000);

// TC-RANK-01 — page loads with the ranking table.
const hasTable = await ap.locator('table tbody tr').count();
hasTable >= 5 ? pass('TC-RANK-01', `tabel peringkat termuat (${hasTable} baris)`)
              : fail('TC-RANK-01', `baris kurang: ${hasTable}`);

// TC-RANK-02 — rows ordered by score descending: Budi(91), Sari(87), Andi(82), Eko(~54), Zulfa.
const names = await ap.$$eval('table tbody tr td:nth-child(2) .fw-semibold',
  els => els.map(e => e.textContent.trim()));
const expected = ['Budi', 'Sari', 'Andi', 'Eko', 'Zulfa'];
JSON.stringify(names.slice(0, 5)) === JSON.stringify(expected)
  ? pass('TC-RANK-02', `urutan 1..N benar: ${names.slice(0,5).join(' > ')}`)
  : fail('TC-RANK-02', `urutan salah: ${names.slice(0,5).join(' > ')} (harusnya ${expected.join(' > ')})`);

// TC-RANK-03 — rank column shows 1..N (medal for top-3, number after).
const firstRank = await ap.locator('table tbody tr:first-child td:first-child').innerText();
firstRank.includes('🥇')
  ? pass('TC-RANK-03', 'peringkat #1 = medali emas')
  : fail('TC-RANK-03', `kolom # pertama: "${firstRank}"`);

// TC-RANK-04 — top score in stat header = 91.
const topScore = await ap.locator('.card .h3').nth(3).innerText();
topScore.trim() === '91'
  ? pass('TC-RANK-04', 'stat "skor tertinggi" = 91')
  : fail('TC-RANK-04', `skor tertinggi: "${topScore}"`);

// TC-RANK-05 — "sudah dinilai AI" count = 3.
const aiCount = await ap.locator('.card .h3').nth(1).innerText();
aiCount.trim() === '3'
  ? pass('TC-RANK-05', 'stat "dinilai AI" = 3')
  : fail('TC-RANK-05', `dinilai AI: "${aiCount}"`);

// TC-RANK-06 — unscored candidate flagged "belum dinilai AI".
const ekoRow = ap.locator(`tr:has(td:nth-child(2):has-text("Eko"))`);
const ekoText = await ekoRow.innerText();
ekoText.includes('belum dinilai')
  ? pass('TC-RANK-06', 'kandidat vektor-only ditandai "belum dinilai AI"')
  : fail('TC-RANK-06', 'penanda belum dinilai tak ada');

// TC-RANK-07 — toggle sort by Nama re-orders alphabetically.
await ap.goto(`${BASE}/admin/recruitment/ranking?job_opening_id=${openingId}&order_by=name`);
await ap.waitForTimeout(800);
const byName = await ap.$$eval('table tbody tr td:nth-child(2) .fw-semibold',
  els => els.map(e => e.textContent.trim()));
JSON.stringify(byName.slice(0, 5)) === JSON.stringify(['Andi', 'Budi', 'Eko', 'Sari', 'Zulfa'])
  ? pass('TC-RANK-07', `toggle urut Nama jalan: ${byName.slice(0,5).join(', ')}`)
  : fail('TC-RANK-07', `urut nama salah: ${byName.slice(0,5).join(', ')}`);

// TC-RANK-08 — click Detail on Budi → drawer opens with AI reasoning + rank badge.
await ap.goto(`${BASE}/admin/recruitment/ranking?job_opening_id=${openingId}`);
await ap.waitForTimeout(800);
await ap.locator(`.btn-detail[data-id="${budi}"]`).click();
await ap.waitForTimeout(1500);
const drawerOpen = await ap.locator('#applicantDrawer.show').count();
drawerOpen ? pass('TC-RANK-08', 'drawer terbuka dari tabel peringkat')
           : fail('TC-RANK-08', 'drawer tidak terbuka');

// TC-RANK-09 — drawer shows per-criteria reasoning (Automation Testing).
const critText = await ap.locator('#drawer-ai-criteria').innerText();
critText.includes('Automation Testing')
  ? pass('TC-RANK-09', 'drawer menampilkan rincian kriteria + alasan')
  : fail('TC-RANK-09', 'rincian kriteria tak tampil di drawer');

// TC-RANK-10 — drawer rank badge shows #1 dari 5.
const rankBadge = await ap.locator('#drawer-ai-rank').innerText().catch(() => '');
rankBadge.includes('#1')
  ? pass('TC-RANK-10', `badge peringkat di drawer: "${rankBadge}"`)
  : fail('TC-RANK-10', `badge peringkat salah: "${rankBadge}"`);

if (jsErr.length) fail('TC-RANK-JS', 'JS error: ' + jsErr.join(' | '));
else pass('TC-RANK-JS', 'tidak ada JS error');

await ac.close();
await browser.close();
seed('cleanup', openingId, stamp);

const failed = results.filter(r => r[0] === 'FAIL');
console.log(`\n${'='.repeat(70)}\n${results.length - failed.length} PASS / ${failed.length} FAIL\n${'='.repeat(70)}`);
failed.forEach(([, id, n]) => console.log(`  FAIL ${id} — ${n}`));
process.exit(failed.length ? 1 : 0);
