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

import { renderHook, act, waitFor } from '@testing-library/react';
import type { TFunction } from 'i18next';
import { describe, it, expect, vi, beforeEach } from 'vitest';

import { useMediaFilterOptions } from '../hooks/useMediaFilterOptions';

// ---------------------------------------------------------------------------
// Module mocks
// ---------------------------------------------------------------------------

const mockFetchUsers = vi.fn();
const mockFetchUserGroups = vi.fn();

vi.mock('@/services/userApi', () => ({
  fetchUsers: (...args: unknown[]) => mockFetchUsers(...args),
}));

vi.mock('@/services/userGroupApi', () => ({
  fetchUserGroups: (...args: unknown[]) => mockFetchUserGroups(...args),
}));

// Bypass debounce so search terms take effect immediately in tests.
vi.mock('@/hooks/useDebounce', () => ({
  useDebounce: <T>(value: T) => value,
}));

// Use real getBaseFilterKeys (no mock) so we can verify pass-through items.
vi.mock('@/pages/Library/Media/MediaConfig', async (importOriginal) => {
  return await importOriginal();
});

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

const PAGE_SIZE = 10;
const mockT = ((str: string) => str) as unknown as TFunction;

const makeUsers = (count: number) =>
  Array.from({ length: count }, (_, i) => ({ userId: i + 1, userName: `User${i + 1}` }));

const makeGroups = (count: number) =>
  Array.from({ length: count }, (_, i) => ({ groupId: i + 1, group: `Group${i + 1}` }));

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

describe('useMediaFilterOptions', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  // S30
  it('calls fetchUsers on mount', async () => {
    mockFetchUsers.mockResolvedValue({ rows: [], totalCount: 0 });
    mockFetchUserGroups.mockResolvedValue({ rows: [], totalCount: 0 });

    renderHook(() => useMediaFilterOptions(mockT));

    await waitFor(() => {
      expect(mockFetchUsers).toHaveBeenCalledTimes(1);
    });
  });

  // S31
  it('calls fetchUserGroups on mount', async () => {
    mockFetchUsers.mockResolvedValue({ rows: [], totalCount: 0 });
    mockFetchUserGroups.mockResolvedValue({ rows: [], totalCount: 0 });

    renderHook(() => useMediaFilterOptions(mockT));

    await waitFor(() => {
      expect(mockFetchUserGroups).toHaveBeenCalledTimes(1);
    });
  });

  // S32
  it('owner options are mapped from fetchUsers response', async () => {
    mockFetchUsers.mockResolvedValue({
      rows: [
        { userId: 1, userName: 'Alice' },
        { userId: 2, userName: 'Bob' },
      ],
      totalCount: 2,
    });
    mockFetchUserGroups.mockResolvedValue({ rows: [], totalCount: 0 });

    const { result } = renderHook(() => useMediaFilterOptions(mockT));

    await waitFor(() => {
      const ownerFilter = result.current.filterOptions.find((f) => f.name === 'ownerId');
      expect(ownerFilter?.options).toEqual([
        { label: 'Alice', value: '1' },
        { label: 'Bob', value: '2' },
      ]);
    });
  });

  // S33
  it('group options are mapped from fetchUserGroups response', async () => {
    mockFetchUsers.mockResolvedValue({ rows: [], totalCount: 0 });
    mockFetchUserGroups.mockResolvedValue({
      rows: [
        { groupId: 10, group: 'Editors' },
        { groupId: 20, group: 'Viewers' },
      ],
      totalCount: 2,
    });

    const { result } = renderHook(() => useMediaFilterOptions(mockT));

    await waitFor(() => {
      const groupFilter = result.current.filterOptions.find((f) => f.name === 'ownerUserGroupId');
      expect(groupFilter?.options).toEqual([
        { label: 'Editors', value: '10' },
        { label: 'Viewers', value: '20' },
      ]);
    });
  });

  // S34
  it('hasMore for owners is true when exactly PAGE_SIZE users returned, false otherwise', async () => {
    // First: exactly PAGE_SIZE → hasMore true
    mockFetchUsers.mockResolvedValueOnce({ rows: makeUsers(PAGE_SIZE), totalCount: PAGE_SIZE });
    mockFetchUserGroups.mockResolvedValue({ rows: [], totalCount: 0 });

    const { result: resultA } = renderHook(() => useMediaFilterOptions(mockT));
    await waitFor(() => {
      const f = resultA.current.filterOptions.find((f) => f.name === 'ownerId');
      expect(f?.hasMore).toBe(true);
    });

    vi.clearAllMocks();

    // Second: fewer than PAGE_SIZE → hasMore false
    mockFetchUsers.mockResolvedValueOnce({ rows: makeUsers(3), totalCount: 3 });
    mockFetchUserGroups.mockResolvedValue({ rows: [], totalCount: 0 });

    const { result: resultB } = renderHook(() => useMediaFilterOptions(mockT));
    await waitFor(() => {
      const f = resultB.current.filterOptions.find((f) => f.name === 'ownerId');
      expect(f?.hasMore).toBe(false);
    });
  });

  // S35
  it('handleLoadMoreOwners appends the next page of users', async () => {
    const firstPage = makeUsers(PAGE_SIZE);
    const secondPage = makeUsers(3).map((u) => ({ ...u, userId: u.userId + PAGE_SIZE }));

    mockFetchUsers
      .mockResolvedValueOnce({ rows: firstPage, totalCount: PAGE_SIZE })
      .mockResolvedValueOnce({ rows: secondPage, totalCount: 3 });
    mockFetchUserGroups.mockResolvedValue({ rows: [], totalCount: 0 });

    const { result } = renderHook(() => useMediaFilterOptions(mockT));

    await waitFor(() => {
      const f = result.current.filterOptions.find((f) => f.name === 'ownerId');
      expect(f?.hasMore).toBe(true);
    });

    act(() => {
      const f = result.current.filterOptions.find((f) => f.name === 'ownerId');
      f?.onLoadMore?.();
    });

    await waitFor(() => {
      const f = result.current.filterOptions.find((f) => f.name === 'ownerId');
      expect(f?.options?.length).toBe(PAGE_SIZE + 3);
    });
  });

  // S36
  it('handleLoadMoreGroups appends the next page of groups', async () => {
    const firstPage = makeGroups(PAGE_SIZE);
    const secondPage = makeGroups(2).map((g) => ({ ...g, groupId: g.groupId + PAGE_SIZE }));

    mockFetchUsers.mockResolvedValue({ rows: [], totalCount: 0 });
    mockFetchUserGroups
      .mockResolvedValueOnce({ rows: firstPage, totalCount: PAGE_SIZE })
      .mockResolvedValueOnce({ rows: secondPage, totalCount: 2 });

    const { result } = renderHook(() => useMediaFilterOptions(mockT));

    await waitFor(() => {
      const f = result.current.filterOptions.find((f) => f.name === 'ownerUserGroupId');
      expect(f?.hasMore).toBe(true);
    });

    act(() => {
      const f = result.current.filterOptions.find((f) => f.name === 'ownerUserGroupId');
      f?.onLoadMore?.();
    });

    await waitFor(() => {
      const f = result.current.filterOptions.find((f) => f.name === 'ownerUserGroupId');
      expect(f?.options?.length).toBe(PAGE_SIZE + 2);
    });
  });

  // S37
  it('filter items that are not ownerId/ownerUserGroupId are passed through unchanged', async () => {
    mockFetchUsers.mockResolvedValue({ rows: [], totalCount: 0 });
    mockFetchUserGroups.mockResolvedValue({ rows: [], totalCount: 0 });

    const { result } = renderHook(() => useMediaFilterOptions(mockT));

    await waitFor(() => {
      const typeFilter = result.current.filterOptions.find((f) => f.name === 'type');
      // 'type' is a plain select filter — no onLoadMore injected.
      expect(typeFilter).toBeDefined();
      expect(typeFilter?.onLoadMore).toBeUndefined();
    });
  });
});
