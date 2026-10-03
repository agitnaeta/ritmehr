// RV5-01 — cek mobile (<992px) + dark mode utk sampel list page kritis.
import { chromium } from 'playwright';
const BASE = 'http://127.0.0.1:8000';
const SAMPLE = ['user','salary','audit-log','presence','leave-request','department'];

async function login(page){
  await page.goto(`${BASE}/admin/login`);
  await page.fill('input[name="email"]','siti@demo.test');
  await page.fill('input[name="password"]','password');
  await Promise.all([page.waitForNavigation(), page.click('button[type="submit"]')]);
}

async function check(page, slug, label){
  await page.goto(`${BASE}/admin/${slug}`, { waitUntil:'domcontentloaded' });
  await page.waitForTimeout(1800);
  try { await page.waitForFunction(()=>document.querySelectorAll('#crudTable tbody tr').length>0||document.querySelector('.dataTables_empty'),{timeout:12000}); } catch {}
  await page.waitForTimeout(400);
  const m = await page.evaluate(()=>{
    const docW=document.documentElement.clientWidth;
    const search=document.querySelector('#datatable_search_stack input, input[type="search"]');
    const btn=document.querySelector('[bp-section="page-header-actions"], .um-header-actions, a.btn[href$="/create"]');
    const bb=el=>{if(!el)return null;const b=el.getBoundingClientRect();return{x:b.x,y:b.y,r:b.right,btm:b.bottom,w:b.width,h:b.height};};
    const sb=bb(search),ab=bb(btn);
    let overlap=null;
    if(sb&&ab) overlap=!(sb.r<=ab.x||ab.r<=sb.x||sb.btm<=ab.y||ab.btm<=sb.y);
    return {
      horizOverflow: document.documentElement.scrollWidth>docW+2,
      scrollW: document.documentElement.scrollWidth, clientW: docW,
      searchBtnOverlap: overlap, hasSearch: !!search, hasBtn: !!btn,
    };
  });
  const iss=[];
  if(m.horizOverflow) iss.push(`HORIZ-OVERFLOW ${m.scrollW}>${m.clientW}`);
  if(m.searchBtnOverlap===true) iss.push('OVERLAP search×tombol');
  console.log(`${iss.length?'ISSUE':'OK   '} [${label}] ${slug.padEnd(16)} sw=${m.scrollW} cw=${m.clientW}${iss.length?' :: '+iss.join(' | '):''}`);
  return {slug,label,...m,issues:iss};
}

(async()=>{
  const browser=await chromium.launch();
  const report=[];

  // MOBILE 390px
  console.log('=== MOBILE 390px ===');
  const mctx=await browser.newContext({viewport:{width:390,height:844},isMobile:true});
  const mp=await mctx.newPage(); mp.setDefaultTimeout(60000); mp.setDefaultNavigationTimeout(60000);
  await login(mp);
  for(const s of SAMPLE) report.push(await check(mp,s,'mobile'));
  await mctx.close();

  // DARK MODE 1280px — set data-bs-theme=dark via localStorage + attribute
  console.log('\n=== DARK MODE 1280px ===');
  const dctx=await browser.newContext({viewport:{width:1280,height:900},colorScheme:'dark'});
  const dp=await dctx.newPage(); dp.setDefaultTimeout(60000); dp.setDefaultNavigationTimeout(60000);
  await login(dp);
  // paksa dark theme Tabler/Backpack
  await dp.addInitScript(()=>{ try{ localStorage.setItem('theme','dark'); localStorage.setItem('backpack-dark-mode','1'); }catch(e){} });
  for(const s of SAMPLE){
    await dp.goto(`${BASE}/admin/${s}`,{waitUntil:'domcontentloaded'});
    await dp.evaluate(()=>{document.documentElement.setAttribute('data-bs-theme','dark');document.body.setAttribute('data-bs-theme','dark');});
    report.push(await check(dp,s,'dark'));
  }
  await dctx.close();

  console.log('\n'+'='.repeat(70));
  const bad=report.filter(r=>r.issues.length);
  console.log(`SAMPLE ${report.length} :: ISSUE ${bad.length}`);
  for(const r of bad) console.log(`  [${r.label}] ${r.slug}: ${r.issues.join(' | ')}`);
  await browser.close();
})();
