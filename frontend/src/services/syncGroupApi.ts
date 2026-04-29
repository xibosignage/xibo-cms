/*
 * Copyright (C) 2026 Xibo Signage Ltd
 *
 * Xibo - Digital Signage - https://xibosignage.com
 *
 * This file is part of Xibo.
 *
 * Xibo is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * any later version.
 *
 * Xibo is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with Xibo.  If not, see <http://www.gnu.org/licenses/>.
 */

import http from '@/lib/api';
import type { SyncGroup, SyncGroupDisplay } from '@/types/syncGroup';

export type { SyncGroup, SyncGroupDisplay };

export interface FetchSyncGroupsRequest {
  start: number;
  length: number;
  keyword?: string;
  leadDisplayId?: number | null;
  signal?: AbortSignal;
}

export interface FetchSyncGroupsResponse {
  rows: SyncGroup[];
  totalCount: number;
}

export async function fetchSyncGroups(
  options: FetchSyncGroupsRequest = { start: 0, length: 100 },
): Promise<FetchSyncGroupsResponse> {
  const { signal, ...queryParams } = options;

  const response = await http.get('/syncgroups', {
    params: queryParams,
    signal,
  });

  const rows = response.data;

  const totalCountHeader = response.headers['x-total-count'];
  const totalCount = totalCountHeader ? parseInt(totalCountHeader, 10) : 0;

  return {
    rows,
    totalCount,
  };
}

export async function fetchSyncGroupDisplays(
  syncGroupId: number,
  eventId?: number,
): Promise<SyncGroupDisplay[]> {
  const response = await http.get(`/syncgroup/${syncGroupId}/displays`, {
    params: eventId ? { eventId } : undefined,
  });

  return response.data?.data?.displays ?? response.data?.displays ?? response.data;
}

export interface CreateSyncGroupRequest {
  name: string;
  syncPublisherPort?: number;
  syncSwitchDelay?: number;
  syncVideoPauseDelay?: number;
  folderId?: number;
}

export async function createSyncGroup(payload: CreateSyncGroupRequest): Promise<SyncGroup> {
  const params = new URLSearchParams();

  Object.entries(payload).forEach(([key, value]) => {
    if (value !== undefined && value !== null) {
      params.append(key, String(value));
    }
  });

  const { data } = await http.post('/syncgroup/add', params, {
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
  });

  return data;
}

export interface UpdateSyncGroupRequest {
  name: string;
  syncPublisherPort?: number;
  syncSwitchDelay?: number;
  syncVideoPauseDelay?: number;
  leadDisplayId?: number;
  folderId?: number;
}

export async function updateSyncGroup(
  syncGroupId: number,
  payload: UpdateSyncGroupRequest,
): Promise<SyncGroup> {
  const params = new URLSearchParams();

  Object.entries(payload).forEach(([key, value]) => {
    if (value !== undefined && value !== null) {
      params.append(key, String(value));
    }
  });

  const { data } = await http.put(`/syncgroup/${syncGroupId}/edit`, params, {
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
  });

  return data;
}

export async function deleteSyncGroup(syncGroupId: number): Promise<void> {
  await http.delete(`/syncgroup/${syncGroupId}/delete`);
}

export async function assignSyncGroupMembers(
  syncGroupId: number,
  displayIds: number[],
  unassignDisplayIds: number[] = [],
): Promise<void> {
  const params = new URLSearchParams();
  displayIds.forEach((id) => params.append('displayId[]', String(id)));
  unassignDisplayIds.forEach((id) => params.append('unassignDisplayId[]', String(id)));

  await http.post(`/syncgroup/${syncGroupId}/members`, params.toString(), {
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
  });
}
