/**
 * E2E テストデータ API クライアント
 * setup / cleanup を呼び出す（X-E2E-Test-Key ヘッダ認証、Cookie 不要）
 */

const baseURL = process.env.BASE_URL || process.env.PLAYWRIGHT_TEST_BASE_URL || 'http://aidunite-d.local';

const FETCH_TIMEOUT_MS = 120000;
const RETRYABLE_STATUSES = new Set([502, 503, 504, 429]);
const MAX_ATTEMPTS = 4;

/** 環境変数 E2E_TEST_KEY または PLAYWRIGHT_E2E_TEST_KEY（wp-config の AIDUNITE_E2E_TEST_KEY と一致させる） */
function getE2ETestKey(): string {
  const key = process.env.E2E_TEST_KEY || process.env.PLAYWRIGHT_E2E_TEST_KEY || '';
  return key;
}

function sleep(ms: number): Promise<void> {
  return new Promise((resolve) => setTimeout(resolve, ms));
}

async function parseResponseBody(res: Response): Promise<{ data: Record<string, unknown>; raw: string }> {
  const raw = await res.text();
  if (!raw.trim()) {
    return { data: {}, raw };
  }
  try {
    return { data: JSON.parse(raw) as Record<string, unknown>, raw };
  } catch {
    const snippet = raw.replace(/\s+/g, ' ').slice(0, 280);
    throw new Error(
      `E2E API returned non-JSON (HTTP ${res.status}). Local が 502/タイムアウトの可能性があります。本文: ${snippet}`
    );
  }
}

async function postE2eApi(
  path: string,
  body: Record<string, unknown>,
  options: { requiredKey?: boolean; throwOnError?: boolean } = {}
): Promise<{ res: Response; data: Record<string, unknown> }> {
  const { requiredKey = false, throwOnError = true } = options;
  const key = getE2ETestKey();
  if (requiredKey && !key) {
    throw new Error('E2E_TEST_KEY or PLAYWRIGHT_E2E_TEST_KEY must be set for test-data setup');
  }

  const url = getApiBase() + path;
  let lastError: Error | null = null;

  for (let attempt = 1; attempt <= MAX_ATTEMPTS; attempt++) {
    const controller = new AbortController();
    const timer = setTimeout(() => controller.abort(), FETCH_TIMEOUT_MS);
    try {
      const res = await fetch(url, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          ...(key ? { 'X-E2E-Test-Key': key } : {}),
        },
        body: JSON.stringify(body),
        signal: controller.signal,
      });

      let data: Record<string, unknown>;
      try {
        ({ data } = await parseResponseBody(res));
      } catch (parseErr) {
        lastError = parseErr instanceof Error ? parseErr : new Error(String(parseErr));
        if (attempt < MAX_ATTEMPTS) {
          await sleep(1500 * attempt);
          continue;
        }
        throw lastError;
      }

      if (!res.ok) {
        const msg = `E2E API ${path} failed: HTTP ${res.status} ${JSON.stringify(data)}`;
        lastError = new Error(msg);
        if (attempt < MAX_ATTEMPTS && RETRYABLE_STATUSES.has(res.status)) {
          await sleep(1500 * attempt);
          continue;
        }
        if (throwOnError) {
          throw lastError;
        }
        return { res, data };
      }

      return { res, data };
    } catch (err) {
      if (err instanceof Error && err.name === 'AbortError') {
        lastError = new Error(`E2E API ${path} timed out after ${FETCH_TIMEOUT_MS}ms`);
      } else {
        lastError = err instanceof Error ? err : new Error(String(err));
      }
      if (attempt < MAX_ATTEMPTS) {
        await sleep(1500 * attempt);
        continue;
      }
      if (throwOnError) {
        throw lastError;
      }
      return { res: new Response(null, { status: 599 }), data: {} };
    } finally {
      clearTimeout(timer);
    }
  }

  if (throwOnError && lastError) {
    throw lastError;
  }
  return { res: new Response(null, { status: 599 }), data: {} };
}

export type SetupParams = {
  scenario: string;
  teamA_email?: string;
  teamB_email?: string;
  team_ids?: number[];
  match_date?: string;
  start_time?: string;
  end_time?: string;
  gender?: string;
  place_type?: string;
  create_chat?: boolean;
  create_survey_state?: boolean;
  /** five_plus_two_market: team_id => 試合日の加算日数 */
  team_date_offsets?: Record<number, number>;
  /** 2チーム setup: 募集側（teamA）の team_id 上書き（マルチチーム用） */
  teamA_team_id?: number;
  /** 2チーム setup: 申請側（teamB）の team_id 上書き */
  teamB_team_id?: number;
};

export type SetupResponse = {
  success: boolean;
  scenario?: string;
  current_status?: string;
  schedule_ids?: number[];
  match_request_id?: number | null;
  chat_room_id?: number | null;
  survey_response_id?: number | null;
  match_detail_url?: string;
  team_chat_url?: string;
  survey_url?: string;
  message?: string;
  teams?: { team_id: number; schedule_id: number; leader_id?: number; gender?: string }[];
  team_a_team_id?: number;
  team_b_team_id?: number;
};

export type CleanupParams = {
  schedule_ids?: number[];
  match_request_id?: number;
  chat_room_id?: number;
  survey_response_id?: number;
  teamA_email?: string;
  teamB_email?: string;
  team_ids?: number[];
};

export function getApiBase(): string {
  return baseURL.replace(/\/$/, '') + '/wp-json/aidunite/v1';
}

export async function setupTestData(
  params: SetupParams,
  _cookies?: { name: string; value: string }[]
): Promise<SetupResponse> {
  const { data } = await postE2eApi(
    '/test-data/setup',
    {
      scenario: params.scenario,
      teamA_email: params.teamA_email ?? 'shogo.saito@aidunite-inc.com',
      teamB_email: params.teamB_email ?? 's.shogo1018@gmail.com',
      match_date: params.match_date,
      start_time: params.start_time,
      end_time: params.end_time,
      team_ids: params.team_ids,
      gender: params.gender,
      place_type: params.place_type,
      create_chat: params.create_chat !== false,
      create_survey_state: params.create_survey_state === true,
      team_date_offsets: params.team_date_offsets,
      teamA_team_id: params.teamA_team_id,
      teamB_team_id: params.teamB_team_id,
    },
    { requiredKey: true, throwOnError: true }
  );
  return data as SetupResponse;
}

export async function cleanupTestData(
  params: CleanupParams,
  _cookies?: { name: string; value: string }[]
): Promise<void> {
  const hasTarget =
    (params.schedule_ids && params.schedule_ids.length > 0) ||
    params.match_request_id ||
    params.chat_room_id ||
    params.survey_response_id ||
    (params.team_ids && params.team_ids.length > 0) ||
    params.teamA_email ||
    params.teamB_email;

  if (!hasTarget) {
    return;
  }

  const { res, data } = await postE2eApi('/test-data/cleanup', params, {
    requiredKey: false,
    throwOnError: false,
  });

  if (!res.ok) {
    console.warn('cleanup warning:', res.status, data);
  }
}

export type ChatRoomSnapshot = {
  id: number;
  status: string;
  match_id: number;
  room_type: string;
  schedule_id: number;
};

export type MatchRequestChatRoomsResponse = {
  success: boolean;
  match_request_id: number;
  bound_room_id: number;
  active_room_id: number;
  completed_room_ids: number[];
  active_room_ids: number[];
  rooms: ChatRoomSnapshot[];
  message?: string;
};

async function getE2eApi(
  path: string,
  options: { requiredKey?: boolean } = {}
): Promise<{ res: Response; data: Record<string, unknown> }> {
  const { requiredKey = true } = options;
  const key = getE2ETestKey();
  if (requiredKey && !key) {
    throw new Error('E2E_TEST_KEY must be set for test-data GET');
  }

  const url = getApiBase() + path;
  const controller = new AbortController();
  const timer = setTimeout(() => controller.abort(), FETCH_TIMEOUT_MS);
  try {
    const res = await fetch(url, {
      method: 'GET',
      headers: {
        ...(key ? { 'X-E2E-Test-Key': key } : {}),
      },
      signal: controller.signal,
    });
    const { data } = await parseResponseBody(res);
    if (!res.ok) {
      throw new Error(`E2E API GET ${path} failed: HTTP ${res.status} ${JSON.stringify(data)}`);
    }
    return { res, data };
  } finally {
    clearTimeout(timer);
  }
}

/** MR に紐づく chat_rooms の DB スナップショット（ルーム ID・status 検証用） */
export async function fetchMatchRequestChatRooms(
  matchRequestId: number
): Promise<MatchRequestChatRoomsResponse> {
  const { data } = await getE2eApi(`/test-data/match-request/${matchRequestId}/chat-rooms`);
  return data as MatchRequestChatRoomsResponse;
}
