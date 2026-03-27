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
import type { Daypart } from '@/types/daypart';

export interface FetchDaypartRequest {
  start: number;
  length: number;
  keyword?: string;
  isRetired?: number | null;
  sortBy?: string;
  sortDir?: string;
  signal?: AbortSignal;
}

export interface FetchDaypartResponse {
  rows: Daypart[];
  totalCount: number;
}

export async function fetchDaypart(
  options: FetchDaypartRequest = { start: 0, length: 10 },
): Promise<FetchDaypartResponse> {
  const { signal, ...queryParams } = options;

  const response = await http.get('/daypart', {
    params: { ...queryParams, embed: 'exceptions' },
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

export interface UpdateDaypartRequest {
  name: string;
  description?: string;
  isRetired?: number;
  startTime: string;
  endTime: string;
  exceptionDays?: string[];
  exceptionStartTimes?: string[];
  exceptionEndTimes?: string[];
}

export async function createDaypart(data: UpdateDaypartRequest): Promise<Daypart> {
  const params = new URLSearchParams();

  params.append('name', data.name);
  if (data.description) params.append('description', data.description);
  params.append('isRetired', String(data.isRetired ?? 0));
  params.append('startTime', data.startTime);
  params.append('endTime', data.endTime);

  data.exceptionDays?.forEach((day) => params.append('exceptionDays[]', day));
  data.exceptionStartTimes?.forEach((t) => params.append('exceptionStartTimes[]', t));
  data.exceptionEndTimes?.forEach((t) => params.append('exceptionEndTimes[]', t));

  const response = await http.post('/daypart', params.toString(), {
    headers: {
      'Content-Type': 'application/x-www-form-urlencoded',
      'X-Requested-With': 'XMLHttpRequest',
    },
  });

  return response.data;
}

export async function updateDaypart(
  daypartId: number | string,
  data: UpdateDaypartRequest,
): Promise<Daypart> {
  const params = new URLSearchParams();

  params.append('name', data.name);
  if (data.description) params.append('description', data.description);
  params.append('isRetired', String(data.isRetired ?? 0));
  params.append('startTime', data.startTime);
  params.append('endTime', data.endTime);

  data.exceptionDays?.forEach((day) => params.append('exceptionDays[]', day));
  data.exceptionStartTimes?.forEach((t) => params.append('exceptionStartTimes[]', t));
  data.exceptionEndTimes?.forEach((t) => params.append('exceptionEndTimes[]', t));

  const response = await http.put(`/daypart/${daypartId}`, params.toString(), {
    headers: {
      'Content-Type': 'application/x-www-form-urlencoded',
      'X-Requested-With': 'XMLHttpRequest',
    },
  });

  return response.data;
}

export async function deleteDaypart(daypartId: number | string): Promise<void> {
  await http.delete(`/daypart/${daypartId}`, {
    headers: { 'X-Requested-With': 'XMLHttpRequest' },
  });
}
