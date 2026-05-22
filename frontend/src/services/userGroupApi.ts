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
import type { User } from '@/types/user';
import type { UserGroup } from '@/types/userGroup';

export interface FetchUserGroupsRequest {
  start: number;
  length: number;
  userGroupId?: number;
  userGroup?: string;
  keyword?: string;
  isUser?: number;
  isShownForAddUser?: number;
  sortBy?: string;
  sortDir?: string;
  logicalOperatorName?: string;
  useRegexForName?: number;
  signal?: AbortSignal;
}

export interface FetchUserGroupsResponse {
  rows: UserGroup[];
  totalCount: number;
}

export async function fetchUserGroups(
  options: FetchUserGroupsRequest = { start: 0, length: 10 },
): Promise<FetchUserGroupsResponse> {
  const { signal, ...queryParams } = options;

  const response = await http.get<UserGroup[]>('/group', {
    params: queryParams,
    signal,
  });

  const totalCountHeader = response.headers['x-total-count'];

  return {
    rows: response.data,
    totalCount: totalCountHeader ? parseInt(totalCountHeader, 10) : 0,
  };
}

export async function fetchUserGroupById(groupId: number): Promise<UserGroup> {
  const response = await http.get<UserGroup>(`/group/${groupId}`);
  return response.data;
}

export async function fetchUserGroupMembers(groupId: number): Promise<User[]> {
  const response = await http.get<UserGroup>(`/group/${groupId}`);
  return response.data?.users ?? [];
}

export interface CreateUserGroupPayload {
  group: string;
  description?: string;
  libraryQuota?: number;
  isSystemNotification?: number;
  isDisplayNotification?: number;
  isDataSetNotification?: number;
  isCustomNotification?: number;
  isLayoutNotification?: number;
  isLibraryNotification?: number;
  isReportNotification?: number;
  isScheduleNotification?: number;
  isShownForAddUser?: number;
  defaultHomepageId?: string;
}

export type UpdateUserGroupPayload = CreateUserGroupPayload;

function serializePayload(payload: object): URLSearchParams {
  const params = new URLSearchParams();
  Object.entries(payload).forEach(([key, value]) => {
    if (value !== undefined && value !== null && value !== '') {
      params.append(key, String(value));
    }
  });
  return params;
}

export async function createUserGroup(payload: CreateUserGroupPayload): Promise<UserGroup> {
  const response = await http.post('/group', serializePayload(payload), {
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
  });
  return response.data;
}

export async function updateUserGroup(
  groupId: number,
  payload: UpdateUserGroupPayload,
): Promise<UserGroup> {
  const response = await http.put(`/group/${groupId}`, serializePayload(payload), {
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
  });
  return response.data;
}

export async function deleteUserGroup(groupId: number): Promise<void> {
  await http.delete(`/group/${groupId}`);
}

export interface CopyUserGroupPayload {
  group: string;
  copyMembers?: number;
  copyFeatures?: number;
}

export async function copyUserGroup(
  groupId: number,
  payload: CopyUserGroupPayload,
): Promise<UserGroup> {
  const response = await http.post(`/group/${groupId}/copy`, serializePayload(payload), {
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
  });
  return response.data;
}

export async function assignGroupMembers(
  groupId: number,
  toAdd: number[],
  toRemove: number[],
): Promise<void> {
  const formData = new URLSearchParams();
  toAdd.forEach((id) => formData.append('userId[]', String(id)));
  toRemove.forEach((id) => formData.append('unassignUserId[]', String(id)));

  await http.post(`/group/members/assign/${groupId}`, formData, {
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
  });
}

export async function updateGroupFeatures(groupId: number, features: string[]): Promise<void> {
  const formData = new URLSearchParams();
  features.forEach((f) => formData.append('features[]', f));

  await http.post(`/group/acl/${groupId}`, formData, {
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
  });
}
