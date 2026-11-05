/*
 * Browser checks for layout and accessibility basics.
 *
 *   npm install puppeteer          (once, anywhere - e.g. in this folder)
 *   node tests/browser/ui-checks.js http://127.0.0.1:8080
 *
 * Requires a running app with the demo data (php bin/install-db.php --seed) and the demo accounts' password
 * changed to the default, or set DEMO_PASSWORD. Set CHROME_PATH to use an existing Chrome/Chromium instead of
 * Puppeteer's download.
 *
 * Checks per page and role: no horizontal scrolling at 390 / 768 / 1280 px, every form control has an accessible
 * name, every image has alt text, exactly one <h1>, the page has a title and lang, buttons and links have a name,
 * and the first Tab press lands on the visible "Skip to main content" link.
 */
const base = process.argv[2] || 'http://127.0.0.1:8080';
const password = process.env.DEMO_PASSWORD || 'ChangeMe!2026';

async function launch() {
  let puppeteer;
  try { puppeteer = require('puppeteer'); } catch (e) { puppeteer = require('puppeteer-core'); }
  const options = { headless: 'new', args: ['--no-sandbox'] };
  if (process.env.CHROME_PATH) options.executablePath = process.env.CHROME_PATH;
  else {
    try { const chromium = require('@sparticuz/chromium'); options.executablePath = await chromium.executablePath(); options.args = [...chromium.args, '--no-sandbox']; options.headless = 'shell'; } catch (e) { /* use puppeteer's own browser */ }
  }
  return puppeteer.launch(options);
}

const roles = {
  'admin@demo-clinic.test': ['/dashboard', '/patients', '/patients/1', '/appointments', '/appointments/create', '/queue', '/followups', '/doctors', '/doctors/1', '/invoices', '/invoices/1', '/payments', '/reports/financial', '/reports/operational', '/users', '/users/create', '/settings', '/audit', '/patients/create', '/specialties', '/services'],
  'reception@demo-clinic.test': ['/dashboard', '/patients/create', '/appointments/walk-in', '/queue'],
  'accounts@demo-clinic.test': ['/dashboard', '/invoices/create?patient_id=1', '/invoices/1'],
  'ayesha@demo-clinic.test': ['/dashboard', '/queue', '/followups/create'],
};

const inPage = () => {
  const problems = [];
  const name = (el) => {
    if (el.getAttribute('aria-label') || el.getAttribute('aria-labelledby') || el.getAttribute('title')) return true;
    if (el.id && document.querySelector(`label[for="${CSS.escape(el.id)}"]`)) return true;
    if (el.closest('label')) return true;
    return false;
  };
  document.querySelectorAll('input, select, textarea').forEach((el) => {
    const type = (el.getAttribute('type') || '').toLowerCase();
    if (['hidden', 'submit', 'button'].includes(type)) return;
    if (!name(el)) problems.push('form control without a label: ' + el.outerHTML.slice(0, 90));
  });
  document.querySelectorAll('img').forEach((el) => { if (!el.hasAttribute('alt')) problems.push('img without alt: ' + el.src); });
  document.querySelectorAll('button, a[href]').forEach((el) => {
    const text = (el.textContent || '').trim() || el.getAttribute('aria-label') || el.getAttribute('title');
    if (!text) problems.push('control without a name: ' + el.outerHTML.slice(0, 90));
  });
  const h1 = document.querySelectorAll('h1').length;
  if (h1 !== 1) problems.push(`expected one <h1>, found ${h1}`);
  if (!document.title.trim()) problems.push('missing <title>');
  if (!document.documentElement.lang) problems.push('missing lang attribute');
  return problems;
};

(async () => {
  const browser = await launch();
  let failures = 0;
  for (const [email, pages] of Object.entries(roles)) {
    const page = await browser.newPage();
    await page.emulateMediaFeatures([{ name: 'prefers-reduced-motion', value: 'reduce' }]);
    await page.setViewport({ width: 1280, height: 900 });
    await page.goto(base + '/login', { waitUntil: 'networkidle0' });
    // keyboard: the very first Tab stop must be the skip link on app pages; on login it is the email field
    await page.type('#email', email);
    await page.type('#password', password);
    await Promise.all([page.waitForNavigation({ waitUntil: 'networkidle0' }), page.keyboard.press('Enter')]);
    if (page.url().includes('/account/password')) { console.log(`SKIP ${email}: password change pending - change it first or reseed`); await page.close(); continue; }
    for (const path of pages) {
      for (const width of [390, 768, 1280]) {
        await page.setViewport({ width, height: 900 });
        await page.goto(base + path, { waitUntil: 'networkidle0' });
        const overflow = await page.evaluate(() => document.documentElement.scrollWidth - window.innerWidth);
        if (overflow > 1) { failures++; console.log(`FAIL ${email} ${path} @${width}px: horizontal overflow of ${overflow}px`); }
      }
      await page.setViewport({ width: 1280, height: 900 });
      await page.goto(base + path, { waitUntil: 'networkidle0' });
      for (const problem of await page.evaluate(inPage)) { failures++; console.log(`FAIL ${email} ${path}: ${problem}`); }
      await page.bringToFront();   // headless pages need window focus for :focus styles to apply
      await page.evaluate(() => document.activeElement && document.activeElement.blur());
      await page.keyboard.press('Tab');
      await new Promise((r) => setTimeout(r, 50));
      const focus = await page.evaluate(() => { const a = document.activeElement; const r = a.getBoundingClientRect(); return { text: (a.textContent || '').trim().slice(0, 30), visible: r.left >= 0 && r.top >= 0, rect: `${Math.round(r.left)},${Math.round(r.top)}` }; });
      if (!/Skip to main content/.test(focus.text) || !focus.visible) { failures++; console.log(`FAIL ${email} ${path}: first Tab stop is "${focus.text}" (expected the visible skip link), position ${focus.rect}`); }
    }
    await page.deleteCookie(...(await page.cookies()));   // sign this role out before testing the next one
    await page.close();
    console.log(`checked ${pages.length} pages x 3 widths as ${email}`);
  }
  await browser.close();
  console.log(failures === 0 ? 'UI CHECKS PASSED' : `UI CHECKS FAILED: ${failures} problem(s)`);
  process.exit(failures === 0 ? 0 : 1);
})().catch((e) => { console.error(e); process.exit(2); });
