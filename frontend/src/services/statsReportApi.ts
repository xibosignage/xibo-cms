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
