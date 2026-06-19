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

export interface BandwidthReportRequest {
  fromDt?: string;
  toDt?: string;
  displayId?: number | null;
  signal?: AbortSignal;
}

export interface BandwidthTableRow {
  label: string;
  bandwidth: number;
  unit: string;
  deleted: boolean;
}

export interface BandwidthReportResponse {
  metadata: {
    periodStart: string;
    periodEnd: string;
  };
  table: BandwidthTableRow[];
  recordsTotal: number;
  error?: string | null;
}

export async function fetchBandwidthReport(
  req: BandwidthReportRequest,
): Promise<BandwidthReportResponse> {
  const params = new URLSearchParams();

  if (req.fromDt) {
    params.append('fromDt', req.fromDt);
  }
  if (req.toDt) {
    params.append('toDt', req.toDt);
  }
  if (req.displayId) {
    params.append('displayId', String(req.displayId));
  }

  // `/report/data/{name}` is a root web route (routes-web.php), not a `/json` API route, so this
  // uses raw axios rather than the `@/lib/api` client (baseURL `/json`). Mirrors timeConnectedApi.
  const response = await axios.get<BandwidthReportResponse>('/report/data/bandwidth', {
    params,
    signal: req.signal,
    withCredentials: true,
  });

  return response.data;
}
