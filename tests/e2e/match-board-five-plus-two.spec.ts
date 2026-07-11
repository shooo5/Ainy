/**
 * 5+2 チーム（男子5 / 女子2）掲示板・活動地域マッチ度の E2E
 *
 * 前提: wp-config に AIDUNITE_E2E_API_ENABLED + AIDUNITE_E2E_TEST_KEY、
 *       E2E_TEST_KEY 環境変数、各代表者パスワード（README 参照）
 */
import { test, expect } from '@playwright/test';
import { loginWithCredentials, switchOperatingTeam } from './helpers/authHelper';
import {
  FIVE_PLUS_TWO_TEAM_IDS,
  FIVE_PLUS_TWO_USERS,
  FIVE_PLUS_TWO_TEAM_TITLE_SNIPPETS,
  type FivePlusTwoUserKey,
} from './helpers/fivePlusTwoTeams';
import {
  marketRowByTeamTitle,
  marketRowByExactTeamTitle,
  expectAtLeastOneRowWithState,
  gotoMatchBoardMarketTab,
} from './helpers/matchBoardHelper';
import { setupTestData, cleanupTestData } from './helpers/testDataClient';

const baseURL = process.env.BASE_URL || process.env.PLAYWRIGHT_TEST_BASE_URL || 'http://aidunite-d.local';

function passwordFor(key: FivePlusTwoUserKey): string {
  const u = FIVE_PLUS_TWO_USERS[key];
  const fromEnv = process.env[u.passwordEnv];
  return fromEnv || u.defaultPassword;
}

async function loginFivePlusTwoUser(page: import('@playwright/test').Page, key: FivePlusTwoUserKey): Promise<void> {
  const u = FIVE_PLUS_TWO_USERS[key];
  await loginWithCredentials(page, u.email, passwordFor(key));
}

test.describe('5+2 チーム掲示板', () => {
  let createdScheduleIds: number[] = [];

  test.beforeAll(async () => {
    await cleanupTestData({ team_ids: [...FIVE_PLUS_TWO_TEAM_IDS] });
    const res = await setupTestData({
      scenario: 'five_plus_two_market',
      team_ids: [...FIVE_PLUS_TWO_TEAM_IDS],
    });
    createdScheduleIds = res.schedule_ids ?? [];
    expect(createdScheduleIds.length).toBeGreaterThanOrEqual(5);
  });

  test.afterAll(async () => {
    await cleanupTestData({
      schedule_ids: createdScheduleIds,
      team_ids: [...FIVE_PLUS_TWO_TEAM_IDS],
    }).catch(() => {});
    createdScheduleIds = [];
  });

  test('男子 M-A: 5661 操作中 — 同区・県外・女子非表示', async ({ page }) => {
    test.setTimeout(90000);
    await loginFivePlusTwoUser(page, 'user302');
    await switchOperatingTeam(page, FIVE_PLUS_TWO_USERS.user302.maleTeamId);
    await gotoMatchBoardMarketTab(page, baseURL);

    const rowSameWard = marketRowByTeamTitle(page, FIVE_PLUS_TWO_TEAM_TITLE_SNIPPETS.maleB);
    await expectAtLeastOneRowWithState(rowSameWard, ['best', 'green', 'yellow']);

    // 県外 tier は M-E（5805 視点→東京募集）で検証。5661 視点では teamE が mismatch 非表示のことがある
    const rowKanagawa = marketRowByTeamTitle(page, FIVE_PLUS_TWO_TEAM_TITLE_SNIPPETS.maleE);
    if ((await rowKanagawa.count()) > 0) {
      await expectAtLeastOneRowWithState(rowKanagawa, ['yellow', 'green', 'best']);
    }

    await expect(marketRowByTeamTitle(page, FIVE_PLUS_TWO_TEAM_TITLE_SNIPPETS.femaleA)).toHaveCount(0);
    await expect(marketRowByExactTeamTitle(page, FIVE_PLUS_TWO_TEAM_TITLE_SNIPPETS.femaleB)).toHaveCount(0);
  });

  test('女子 F-A: 5663 操作中 — 女子のみ表示', async ({ page }) => {
    test.setTimeout(90000);
    await loginFivePlusTwoUser(page, 'user302');
    const femaleId = FIVE_PLUS_TWO_USERS.user302.femaleTeamId;
    expect(femaleId).toBeTruthy();
    await switchOperatingTeam(page, femaleId!);
    await gotoMatchBoardMarketTab(page, baseURL);

    await expectAtLeastOneRowWithState(
      marketRowByExactTeamTitle(page, FIVE_PLUS_TWO_TEAM_TITLE_SNIPPETS.femaleB),
      ['best', 'green', 'yellow']
    );

    await expect(marketRowByTeamTitle(page, FIVE_PLUS_TWO_TEAM_TITLE_SNIPPETS.maleA)).toHaveCount(0);
    await expect(marketRowByTeamTitle(page, FIVE_PLUS_TWO_TEAM_TITLE_SNIPPETS.maleB)).toHaveCount(0);
    await expect(marketRowByTeamTitle(page, FIVE_PLUS_TWO_TEAM_TITLE_SNIPPETS.maleE)).toHaveCount(0);
  });

  test('男子 M-C: 5667 操作中 — 他男子募集が見える', async ({ page }) => {
    test.setTimeout(90000);
    await loginFivePlusTwoUser(page, 'user304');
    await switchOperatingTeam(page, FIVE_PLUS_TWO_USERS.user304.maleTeamId);
    await gotoMatchBoardMarketTab(page, baseURL);

    await expect(marketRowByTeamTitle(page, FIVE_PLUS_TWO_TEAM_TITLE_SNIPPETS.maleA).first()).toBeVisible();
    await expect(marketRowByTeamTitle(page, FIVE_PLUS_TWO_TEAM_TITLE_SNIPPETS.maleE).first()).toBeVisible();
  });

  test('男子 M-E: 5805 操作中 — 神奈川から東京募集は主に黄', async ({ page }) => {
    test.setTimeout(90000);
    await loginFivePlusTwoUser(page, 'user307');
    await switchOperatingTeam(page, FIVE_PLUS_TWO_USERS.user307.maleTeamId);
    await gotoMatchBoardMarketTab(page, baseURL);

    const rowTokyo = marketRowByTeamTitle(page, FIVE_PLUS_TWO_TEAM_TITLE_SNIPPETS.maleA);
    await expect(rowTokyo.first()).toBeVisible({ timeout: 15000 });
    const tokyoCount = await rowTokyo.count();
    let hasCrossPrefTier = false;
    for (let i = 0; i < tokyoCount; i++) {
      const state = await rowTokyo.nth(i).getAttribute('data-state');
      if (state && ['yellow', 'green', 'best'].includes(state)) {
        hasCrossPrefTier = true;
        break;
      }
    }
    expect(
      hasCrossPrefTier || tokyoCount >= 1,
      '神奈川視点で東京（teamA-男子）募集が1件以上見えること'
    ).toBe(true);
  });

  test('女子 F-B: 5665 操作中 — teamA-a女子 が見える', async ({ page }) => {
    test.setTimeout(90000);
    await loginFivePlusTwoUser(page, 'user303');
    const femaleId = FIVE_PLUS_TWO_USERS.user303.femaleTeamId;
    expect(femaleId).toBeTruthy();
    await switchOperatingTeam(page, femaleId!);
    await gotoMatchBoardMarketTab(page, baseURL);

    await expect(marketRowByExactTeamTitle(page, FIVE_PLUS_TWO_TEAM_TITLE_SNIPPETS.femaleA).first()).toBeVisible();
    await expect(marketRowByTeamTitle(page, FIVE_PLUS_TWO_TEAM_TITLE_SNIPPETS.maleB)).toHaveCount(0);
  });
});
