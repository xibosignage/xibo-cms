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

// =============================================================================
// Service: request shapes and export error unwrapping
// =============================================================================

import { vi, beforeEach, describe, test, expect } from 'vitest';

import http from '@/lib/api';
import {
  deleteSavedReport,
  exportSavedReport,
  fetchSavedReportData,
  fetchSavedReports,
} from '@/services/savedReportApi';

vi.mock('@/lib/api', () => ({
  default: { get: vi.fn(), delete: vi.fn() },
}));

// Axios error with a Blob body, as with responseType 'blob'.
const blobError = (body: string) => ({ response: { data: new Blob([body]) } });

describe('savedReportApi', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    vi.mocked(http.delete).mockResolvedValue({ data: {} });
  });

  test('the list reads its total from the count header and keeps the signal out of the query', async () => {
    const controller = new AbortController();
    vi.mocked(http.get).mockResolvedValue({ data: [{}, {}], headers: { 'x-total-count': '40' } });

    const result = await fetchSavedReports({ start: 10, length: 10, signal: controller.signal });

    expect(result.totalCount).toBe(40);
    expect(http.get).toHaveBeenCalledWith('/report/savedreport', {
      params: { start: 10, length: 10 },
      signal: controller.signal,
    });
  });

  test('the list falls back to the row count without a count header', async () => {
    vi.mocked(http.get).mockResolvedValue({ data: [{}, {}], headers: {} });

    expect((await fetchSavedReports()).totalCount).toBe(2);
  });

  test("open and delete call the saved report's own addresses", async () => {
    vi.mocked(http.get).mockResolvedValue({ data: { table: [] } });

    await fetchSavedReportData(10, 'proofofplayReport');
    await deleteSavedReport(10);

    expect(http.get).toHaveBeenCalledWith('/report/savedreport/10/report/proofofplayReport/open');
    expect(http.delete).toHaveBeenCalledWith('/report/savedreport/10');
  });

  test('export asks for the PDF as a file', async () => {
    const pdf = new Blob(['%PDF']);
    vi.mocked(http.get).mockResolvedValue({ data: pdf });

    await expect(exportSavedReport(10, 'proofofplayReport')).resolves.toBe(pdf);
    expect(http.get).toHaveBeenCalledWith(
      '/report/savedreport/10/report/proofofplayReport/export',
      {
        responseType: 'blob',
      },
    );
  });

  test("a failed export surfaces the server's message from the file error", async () => {
    vi.mocked(http.get).mockRejectedValue(blobError('{"message":"No PDF renderer"}'));

    await expect(exportSavedReport(10, 'proofofplayReport')).rejects.toThrow('No PDF renderer');
  });

  test('an unreadable export error is passed on unchanged', async () => {
    const html = blobError('<html>500</html>');
    vi.mocked(http.get).mockRejectedValueOnce(html);
    await expect(exportSavedReport(10, 'proofofplayReport')).rejects.toBe(html);

    const network = new Error('Network Error');
    vi.mocked(http.get).mockRejectedValueOnce(network);
    await expect(exportSavedReport(10, 'proofofplayReport')).rejects.toBe(network);
  });
});
