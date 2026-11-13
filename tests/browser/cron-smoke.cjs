// 在已安装的隔离测试站点上运行；仅使用专用管理员与 it_ui_probe 本地任务。
// TCS_UI_URL / TCS_UI_USER / TCS_UI_PASSWORD / TCS_PLAYWRIGHT_MODULE 必须由测试环境提供。
const assert = require('node:assert/strict');
const path = require('node:path');
const fs = require('node:fs');
const { chromium } = require(process.env.TCS_PLAYWRIGHT_MODULE || 'playwright');

(async () => {
    const base = process.env.TCS_UI_URL;
    assert(base && process.env.TCS_UI_USER && process.env.TCS_UI_PASSWORD, '需要专用测试站点与账号');
    assert(['127.0.0.1', 'localhost', '[::1]'].includes(new URL(base).hostname), '仅允许本地隔离测试站点');
    const browser = await chromium.launch({ headless: true, channel: 'msedge' });
    const page = await browser.newPage({ viewport: { width: 1440, height: 1000 } });
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    const results = [];
    const check = (name, condition) => { assert(condition, name); results.push(name); };
    try {
        await page.goto(base + '/index.php?mod=login');
        await page.waitForLoadState('networkidle');
        await page.locator('#login-user').fill(process.env.TCS_UI_USER);
        await page.locator('#login-password').fill(process.env.TCS_UI_PASSWORD);
        await page.getByRole('button', { name: '进入工作台', exact: true }).click();
        await page.waitForLoadState('networkidle');
        await page.goto(base + '/index.php?mod=admin:cron');
        await page.waitForLoadState('networkidle');
        await page.locator('#cron-search').waitFor();
        const tasks = page.locator('[data-cron-task]');
        check('真实管理员登录与任务列表', await tasks.count() > 0);
        await page.locator('#cron-search').fill('it_ui_probe');
        check('任务文本搜索', await page.locator('[data-cron-task]:visible').count() === 1);
        await page.locator('#cron-search').fill('不存在的任务');
        check('搜索空状态', await page.locator('#cron-empty').isVisible());
        await page.locator('#cron-search').fill('');
        await page.locator('#cron-filter').selectOption('disabled');
        check('禁用状态筛选', await page.locator('[data-cron-task]:visible').count() > 0 && await page.locator('[data-cron-task][data-enabled="1"]:visible').count() === 0);
        await page.locator('#cron-filter').selectOption('all');
        await page.getByRole('button', { name: '运行 it_ui_probe', exact: true }).click();
        await page.waitForLoadState('networkidle');
        check('真实 POST 运行与成功反馈', await page.getByRole('status').filter({ hasText: '计划任务操作已完成' }).count() === 1);
        const probe = page.locator('[data-cron-task]', { has: page.locator('strong', { hasText: 'it_ui_probe' }) });
        check('执行状态更新', await probe.getByText('上次成功', { exact: true }).count() === 1);
        await probe.getByRole('button', { name: '禁用', exact: true }).click();
        await page.waitForLoadState('networkidle');
        check('禁用写入与暂停反馈', await probe.getByText('已暂停', { exact: true }).count() === 1);
        await probe.getByRole('button', { name: '启用', exact: true }).click();
        await page.waitForLoadState('networkidle');
        check('启用写入', await probe.getByText('已启用', { exact: true }).count() === 1);
        await probe.locator('.cron-order-input').fill('77');
        await page.getByRole('button', { name: '保存运行顺序', exact: true }).click();
        await page.waitForLoadState('networkidle');
        check('顺序保存与读回', await probe.locator('.cron-order-input').inputValue() === '77');
        await page.locator('#history-task').fill('it_ui_probe');
        await page.locator('#history-status').selectOption('success');
        await page.getByRole('button', { name: '查询历史', exact: true }).click();
        await page.waitForLoadState('networkidle');
        check('后端历史筛选', await page.locator('.cron-history-table tbody tr').count() === 20);
        await page.getByRole('link', { name: '下一页', exact: true }).click();
        await page.waitForLoadState('networkidle');
        check('分页保留筛选条件', page.url().includes('task=it_ui_probe') && page.url().includes('page=2') && await page.locator('.cron-history-table tbody tr').count() >= 1);
        const token = await page.locator('#cron-action-form input[name="csrf_token"]').inputValue();
        const rejectedGet = await page.request.get(base + '/setting.php?mod=admin:cron&dis=it_ui_probe&csrf_token=' + encodeURIComponent(token));
        check('GET 写操作拒绝', rejectedGet.status() === 405);
        const badPost = await page.request.post(base + '/setting.php?mod=admin:cron&dis=it_ui_probe', { form: { csrf_token: 'invalid-test-token' } });
        check('非法 CSRF 令牌拒绝', (await badPost.text()).includes('安全令牌校验失败'));
        await page.goto(base + '/index.php?mod=admin:cron');
        await page.waitForLoadState('networkidle');
        check('拒绝请求未改变任务状态', await probe.getByText('已启用', { exact: true }).count() === 1);
        // 卸载确认取消后必须仍可操作。
        page.once('dialog', dialog => dialog.dismiss());
        await probe.getByRole('button', { name: '卸载', exact: true }).click();
        check('取消卸载保留任务', await probe.count() === 1);
        const shots = process.env.TCS_UI_SCREENSHOTS;
        await page.evaluate(() => window.scrollTo(0, 0));
        if (shots) { fs.mkdirSync(shots, { recursive: true }); await page.screenshot({ path: path.join(shots, 'cron-desktop.png') }); }
        for (const width of [375, 768]) {
            await page.setViewportSize({ width, height: 900 });
            check(width + 'px 页面无整体横向溢出', await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth + 1));
        }
        await page.evaluate(() => window.scrollTo(0, 0));
        if (shots) { await page.screenshot({ path: path.join(shots, 'cron-tablet.png') }); }
        await page.setViewportSize({ width: 375, height: 900 });
        if (shots) { await page.screenshot({ path: path.join(shots, 'cron-mobile.png') }); }
        await page.emulateMedia({ colorScheme: 'dark', reducedMotion: 'reduce' });
        await page.reload(); await page.waitForLoadState('networkidle');
        check('深色主题可用', await page.locator('html').getAttribute('data-theme') === 'dark');
        if (shots) { await page.screenshot({ path: path.join(shots, 'cron-dark.png') }); }
        check('无浏览器脚本异常', errors.length === 0);
        console.log(JSON.stringify({ status: 'PASS', checks: results.length, results }, null, 2));
    } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exitCode = 1; });
