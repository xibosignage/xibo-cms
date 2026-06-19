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

export interface SessionHistoryRequest {
  reportFilter?: string;
  fromDt?: string;
  toDt?: string;
  type?: string;
  userId?: number | null;
  sessionHistoryId?: string;
  keyword?: string;
  signal?: AbortSignal;
}

export interface SessionHistoryRow {
  // sessions mode
  sessionId?: string;
  startTime?: string;
  userId?: number;
  userAgent?: string;
  ipAddress?: string;
  lastUsedTime?: string;
  userName?: string;
  userType?: string;
  duration?: string;
  // audit mode
  logId?: number;
  logDate?: string;
  message?: string;
  objectAfter?: string;
  entity?: string;
  entityId?: number;
  sessionHistoryId?: string;
  // debug mode
  runNo?: string;
  channel?: string;
  page?: string;
  function?: string;
  type?: string;
  displayId?: number;
  display?: string;
}

export interface SessionHistoryResponse {
  metadata: {
    periodStart: string;
    periodEnd: string;
  };
  table: SessionHistoryRow[];
  recordsTotal: number;
  error?: string | null;
}

export async function fetchSessionHistory(
  req: SessionHistoryRequest,
): Promise<SessionHistoryResponse> {
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
  if (req.sessionHistoryId) {
    params.append('sessionHistoryId', req.sessionHistoryId);
  }
  if (req.keyword) {
    params.append('keyword', req.keyword);
  }

  const response = await axios.get<SessionHistoryResponse>('/report/data/sessionhistory', {
    params,
    signal: req.signal,
    withCredentials: true,
  });

  return response.data;
}
