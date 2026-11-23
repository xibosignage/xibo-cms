/*
 * Xibo Digital Signage
 * Copyright (C) 2006-2026 Xibo Signage Ltd
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published
 * by the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 *
 * This program is also available under a commercial licence from
 * Xibo Signage Ltd. See https://xibosignage.com/terms
 */

import axios from 'axios';

export interface TimeDisconnectedSummaryRequest {
  reportFilter?: string;
  fromDt?: string;
  toDt?: string;
  groupBy: string;
  displayId?: number | null;
  displayGroupId?: number[];
  tags?: string;
  exactTags?: boolean;
  onlyLoggedIn?: boolean;
  signal?: AbortSignal;
}

export interface TimeDisconnectedSummaryRow {
  displayId: number;
  display: string;
  displayGroupId: number;
  displayGroup: string;
  timeDisconnected: number;
  timeConnected: number;
  avgTimeDisconnected: number;
  avgTimeConnected: number;
  availabilityPercentage: string;
  postUnits: string;
}

export interface TimeDisconnectedSummaryResponse {
  metadata: {
    periodStart: string;
    periodEnd: string;
  };
  table: TimeDisconnectedSummaryRow[];
  recordsTotal: number;
  error?: string | null;
}

export async function fetchTimeDisconnectedSummary(
  req: TimeDisconnectedSummaryRequest,
): Promise<TimeDisconnectedSummaryResponse> {
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

  params.append('groupBy', req.groupBy);

  if (req.displayId) {
    params.append('displayId', String(req.displayId));
  }
  req.displayGroupId?.forEach((id) => params.append('displayGroupId[]', String(id)));

  if (req.tags) {
    params.append('tags', req.tags);
    params.append('exactTags', req.exactTags ? '1' : '0');
  }

  if (req.onlyLoggedIn) {
    params.append('onlyLoggedIn', '1');
  }

  // `/report/data/{name}` is a root web route (routes-web.php), not a `/json` API route, so this
  // uses raw axios rather than the `@/lib/api` client (baseURL `/json`). Mirrors timeConnectedApi.
  const response = await axios.get<TimeDisconnectedSummaryResponse>(
    '/report/data/timedisconnectedsummary',
    {
      params,
      signal: req.signal,
      withCredentials: true,
    },
  );

  return response.data;
}
