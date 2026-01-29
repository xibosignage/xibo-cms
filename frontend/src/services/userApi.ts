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
