import { test, expect } from '@playwright/test';

const student = { id: 1, first_name: 'Browser', last_name: 'Student', full_name: 'Browser Student', email: 'student@example.invalid', account_status: 'active', student_number: 'TEST-1', admission_year: '2026-2027' };
const admin = { id: 1, name: 'Browser Admin', email: 'admin@example.invalid', role: 'exam_manager', status: 'active' };
const fulfill = (route, data, status = 200) => route.fulfill({ status, contentType: 'application/json', body: JSON.stringify(data) });

async function identityButton(page) {
  await page.addInitScript(() => {
    let callback;
    window.google = { accounts: { id: {
      initialize(options) { callback = options.callback; },
      renderButton(container) {
        container.replaceChildren();
        const button = document.createElement('button');
        button.textContent = 'Continue with Google';
        button.onclick = () => callback({ credential: 'synthetic-browser-credential' });
        container.appendChild(button);
      },
    } } };
  });
  await page.route('https://accounts.google.com/gsi/client', route => route.fulfill({ contentType: 'text/javascript', body: '' }));
}

async function expectBranding(page, basePath) {
  const logo = page.locator('img').first();
  await expect(logo).toHaveAttribute('src', `${basePath}The_Colegio_de_Montalban_Seal.png`);
  await expect.poll(() => logo.evaluate(image => image.complete && image.naturalWidth > 0)).toBe(true);
  const favicon = await page.locator('link[rel="icon"]').getAttribute('href');
  expect(favicon).toBe(`${basePath}The_Colegio_de_Montalban_Seal.png?v=2`);
  const response = await page.request.get(new URL(favicon, page.url()).href);
  expect(response.ok()).toBe(true);
  expect(response.headers()['content-type']).toContain('image/png');
}

test('student Google login opens the dashboard and loads the portal logo', async ({ page }) => {
  await identityButton(page);
  await page.route('**/api/**', route => {
    const path = new URL(route.request().url()).pathname;
    if (path.endsWith('/auth/google-login')) return fulfill(route, { success: true, student, token: 'student-browser-token' });
    if (path.endsWith('/auth/profile')) return fulfill(route, { success: true, student });
    if (path.endsWith('/student/status')) return fulfill(route, { status: 'NOT_STARTED', attempts_used: 0, timeline: [], notifications: [] });
    return fulfill(route, { success: true, exam_taken: false });
  });
  await page.goto('/cdm-career-recommendation-system/#/login');
  await expectBranding(page, '/cdm-career-recommendation-system/');
  await page.getByRole('button', { name: 'Continue with Google', exact: true }).click();
  await expect(page.getByText('Google sign-in successful.', { exact: true })).toBeVisible();
  await page.getByRole('button', { name: 'OK', exact: true }).click();
  await expect(page).toHaveURL(/#\/dashboard$/);
  expect(await page.evaluate(() => localStorage.getItem('auth_token'))).toBe('student-browser-token');
});

test('admin login keeps credential errors on the login page and permits retry', async ({ page }) => {
  await identityButton(page);
  let accepted = false;
  await page.route('**/api/**', route => {
    const path = new URL(route.request().url()).pathname;
    if (path.endsWith('/admin/auth/google-login')) return accepted
      ? fulfill(route, { success: true, admin, token: 'admin-browser-token' })
      : fulfill(route, { message: 'Google could not verify this account.' }, 401);
    if (path.endsWith('/admin/auth/profile')) return fulfill(route, { admin });
    return fulfill(route, { stats: {}, latest_results: [] });
  });
  await page.goto('http://127.0.0.1:5190/cdm-career-recommendation-system/admin/#/login');
  await expectBranding(page, '/cdm-career-recommendation-system/admin/');
  await page.getByRole('button', { name: 'Continue with Google', exact: true }).click();
  await expect(page.getByRole('alert')).toHaveText('Google could not verify this account.');
  await expect(page).toHaveURL(/\/admin\/#\/login$/);
  accepted = true;
  await page.getByRole('button', { name: 'Continue with Google', exact: true }).click();
  await expect(page.getByRole('heading', { name: 'Administrative overview', exact: true })).toBeVisible();
  expect(await page.evaluate(() => localStorage.getItem('admin_token'))).toBe('admin-browser-token');
});

test('student email login still requires OTP verification before issuing a session', async ({ page }) => {
  await identityButton(page);
  await page.route('**/api/**', route => {
    const path = new URL(route.request().url()).pathname;
    if (path.endsWith('/auth/login')) return fulfill(route, { otp_required: true, challenge_id: 'test-challenge', purpose: 'login', masked_email: 's***@example.invalid', expires_in: 600, resend_after: 60 });
    if (path.endsWith('/auth/otp/verify')) return fulfill(route, { student, token: 'otp-browser-token' });
    if (path.endsWith('/auth/profile')) return fulfill(route, { student });
    if (path.endsWith('/student/status')) return fulfill(route, { status: 'NOT_STARTED', attempts_used: 0, timeline: [], notifications: [] });
    return fulfill(route, { success: true, exam_taken: false });
  });
  await page.goto('/cdm-career-recommendation-system/#/login');
  await page.getByLabel('Email', { exact: true }).fill('student@example.invalid');
  await page.getByLabel('Password', { exact: true }).fill('synthetic-password');
  await page.getByRole('button', { name: 'Sign In', exact: true }).click();
  await expect(page.getByRole('heading', { name: 'Verify your login', exact: true })).toBeVisible();
  expect(await page.evaluate(() => localStorage.getItem('auth_token'))).toBe(null);
  await page.getByLabel('Verification code', { exact: true }).fill('123456');
  await page.getByRole('button', { name: 'Verify & Sign In', exact: true }).click();
  await expect(page.getByText('Login verified successfully.', { exact: true })).toBeVisible();
  await page.getByRole('button', { name: 'OK', exact: true }).click();
  await expect(page).toHaveURL(/#\/dashboard$/);
  expect(await page.evaluate(() => localStorage.getItem('auth_token'))).toBe('otp-browser-token');
});
