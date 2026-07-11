/**
 * 5+2 チーム掲示板 E2E 用の固定 ID（ローカル DB 報告値）
 */
export const FIVE_PLUS_TWO_TEAM_IDS = [5661, 5663, 5665, 5667, 5680, 5803, 5805] as const;

export type FivePlusTwoUserKey = 'user302' | 'user303' | 'user304' | 'user306' | 'user307';

export const FIVE_PLUS_TWO_USERS: Record<
  FivePlusTwoUserKey,
  { email: string; passwordEnv: string; defaultPassword: string; maleTeamId: number; femaleTeamId?: number }
> = {
  user302: {
    email: 'shogo.saito@aidunite-inc.com',
    passwordEnv: 'E2E_TEAM_A_PASSWORD',
    defaultPassword: 'TestPassword123!',
    maleTeamId: 5661,
    femaleTeamId: 5663,
  },
  user303: {
    email: 's.shogo1018@gmail.com',
    passwordEnv: 'E2E_TEAM_B_PASSWORD',
    defaultPassword: 'TestPassword123!',
    maleTeamId: 5680,
    femaleTeamId: 5665,
  },
  user304: {
    email: 's.shogo13@gmail.com',
    passwordEnv: 'E2E_USER_304_PASSWORD',
    defaultPassword: 'TestPassword123!',
    maleTeamId: 5667,
  },
  user306: {
    email: 'test04@ainy.local',
    passwordEnv: 'E2E_USER_306_PASSWORD',
    defaultPassword: 'TestPassword123!',
    maleTeamId: 5803,
  },
  user307: {
    email: 'test05@ainy.local',
    passwordEnv: 'E2E_USER_307_PASSWORD',
    defaultPassword: 'TestPassword123!',
    maleTeamId: 5805,
  },
};

/** 掲示板行の title 属性用（チーム名の一部で特定） */
export const FIVE_PLUS_TWO_TEAM_TITLE_SNIPPETS: Record<string, string> = {
  maleA: 'teamA-男子',
  maleB: 'teamB-b男子',
  maleC: 'teamC',
  maleD: 'teamD-d男子',
  maleE: 'teamE-e男子',
  femaleA: 'teamA-a女子',
  /** 掲示板 link の title 属性（表示短縮は teamB だが title は投稿名） */
  femaleB: 'teamB',
};
