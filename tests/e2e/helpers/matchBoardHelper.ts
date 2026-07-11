import { expect, Page, Locator } from '@playwright/test';

/**
 * 募集中一覧の行（相手チーム名の title 部分一致）
 */
export function marketRowByTeamTitle(page: Page, titleSnippet: string): Locator {
  return page.locator('#market-axis-list li.market-axis-row').filter({
    has: page.locator(`a.market-axis-team-link[title*="${titleSnippet}"]`),
  });
}

/** title 属性の完全一致（「teamB」が「teamB-b男子」に部分一致しないようにする） */
export function marketRowByExactTeamTitle(page: Page, fullTitle: string): Locator {
  return page.locator('#market-axis-list li.market-axis-row').filter({
    has: page.locator(`a.market-axis-team-link[title="${fullTitle}"]`),
  });
}

export async function expectMarketRowState(
  row: Locator,
  allowedStates: string[]
): Promise<void> {
  await expect(row.first()).toBeVisible({ timeout: 15000 });
  const state = await row.first().getAttribute('data-state');
  expect(state, `data-state should be one of ${allowedStates.join(', ')}`).toBeTruthy();
  expect(allowedStates).toContain(state);
}

/** 同一チーム名の行が複数あっても、いずれかが許容 tier なら OK */
export async function expectAtLeastOneRowWithState(
  rows: Locator,
  allowedStates: string[]
): Promise<void> {
  await expect(rows.first()).toBeVisible({ timeout: 15000 });
  const count = await rows.count();
  expect(count).toBeGreaterThanOrEqual(1);

  let matched = false;
  for (let i = 0; i < count; i++) {
    const state = await rows.nth(i).getAttribute('data-state');
    if (state && allowedStates.includes(state)) {
      matched = true;
      break;
    }
  }
  expect(matched, `expected data-state in [${allowedStates.join(', ')}] among ${count} row(s)`).toBe(true);
}

export async function gotoMatchBoardMarketTab(page: Page, baseURL: string): Promise<void> {
  const url = `${baseURL.replace(/\/$/, '')}/match-board-own`;
  const maxAttempts = 4;
  let lastError: unknown;

  for (let attempt = 1; attempt <= maxAttempts; attempt++) {
    try {
      const response = await page.goto(url, { waitUntil: 'domcontentloaded', timeout: 45000 });
      const status = response?.status() ?? 0;
      if (status >= 500) {
        throw new Error(`match-board-own returned HTTP ${status}`);
      }
      const badGateway = page.getByRole('heading', { name: '502 Bad Gateway' });
      if (await badGateway.isVisible().catch(() => false)) {
        throw new Error('502 Bad Gateway on match-board-own');
      }
      // ページ見出しは「試合一覧」。「募集中の試合」は role=tab のピルタブ
      await expect(page.getByRole('heading', { name: '試合一覧' })).toBeVisible({ timeout: 20000 });
      const recruitTab = page.getByRole('tab', { name: '募集中の試合' });
      await expect(recruitTab).toBeVisible({ timeout: 20000 });
      if ((await recruitTab.getAttribute('aria-selected')) !== 'true') {
        await recruitTab.click();
        await expect(recruitTab).toHaveAttribute('aria-selected', 'true', { timeout: 10000 });
      }
      const list = page.locator('#market-axis-list');
      await expect(list).toBeVisible({ timeout: 20000 });
      return;
    } catch (e) {
      lastError = e;
      if (attempt < maxAttempts) {
        await page.waitForTimeout(1500 * attempt);
      }
    }
  }

  throw lastError;
}
