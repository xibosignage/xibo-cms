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

export interface FetchUsersRequest {
  start: number;
  length: number;
  userName?: string;
  userType?: string;
  signal?: AbortSignal;
}

export interface FetchUsersResponse {
  rows: User[];
  totalCount: number;
}

export async function fetchUsers(
  options: FetchUsersRequest = { start: 0, length: 10 },
): Promise<FetchUsersResponse> {
  const response = await http.get('/user', {
    params: options,
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
