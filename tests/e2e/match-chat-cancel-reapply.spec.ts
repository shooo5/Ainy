/**
 * チャット: キャンセル → 再申請 → 再承認（room fork）
 *
 * 手順・期待値: docs/reports/e2e-chat-cancel-reapply-automation.md
 */
import { test, expect, type Browser, type Page } from '@playwright/test';
import { loginWithCredentials, switchOperatingTeam } from './helpers/authHelper';
import {
  FIVE_PLUS_TWO_USERS,
  type FivePlusTwoUserKey,
} from './helpers/fivePlusTwoTeams';
import {
  cancelApplication,
  reapplyAndWaitPending,
  approveMatchAsReceiver,
  matchDetailUrl,
  openChatFromMatchDetail,
} from './helpers/matchFlowHelper';
import {
  loadChatLifecycleSnapshot,
  summarizeForkExpectation,
} from './helpers/chatLifecycleHelper';
import {
  gotoCommunicationMain,
  activateCommunicationFilter,
  timelineCardByRoomId,
} from './helpers/communicationHelper';
import { setupTestData, cleanupTestData, type CleanupParams } from './helpers/testDataClient';

/** 募集 5663（user302）/ 申請 5665（user303） */
const RECRUIT_TEAM_ID = 5663;
const APPLICANT_TEAM_ID = 5665;

function passwordFor(key: FivePlusTwoUserKey): string {
  const u = FIVE_PLUS_TWO_USERS[key];
  return process.env[u.passwordEnv] || u.defaultPassword;
}

async function loginUser(page: Page, key: FivePlusTwoUserKey): Promise<void> {
  const u = FIVE_PLUS_TWO_USERS[key];
  await loginWithCredentials(page, u.email, passwordFor(key));
}

test.describe.configure({ mode: 'serial' });

test.describe('チャット cancel → reapply → approve @chat-lifecycle', () => {
  let pairCleanup: CleanupParams = {};

  test.beforeAll(async () => {
    if (!process.env.E2E_TEST_KEY && !process.env.PLAYWRIGHT_E2E_TEST_KEY) {
      return;
    }
    await cleanupTestData({ team_ids: [RECRUIT_TEAM_ID, APPLICANT_TEAM_ID] });
  });

  test.afterAll(async () => {
    if (!process.env.E2E_TEST_KEY && !process.env.PLAYWRIGHT_E2E_TEST_KEY) {
      return;
    }
    await cleanupTestData({
      ...pairCleanup,
      team_ids: [RECRUIT_TEAM_ID, APPLICANT_TEAM_ID],
    });
    pairCleanup = {};
  });

  test('成立→キャンセル→再申請→承認で旧 room completed・新 active fork', async ({ browser }) => {
    test.setTimeout(300000);

    if (!process.env.E2E_TEST_KEY && !process.env.PLAYWRIGHT_E2E_TEST_KEY) {
      test.skip(true, 'E2E_TEST_KEY が未設定のためスキップ');
      return;
    }
    if (!browser) {
      test.skip();
      return;
    }

    const ready = await setupTestData({
      scenario: 'accepted_match',
      teamA_email: FIVE_PLUS_TWO_USERS.user302.email,
      teamB_email: FIVE_PLUS_TWO_USERS.user303.email,
      teamA_team_id: RECRUIT_TEAM_ID,
      teamB_team_id: APPLICANT_TEAM_ID,
      gender: 'female',
      create_chat: true,
    });

    expect(ready.match_request_id).toBeTruthy();
    expect(ready.match_detail_url).toBeTruthy();
    expect(ready.schedule_ids?.length).toBe(2);
    expect(ready.chat_room_id).toBeTruthy();

    const matchRequestId = ready.match_request_id!;
    const receiverScheduleId = ready.schedule_ids![0];
    const applicantScheduleId = ready.schedule_ids![1];
    const detailUrl = ready.match_detail_url!;

    pairCleanup = {
      schedule_ids: ready.schedule_ids,
      match_request_id: matchRequestId,
      ...(ready.chat_room_id ? { chat_room_id: ready.chat_room_id } : {}),
    };

    const snapEstablished = await loadChatLifecycleSnapshot(matchRequestId);
    expect(snapEstablished.activeRoomIds).toHaveLength(1);
    const firstActiveRoomId = snapEstablished.activeRoomIds[0];
    expect(firstActiveRoomId).toBeGreaterThan(0);
    expect(firstActiveRoomId).toBe(ready.chat_room_id);

    const ctxA = await browser.newContext();
    const ctxR = await browser.newContext();
    const applicantPage = await ctxA.newPage();
    const receiverPage = await ctxR.newPage();

    try {
      await loginUser(applicantPage, 'user303');
      await loginUser(receiverPage, 'user302');

      await switchOperatingTeam(applicantPage, APPLICANT_TEAM_ID);
      await cancelApplication(applicantPage, detailUrl);

      await expect(async () => {
        const snapCanceled = await loadChatLifecycleSnapshot(matchRequestId);
        expect(snapCanceled.completedRoomIds).toContain(firstActiveRoomId);
        expect(snapCanceled.activeRoomIds).toHaveLength(0);
      }).toPass({ timeout: 30000 });

      await reapplyAndWaitPending(applicantPage);
      const pendingLink = applicantPage.locator('a[href*="match_request_id="]').first();
      await expect(pendingLink).toBeVisible({ timeout: 35000 });

      await switchOperatingTeam(receiverPage, RECRUIT_TEAM_ID);
      const receiverDetailUrl = matchDetailUrl(
        receiverScheduleId,
        applicantScheduleId,
        matchRequestId
      );
      await approveMatchAsReceiver(
        receiverPage,
        receiverScheduleId,
        applicantScheduleId,
        matchRequestId,
        receiverDetailUrl
      );

      const snapAfterApprove = await loadChatLifecycleSnapshot(matchRequestId);
      const fork = summarizeForkExpectation(snapAfterApprove);
      expect(fork.completedCount).toBeGreaterThanOrEqual(1);
      expect(fork.activeCount).toBe(1);
      expect(fork.latestActiveId).toBeGreaterThan(fork.latestCompletedId);
      expect(fork.latestActiveId).toBeGreaterThan(firstActiveRoomId);
      expect(snapAfterApprove.completedRoomIds).toContain(firstActiveRoomId);
      expect(snapAfterApprove.activeRoomIds[0]).not.toBe(firstActiveRoomId);
      expect(snapAfterApprove.boundRoomId).toBe(snapAfterApprove.activeRoomIds[0]);

      const newActiveRoomId = snapAfterApprove.activeRoomIds[0];

      await switchOperatingTeam(applicantPage, APPLICANT_TEAM_ID);
      await gotoCommunicationMain(applicantPage);
      await activateCommunicationFilter(applicantPage, 'completed');
      await expect(timelineCardByRoomId(applicantPage, firstActiveRoomId)).toBeVisible({
        timeout: 25000,
      });

      await activateCommunicationFilter(applicantPage, 'chat');
      await expect(timelineCardByRoomId(applicantPage, newActiveRoomId)).toBeVisible({
        timeout: 25000,
      });

      const applicantDetailUrl = matchDetailUrl(
        applicantScheduleId,
        receiverScheduleId,
        matchRequestId
      );
      await openChatFromMatchDetail(applicantPage, applicantDetailUrl);
      expect(applicantPage.url()).toMatch(new RegExp(`room_id=${newActiveRoomId}`));
      expect(applicantPage.url()).not.toMatch(new RegExp(`room_id=${firstActiveRoomId}`));
    } finally {
      await ctxA.close();
      await ctxR.close();
    }
  });
});
