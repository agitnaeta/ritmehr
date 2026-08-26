// M18-1 quick live check — CV stream returns PDF inline for an authorized admin.
import { chromium } from 'playwright';
import { execSync } from 'node:child_process';
import path from 'node:path';
const BASE = 'http://127.0.0.1:8000';
const CV = path.resolve('tests/browser/fixtures/dummy-cv.pdf');
const stamp = Date.now();

// seed opening + candidate + application with a real CV via career portal apply
const seed = execSync(`php artisan tinker --execute="\\$o=App\\Models\\JobOpening::create(['title'=>'CVStream Role ${stamp}','vacancies'=>1,'status'=>'open','is_published'=>true,'published_at'=>now()]); echo \\$o->id.chr(124).\\$o->slug;"`, { cwd: process.cwd() }).toString().trim().split('\n').pop().trim();
const [openingId, slug] = seed.split('|');

const b = await chromium.launch();
// candidate applies (real CV upload → cv_path set)
const cc = await b.newContext();
const cp = await cc.newPage(); cp.setDefaultTimeout(60000);
await cp.goto(`${BASE}/karir/daftar`);
await cp.fill('input[name="name"]','CV Streamer');
await cp.fill('input[name="email"]',`cvs${stamp}@ex.test`);
await cp.fill('input[name="password"]','password123');
await cp.fill('input[name="password_confirmation"]','password123');
await Promise.all([cp.waitForNavigation(), cp.click('#btn-register')]);
await cp.goto(`${BASE}/karir/lowongan/${slug}`);
await cp.setInputFiles('input[name="cv"]', CV);
await Promise.all([cp.waitForNavigation(), cp.click('#btn-submit-apply')]);
await cc.close();

const appId = execSync(`php artisan tinker --execute="echo App\\Models\\Applicant::where('job_opening_id',${openingId})->value('id');"`, { cwd: process.cwd() }).toString().trim().split('\n').pop().trim();

// admin logs in, fetches the CV stream URL
const ac = await b.newContext();
const ap = await ac.newPage(); ap.setDefaultTimeout(60000);
await ap.goto(`${BASE}/admin/login`);
await ap.fill('input[name="email"]','siti@demo.test');
await ap.fill('input[name="password"]','password');
await Promise.all([ap.waitForNavigation(), ap.click('button[type="submit"]')]);
const resp = await ap.request.get(`${BASE}/admin/recruitment/applicant/${appId}/cv`);
const ct = resp.headers()['content-type'] || '';
const cd = resp.headers()['content-disposition'] || '';
console.log(`status=${resp.status()} content-type=${ct} disposition=${cd}`);
const ok = resp.status() === 200 && ct.includes('pdf') && cd.includes('inline');
await ac.close();
await b.close();

execSync(`php artisan tinker --execute="App\\Models\\Applicant::where('job_opening_id',${openingId})->delete(); App\\Models\\Candidate::where('email','cvs${stamp}@ex.test')->delete(); App\\Models\\JobOpening::where('id',${openingId})->delete();"`, { cwd: process.cwd() });

console.log(ok ? 'PASS M18-1 live: CV streamed inline as PDF' : 'FAIL M18-1 live');
process.exit(ok ? 0 : 1);
