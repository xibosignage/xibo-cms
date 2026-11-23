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
// Hook: Owner and Type options for the filter panel
// =============================================================================

import { act, renderHook, waitFor } from '@testing-library/react';
import type { TFunction } from 'i18next';
import { vi, beforeEach, describe, test, expect } from 'vitest';

import { REPORTS_BY_CATEGORY, REPORT_DESCRIPTION_MAP } from '../../../__tests__/fixtures/report';
import { useSavedReportFilterOptions } from '../../hooks/useSavedReportFilterOptions';

import { fetchReports } from '@/services/reportApi';
import { fetchUsers } from '@/services/userApi';

// =============================================================================
// Module mocks
// =============================================================================

vi.mock('@/services/userApi', () => ({ fetchUsers: vi.fn() }));
vi.mock('@/services/reportApi', () => ({ fetchReports: vi.fn() }));
vi.mock('@/hooks/useDebounce');

// =============================================================================
// Helpers
// =============================================================================

const t = ((key: string) => key) as unknown as TFunction;

type UsersResponse = Awaited<ReturnType<typeof fetchUsers>>;

const users = (from: number, count: number) =>
  ({
    rows: Array.from({ length: count }, (_, i) => ({
      userId: from + i,
      userName: `user${from + i}`,
    })),
    totalCount: 0,
  }) as unknown as UsersResponse;

const ownerOf = (result: { current: ReturnType<typeof useSavedReportFilterOptions> }) =>
  result.current.filterOptions.find((o) => o.name === 'userId')! as ReturnType<
    typeof useSavedReportFilterOptions
  >['filterOptions'][number] & {
    hasMore: boolean;
    onLoadMore: () => void;
    onSearch: (term: string) => void;
    resolveLabel: (value: string) => Promise<string>;
  };

const usersCall = (n: number) => vi.mocked(fetchUsers).mock.calls[n]![0];

// =============================================================================
// Tests
// =============================================================================

describe('useSavedReportFilterOptions', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    vi.mocked(fetchReports).mockResolvedValue(REPORTS_BY_CATEGORY);
    vi.mocked(fetchUsers).mockResolvedValue(users(1, 10));
  });

  test('loads the first 10 owners and offers more when a full page returns', async () => {
    const { result } = renderHook(() => useSavedReportFilterOptions(t));

    await waitFor(() => expect(ownerOf(result).options).toHaveLength(10));
    expect(ownerOf(result).options![0]).toEqual({ label: 'user1', value: '1' });
    expect(ownerOf(result).hasMore).toBe(true);
    expect(usersCall(0)).toEqual(expect.objectContaining({ start: 0, length: 10 }));
  });

  test('a short page means there are no more owners', async () => {
    vi.mocked(fetchUsers).mockResolvedValue(users(1, 3));
    const { result } = renderHook(() => useSavedReportFilterOptions(t));

    await waitFor(() => expect(ownerOf(result).options).toHaveLength(3));
    expect(ownerOf(result).hasMore).toBe(false);
  });

  test('loading more appends the next page using the current search', async () => {
    const { result } = renderHook(() => useSavedReportFilterOptions(t));
    await waitFor(() => expect(ownerOf(result).options).toHaveLength(10));

    act(() => ownerOf(result).onSearch('u'));
    await waitFor(() => expect(usersCall(1)).toEqual(expect.objectContaining({ userName: 'u' })));
    await waitFor(() => expect(ownerOf(result).options).toHaveLength(10));

    vi.mocked(fetchUsers).mockResolvedValue(users(11, 2));
    act(() => ownerOf(result).onLoadMore());

    await waitFor(() => expect(ownerOf(result).options).toHaveLength(12));
    expect(usersCall(2)).toEqual(expect.objectContaining({ start: 10, userName: 'u' }));
    expect(ownerOf(result).options![11]).toEqual({ label: 'user12', value: '12' });
    expect(ownerOf(result).hasMore).toBe(false);
  });

  test('searching restarts the owner list from the first page', async () => {
    const { result } = renderHook(() => useSavedReportFilterOptions(t));
    await waitFor(() => expect(ownerOf(result).options).toHaveLength(10));
    vi.mocked(fetchUsers).mockResolvedValue(users(1, 10));
    act(() => ownerOf(result).onLoadMore());
    await waitFor(() => expect(ownerOf(result).options).toHaveLength(20));

    vi.mocked(fetchUsers).mockResolvedValue(users(50, 1));
    act(() => ownerOf(result).onSearch('bob'));

    await waitFor(() =>
      expect(ownerOf(result).options).toEqual([{ label: 'user50', value: '50' }]),
    );
    const calls = vi.mocked(fetchUsers).mock.calls;
    expect(calls[calls.length - 1]![0]).toEqual(
      expect.objectContaining({ start: 0, userName: 'bob' }),
    );
  });

  test("a chosen owner's name is looked up by ID, falling back to the ID", async () => {
    const { result } = renderHook(() => useSavedReportFilterOptions(t));
    await waitFor(() => expect(ownerOf(result).options).toHaveLength(10));

    vi.mocked(fetchUsers).mockResolvedValueOnce({
      rows: [{ userId: 7, userName: 'alice' }],
      totalCount: 1,
    } as unknown as UsersResponse);
    await expect(ownerOf(result).resolveLabel('7')).resolves.toBe('alice');
    expect(vi.mocked(fetchUsers)).toHaveBeenLastCalledWith(
      expect.objectContaining({ userId: 7, length: 1 }),
    );

    vi.mocked(fetchUsers).mockResolvedValueOnce(users(0, 0));
    await expect(ownerOf(result).resolveLabel('99')).resolves.toBe('99');
  });

  test('Type options and friendly names include every category', async () => {
    const { result } = renderHook(() => useSavedReportFilterOptions(t));

    await waitFor(() =>
      expect(result.current.reportDescriptionMap).toEqual(REPORT_DESCRIPTION_MAP),
    );
    const type = result.current.filterOptions.find((o) => o.name === 'reportName')!;
    expect(type.options).toEqual([
      { label: 'Proof of Play', value: 'proofofplayReport' },
      { label: 'Display Statistics: Bandwidth', value: 'bandwidth' },
    ]);
  });
});
