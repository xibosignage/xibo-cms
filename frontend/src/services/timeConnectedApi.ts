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

export interface TimeConnectedRequest {
  reportFilter?: string;
  fromDt?: string;
  toDt?: string;
  groupByFilter: string;
  displayGroupId?: number[];
  signal?: AbortSignal;
}

interface PeriodData {
  percent: number;
  label: string;
}

export interface DisplayMeta {
  lastAccessed: string | null;
}

export interface TimeConnectedTable {
  timeConnected: Array<Record<string, Record<string, PeriodData>>>;
  displays: Array<Record<string, string>>;
  displayMeta?: Record<string, DisplayMeta>;
}

export interface TimeConnectedResponse {
  success?: boolean;
  table: TimeConnectedTable;
  metadata: {
    periodStart: string;
    periodEnd: string;
  };
}

export async function fetchTimeConnected(
  req: TimeConnectedRequest,
): Promise<TimeConnectedResponse> {
  const params = new URLSearchParams();

  if (req.reportFilter) {
    params.append('reportFilter', req.reportFilter);
  }
  if (req.fromDt) {
    params.append('fromDt', req.fromDt);
  }
  if (req.toDt) {
    params.append('toDt', req.toDt);
  }
  params.append('groupByFilter', req.groupByFilter);
  req.displayGroupId?.forEach((id) => params.append('displayGroupId[]', String(id)));

  // `/report/data/{name}` is a root web route (routes-web.php), not a `/json` API route, so this
  // uses raw axios rather than the `@/lib/api` client (baseURL `/json`). Mirrors displaysApi.
  const response = await axios.get<TimeConnectedResponse>('/report/data/timeconnected', {
    params,
    signal: req.signal,
    withCredentials: true,
  });

  return response.data;
}
