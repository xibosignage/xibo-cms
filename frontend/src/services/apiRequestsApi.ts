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

export interface ApiRequestsRequest {
  reportFilter?: string;
  fromDt?: string;
  toDt?: string;
  type?: string;
  userId?: number | null;
  requestId?: string;
  keyword?: string;
  signal?: AbortSignal;
}

export interface ApiRequestsRow {
  logId?: number;
  logDate?: string;
  userName?: string;
  message?: string;
  objectAfter?: string;
  entity?: string;
  entityId?: number;
  userId?: number;
  ipAddress?: string;
  requestId?: string;
  applicationId?: number;
  applicationName?: string;
  url?: string;
  method?: string;
  startTime?: string;
  runNo?: string;
  channel?: string;
  page?: string;
  function?: string;
  type?: string;
}

export interface ApiRequestsResponse {
  metadata: {
    periodStart: string;
    periodEnd: string;
    logType: string;
  };
  table: ApiRequestsRow[];
  recordsTotal: number;
  error?: string | null;
}

export async function fetchApiRequests(req: ApiRequestsRequest): Promise<ApiRequestsResponse> {
  const params = new URLSearchParams();

  if (req.reportFilter) {
    params.append('reportFilter', req.reportFilter);
  } else {
    if (req.fromDt) {
      params.append('fromDt', req.fromDt);
    }
    if (req.toDt) {
      params.append('toDt', req.toDt);
    }
  }

  if (req.type) {
    params.append('type', req.type);
  }
  if (req.userId) {
    params.append('userId', String(req.userId));
  }
  if (req.requestId) {
    params.append('requestId', req.requestId);
  }
  if (req.keyword) {
    params.append('keyword', req.keyword);
  }

  const response = await axios.get<ApiRequestsResponse>('/report/data/apirequests', {
    params,
    signal: req.signal,
    withCredentials: true,
  });

  return response.data;
}
