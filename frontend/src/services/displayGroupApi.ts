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
import type { Display } from '@/types/display';
import type { DisplayGroup } from '@/types/displayGroup';

export interface FetchDisplayGroupRequest {
  start: number;
  length: number;
  displayGroup?: string;
  folderId?: number | null;
  displayId?: number;
  nestedDisplayId?: number;
  dynamicCriteria?: string;
  tags?: string;
  sortBy?: string;
  sortDir?: string;
  signal?: AbortSignal;
  keyword?: string;
  isDisplaySpecific?: number;
}

export interface FetchDisplayGroupsResponse {
  rows: DisplayGroup[];
  totalCount: number;
}

export async function fetchDisplayGroups(
  options: FetchDisplayGroupRequest = { start: 0, length: 10 },
): Promise<FetchDisplayGroupsResponse> {
  const { signal, ...queryParams } = options;

  const response = await http.get('/displaygroup', {
    params: queryParams,
    signal,
  });

  const rows = response.data;
  const totalCountHeader = response.headers['x-total-count'];
  const totalCount = totalCountHeader ? parseInt(totalCountHeader, 10) : 0;

  return { rows, totalCount };
}

export interface CreateDisplayGroupRequest {
  displayGroup: string;
  description?: string;
  tags?: string;
  isDynamic?: number;
  dynamicCriteria?: string;
  logicalOperatorName?: string;
  dynamicCriteriaTags?: string;
  exactTags?: number;
  logicalOperator?: string;
  folderId?: number | null;
}

export async function createDisplayGroup(data: CreateDisplayGroupRequest): Promise<DisplayGroup> {
  const params = new URLSearchParams();
  params.append('displayGroup', data.displayGroup);
  if (data.description !== undefined) params.append('description', data.description);
  if (data.tags !== undefined) params.append('tags', data.tags);
  if (data.isDynamic !== undefined) params.append('isDynamic', String(data.isDynamic));
  if (data.dynamicCriteria !== undefined) params.append('dynamicCriteria', data.dynamicCriteria);
  if (data.logicalOperatorName !== undefined)
    params.append('logicalOperatorName', data.logicalOperatorName);
  if (data.dynamicCriteriaTags !== undefined)
    params.append('dynamicCriteriaTags', data.dynamicCriteriaTags);
  if (data.exactTags !== undefined) params.append('exactTags', String(data.exactTags));
  if (data.logicalOperator !== undefined) params.append('logicalOperator', data.logicalOperator);
  if (data.folderId != null) params.append('folderId', String(data.folderId));

  const { data: responseData } = await http.post('/displaygroup', params.toString(), {
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
  });
  return responseData;
}

export interface UpdateDisplayGroupRequest {
  displayGroup: string;
  description?: string;
  tags?: string;
  isDynamic?: number;
  dynamicCriteria?: string;
  logicalOperatorName?: string;
  dynamicCriteriaTags?: string;
  exactTags?: number;
  logicalOperator?: string;
  folderId?: number | null;
  ref1?: string;
  ref2?: string;
  ref3?: string;
  ref4?: string;
  ref5?: string;
}

export async function updateDisplayGroup(
  displayGroupId: number,
  data: UpdateDisplayGroupRequest,
): Promise<DisplayGroup> {
  const params = new URLSearchParams();
  params.append('displayGroup', data.displayGroup);
  if (data.description !== undefined) params.append('description', data.description);
  if (data.tags !== undefined) params.append('tags', data.tags);
  if (data.isDynamic !== undefined) params.append('isDynamic', String(data.isDynamic));
  if (data.dynamicCriteria !== undefined) params.append('dynamicCriteria', data.dynamicCriteria);
  if (data.logicalOperatorName !== undefined)
    params.append('logicalOperatorName', data.logicalOperatorName);
  if (data.dynamicCriteriaTags !== undefined)
    params.append('dynamicCriteriaTags', data.dynamicCriteriaTags);
  if (data.exactTags !== undefined) params.append('exactTags', String(data.exactTags));
  if (data.logicalOperator !== undefined) params.append('logicalOperator', data.logicalOperator);
  if (data.folderId != null) params.append('folderId', String(data.folderId));
  if (data.ref1 !== undefined) params.append('ref1', data.ref1);
  if (data.ref2 !== undefined) params.append('ref2', data.ref2);
  if (data.ref3 !== undefined) params.append('ref3', data.ref3);
  if (data.ref4 !== undefined) params.append('ref4', data.ref4);
  if (data.ref5 !== undefined) params.append('ref5', data.ref5);

  const { data: responseData } = await http.put(
    `/displaygroup/${displayGroupId}`,
    params.toString(),
    { headers: { 'Content-Type': 'application/x-www-form-urlencoded' } },
  );
  return responseData;
}

export async function fetchDisplaysAssigned(displayGroupId: number): Promise<Display[]> {
  const response = await http.get(`/displaygroup/${displayGroupId}/displays`);
  return response.data ?? [];
}

export async function fetchDisplayGroupsAssigned(displayGroupId: number): Promise<DisplayGroup[]> {
  const response = await http.get(`/displaygroup/${displayGroupId}/displayGroups`);
  return response.data ?? [];
}

export async function fetchDisplayGroupRelationshipTree(
  displayGroupId: number,
): Promise<DisplayGroup[]> {
  const response = await http.get(`/displaygroup/${displayGroupId}/relationshiptree`);
  return response.data ?? [];
}

export async function assignDisplaysToGroup(
  displayGroupId: number,
  displayIds: number[],
  unassignDisplayIds: number[] = [],
): Promise<void> {
  const params = new URLSearchParams();
  displayIds.forEach((id) => params.append('displayId[]', String(id)));
  unassignDisplayIds.forEach((id) => params.append('unassignDisplayId[]', String(id)));
  await http.post(`/displaygroup/${displayGroupId}/display/assign`, params.toString(), {
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
  });
}

export async function assignDisplayGroupsToGroup(
  displayGroupId: number,
  memberGroupIds: number[],
  unassignGroupIds: number[] = [],
): Promise<void> {
  const params = new URLSearchParams();
  memberGroupIds.forEach((id) => params.append('displayGroupId[]', String(id)));
  unassignGroupIds.forEach((id) => params.append('unassignDisplayGroupId[]', String(id)));
  await http.post(`/displaygroup/${displayGroupId}/displayGroup/assign`, params.toString(), {
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
  });
}

export async function deleteDisplayGroup(displayGroupId: number | string): Promise<void> {
  await http.delete(`/displaygroup/${displayGroupId}`, {
    headers: { 'X-Requested-With': 'XMLHttpRequest' },
  });
}

export interface CopyDisplayGroupRequest {
  displayGroup: string;
  description?: string;
  copyMembers?: number;
  copyAssignments?: number;
  copyTags?: number;
}

export async function collectNow(displayGroupId: number | string): Promise<void> {
  await http.post(`/displaygroup/${displayGroupId}/action/collectNow`);
}

export async function copyDisplayGroup(
  displayGroupId: number | string,
  data: CopyDisplayGroupRequest,
): Promise<DisplayGroup> {
  const params = new URLSearchParams();
  params.append('displayGroup', data.displayGroup);
  if (data.description !== undefined) params.append('description', data.description);
  if (data.copyMembers !== undefined) params.append('copyMembers', String(data.copyMembers));
  if (data.copyAssignments !== undefined)
    params.append('copyAssignments', String(data.copyAssignments));
  if (data.copyTags !== undefined) params.append('copyTags', String(data.copyTags));

  const response = await http.post(`/displaygroup/${displayGroupId}/copy`, params.toString(), {
    headers: {
      'Content-Type': 'application/x-www-form-urlencoded',
      'X-Requested-With': 'XMLHttpRequest',
    },
  });

  return response.data;
}
