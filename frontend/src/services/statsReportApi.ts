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

export type StatsReportType = 'layout' | 'media' | 'event';

export interface StatsReportRequest {
  type: StatsReportType;
  layoutId?: number | null;
  mediaId?: number | null;
  eventTag?: string;
  displayId?: number | null;
  displayGroupId?: number[];
  reportFilter?: string;
  statsFromDt?: string;
  statsToDt?: string;
  groupByFilter?: string;
  signal?: AbortSignal;
}

export interface StatsReportTableRow {
  label: string;
  duration: number;
  count: number;
}

export interface StatsReportResponse {
  metadata: {
    periodStart: string;
    periodEnd: string;
    type: StatsReportType;
    subject: string;
  };
  table: StatsReportTableRow[];
  recordsTotal: number;
  error?: string | null;
}

export async function fetchStatsReport(
  reportName: string,
  req: StatsReportRequest,
): Promise<StatsReportResponse> {
  const params = new URLSearchParams();

  params.append('type', req.type);

  if (req.type === 'layout' && req.layoutId) {
    params.append('layoutId', String(req.layoutId));
  } else if (req.type === 'media' && req.mediaId) {
    params.append('mediaId', String(req.mediaId));
  } else if (req.type === 'event' && req.eventTag) {
    params.append('eventTag', req.eventTag);
  }

  if (req.displayId) {
    params.append('displayId', String(req.displayId));
  }
  req.displayGroupId?.forEach((id) => params.append('displayGroupId[]', String(id)));

  if (req.reportFilter) {
    params.append('reportFilter', req.reportFilter);
  } else {
    // Custom date range — the backend reads statsFromDt/statsToDt when reportFilter is empty.
    if (req.statsFromDt) {
      params.append('statsFromDt', req.statsFromDt);
    }
    if (req.statsToDt) {
      params.append('statsToDt', req.statsToDt);
    }
  }

  if (req.groupByFilter) {
    params.append('groupByFilter', req.groupByFilter);
  }

  const response = await axios.get<StatsReportResponse>(`/report/data/${reportName}`, {
    params,
    signal: req.signal,
    withCredentials: true,
  });

  return response.data;
}
