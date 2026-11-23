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
// Drives the hook directly to reach branches the filter panel blocks.
// =============================================================================

import { QueryClientProvider } from '@tanstack/react-query';
import type { SortingState } from '@tanstack/react-table';
import { renderHook, waitFor } from '@testing-library/react';
import type { ReactNode } from 'react';
import { vi, beforeEach, describe, test, expect } from 'vitest';

import { INITIAL_FILTER_STATE } from '../../ReportSchedulesConfig';
import type { ReportScheduleFilterInput } from '../../ReportSchedulesConfig';
import { useReportScheduleData } from '../../hooks/useReportScheduleData';

import { fetchReportSchedules } from '@/services/reportScheduleApi';
import { testQueryClient } from '@/setupTests';

// =============================================================================
// Module mocks
// =============================================================================

vi.mock('@/services/reportScheduleApi', () => ({
  fetchReportSchedules: vi.fn().mockResolvedValue({ rows: [], totalCount: 0 }),
}));

// =============================================================================
// Helpers
// =============================================================================

const wrapper = ({ children }: { children: ReactNode }) => (
  <QueryClientProvider client={testQueryClient}>{children}</QueryClientProvider>
);

interface Params {
  filters?: Partial<ReportScheduleFilterInput>;
  search?: string;
  pageIndex?: number;
  sorting?: SortingState;
  enabled?: boolean;
}

// Renders the hook and resolves with the request it sent.
const requestFor = async ({
  filters = {},
  search = '',
  pageIndex = 0,
  sorting = [],
}: Params = {}) => {
  renderHook(
    () =>
      useReportScheduleData({
        pagination: { pageIndex, pageSize: 10 },
        sorting,
        filter: search,
        advancedFilters: { ...INITIAL_FILTER_STATE, ...filters },
      }),
    { wrapper },
  );
  await waitFor(() => expect(fetchReportSchedules).toHaveBeenCalled());
  return vi.mocked(fetchReportSchedules).mock.calls[0]![0]!;
};

// =============================================================================
// Tests
// =============================================================================

describe('useReportScheduleData - request mapping', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
  });

  test('the Name filter takes priority over the search box', async () => {
    const req = await requestFor({ search: 'box', filters: { name: 'panel' } });

    expect(req.name).toBe('panel');
  });

  test('the search box alone is sent as the name', async () => {
    const req = await requestFor({ search: 'box' });

    expect(req.name).toBe('box');
  });

  test('AND/OR is not sent with only the search box', async () => {
    const req = await requestFor({ search: 'box', filters: { logicalOperatorName: 'AND' } });

    expect(req).not.toHaveProperty('logicalOperatorName');
  });

  test('AND/OR is sent with the Name filter', async () => {
    const req = await requestFor({ filters: { name: 'panel', logicalOperatorName: 'AND' } });

    expect(req.logicalOperatorName).toBe('AND');
  });

  test('regex is not sent without a name', async () => {
    const req = await requestFor({ filters: { useRegexForName: true } });

    expect(req).not.toHaveProperty('useRegexForName');
  });

  test('regex is not sent for an invalid pattern', async () => {
    const req = await requestFor({ filters: { useRegexForName: true, name: '[' } });

    expect(req.name).toBe('[');
    expect(req).not.toHaveProperty('useRegexForName');
  });

  test('regex is sent for a valid pattern', async () => {
    const req = await requestFor({ filters: { useRegexForName: true, name: '^W' } });

    expect(req.useRegexForName).toBe(1);
  });

  test('owner, ID and only-mine are mapped to the fields the server reads', async () => {
    const req = await requestFor({
      filters: { userId: '7', reportScheduleId: 42, onlyMySchedules: '1', reportName: 'bandwidth' },
    });

    expect(req).toEqual(
      expect.objectContaining({
        userId: 7,
        reportScheduleId: 42,
        onlyMySchedules: 1,
        reportName: 'bandwidth',
      }),
    );
  });

  test('empty filters send none of the optional fields', async () => {
    const req = await requestFor();

    for (const key of [
      'name',
      'userId',
      'reportScheduleId',
      'onlyMySchedules',
      'reportName',
      'logicalOperatorName',
      'useRegexForName',
    ]) {
      expect(req).not.toHaveProperty(key);
    }
  });

  test('with no sorting no sort direction is sent', async () => {
    const req = await requestFor();

    expect(req.sortBy).toBeUndefined();
    expect(req.sortDir).toBeUndefined();
  });

  test('a descending sort sends the column and desc', async () => {
    const req = await requestFor({ sorting: [{ id: 'name', desc: true }] });

    expect(req).toEqual(expect.objectContaining({ sortBy: 'name', sortDir: 'desc' }));
  });

  test('page 3 requests rows from 20', async () => {
    const req = await requestFor({ pageIndex: 2 });

    expect(req).toEqual(expect.objectContaining({ start: 20, length: 10 }));
  });

  test('nothing is requested while disabled', () => {
    const { result } = renderHook(
      () =>
        useReportScheduleData({
          pagination: { pageIndex: 0, pageSize: 10 },
          sorting: [],
          filter: '',
          advancedFilters: INITIAL_FILTER_STATE,
          enabled: false,
        }),
      { wrapper },
    );

    expect(result.current.fetchStatus).toBe('idle');
    expect(fetchReportSchedules).not.toHaveBeenCalled();
  });
});
