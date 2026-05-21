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

import { renderHook, act, waitFor } from '@testing-library/react';
import type { TFunction } from 'i18next';
import { describe, it, expect, vi, beforeEach } from 'vitest';

import { useDatasetFilterOptions } from '../hooks/useDatasetFilterOptions';

// -- Module mocks --

const mockFetchUsers = vi.fn();

vi.mock('@/services/userApi', () => ({
  fetchUsers: (...args: unknown[]) => mockFetchUsers(...args),
}));

vi.mock('@/hooks/useDebounce', () => ({
  useDebounce: <T>(value: T) => value,
}));

vi.mock('../DatasetConfig', () => ({
  getBaseFilterKeys: () => [
    { name: 'userId', label: 'Owner' },
    { name: 'tag', label: 'Tag' },
  ],
}));

// -- Tests --

describe('useDatasetFilterOptions', () => {
  const mockT = ((str: string) => str) as unknown as TFunction;

  const PAGE_SIZE = 10;

  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('calls fetchUsers on mount', async () => {
    mockFetchUsers.mockResolvedValue({ rows: [], recordsTotal: 0 });

    renderHook(() => useDatasetFilterOptions(mockT));

    await waitFor(() => {
      expect(mockFetchUsers).toHaveBeenCalledTimes(1);
    });
  });

  it('returns ownerOptions mapped from fetchUsers response', async () => {
    mockFetchUsers.mockResolvedValue({
      rows: [
        { userId: 1, userName: 'Alice' },
        { userId: 2, userName: 'Bob' },
      ],
      recordsTotal: 2,
    });

    const { result } = renderHook(() => useDatasetFilterOptions(mockT));

    await waitFor(() => {
      const userIdFilter = result.current.filterOptions.find((f) => f.name === 'userId');
      expect(userIdFilter?.options).toEqual([
        { label: 'Alice', value: '1' },
        { label: 'Bob', value: '2' },
      ]);
    });
  });

  it('isLoading is true initially and false after fetch completes', async () => {
    let resolveUsers!: (v: unknown) => void;
    mockFetchUsers.mockReturnValue(
      new Promise((resolve) => {
        resolveUsers = resolve;
      }),
    );

    const { result } = renderHook(() => useDatasetFilterOptions(mockT));

    expect(result.current.isLoading).toBe(true);

    act(() => resolveUsers({ rows: [], recordsTotal: 0 }));

    await waitFor(() => {
      expect(result.current.isLoading).toBe(false);
    });
  });

  it('hasMoreOwners is false when fewer than PAGE_SIZE users returned', async () => {
    mockFetchUsers.mockResolvedValue({
      rows: [{ userId: 1, userName: 'Alice' }],
      recordsTotal: 1,
    });

    const { result } = renderHook(() => useDatasetFilterOptions(mockT));

    await waitFor(() => {
      const userIdFilter = result.current.filterOptions.find((f) => f.name === 'userId');
      expect(userIdFilter?.hasMore).toBe(false);
    });
  });

  it('hasMoreOwners is true when exactly PAGE_SIZE users are returned', async () => {
    const rows = Array.from({ length: PAGE_SIZE }, (_, i) => ({
      userId: i + 1,
      userName: `User${i + 1}`,
    }));
    mockFetchUsers.mockResolvedValue({ rows, recordsTotal: PAGE_SIZE });

    const { result } = renderHook(() => useDatasetFilterOptions(mockT));

    await waitFor(() => {
      const userIdFilter = result.current.filterOptions.find((f) => f.name === 'userId');
      expect(userIdFilter?.hasMore).toBe(true);
    });
  });

  it('handleLoadMoreOwners appends additional users to ownerOptions', async () => {
    const firstPage = [{ userId: 1, userName: 'Alice' }];
    const secondPage = Array.from({ length: PAGE_SIZE }, (_, i) => ({
      userId: i + 1,
      userName: `User${i + 1}`,
    }));

    mockFetchUsers
      .mockResolvedValueOnce({ rows: secondPage, recordsTotal: PAGE_SIZE })
      .mockResolvedValueOnce({ rows: firstPage, recordsTotal: 1 });

    const { result } = renderHook(() => useDatasetFilterOptions(mockT));

    await waitFor(() => {
      const userIdFilter = result.current.filterOptions.find((f) => f.name === 'userId');
      expect(userIdFilter?.hasMore).toBe(true);
    });

    act(() => {
      const userIdFilter = result.current.filterOptions.find((f) => f.name === 'userId');
      userIdFilter?.onLoadMore?.();
    });

    await waitFor(() => {
      const userIdFilter = result.current.filterOptions.find((f) => f.name === 'userId');
      expect(userIdFilter?.options.length).toBe(PAGE_SIZE + 1);
    });
  });

  it('handleLoadMoreOwners does nothing when hasMoreOwners is false', async () => {
    mockFetchUsers.mockResolvedValue({
      rows: [{ userId: 1, userName: 'Alice' }],
      recordsTotal: 1,
    });

    const { result } = renderHook(() => useDatasetFilterOptions(mockT));

    await waitFor(() => {
      const userIdFilter = result.current.filterOptions.find((f) => f.name === 'userId');
      expect(userIdFilter?.hasMore).toBe(false);
    });

    act(() => {
      const userIdFilter = result.current.filterOptions.find((f) => f.name === 'userId');
      userIdFilter?.onLoadMore?.();
    });

    expect(mockFetchUsers).toHaveBeenCalledTimes(1);
  });

  it('non-userId filter keys are returned unchanged', async () => {
    mockFetchUsers.mockResolvedValue({ rows: [], recordsTotal: 0 });

    const { result } = renderHook(() => useDatasetFilterOptions(mockT));

    await waitFor(() => {
      const tagFilter = result.current.filterOptions.find((f) => f.name === 'tag');
      expect(tagFilter).toEqual({ name: 'tag', label: 'Tag' });
    });
  });

  it('filterOptions includes both userId and tag entries', async () => {
    mockFetchUsers.mockResolvedValue({ rows: [], recordsTotal: 0 });

    const { result } = renderHook(() => useDatasetFilterOptions(mockT));

    await waitFor(() => {
      const names = result.current.filterOptions.map((f) => f.name);
      expect(names).toContain('userId');
      expect(names).toContain('tag');
    });
  });
});
