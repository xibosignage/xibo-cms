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

import axios from 'axios';

import { withPublicPath } from '@/config/publicPath';
import http from '@/lib/api';
import type { User } from '@/types/user';

export async function fetchCurrentUser(): Promise<User> {
  const response = await http.get<User>('/user/me');
  return response.data;
}

export interface FetchUsersRequest {
  start: number;
  length: number;
  userId?: number;
  userName?: string;
  userTypeId?: number;
  firstName?: string;
  lastName?: string;
  retired?: number;
  useRegexForName?: number;
  logicalOperatorName?: string;
  userGroupIdMembers?: number;
  sortBy?: string;
  sortDir?: string;
  signal?: AbortSignal;
}

export interface FetchUsersResponse {
  rows: User[];
  totalCount: number;
}

export async function fetchUsers(
  options: FetchUsersRequest = { start: 0, length: 10 },
): Promise<FetchUsersResponse> {
  const { signal, ...queryParams } = options;

  const response = await http.get('/user', {
    params: queryParams,
    signal,
  });

  return {
    rows: response.data,
    totalCount: parseInt(response.headers['x-total-count'] ?? '0', 10),
  };
}

export interface SavePreferenceParams {
  option: string;
  value: Record<string, unknown>;
}

export async function saveUserPreference({ option, value }: SavePreferenceParams): Promise<void> {
  const formData = new URLSearchParams();

  formData.append('preference[0][option]', option);
  formData.append('preference[0][value]', JSON.stringify(value));

  await http.post('/user/pref', formData, {
    headers: {
      'Content-Type': 'application/x-www-form-urlencoded',
    },
  });
}

export async function saveUserPreferencesBulk(preferences: Record<string, string>): Promise<void> {
  const formData = new URLSearchParams(preferences);

  await http.put('/user/pref', formData, {
    headers: {
      'Content-Type': 'application/x-www-form-urlencoded',
    },
  });
}

export async function updateUserProfile(profileData: Record<string, string>): Promise<void> {
  const formData = new URLSearchParams(profileData);

  await http.put('/user/profile/edit', formData, {
    headers: {
      'Content-Type': 'application/x-www-form-urlencoded',
    },
  });
}

export interface FetchPreferenceResponse {
  option: string;
  value: string;
}

export async function fetchUserPreference<T = Record<string, unknown>>(
  preferenceKey: string,
): Promise<T | null> {
  const response = await http.get<FetchPreferenceResponse>('/user/pref', {
    params: { preference: preferenceKey },
  });

  const valueString = response.data?.value;

  if (valueString) {
    try {
      return JSON.parse(valueString);
    } catch (error) {
      console.error('Failed to parse user preference:', error);
      return null;
    }
  }

  return null;
}

// 2FA
export async function fetch2FASetup(): Promise<{ qRUrl: string } | null> {
  const response = await http.get('/user/profile/setup');
  return response.data || null;
}

export async function generate2FARecoveryCodes(): Promise<string[]> {
  const response = await http.get('/user/profile/recoveryGenerate');
  const rawCodes = response.data?.codes;
  return rawCodes ? JSON.parse(rawCodes) : [];
}

export async function fetch2FARecoveryCodes(): Promise<string[]> {
  const response = await http.get('/user/profile/recoveryShow');
  return response.data?.codes || [];
}

// User Apps
export interface UserApplication {
  id: number;
  name: string;
  approvedDate: string;
  approvedIp: string;
}

export async function fetchUserApplications(userId: number): Promise<UserApplication[]> {
  const response = await http.get(`/user/${userId}/applications`);

  return response.data || [];
}

export async function revokeApplicationAccess(clientId: number, userId: number): Promise<void> {
  await http.delete(`/application/revoke/${clientId}/${userId}`);
}

// Create User
export interface CreateUserPayload {
  userName: string;
  password: string;
  email?: string;
  userTypeId: number;
  groupId: number;
  homePageId?: string;
  libraryQuota?: number;
  firstName?: string;
  lastName?: string;
  phone?: string;
  ref1?: string;
  ref2?: string;
  ref3?: string;
  ref4?: string;
  ref5?: string;
  isPasswordChangeRequired?: number;
  newUserWizard?: number;
  hideNavigation?: number;
}

export async function createUser(payload: CreateUserPayload): Promise<User> {
  const formData = new URLSearchParams();

  Object.entries(payload).forEach(([key, value]) => {
    if (value !== undefined && value !== null && value !== '') {
      formData.append(key, String(value));
    }
  });

  const response = await http.post('/user', formData, {
    headers: {
      'Content-Type': 'application/x-www-form-urlencoded',
    },
  });

  return response.data;
}

// Update User
export interface UpdateUserPayload {
  userName: string;
  email?: string;
  userTypeId?: number;
  homePageId?: string;
  libraryQuota?: number;
  retired?: number;
  homeFolderId?: number;
  firstName?: string;
  lastName?: string;
  phone?: string;
  ref1?: string;
  ref2?: string;
  ref3?: string;
  ref4?: string;
  ref5?: string;
  newUserWizard?: number;
  hideNavigation?: number;
  isPasswordChangeRequired?: number;
  newPassword?: string;
  retypeNewPassword?: string;
  disableTwoFactor?: number;
  isSystemNotification?: number;
  isDisplayNotification?: number;
  isDataSetNotification?: number;
  isCustomNotification?: number;
  isLayoutNotification?: number;
  isLibraryNotification?: number;
  isReportNotification?: number;
  isScheduleNotification?: number;
}

export async function updateUser(userId: number, payload: UpdateUserPayload): Promise<User> {
  const formData = new URLSearchParams();

  Object.entries(payload).forEach(([key, value]) => {
    if (value !== undefined && value !== null && value !== '') {
      formData.append(key, String(value));
    }
  });

  const response = await http.put(`/user/${userId}`, formData, {
    headers: {
      'Content-Type': 'application/x-www-form-urlencoded',
    },
  });

  return response.data;
}

// Delete User
export interface DeleteUserOptions {
  deleteAllItems?: number;
  reassignUserId?: number;
}

export async function deleteUser(userId: number, options?: DeleteUserOptions): Promise<void> {
  const params: Record<string, string> = {};

  if (options?.deleteAllItems !== undefined) {
    params.deleteAllItems = String(options.deleteAllItems);
  }

  if (options?.reassignUserId !== undefined) {
    params.reassignUserId = String(options.reassignUserId);
  }

  await http.delete(`/user/${userId}`, { params });
}

// Set Home Folder
export async function setUserHomeFolder(userId: number, homeFolderId: number): Promise<void> {
  const formData = new URLSearchParams();
  formData.append('homeFolderId', String(homeFolderId));

  await http.post(`/user/${userId}/setHomeFolder`, formData, {
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
  });
}

// Assign User Groups
export async function assignUserGroups(
  userId: number,
  toAdd: number[],
  toRemove: number[],
): Promise<void> {
  const formData = new URLSearchParams();
  toAdd.forEach((id) => formData.append('userGroupId[]', String(id)));
  toRemove.forEach((id) => formData.append('unassignUserGroupId[]', String(id)));

  await http.post(`/user/${userId}/usergroup/assign`, formData, {
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
  });
}

// Homepages
export interface Homepage {
  homepage: string;
  title: string;
  feature?: string;
  description?: string;
}

export async function fetchHomepages(
  params: {
    userId?: number;
    userTypeId?: number;
    groupId?: number;
  },
  signal?: AbortSignal,
): Promise<Homepage[]> {
  // This is a web route (served at the install root, not under the /json API
  // prefix), so it must be called with the raw axios client + withPublicPath.
  const response = await axios.get(withPublicPath('user/form/homepages'), {
    params,
    signal,
    withCredentials: true,
    // This web route only returns JSON for XHR requests (Base::isXhr); without
    // this header the CMS renders a full HTML page and 500s.
    headers: { 'X-Requested-With': 'XMLHttpRequest' },
  });

  // Web grid routes wrap rows in a DataTables envelope ({ data: [...] });
  // fall back to the payload itself in case it is already an array.
  const payload = response.data;
  return Array.isArray(payload) ? payload : (payload?.data ?? []);
}
