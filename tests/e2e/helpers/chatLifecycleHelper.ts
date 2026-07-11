/**
 * チャットルーム lifecycle（キャンセル→再申請→再承認）の E2E 補助
 * アサーションは spec 側で記述（.cursorrules）
 */
import { Page } from '@playwright/test';
import {
  fetchMatchRequestChatRooms,
  MatchRequestChatRoomsResponse,
} from './testDataClient';

const baseURL = process.env.BASE_URL || process.env.PLAYWRIGHT_TEST_BASE_URL || 'http://aidunite-d.local';

export type ChatLifecycleSnapshot = {
  matchRequestId: number;
  boundRoomId: number;
  activeRoomId: number;
  completedRoomIds: number[];
  activeRoomIds: number[];
  rooms: MatchRequestChatRoomsResponse['rooms'];
};

export async function loadChatLifecycleSnapshot(
  matchRequestId: number
): Promise<ChatLifecycleSnapshot> {
  const data = await fetchMatchRequestChatRooms(matchRequestId);
  return {
    matchRequestId: data.match_request_id,
    boundRoomId: data.bound_room_id ?? 0,
    activeRoomId: data.active_room_id ?? 0,
    completedRoomIds: data.completed_room_ids ?? [],
    activeRoomIds: data.active_room_ids ?? [],
    rooms: data.rooms ?? [],
  };
}

/** 再承認後: completed が1件以上あり、active は1件のみで completed と異なる */
export function summarizeForkExpectation(snapshot: ChatLifecycleSnapshot): {
  completedCount: number;
  activeCount: number;
  latestActiveId: number;
  latestCompletedId: number;
} {
  const completed = snapshot.completedRoomIds;
  const active = snapshot.activeRoomIds;
  return {
    completedCount: completed.length,
    activeCount: active.length,
    latestActiveId: active.length ? Math.max(...active) : 0,
    latestCompletedId: completed.length ? Math.max(...completed) : 0,
  };
}
