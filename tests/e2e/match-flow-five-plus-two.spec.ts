/**
 * 5+2 フロー E2E: 各チームが申請→承認（UI）
 * - 女子2組 + 男子5組（ペアごとに market setup）
 * - 女子代表: キャンセル→再申請→承認
 * - アンケートは含めない（手動チェックリスト参照）
 */
import { test, expect, type Browser } from '@playwright/test';
import { loginWithCredentials, switchOperatingTeam } from './helpers/authHelper';
import {
  FIVE_PLUS_TWO_TEAM_IDS,
  FIVE_PLUS_TWO_USERS,
  FIVE_PLUS_TWO_TEAM_TITLE_SNIPPETS,
  type FivePlusTwoUserKey,
} from './helpers/fivePlusTwoTeams';
import {
  runApplyApproveFlow,
  freshMarketPair,
  reapplyAndWaitPending,
  approveMatchAsReceiver,
  parseMatchRequestIdFromUrl,
  matchDetailUrl,
  type TeamScheduleMap,
} from './helpers/matchFlowHelper';
import { setupTestData, cleanupTestData, type CleanupParams } from './helpers/testDataClient';

const baseURL = process.env.BASE_URL || process.env.PLAYWRIGHT_TEST_BASE_URL || 'http://aidunite-d.local';

function passwordFor(key: FivePlusTwoUserKey): string {
  const u = FIVE_PLUS_TWO_USERS[key];
  return process.env[u.passwordEnv] || u.defaultPassword;
}

async function loginUser(page: import('@playwright/test').Page, key: FivePlusTwoUserKey): Promise<void> {
  const u = FIVE_PLUS_TWO_USERS[key];
  await loginWithCredentials(page, u.email, passwordFor(key));
}

type FlowPair = {
  name: string;
  applicantTeamId: number;
  receiverTeamId: number;
  applicantUser: FivePlusTwoUserKey;
  receiverUser: FivePlusTwoUserKey;
  opponentTitle: string;
  withChat?: boolean;
};

const FEMALE_PAIR_A: FlowPair = {
  name: '女子 5663→5665',
  applicantTeamId: 5663,
  receiverTeamId: 5665,
  applicantUser: 'user302',
  receiverUser: 'user303',
  opponentTitle: FIVE_PLUS_TWO_TEAM_TITLE_SNIPPETS.femaleB,
  withChat: true,
};

const FEMALE_PAIR_B: FlowPair = {
  name: '女子 5665→5663',
  applicantTeamId: 5665,
  receiverTeamId: 5663,
  applicantUser: 'user303',
  receiverUser: 'user302',
  opponentTitle: FIVE_PLUS_TWO_TEAM_TITLE_SNIPPETS.femaleA,
};

const MALE_PAIRS: FlowPair[] = [
  {
    name: '男子 5661→5680',
    applicantTeamId: 5661,
    receiverTeamId: 5680,
    applicantUser: 'user302',
    receiverUser: 'user303',
    opponentTitle: FIVE_PLUS_TWO_TEAM_TITLE_SNIPPETS.maleB,
  },
  {
    name: '男子 5667→5803',
    applicantTeamId: 5667,
    receiverTeamId: 5803,
    applicantUser: 'user304',
    receiverUser: 'user306',
    opponentTitle: FIVE_PLUS_TWO_TEAM_TITLE_SNIPPETS.maleD,
  },
  {
    name: '男子 5680→5805',
    applicantTeamId: 5680,
    receiverTeamId: 5805,
    applicantUser: 'user303',
    receiverUser: 'user307',
    opponentTitle: FIVE_PLUS_TWO_TEAM_TITLE_SNIPPETS.maleE,
  },
  {
    name: '男子 5803→5661',
    applicantTeamId: 5803,
    receiverTeamId: 5661,
    applicantUser: 'user306',
    receiverUser: 'user302',
    opponentTitle: FIVE_PLUS_TWO_TEAM_TITLE_SNIPPETS.maleA,
  },
  {
    name: '男子 5805→5667',
    applicantTeamId: 5805,
    receiverTeamId: 5667,
    applicantUser: 'user307',
    receiverUser: 'user304',
    opponentTitle: FIVE_PLUS_TWO_TEAM_TITLE_SNIPPETS.maleC,
  },
];

test.describe.configure({ mode: 'serial' });

test.describe('5+2 マッチフロー（申請→承認）', () => {
  /** 直前ペアの schedule / MR のみ削除（team_ids 全掃除は Local で 502 になりやすい） */
  let previousPairCleanup: CleanupParams = {};

  test.beforeAll(async () => {
    await cleanupTestData({ team_ids: [...FIVE_PLUS_TWO_TEAM_IDS] });
  });

  test.afterAll(async () => {
    await cleanupTestData({
      ...previousPairCleanup,
      team_ids: [...FIVE_PLUS_TWO_TEAM_IDS],
    });
    previousPairCleanup = {};
  });

  async function runPair(
    browser: Browser,
    pair: FlowPair
  ): Promise<{ schedules: TeamScheduleMap; applicantDetailUrl: string }> {
    if (
      previousPairCleanup.schedule_ids?.length ||
      previousPairCleanup.match_request_id
    ) {
      await cleanupTestData(previousPairCleanup);
    }

    const { schedules, scheduleIds } = await freshMarketPair(
      [pair.applicantTeamId, pair.receiverTeamId],
      setupTestData
    );
    const ctxA = await browser.newContext();
    const ctxR = await browser.newContext();
    const applicantPage = await ctxA.newPage();
    const receiverPage = await ctxR.newPage();

    await loginUser(applicantPage, pair.applicantUser);
    await loginUser(receiverPage, pair.receiverUser);

    const flow = await runApplyApproveFlow({
      applicantPage,
      receiverPage,
      applicantTeamId: pair.applicantTeamId,
      receiverTeamId: pair.receiverTeamId,
      opponentTitle: pair.opponentTitle,
      schedules,
      withChat: pair.withChat,
      chatMessage: pair.withChat ? `E2E chat ${pair.name}` : undefined,
    });

    await ctxA.close();
    await ctxR.close();

    const mrId = parseMatchRequestIdFromUrl(flow.applicantDetailUrl);
    previousPairCleanup = {
      schedule_ids: scheduleIds,
      ...(mrId ? { match_request_id: mrId } : {}),
    };

    return { schedules, applicantDetailUrl: flow.applicantDetailUrl };
  }

  test('女子: 申請→承認→チャット（5663→5665）', async ({ browser }) => {
    test.setTimeout(180000);
    if (!browser) {
      test.skip();
      return;
    }
    await runPair(browser, FEMALE_PAIR_A);
  });

  test('女子: 再申請→承認（reapply_ready）', async ({ page }) => {
    test.setTimeout(180000);
    const femaleRecruitId = FIVE_PLUS_TWO_USERS.user302.femaleTeamId!;
    const femaleApplicantId = FIVE_PLUS_TWO_USERS.user303.femaleTeamId!;

    if (
      previousPairCleanup.schedule_ids?.length ||
      previousPairCleanup.match_request_id
    ) {
      await cleanupTestData(previousPairCleanup);
    }
    await cleanupTestData({ team_ids: [femaleRecruitId, femaleApplicantId] });

    const ready = await setupTestData({
      scenario: 'reapply_ready_match',
      teamA_email: FIVE_PLUS_TWO_USERS.user302.email,
      teamB_email: FIVE_PLUS_TWO_USERS.user303.email,
      teamA_team_id: femaleRecruitId,
      teamB_team_id: femaleApplicantId,
      gender: 'female',
    });
    expect(ready.match_detail_url).toBeTruthy();
    expect(ready.schedule_ids?.length).toBe(2);
    const receiverScheduleId = ready.schedule_ids![0];
    const applicantScheduleId = ready.schedule_ids![1];

    await loginUser(page, 'user303');
    await switchOperatingTeam(page, femaleApplicantId);
    await page.goto(ready.match_detail_url!, { waitUntil: 'domcontentloaded' });
    await expect(page.getByTestId('match-reapply-button')).toBeVisible({ timeout: 20000 });
    await reapplyAndWaitPending(page);

    const pendingLink = page.locator('a[href*="match_request_id="]').first();
    await expect(pendingLink).toBeVisible({ timeout: 35000 });
    const href = await pendingLink.getAttribute('href');
    expect(href).toBeTruthy();
    const detailAfterReapply = href!.startsWith('http')
      ? href!
      : `${baseURL.replace(/\/$/, '')}${href!.startsWith('/') ? href! : `/${href!}`}`;

    await loginUser(page, 'user302');
    await switchOperatingTeam(page, femaleRecruitId);
    const mrId =
      parseMatchRequestIdFromUrl(detailAfterReapply) ?? ready.match_request_id ?? undefined;
    const receiverDetailUrl = matchDetailUrl(
      receiverScheduleId,
      applicantScheduleId,
      mrId ?? undefined
    );
    await approveMatchAsReceiver(
      page,
      receiverScheduleId,
      applicantScheduleId,
      mrId,
      receiverDetailUrl
    );

    previousPairCleanup = {
      schedule_ids: ready.schedule_ids,
      ...(ready.match_request_id ? { match_request_id: ready.match_request_id } : {}),
    };
  });

  test('女子: 申請→承認（5665→5663）', async ({ browser }) => {
    test.setTimeout(180000);
    if (!browser) {
      test.skip();
      return;
    }
    await runPair(browser, FEMALE_PAIR_B);
  });

  for (const pair of MALE_PAIRS) {
    test(`男子: ${pair.name}`, async ({ browser }) => {
      test.setTimeout(180000);
      if (!browser) {
        test.skip();
        return;
      }
      await runPair(browser, pair);
    });
  }
});
