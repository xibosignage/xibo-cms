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

export interface DisplayAlertsRequest {
  reportFilter?: string;
  fromDt?: string;
  toDt?: string;
  eventType?: string;
  displayId?: number | null;
  displayGroupId?: number[];
  tags?: string;
  exactTags?: boolean;
  logicalOperator?: string;
  onlyLoggedIn?: boolean;
  signal?: AbortSignal;
}

export interface DisplayAlertsRow {
  displayId: number;
  display: string;
  start: string;
  end: string;
  eventTypeId: number;
  refId: number;
  detail: string;
  eventType: string;
}

export interface DisplayAlertsResponse {
  metadata: {
    periodStart: string;
    periodEnd: string;
  };
  table: DisplayAlertsRow[];
  recordsTotal: number;
  error?: string | null;
}

export async function fetchDisplayAlerts(
  req: DisplayAlertsRequest,
): Promise<DisplayAlertsResponse> {
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

  params.append('eventType', req.eventType || '-1');
  if (req.displayId) {
    params.append('displayId', String(req.displayId));
  }
  req.displayGroupId?.forEach((id) => params.append('displayGroupId[]', String(id)));

  if (req.tags) {
    params.append('tags', req.tags);
    params.append('tagsType', 'dg');
    params.append('exactTags', req.exactTags ? '1' : '0');
    if (req.logicalOperator) {
      params.append('logicalOperator', req.logicalOperator);
    }
  }

  if (req.onlyLoggedIn) {
    params.append('onlyLoggedIn', '1');
  }

  const response = await axios.get<DisplayAlertsResponse>('/report/data/displayalerts', {
    params,
    signal: req.signal,
    withCredentials: true,
  });

  return response.data;
}
