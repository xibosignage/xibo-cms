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
// Hook: how filters, paging and sorting map to the list request
// =============================================================================

import { QueryClientProvider } from '@tanstack/react-query';
import type { SortingState } from '@tanstack/react-table';
import { renderHook, waitFor } from '@testing-library/react';
import type { ReactNode } from 'react';
import { vi, beforeEach, describe, test, expect } from 'vitest';

import { INITIAL_FILTER_STATE } from '../../SavedReportsConfig';
import type { SavedReportFilterInput } from '../../SavedReportsConfig';
import { useSavedReportData } from '../../hooks/useSavedReportData';

import { fetchSavedReports } from '@/services/savedReportApi';
import { testQueryClient } from '@/setupTests';

vi.mock('@/services/savedReportApi', () => ({
  fetchSavedReports: vi.fn().mockResolvedValue({ rows: [], totalCount: 0 }),
}));

const wrapper = ({ children }: { children: ReactNode }) => (
  <QueryClientProvider client={testQueryClient}>{children}</QueryClientProvider>
);

interface Params {
  filters?: Partial<SavedReportFilterInput>;
  search?: string;
  pageIndex?: number;
  sorting?: SortingState;
}

const requestFor = async ({
  filters = {},
  search = '',
  pageIndex = 0,
  sorting = [],
}: Params = {}) => {
  renderHook(
    () =>
      useSavedReportData({
        pagination: { pageIndex, pageSize: 10 },
        sorting,
        filter: search,
        advancedFilters: { ...INITIAL_FILTER_STATE, ...filters },
      }),
    { wrapper },
  );
  await waitFor(() => expect(fetchSavedReports).toHaveBeenCalled());
  return vi.mocked(fetchSavedReports).mock.calls[0]![0]!;
};

describe('useSavedReportData - request mapping', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
  });

  test('the Name filter takes priority over the search box', async () => {
    const req = await requestFor({ search: 'box', filters: { saveAs: 'panel' } });

    expect(req.saveAs).toBe('panel');
  });

  test('the search box alone is sent as the name', async () => {
    const req = await requestFor({ search: 'box' });

    expect(req.saveAs).toBe('box');
  });

  test('AND/OR is not sent with only the search box', async () => {
    const req = await requestFor({ search: 'box', filters: { logicalOperatorName: 'AND' } });

    expect(req).not.toHaveProperty('logicalOperatorName');
  });

  test('AND/OR is sent with the Name filter', async () => {
    const req = await requestFor({ filters: { saveAs: 'panel', logicalOperatorName: 'AND' } });

    expect(req.logicalOperatorName).toBe('AND');
  });

  test('regex is not sent without a name', async () => {
    const req = await requestFor({ filters: { useRegexForName: true } });

    expect(req).not.toHaveProperty('useRegexForName');
  });

  test('regex is not sent for an invalid pattern', async () => {
    const req = await requestFor({ filters: { useRegexForName: true, saveAs: '[' } });

    expect(req.saveAs).toBe('[');
    expect(req).not.toHaveProperty('useRegexForName');
  });

  test('regex is sent for a valid pattern', async () => {
    const req = await requestFor({ filters: { useRegexForName: true, saveAs: '^W' } });

    expect(req.useRegexForName).toBe(1);
  });

  // The server reads the singular onlyMyReport (SavedReport.php).
  test('owner, type and only-mine are mapped to the fields the server reads', async () => {
    const req = await requestFor({
      filters: { userId: '7', reportName: 'bandwidth', onlyMyReports: '1' },
    });

    expect(req).toEqual(
      expect.objectContaining({ userId: 7, reportName: 'bandwidth', onlyMyReport: 1 }),
    );
  });

  test('empty filters send none of the optional fields', async () => {
    const req = await requestFor();

    for (const key of [
      'saveAs',
      'userId',
      'reportName',
      'onlyMyReport',
      'logicalOperatorName',
      'useRegexForName',
    ]) {
      expect(req).not.toHaveProperty(key);
    }
  });

  test('sorting and paging map to the request; no sort sends no direction', async () => {
    const sorted = await requestFor({ pageIndex: 1, sorting: [{ id: 'generatedOn', desc: true }] });
    expect(sorted).toEqual(
      expect.objectContaining({ start: 10, length: 10, sortBy: 'generatedOn', sortDir: 'desc' }),
    );

    testQueryClient.clear();
    vi.clearAllMocks();
    const unsorted = await requestFor();
    expect(unsorted.sortDir).toBeUndefined();
  });

  test('nothing is requested while disabled', () => {
    const { result } = renderHook(
      () =>
        useSavedReportData({
          pagination: { pageIndex: 0, pageSize: 10 },
          sorting: [],
          filter: '',
          advancedFilters: INITIAL_FILTER_STATE,
          enabled: false,
        }),
      { wrapper },
    );

    expect(result.current.fetchStatus).toBe('idle');
    expect(fetchSavedReports).not.toHaveBeenCalled();
  });
});
