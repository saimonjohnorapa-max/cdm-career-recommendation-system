import { test, expect } from '@playwright/test';

const student = (id, name) => ({ id, full_name: name, email: `student${id}@example.invalid`, student_number: `TEST-${id}`, admission_year: '2026-2027', account_status: 'active', exam_taken: false });
const fulfill = (route, data, status = 200) => route.fulfill({ status, contentType: 'application/json', body: JSON.stringify(data) });

async function studentsPortal(page, { role = 'admissions_staff', rows = [student(1, 'Demo Student')], pageSize = 15 } = {}) {
  const state = { rows: [...rows], deletions: [], failDelete: false };
  await page.addInitScript(() => localStorage.setItem('admin_token', 'synthetic-student-management-token'));
  await page.route('**/api/**', route => {
    const url = new URL(route.request().url());
    if (url.pathname.endsWith('/admin/auth/profile')) return fulfill(route, { admin: { id: 1, name: 'Test Admin', role, status: 'active' } });
    const record = url.pathname.match(/\/admin\/students\/(\d+)$/);
    if (record) {
      const id = Number(record[1]);
      if (route.request().method() === 'DELETE') {
        state.deletions.push(id);
        if (state.failDelete) return fulfill(route, { message: 'Unable to remove student. Please retry.' }, 500);
        state.rows = state.rows.filter(row => row.id !== id);
        return fulfill(route, { success: true, message: 'Student account and linked examination records removed permanently.' });
      }
      return fulfill(route, { student: state.rows.find(row => row.id === id) });
    }
    if (url.pathname.endsWith('/admin/students')) {
      const currentPage = Number(url.searchParams.get('page') || 1);
      const search = (url.searchParams.get('search') || '').toLowerCase();
      const status = url.searchParams.get('status');
      const rows = state.rows.filter(row => (!search || row.full_name.toLowerCase().includes(search)) && (!status || row.account_status === status));
      const lastPage = Math.max(1, Math.ceil(rows.length / pageSize));
      return fulfill(route, { data: rows.slice((currentPage - 1) * pageSize, currentPage * pageSize), current_page: currentPage, last_page: lastPage, total: rows.length, prev_page_url: currentPage > 1 ? '?previous' : null, next_page_url: currentPage < lastPage ? '?next' : null });
    }
    return fulfill(route, {});
  });
  await page.goto('http://127.0.0.1:5190/cdm-career-recommendation-system/admin/#/students');
  await expect(page.getByRole('heading', { name: 'Student management', exact: true })).toBeVisible();
  return state;
}

test('student removal asks for confirmation and refreshes the list after acceptance', async ({ page }) => {
  const state = await studentsPortal(page);
  page.once('dialog', async dialog => {
    expect(dialog.message()).toContain('Demo Student (TEST-1)');
    expect(dialog.message()).toContain('This cannot be undone.');
    await dialog.dismiss();
  });
  await page.getByRole('button', { name: 'Remove Demo Student', exact: true }).click();
  await expect(page.getByRole('cell', { name: /^Demo Student/ })).toBeVisible();
  expect(state.deletions).toEqual([]);
  page.once('dialog', dialog => dialog.accept());
  await page.getByRole('button', { name: 'Remove Demo Student', exact: true }).click();
  await expect(page.getByRole('status')).toContainText('removed permanently');
  await expect(page.getByRole('cell', { name: 'No students found.', exact: true })).toBeVisible();
  expect(state.deletions).toEqual([1]);
});

test('student detail removal preserves the student after an error and allows retry', async ({ page }) => {
  const state = await studentsPortal(page);
  state.failDelete = true;
  await page.getByRole('button', { name: 'View', exact: true }).click();
  const details = page.getByRole('dialog', { name: 'Demo Student', exact: true });
  await expect(details).toBeVisible();
  page.on('dialog', dialog => dialog.accept());
  await details.getByRole('button', { name: 'Remove student', exact: true }).click();
  await expect(details.getByRole('alert')).toHaveText('Unable to remove student. Please retry.');
  expect(state.rows).toHaveLength(1);
  await expect(details.getByRole('button', { name: 'Remove student', exact: true })).toBeEnabled();
  state.failDelete = false;
  await details.getByRole('button', { name: 'Remove student', exact: true }).click();
  await expect(details).toHaveCount(0);
  await expect(page.getByRole('status')).toContainText('removed permanently');
});

test('removing the final student on a later page returns to an existing page', async ({ page }) => {
  await studentsPortal(page, { rows: [student(1, 'Keep Student'), student(2, 'Remove Student')], pageSize: 1 });
  await page.getByRole('button', { name: 'Next', exact: true }).click();
  await expect(page.getByRole('button', { name: 'Remove Remove Student', exact: true })).toBeVisible();
  page.once('dialog', dialog => dialog.accept());
  await page.getByRole('button', { name: 'Remove Remove Student', exact: true }).click();
  await expect(page.getByRole('cell', { name: /^Keep Student/ })).toBeVisible();
  await expect(page.getByRole('button', { name: 'Previous', exact: true })).toBeDisabled();
});

test('viewers do not receive student removal controls', async ({ page }) => {
  await studentsPortal(page, { role: 'viewer' });
  await expect(page.getByRole('cell', { name: /Demo Student/ })).toBeVisible();
  await expect(page.getByRole('button', { name: /^Remove/ })).toHaveCount(0);
  await page.getByRole('button', { name: 'View', exact: true }).click();
  await expect(page.getByRole('dialog', { name: 'Demo Student', exact: true })).toBeVisible();
  await expect(page.getByRole('button', { name: /^Remove/ })).toHaveCount(0);
});
