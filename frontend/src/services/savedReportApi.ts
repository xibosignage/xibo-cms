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

import http from '@/lib/api';
import type { SavedReport } from '@/types/savedReport';

export interface SavedReportData {
  recordsTotal: number;
  chart: unknown;
  table: Record<string, unknown>[] | null;
  metadata: Record<string, unknown> | null;
  error: string | null;
}

export interface FetchSavedReportsRequest {
  start?: number;
  length?: number;
  sortBy?: string;
  sortDir?: string;
  saveAs?: string;
  useRegexForName?: number;
  logicalOperatorName?: 'OR' | 'AND';
  userId?: number;
  reportName?: string;
  onlyMyReport?: number;
  signal?: AbortSignal;
}

export interface FetchSavedReportsResponse {
  rows: SavedReport[];
  totalCount: number;
}

export async function fetchSavedReports(
  options: FetchSavedReportsRequest = {},
): Promise<FetchSavedReportsResponse> {
  const { signal, ...queryParams } = options;

  const response = await http.get('/report/savedreport', {
    params: queryParams,
    signal,
  });

  const rows = response.data;
  const totalCountHeader = response.headers['x-total-count'];
  const totalCount = totalCountHeader ? parseInt(totalCountHeader, 10) : rows.length;

  return { rows, totalCount };
}

export async function deleteSavedReport(id: number): Promise<void> {
  await http.delete(`/report/savedreport/${id}`);
}

export async function fetchSavedReportData(
  savedReportId: number,
  reportName: string,
): Promise<SavedReportData> {
  const response = await http.get<SavedReportData>(
    `/report/savedreport/${savedReportId}/report/${reportName}/open`,
  );
  return response.data;
}

export async function exportSavedReport(savedReportId: number, reportName: string): Promise<Blob> {
  try {
    const response = await http.get<Blob>(
      `/report/savedreport/${savedReportId}/report/${reportName}/export`,
      { responseType: 'blob' },
    );
    return response.data;
  } catch (err) {
    // When responseType is 'blob', axios wraps the error body as a Blob too.
    // Convert it back to a plain Error with the server's message.
    if (err && typeof err === 'object' && 'response' in err) {
      const axiosErr = err as { response?: { data?: unknown } };
      if (axiosErr.response?.data instanceof Blob) {
        const text = await (axiosErr.response.data as Blob).text();
        let message: string | undefined;
        try {
          message = (JSON.parse(text) as { message?: string }).message;
        } catch {
          // Response body wasn't JSON — fall through to the generic error below.
        }
        if (message) throw new Error(message);
      }
    }
    throw err;
  }
}
