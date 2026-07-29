/**
 * Regenerates the WordPress.org screenshots in .wordpress-org/.
 *
 * Drives a real WordPress install through a real browser, because the shots have
 * to stay true: every number, every checkbox and every notice below is something
 * the plugin actually rendered, not a mockup that drifts from the code.
 *
 * The run stages the state it needs (see state.php), takes the shots, and puts
 * the site back - including the site language, which is forced to English while
 * it runs. A crash between staging and restoring leaves the test site staged;
 * `npm run restore` finishes the job.
 *
 * Shot 1 comes last on purpose. It needs both key fields empty, and the shots
 * before it need working keys - a real "Connected", a real badge on the login
 * form. Emptying the keys first would quietly hollow all of them out.
 *
 * Usage:
 *   npm run shoot            all seven
 *   npm run shoot -- 2 5     only those
 *
 * Environment:
 *   WP_PATH   the WordPress install with this plugin active
 *             (default: ../../../wp-test, next to the plugin checkout)
 *   WP_USER   ID of the administrator to open wp-admin as (default: 1)
 */

const { chromium } = require('playwright');
const { execFileSync } = require('child_process');
const fs = require('fs');
const path = require('path');

const OUT = path.resolve(__dirname, '../../.wordpress-org');
const WP_PATH = process.env.WP_PATH || path.resolve(__dirname, '../../../wp-test');

// A plugin developer's test site is almost always on a self-signed local
// certificate, so the browser is told not to argue about it.
const VIEWPORT = { width: 1280, height: 860 };
const SCALE = 1.5;

const only = process.argv.slice(2).filter((a) => /^[1-7]$/.test(a));
const wanted = (n) => only.length === 0 || only.includes(String(n));

function wp(args) {
  const phar = path.join(WP_PATH, 'wp-cli.phar');
  const [cmd, prefix] = fs.existsSync(phar) ? ['php', [phar]] : ['wp', []];

  return execFileSync(cmd, [...prefix, ...args, '--path=' + WP_PATH], {
    encoding: 'utf8',
    env: { ...process.env, CAPTCHAAPI_SHOT_USER: process.env.WP_USER || '1' },
  });
}

const state = (mode) => process.stdout.write(wp(['eval-file', path.join(__dirname, 'state.php'), mode]));

/** Strips the admin bar and every notice that is not ours, so the shot shows the plugin. */
async function tidy(page) {
  await page.addStyleTag({
    content: `
      #wpadminbar { display: none !important; }
      html.wp-toolbar { padding-top: 0 !important; }
      #adminmenuwrap, #adminmenuback { top: 0 !important; }
      #wpfooter, #screen-meta-links { display: none !important; }
    `,
  });

  await page.evaluate(() => {
    // WordPress moves admin notices into the .wrap of the current screen, so a
    // foreign nag ends up inside our settings page. Ownership is decided by the
    // notice itself, not by where it was reparented to.
    document
      .querySelectorAll('#wpbody-content [class*="notice"], #wpbody-content .update-nag')
      .forEach((el) => {
        const ours =
          el.classList.contains('captchaapi-status') || /captchaapi/i.test(el.textContent || '');

        if (!ours) {
          el.remove();
        }
      });
  });
}

/** Tags each settings section by its heading text so the shots can address them. */
async function markSections(page) {
  await page.evaluate(() => {
    const wanted = { 'Account keys': 'keys', 'Protected forms': 'forms', Behavior: 'behavior' };

    document.querySelectorAll('.captchaapi-settings h2.title').forEach((h2) => {
      const slug = wanted[(h2.textContent || '').trim()];

      if (!slug) {
        return;
      }

      h2.id = 'shot-' + slug + '-start';

      let next = h2.nextElementSibling;

      while (next && next.tagName !== 'TABLE') {
        next = next.nextElementSibling;
      }

      if (next) {
        next.id = 'shot-' + slug + '-end';
      }
    });
  });
}

/**
 * Scrolls a section to the top of the viewport and captures down to the end of
 * it, with the admin menu in frame the way a reader would see it.
 */
async function section(page, name, topSelector, bottomSelector) {
  const box = await page.evaluate(
    ([ts, bs]) => {
      const top = ts ? document.querySelector(ts) : null;
      const bottom = document.querySelector(bs);

      if (!bottom || (ts && !top)) {
        throw new Error('missing selector: ' + (top ? bs : ts));
      }

      window.scrollTo(0, top ? Math.max(0, top.getBoundingClientRect().top + window.scrollY - 56) : 0);

      // Cut halfway through the gap on either side, so no neighbouring heading
      // and no half a line of the section above survives at the edge of the
      // frame. The neighbours are found by geometry rather than by sibling
      // order: the line above a heading is often in a different parent entirely.
      const painted = Array.from(document.querySelectorAll('#wpbody-content *'))
        .map((el) => el.getBoundingClientRect())
        .filter((r) => r.height > 0 && r.width > 0);

      const topRect = top ? top.getBoundingClientRect() : null;
      const bottomRect = bottom.getBoundingClientRect();

      const above = painted
        .filter((r) => topRect && r.bottom <= topRect.top + 1)
        .reduce((max, r) => Math.max(max, r.bottom), 0);

      const below = painted
        .filter((r) => r.top >= bottomRect.bottom - 1)
        .reduce((min, r) => Math.min(min, r.top), Infinity);

      return {
        y: topRect ? Math.max(0, Math.round((above + topRect.top) / 2)) : 0,
        bottom: Math.ceil(below === Infinity ? bottomRect.bottom + 24 : (bottomRect.bottom + below) / 2),
      };
    },
    [topSelector, bottomSelector]
  );

  await page.waitForTimeout(250);

  const height = Math.min(VIEWPORT.height - box.y, box.bottom - box.y);

  await page.screenshot({
    path: path.join(OUT, name),
    clip: { x: 0, y: box.y, width: VIEWPORT.width, height },
  });

  console.log('  ' + name, VIEWPORT.width * SCALE + 'x' + Math.round(height * SCALE));
}

async function run() {
  const auth = JSON.parse(wp(['eval-file', path.join(__dirname, 'auth.php')]));
  const admin = auth.home + '/wp-admin/';

  const browser = await chromium.launch({ channel: 'chrome' });
  const context = await browser.newContext({
    ignoreHTTPSErrors: true,
    viewport: VIEWPORT,
    deviceScaleFactor: SCALE,
    colorScheme: 'light',
  });

  await context.addCookies(auth.cookies);
  const page = await context.newPage();

  try {
    if (wanted(2) || wanted(3) || wanted(5) || wanted(6)) {
      state('healthy');

      await page.goto(admin + 'options-general.php?page=captchaapi', { waitUntil: 'networkidle' });
      await tidy(page);
      await markSections(page);

      if (wanted(2)) {
        await section(page, 'screenshot-2.png', null, '.captchaapi-activity + .description');
      }

      if (wanted(5)) {
        await page.click('#captchaapi-test');
        await page.waitForFunction(
          () => {
            const el = document.querySelector('#captchaapi-test-result');

            return el && el.textContent.trim().length > 0 && !/testing/i.test(el.textContent);
          },
          { timeout: 30000 }
        );

        // The test ran against the real key; what ends up on wordpress.org must
        // not be one. Swapped only after the request, so the answer is genuine.
        await page.evaluate(() => {
          const key = document.querySelector('#captchaapi-site-key');

          if (key) {
            key.value = 'pk_live_7Q2mXk9vRt4LbN8sEwHc3Ay6JdPu5Zog';
          }
        });

        await section(page, 'screenshot-5.png', '#shot-keys-start', '#shot-keys-end');
      }

      if (wanted(3)) {
        await section(page, 'screenshot-3.png', '#shot-forms-start', '#shot-forms-end');
      }

      if (wanted(6)) {
        await section(page, 'screenshot-6.png', '#shot-behavior-start', '#shot-behavior-end');
      }
    }

    if (wanted(7)) {
      state('blocked');

      // Searching for the plugin keeps the frame on the notice and the one row
      // it is about, instead of every plugin the test site happens to have.
      await page.goto(admin + 'plugins.php?s=captchaapi&plugin_status=all', { waitUntil: 'networkidle' });
      await tidy(page);
      // The repeated column header under a single-row table only eats frame.
      await page.addStyleTag({ content: '.wp-list-table tfoot { display: none !important; }' });
      await section(page, 'screenshot-7.png', null, '#wpbody-content .wp-list-table');
    }

    if (wanted(4)) {
      const anon = await browser.newContext({
        ignoreHTTPSErrors: true,
        viewport: { width: 760, height: 720 },
        deviceScaleFactor: SCALE,
        colorScheme: 'light',
      });

      const login = await anon.newPage();

      await login.goto(auth.home + '/wp-login.php', { waitUntil: 'networkidle' });
      await login.addStyleTag({ content: '.language-switcher { display: none !important; }' });
      await login.waitForSelector('.captchaapi-badge [data-captcha-status]', { timeout: 30000 });

      // The widget stays on standby until the visitor starts filling the form,
      // so an untouched page would show the least interesting half of the badge.
      await login.fill('#user_login', 'jane');
      await login.fill('#user_pass', 'correct horse battery staple');
      await login.waitForFunction(
        () => {
          const el = document.querySelector('.captchaapi-badge [data-captcha-status]');

          return el && el.textContent.trim().length > 0 && !/standby/i.test(el.textContent);
        },
        { timeout: 30000 }
      );
      await login.waitForTimeout(600);

      const bottom = await login.evaluate(() => {
        const nav = document.querySelector('#backtoblog') || document.querySelector('#nav');

        return Math.ceil(nav.getBoundingClientRect().bottom + 40);
      });

      await login.screenshot({
        path: path.join(OUT, 'screenshot-4.png'),
        clip: { x: 0, y: 0, width: 760, height: bottom },
      });

      console.log('  screenshot-4.png', 760 * SCALE + 'x' + Math.round(bottom * SCALE));
      await anon.close();
    }

    // Last, because it takes the keys away: the shots above need a live account
    // behind them, and `restore` at the end of the run puts the keys back.
    if (wanted(1)) {
      state('fresh');

      await page.goto(admin + 'options-general.php?page=captchaapi', { waitUntil: 'networkidle' });
      await tidy(page);
      await markSections(page);
      await page.waitForSelector('#shot-keys-start', { timeout: 30000 });

      // The button names the hostname it would register, which on this machine
      // is a local dev domain. Same reasoning as the site key above: what the
      // directory shows must not be an artefact of the test site.
      await page.evaluate(() => {
        document.querySelectorAll('.captchaapi-settings code').forEach((el) => {
          if (/\.(test|local|localhost)(:\d+)?$/.test((el.textContent || '').trim())) {
            el.textContent = 'example.com';
          }
        });
      });

      await section(page, 'screenshot-1.png', '#shot-keys-start', '#shot-keys-end');
    }
  } finally {
    await browser.close();
  }
}

(async () => {
  // A run that died before its restore left the test site staged. This is how
  // it gets un-staged without taking the shots again.
  if (process.argv.includes('--restore')) {
    state('restore');

    return;
  }

  console.log('Staging ' + WP_PATH);
  state('backup');

  try {
    await run();
  } finally {
    state('restore');
  }
})().catch((err) => {
  console.error(err);
  process.exit(1);
});
