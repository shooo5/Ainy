/**
 * コミュニケーション（統合タイムライン）画面の E2E 補助
 */
import { expect, Page } from '@playwright/test';

const baseURL = process.env.BASE_URL || process.env.PLAYWRIGHT_TEST_BASE_URL || 'http://aidunite-d.local';

export async function gotoCommunicationMain(page: Page): Promise<void> {
  const timelineReady = page.waitForResponse(
    (res) =>
      res.url().includes('/wp-json/aidunite/v1/timeline') && res.request().method() === 'GET',
    { timeout: 45000 }
  );
  await page.goto(`${baseURL.replace(/\/$/, '')}/communication-main`, {
    waitUntil: 'domcontentloaded',
    timeout: 45000,
  });
  await timelineReady;
  await expect(page.locator('#timelineList')).toBeVisible({ timeout: 20000 });
}

export async function activateCommunicationFilter(
  page: Page,
  filter: 'all' | 'chat' | 'completed' | 'action-required' | 'board'
): Promise<void> {
  const btn = page.locator(`.filter-btn[data-filter="${filter}"]`);
  await expect(btn).toBeVisible({ timeout: 15000 });
  const reload = page.waitForResponse(
    (res) =>
      res.url().includes('/wp-json/aidunite/v1/timeline') && res.request().method() === 'GET',
    { timeout: 45000 }
  );
  await btn.click();
  await reload.catch(() => {});
  await page.waitForTimeout(300);
}

export function timelineCardByRoomId(page: Page, roomId: number) {
  return page.locator(`.timeline-card[data-room-id="${roomId}"]`);
}

/** 装飾ドット（未読連動なし・常時表示） */
export function timelineDecorativeDot(page: Page, roomId: number) {
  return timelineCardByRoomId(page, roomId).locator('.timeline-card__dot');
}
