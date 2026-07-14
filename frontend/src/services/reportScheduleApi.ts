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
import type { ReportSchedule } from '@/types/reportSchedule';
import { formatDateTime } from '@/utils/date';

export interface FetchReportSchedulesRequest {
  start?: number;
  length?: number;
  sortBy?: string;
  sortDir?: string;
  keyword?: string;
  name?: string;
  useRegexForName?: number;
  logicalOperatorName?: 'OR' | 'AND';
  userId?: number;
  reportScheduleId?: number;
  reportName?: string;
  onlyMySchedules?: number;
  signal?: AbortSignal;
}

export interface FetchReportSchedulesResponse {
  rows: ReportSchedule[];
  totalCount: number;
}

export async function fetchReportSchedules(
  options: FetchReportSchedulesRequest = {},
): Promise<FetchReportSchedulesResponse> {
  const { signal, ...queryParams } = options;

  const response = await http.get('/report/reportschedule', {
    params: queryParams,
    signal,
  });

  const rows = response.data;
  const totalCountHeader = response.headers['x-total-count'];
  const totalCount = totalCountHeader ? parseInt(totalCountHeader, 10) : rows.length;

  return { rows, totalCount };
}

export interface UpdateReportSchedulePayload {
  name: string;
  fromDt?: string;
  toDt?: string;
}

export async function updateReportSchedule(
  id: number,
  payload: UpdateReportSchedulePayload,
): Promise<ReportSchedule> {
  const params = new URLSearchParams();
  params.append('name', payload.name);
  if (payload.fromDt) {
    params.append('fromDt', formatDateTime(new Date(payload.fromDt)));
  }
  if (payload.toDt) {
    params.append('toDt', formatDateTime(new Date(payload.toDt)));
  }

  const { data } = await http.put(`/report/reportschedule/${id}`, params.toString(), {
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
  });
  return data;
}

export interface CreateReportSchedulePayload {
  name: string;
  reportName: string;
  filter: string;
  groupByFilter: string;
  displayGroupIds: number[];
  fromDt?: string;
  toDt?: string;
  sendEmail?: boolean;
  nonusers?: string;
  displayId?: number | null;
  displayGroupId?: number[];
  userId?: number | null;
  groupId?: number | null;
  hiddenFields?: Record<string, unknown>;
}

export async function createReportSchedule(
  payload: CreateReportSchedulePayload,
): Promise<ReportSchedule> {
  const params = new URLSearchParams();
  params.append('name', payload.name);
  params.append('reportName', payload.reportName);
  params.append('filter', payload.filter);
  params.append('groupByFilter', payload.groupByFilter);
  payload.displayGroupIds.forEach((id) => params.append('displayGroupIds[]', String(id)));
  if (payload.displayId) {
    params.append('displayId', String(payload.displayId));
  }
  payload.displayGroupId?.forEach((id) => params.append('displayGroupId[]', String(id)));
  if (payload.userId) {
    params.append('userId', String(payload.userId));
  }
  if (payload.groupId) {
    params.append('groupId', String(payload.groupId));
  }
  if (payload.hiddenFields) {
    params.append('hiddenFields', JSON.stringify(payload.hiddenFields));
    for (const [key, value] of Object.entries(payload.hiddenFields)) {
      if (value == null) continue;
      if (Array.isArray(value)) {
        value.forEach((item) => params.append(`${key}[]`, String(item)));
      } else {
        params.append(key, String(value));
      }
    }
  }
  if (payload.fromDt) {
    params.append('fromDt', formatDateTime(new Date(payload.fromDt)));
  }
  if (payload.toDt) {
    params.append('toDt', formatDateTime(new Date(payload.toDt)));
  }
  params.append('sendEmail', payload.sendEmail ? '1' : '0');
  if (payload.nonusers) {
    params.append('nonusers', payload.nonusers);
  }

  const { data } = await http.post('/report/reportschedule', params.toString(), {
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
  });
  return data;
}

export async function deleteReportSchedule(id: number): Promise<void> {
  await http.delete(`/report/reportschedule/${id}`);
}

export async function toggleActiveReportSchedule(id: number): Promise<ReportSchedule> {
  const params = new URLSearchParams();
  const { data } = await http.post(`/report/reportschedule/${id}/toggleactive`, params.toString(), {
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
  });
  return data;
}

export async function resetReportSchedule(id: number): Promise<ReportSchedule> {
  const params = new URLSearchParams();
  const { data } = await http.post(`/report/reportschedule/${id}/reset`, params.toString(), {
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
  });
  return data;
}

export async function deleteAllSavedReportsForSchedule(id: number): Promise<void> {
  const params = new URLSearchParams();
  await http.post(`/report/reportschedule/${id}/deletesavedreport`, params.toString(), {
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
  });
}
