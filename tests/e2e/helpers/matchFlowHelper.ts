import { expect, Page, Browser, Locator } from '@playwright/test';
import { switchOperatingTeam } from './authHelper';
import { gotoMatchBoardMarketTab, marketRowByExactTeamTitle } from './matchBoardHelper';

const baseURL = process.env.BASE_URL || process.env.PLAYWRIGHT_TEST_BASE_URL || 'http://aidunite-d.local';

export type TeamScheduleMap = Record<number, number>;

export function schedulesFromMarketTeams(
  teams: { team_id: number; schedule_id: number }[] | undefined
): TeamScheduleMap {
  const map: TeamScheduleMap = {};
  if (!teams) {
    return map;
  }
  for (const t of teams) {
    map[t.team_id] = t.schedule_id;
  }
  return map;
}

export async function openMarketApplyForOpponent(
  page: Page,
  opponentTitle: string
): Promise<void> {
  await gotoMatchBoardMarketTab(page, baseURL);
  const row = marketRowByExactTeamTitle(page, opponentTitle);
  await expect(row.first()).toBeVisible({ timeout: 20000 });
  const rowCount = await row.count();
  let clicked = false;
  const tryClickApply = async (locator: Locator): Promise<boolean> => {
    if (!(await locator.first().isVisible().catch(() => false))) {
      return false;
    }
    const href = (await locator.first().getAttribute('href').catch(() => null)) ?? '';
    if (href.includes('match_request_id=')) {
      return false;
    }
    await locator.first().click();
    return true;
  };
  for (let i = 0; i < rowCount; i++) {
    const primaryApply = row.nth(i).locator('a.match-btn--primary[href*="match-detail"]');
    if (await tryClickApply(primaryApply)) {
      clicked = true;
      break;
    }
  }
  if (!clicked) {
    for (let i = 0; i < rowCount; i++) {
      const fallbackApply = row
        .nth(i)
        .locator('a[href*="#apply"]')
        .filter({ hasText: /申請|調整して申請|この日程で申請する/ });
      if (await tryClickApply(fallbackApply)) {
        clicked = true;
        break;
      }
    }
  }
  if (!clicked) {
    const anyApply = row.first().locator('a.match-btn--primary[href*="match-detail"]');
    await expect(anyApply.first()).toBeVisible({ timeout: 10000 });
    await anyApply.first().click();
  }
  await page.waitForURL(/match-detail/, { timeout: 30000 });
  await page.waitForLoadState('domcontentloaded');
}

export async function submitApplyOnMatchDetail(page: Page): Promise<string> {
  const applyOther = page.getByTestId('match-apply-button');
  const applyMain = page.locator('#applyButton');
  const newConditionApply = page.getByRole('button', { name: '新しい条件で申請する' });

  if (await applyOther.isVisible().catch(() => false)) {
    await applyOther.click();
  } else if (await newConditionApply.isVisible().catch(() => false)) {
    const placeHome = page.getByRole('button', { name: 'ホーム' });
    if (await placeHome.isVisible().catch(() => false)) {
      await placeHome.click();
    } else {
      const placeCard = page.locator('[data-type="place"].selection-card').first();
      if (await placeCard.isVisible().catch(() => false)) {
        await placeCard.click();
      }
    }
    page.once('dialog', async (d) => d.accept());
    await newConditionApply.click();
  } else if (await applyMain.isVisible().catch(() => false)) {
    const placeCard = page.locator('[data-type="place"].selection-card').first();
    if (await placeCard.isVisible().catch(() => false)) {
      await placeCard.click();
    }
    const genderCard = page.locator('[data-type="gender"].selection-card').first();
    if (await genderCard.isVisible().catch(() => false)) {
      await genderCard.click();
    }
    await applyMain.click();
  } else {
    await expect(applyOther.or(applyMain).or(newConditionApply).first()).toBeVisible({
      timeout: 15000,
    });
  }
  await page.waitForURL(/match-board-own/, { timeout: 45000 });
  await page.waitForLoadState('domcontentloaded');
  const pendingLink = page.locator('a[href*="match_request_id="]').first();
  await expect(pendingLink).toBeVisible({ timeout: 35000 });
  const href = await pendingLink.getAttribute('href');
  if (!href) {
    throw new Error('match detail link with match_request_id not found after apply');
  }
  if (href.startsWith('http')) {
    return href;
  }
  const path = href.startsWith('/') ? href : `/${href}`;
  return `${baseURL.replace(/\/$/, '')}${path}`;
}

export function matchDetailUrl(
  myScheduleId: number,
  otherScheduleId: number,
  matchRequestId?: number
): string {
  let url = `${baseURL.replace(/\/$/, '')}/match-detail/?my_schedule_id=${myScheduleId}&schedule_id=${otherScheduleId}`;
  if (matchRequestId && matchRequestId > 0) {
    url += `&match_request_id=${matchRequestId}`;
  }
  return url;
}

export function parseMatchRequestIdFromUrl(detailUrl: string): number | null {
  const m = detailUrl.match(/[?&]match_request_id=(\d+)/);
  return m ? parseInt(m[1], 10) : null;
}

export async function approveMatchAsReceiver(
  page: Page,
  myScheduleId: number,
  applicantScheduleId: number,
  matchRequestId?: number | null,
  detailUrl?: string
): Promise<void> {
  const url =
    detailUrl && detailUrl.length > 0
      ? detailUrl
      : matchDetailUrl(myScheduleId, applicantScheduleId, matchRequestId ?? undefined);
  await page.goto(url, {
    waitUntil: 'domcontentloaded',
    timeout: 45000,
  });
  const approve = page.getByTestId('match-approve-button');
  await expect(approve).toBeVisible({ timeout: 25000 });

  const ajaxDone = page.waitForResponse(
    (res) => res.url().includes('admin-ajax.php') && res.request().method() === 'POST',
    { timeout: 35000 }
  );
  await approve.click();
  const ajaxRes = await ajaxDone;
  const ajaxJson = (await ajaxRes.json().catch(() => ({}))) as { success?: boolean; data?: { message?: string } };
  expect(ajaxJson.success, `approve ajax failed: ${JSON.stringify(ajaxJson)}`).toBeTruthy();

  const chatModalStay = page.locator('#chat-redirect-stay-btn');
  const chatModalGo = page.locator('#chat-redirect-go-btn');
  if (await chatModalStay.isVisible({ timeout: 15000 }).catch(() => false)) {
    await chatModalStay.click();
    await page.waitForLoadState('load', { timeout: 45000 }).catch(() => {});
  } else if (await chatModalGo.isVisible({ timeout: 3000 }).catch(() => false)) {
    await chatModalGo.click();
    await page.waitForURL(/team-chat|chat/, { timeout: 30000 }).catch(() => {});
  }

  await expect(async () => {
    const chatVisible = await page.getByTestId('match-chat-button').isVisible().catch(() => false);
    const establishedText = await page.getByText('試合確定').isVisible().catch(() => false);
    const onChat = /team-chat|chat/.test(page.url());
    expect(chatVisible || establishedText || onChat).toBeTruthy();
  }).toPass({ timeout: 20000 });
  await page.waitForLoadState('domcontentloaded');
}

function isChatMessagesGetResponse(res: import('@playwright/test').Response): boolean {
  return (
    /\/wp-json\/aidunite\/v1\/chats\/\d+\/messages/.test(res.url()) &&
    res.request().method() === 'GET'
  );
}

function isChatMessagesPostResponse(res: import('@playwright/test').Response): boolean {
  return (
    /\/wp-json\/aidunite\/v1\/chats\/\d+\/messages/.test(res.url()) &&
    res.request().method() === 'POST'
  );
}

/** チャット画面でメッセージ一覧の GET が成功するまで待つ（失敗表示・初期化失敗を除外） */
async function waitForChatMessagesReady(page: Page): Promise<void> {
  await expect(page.locator('#messageInput')).toBeVisible({ timeout: 20000 });
  await expect(async () => {
    const text = await page.locator('#messagesContainer').innerText();
    if (text.includes('メッセージの読み込みに失敗しました')) {
      throw new Error('chat messages GET failed on UI');
    }
    if (text.includes('チャットルームの初期化に失敗しました')) {
      throw new Error('chat room not initialized');
    }
  }).toPass({ timeout: 45000 });
}

/** 成立後マッチ詳細の「チャット」から room を開き、一覧 GET 成功まで待つ */
export async function openChatFromMatchDetail(page: Page, matchDetailUrl: string): Promise<void> {
  await expect(async () => {
    await page.goto(matchDetailUrl, { waitUntil: 'domcontentloaded', timeout: 45000 });
    await expect(page.getByTestId('match-chat-button')).toBeVisible({ timeout: 8000 });
  }).toPass({ timeout: 60000 });

  const loadMessages = async (): Promise<void> => {
    const messagesGet = page.waitForResponse(
      (res) => isChatMessagesGetResponse(res) && res.ok(),
      { timeout: 45000 }
    );
    await page.getByTestId('match-chat-button').click();
    await page.waitForURL(/\/chat/, { timeout: 30000 });
    await page.waitForLoadState('domcontentloaded');
    await messagesGet;
    await waitForChatMessagesReady(page);
  };

  try {
    await loadMessages();
  } catch {
    await page.reload({ waitUntil: 'domcontentloaded' });
    await page.waitForResponse(
      (res) => isChatMessagesGetResponse(res) && res.ok(),
      { timeout: 45000 }
    );
    await waitForChatMessagesReady(page);
  }
}

export async function sendChatFromEstablishedMatch(
  page: Page,
  message: string,
  establishedDetailUrl?: string
): Promise<void> {
  if (establishedDetailUrl) {
    await openChatFromMatchDetail(page, establishedDetailUrl);
  } else {
    const chatBtn = page.getByTestId('match-chat-button');
    if (await chatBtn.isVisible().catch(() => false)) {
      await openChatFromMatchDetail(page, page.url());
    } else if (!/\/chat/.test(page.url())) {
      await page.goto(`${baseURL.replace(/\/$/, '')}/communication-main`, {
        waitUntil: 'domcontentloaded',
      });
      const vsLink = page.locator('text=vs').first();
      await expect(vsLink).toBeVisible({ timeout: 15000 });
      const messagesGet = page.waitForResponse(
        (res) => isChatMessagesGetResponse(res) && res.ok(),
        { timeout: 45000 }
      );
      await vsLink.click();
      await page.waitForURL(/\/chat/, { timeout: 25000 });
      await messagesGet;
      await waitForChatMessagesReady(page);
    } else {
      await waitForChatMessagesReady(page);
    }
  }

  const postOk = page.waitForResponse(
    (res) => isChatMessagesPostResponse(res) && res.ok(),
    { timeout: 35000 }
  );
  await page.locator('#messageInput').fill(message);
  await page.getByTestId('team-chat-send').click();
  await postOk;
  await expect(page.locator('#messagesContainer')).toContainText(message, { timeout: 30000 });
}

export async function waitForReapplyStable(page: Page, timeout = 45000): Promise<void> {
  const reapply = page.getByTestId('match-reapply-button');
  await expect(reapply).toBeVisible({ timeout });
  await expect(page.getByTestId('match-cancel-button')).toHaveCount(0, { timeout: 10000 });
}

export async function cancelApplication(page: Page, detailUrl: string): Promise<void> {
  await page.goto(detailUrl, { waitUntil: 'domcontentloaded', timeout: 45000 });
  const cancel = page.getByTestId('match-cancel-button');
  await expect(cancel).toBeVisible({ timeout: 25000 });
  await cancel.click();
  await waitForReapplyStable(page, 60000);
}

export async function reapplyAndWaitPending(page: Page): Promise<void> {
  page.once('dialog', async (d) => d.accept());
  await page.getByTestId('match-reapply-button').click();
  await page.waitForURL(/match-board-own|match-detail/, { timeout: 45000 });
  await page.waitForLoadState('domcontentloaded');
}

export async function runApplyApproveFlow(opts: {
  applicantPage: Page;
  receiverPage: Page;
  applicantTeamId: number;
  receiverTeamId: number;
  opponentTitle: string;
  schedules: TeamScheduleMap;
  withChat?: boolean;
  chatMessage?: string;
}): Promise<{ applicantDetailUrl: string }> {
  const {
    applicantPage,
    receiverPage,
    applicantTeamId,
    receiverTeamId,
    opponentTitle,
    schedules,
    withChat,
    chatMessage,
  } = opts;

  await switchOperatingTeam(applicantPage, applicantTeamId);
  await switchOperatingTeam(receiverPage, receiverTeamId);
  await openMarketApplyForOpponent(applicantPage, opponentTitle);
  const applicantDetailUrl = await submitApplyOnMatchDetail(applicantPage);

  const mySched = schedules[applicantTeamId];
  const otherSched = schedules[receiverTeamId];
  expect(mySched).toBeTruthy();
  expect(otherSched).toBeTruthy();

  const matchRequestId = parseMatchRequestIdFromUrl(applicantDetailUrl);
  await approveMatchAsReceiver(receiverPage, otherSched, mySched, matchRequestId);

  if (withChat && chatMessage) {
    const receiverDetailUrl = matchDetailUrl(otherSched, mySched, matchRequestId ?? undefined);
    await switchOperatingTeam(applicantPage, applicantTeamId);
    await sendChatFromEstablishedMatch(applicantPage, chatMessage, applicantDetailUrl);
    await switchOperatingTeam(receiverPage, receiverTeamId);
    await sendChatFromEstablishedMatch(receiverPage, chatMessage, receiverDetailUrl);
  }

  return { applicantDetailUrl };
}

export async function freshMarketPair(
  teamIds: number[],
  setupTestData: (p: import('./testDataClient').SetupParams) => Promise<import('./testDataClient').SetupResponse>
): Promise<{ schedules: TeamScheduleMap; scheduleIds: number[] }> {
  const res = await setupTestData({
    scenario: 'five_plus_two_market',
    team_ids: teamIds,
  });
  const scheduleIds = res.schedule_ids ?? [];
  expect(scheduleIds.length).toBe(teamIds.length);
  return {
    schedules: schedulesFromMarketTeams(res.teams as { team_id: number; schedule_id: number }[]),
    scheduleIds,
  };
}

export async function loginUserKey(
  page: Page,
  key: import('./fivePlusTwoTeams').FivePlusTwoUserKey,
  loginFn: (p: Page, k: import('./fivePlusTwoTeams').FivePlusTwoUserKey) => Promise<void>
): Promise<void> {
  await loginFn(page, key);
}
