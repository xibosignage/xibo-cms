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

export interface LibraryUsageRequest {
  userId?: number | null;
  groupId?: number | null;
  signal?: AbortSignal;
}

export interface LibraryUsageTableRow {
  userId: number;
  userName: string;
  bytesUsed: number;
  bytesUsedFormatted: string;
  numFiles: number;
}

/** Chart.js-shaped pie data returned by the report (one dataset of values + labels). */
export interface LibraryUsagePieData {
  type: string;
  data: {
    labels: string[];
    datasets: Array<{ backgroundColor: string[]; data: number[] }>;
  };
}

export interface LibraryUsageChartData {
  User_Percentage_Usage: LibraryUsagePieData;
  Library_Usage: LibraryUsagePieData;
}

export interface LibraryUsageResponse {
  metadata: {
    periodStart: string;
    periodEnd: string;
  };
  table: LibraryUsageTableRow[];
  recordsTotal: number;
  chart: LibraryUsageChartData;
}

export async function fetchLibraryUsage(req: LibraryUsageRequest): Promise<LibraryUsageResponse> {
  const params = new URLSearchParams();

  if (req.userId) {
    params.append('userId', String(req.userId));
  }
  if (req.groupId) {
    params.append('groupId', String(req.groupId));
  }

  // `/report/data/{name}` is a root web route (routes-web.php), not a `/json` API route, so this
  // uses raw axios rather than the `@/lib/api` client (baseURL `/json`). Mirrors timeConnectedApi.
  const response = await axios.get<LibraryUsageResponse>('/report/data/libraryusage', {
    params,
    signal: req.signal,
    withCredentials: true,
  });

  return response.data;
}
