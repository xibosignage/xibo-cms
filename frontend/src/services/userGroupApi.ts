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
import type { UserGroup } from '@/types/userGroup';

export interface FetchUserGroupsRequest {
  start: number;
  length: number;
  userGroup?: string;
  isUser?: number;
  signal?: AbortSignal;
}

export interface FetchUserGroupsResponse {
  rows: UserGroup[];
  totalCount: number;
}

export async function fetchUserGroups(
  { start = 0, length = 10, userGroup, isUser, signal }: FetchUserGroupsRequest = {
    start: 0,
    length: 10,
  },
): Promise<FetchUserGroupsResponse> {
  const params: Record<string, string | number> = {
    start,
    length,
  };

  if (userGroup) {
    params.userGroup = userGroup;
  }

  if (isUser !== undefined) {
    params.isUser = isUser;
  }

  const response = await http.get<UserGroup[]>('/group', {
    params,
    signal,
  });

  const totalCountHeader = response.headers['x-total-count'];

  return {
    rows: response.data,
    totalCount: totalCountHeader ? parseInt(totalCountHeader, 10) : 0,
  };
}
