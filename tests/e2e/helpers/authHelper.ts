import { expect, Page } from '@playwright/test';

const BASE_URL = process.env.BASE_URL || process.env.PLAYWRIGHT_TEST_BASE_URL || 'http://aidunite-d.local';

export const E2E_USERS = {
  teamA: { email: 'shogo.saito@aidunite-inc.com', password: process.env.E2E_TEAM_A_PASSWORD || 'TestPassword123!' },
  teamB: { email: 's.shogo1018@gmail.com', password: process.env.E2E_TEAM_B_PASSWORD || 'TestPassword123!' },
};

/**
 * ログインページで teamA または teamB としてログインする
 */
export async function loginAs(page: Page, user: 'teamA' | 'teamB'): Promise<void> {
  const { email, password } = E2E_USERS[user];
  await loginWithCredentials(page, email, password);
}

/**
 * メール・パスワードでログイン
 */
export async function loginWithCredentials(page: Page, email: string, password: string): Promise<void> {
  await page.goto(BASE_URL + '/login', { waitUntil: 'domcontentloaded' });
  await expect(page.getByTestId('login-email')).toBeVisible({ timeout: 20000 });
  await page.getByTestId('login-email').fill(email);
  await page.getByTestId('login-password').fill(password);
  await page.locator('button[type="submit"]').click();
  await page.waitForURL((url) => !url.pathname.includes('/login') || url.search.includes('error'), { timeout: 15000 }).catch(() => {});
}

/**
 * ヘッダー等の操作中チーム切替（REST 成功後に full reload されるまで待つ）
 */
export async function switchOperatingTeam(page: Page, teamId: number): Promise<void> {
  const target = String(teamId);
  const home = BASE_URL.replace(/\/$/, '') + '/mypage';

  if (!page.url().includes('/mypage') && !page.url().includes('/match-board')) {
    await page.goto(home, { waitUntil: 'domcontentloaded', timeout: 45000 });
  }

  const select = page.locator('[data-aidunite-operating-team-select]').first();
  const selectCount = await select.count();
  if (selectCount === 0) {
    // 単一チーム所属ユーザーはヘッダー切替 UI が出ない（team_id はログイン時の既定）
    return;
  }
  await expect(select, '操作中チーム切替セレクトが DOM にあること').toBeAttached({ timeout: 20000 });

  const current = await select.inputValue();
  if (target === current) {
    return;
  }

  const responseWait = page.waitForResponse(
    (res) =>
      res.url().includes('/aidunite/v1/current-operating-team') &&
      res.request().method() === 'POST' &&
      res.ok(),
    { timeout: 45000 }
  );
  const navigationWait = page.waitForLoadState('load', { timeout: 45000 }).catch(() => {});

  await select.selectOption(target, { force: true });
  await responseWait;
  await navigationWait;
  await expect(select).toHaveValue(target, { timeout: 15000 });
}

/**
 * 現在のコンテキストの cookie を取得（API 呼び出し用）
 */
export async function getCookies(page: Page): Promise<{ name: string; value: string }[]> {
  const cookies = await page.context().cookies();
  return cookies.map((c) => ({ name: c.name, value: c.value }));
}
