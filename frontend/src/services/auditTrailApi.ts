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

import http from '@/lib/api';
import type { AuditLog } from '@/types/auditTrail';

export interface FetchAuditTrailRequest {
  start: number;
  length: number;
  fromDt?: string;
  toDt?: string;
  user?: string;
  entity?: string;
  entityId?: string;
  ipAddress?: string;
  message?: string;
  sortBy?: string;
  sortDir?: string;
  signal?: AbortSignal;
}

export interface FetchAuditTrailResponse {
  rows: AuditLog[];
  totalCount: number;
}

export interface ExportAuditTrailRequest {
  filterFromDt: string;
  filterToDt: string;
}

export async function fetchAuditTrail(
  options: FetchAuditTrailRequest = { start: 0, length: 10 },
): Promise<FetchAuditTrailResponse> {
  const { signal, ...queryParams } = options;

  const response = await http.get('/audit', {
    params: queryParams,
    signal,
  });

  const rows = response.data;
  const totalCountHeader = response.headers['x-total-count'];
  const totalCount = totalCountHeader ? parseInt(totalCountHeader, 10) : 0;

  return { rows, totalCount };
}

export async function exportAuditTrail({
  filterFromDt,
  filterToDt,
}: ExportAuditTrailRequest): Promise<void> {
  const response = await http.get('/audit/export', {
    params: { filterFromDt, filterToDt },
    responseType: 'blob',
  });

  const url = URL.createObjectURL(response.data as Blob);
  const a = document.createElement('a');
  a.href = url;
  a.download = 'audittrail.csv';
  document.body.appendChild(a);
  a.click();
  document.body.removeChild(a);
  URL.revokeObjectURL(url);
}
