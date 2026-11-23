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

import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { act, renderHook, waitFor } from '@testing-library/react';
import type { ReactNode } from 'react';
import { afterEach, beforeEach, describe, expect, test, vi } from 'vitest';

import { useTableState, type TablePreferences } from '../useTableState';

import { useUserContext } from '@/context/UserContext';
import { fetchUserPreference, saveUserPreference } from '@/services/userApi';
import { deferred } from '@/testUtils/deferred';

// The hook every grid uses to restore and save its state (filters, search,
// sorting, columns, view, folder) as a user preference. userApi is mocked:
// what it sends is covered in services/__tests__/userApi.test.ts.

// ─── Mocks ────────────────────────────────────────────────────────────────────

vi.mock('@/services/userApi', () => ({
  fetchUserPreference: vi.fn(),
  saveUserPreference: vi.fn(),
}));

vi.mock('@/context/UserContext', () => ({
  useUserContext: vi.fn(),
}));

// ─── Fixtures ─────────────────────────────────────────────────────────────────

interface Filters {
  status: string;
}

const PAGE = 'layout_page';

const defaults: TablePreferences<Filters> = {
  pagination: { pageIndex: 3, pageSize: 10 },
  sorting: [],
  columnVisibility: {},
  viewMode: 'table',
  globalFilter: '',
  filterInputs: { status: '' },
  folderId: 1,
};

// What the server has stored, by preference name. A missing name is a
// preference never saved; a name set to null is one saved as null.
let stored: Record<string, unknown>;

const setUserSettings = (settings: Record<string, unknown>) =>
  vi.mocked(useUserContext).mockReturnValue({
    user: { settings },
    isAuthenticated: true,
    logout: vi.fn(),
    updateUser: vi.fn(),
  } as unknown as ReturnType<typeof useUserContext>);

// ─── Helpers ──────────────────────────────────────────────────────────────────

let queryClient: QueryClient;

const wrapper = ({ children }: { children: ReactNode }) => (
  <QueryClientProvider client={queryClient}>{children}</QueryClientProvider>
);

const renderTableState = async (debounceMs = 20) => {
  const view = renderHook(() => useTableState<Filters>(PAGE, defaults, debounceMs), { wrapper });
  await waitFor(() => expect(view.result.current.isHydrated).toBe(true));
  return view;
};

const savesOf = (option: string) =>
  vi
    .mocked(saveUserPreference)
    .mock.calls.map(([arg]) => arg)
    .filter((arg) => arg.option === option);

// ─── Tests ────────────────────────────────────────────────────────────────────

beforeEach(() => {
  queryClient = new QueryClient({ defaultOptions: { queries: { retry: false } } });
  stored = {};
  vi.mocked(fetchUserPreference).mockImplementation(async (key) => stored[key] as never);
  vi.mocked(saveUserPreference).mockResolvedValue();
  setUserSettings({});
});

afterEach(() => {
  vi.useRealTimers();
  queryClient.clear();
  vi.clearAllMocks();
});

describe('restoring the folder', () => {
  // "All Items" is stored as null. It used to be treated like no preference at
  // all, so the page fell back to the default folder. Fixed by xibo-cms PR #3919.
  // https://github.com/xibosignage/xibo/issues/3936
  // https://github.com/xibosignage/xibo-cms/pull/3919
  test('a folder saved as "All Items" (null) is restored as All Items', async () => {
    stored.globalSelectedFolderId = null;

    const { result } = await renderTableState();

    expect(result.current.folderId).toBeNull();
  });

  test('a saved folder is restored', async () => {
    stored.globalSelectedFolderId = 12;

    const { result } = await renderTableState();

    expect(result.current.folderId).toBe(12);
  });

  test('with no folder saved, the default folder is kept', async () => {
    const { result } = await renderTableState();

    expect(result.current.folderId).toBe(1);
  });

  test('uses one folder for every page when the folder is remembered globally', async () => {
    await renderTableState();

    expect(fetchUserPreference).toHaveBeenCalledWith('globalSelectedFolderId');
    expect(fetchUserPreference).not.toHaveBeenCalledWith(`${PAGE}_folderId`);
  });

  test('uses a folder per page when the user turns global folders off', async () => {
    setUserSettings({ rememberFolderTreeStateGlobally: 0 });
    stored[`${PAGE}_folderId`] = 4;

    const { result } = await renderTableState();

    expect(fetchUserPreference).toHaveBeenCalledWith(`${PAGE}_folderId`);
    expect(result.current.folderId).toBe(4);
  });

  test('a folder picked before the saved one loads is not overwritten', async () => {
    const pending = deferred<unknown>();
    vi.mocked(fetchUserPreference).mockImplementation(async (key) =>
      key === 'globalSelectedFolderId' ? (pending.promise as never) : (stored[key] as never),
    );
    const { result } = renderHook(() => useTableState<Filters>(PAGE, defaults, 20), { wrapper });

    act(() => result.current.setFolderId(7));
    await act(async () => pending.resolve(12));

    expect(result.current.folderId).toBe(7);
  });

  test('picking a folder saves it straight away', async () => {
    const { result } = await renderTableState();

    act(() => result.current.setFolderId(null));

    expect(savesOf('globalSelectedFolderId')).toEqual([
      { option: 'globalSelectedFolderId', value: null },
    ]);
  });

  test('the grid still loads when the folder preference cannot be read', async () => {
    vi.mocked(fetchUserPreference).mockImplementation(async (key) => {
      if (key === 'globalSelectedFolderId') throw new Error('500');
      return stored[key] as never;
    });

    const { result } = await renderTableState();

    expect(result.current.folderId).toBe(1);
  });
});

describe('restoring the grid', () => {
  // The grid waits for isHydrated before it fetches rows. A page never visited
  // has no preference, and must still hydrate (the empty-grid regression when
  // fetchUserPreference started returning undefined for "nothing stored").
  test('a page with nothing saved still finishes loading, with the defaults', async () => {
    const { result } = await renderTableState();

    expect(result.current.isHydrated).toBe(true);
    expect(result.current.viewMode).toBe('table');
    expect(result.current.filterInputs).toEqual({ status: '' });
  });

  test('is not ready until the saved state has loaded', async () => {
    const pending = deferred<unknown>();
    vi.mocked(fetchUserPreference).mockImplementation(async (key) =>
      key === PAGE ? (pending.promise as never) : (stored[key] as never),
    );
    const { result } = renderHook(() => useTableState<Filters>(PAGE, defaults, 20), { wrapper });

    await act(async () => {});
    expect(result.current.isHydrated).toBe(false);

    pending.resolve({ viewMode: 'grid' });
    await waitFor(() => expect(result.current.isHydrated).toBe(true));
    expect(result.current.viewMode).toBe('grid');
  });

  test('restores sorting, columns, view and filters', async () => {
    stored[PAGE] = {
      sorting: [{ id: 'name', desc: true }],
      columnVisibility: { owner: false },
      viewMode: 'grid',
      filterInputs: { status: 'published' },
    };

    const { result } = await renderTableState();

    expect(result.current.sorting).toEqual([{ id: 'name', desc: true }]);
    expect(result.current.columnVisibility).toEqual({ owner: false });
    expect(result.current.viewMode).toBe('grid');
    expect(result.current.filterInputs).toEqual({ status: 'published' });
  });

  // The search text was neither restored nor saved until xibo-cms PR #3928.
  // https://github.com/xibosignage/xibo-cms/pull/3928
  test('restores the search text, and searches with it straight away', async () => {
    stored[PAGE] = { globalFilter: 'lobby' };

    const { result } = await renderTableState();

    expect(result.current.globalFilter).toBe('lobby');
    expect(result.current.debouncedFilter).toBe('lobby');
  });

  test('restores an empty saved search as empty', async () => {
    stored[PAGE] = { globalFilter: '' };

    const { result } = await renderTableState();

    expect(result.current.globalFilter).toBe('');
  });

  // The page index is never restored: the grid opens on the page it is given
  // (pageIndex 3 in this fixture, so it can't be mistaken for a reset to 0).
  test('ignores the saved page and opens on the page it is given', async () => {
    stored[PAGE] = { pagination: { pageIndex: 5, pageSize: 10 } };

    const { result } = await renderTableState();

    expect(result.current.pagination).toEqual({ pageIndex: 3, pageSize: 10 });
  });
});

describe('saving the grid', () => {
  test('restoring the saved state does not save it again', async () => {
    stored[PAGE] = { viewMode: 'grid', globalFilter: 'lobby' };
    // shouldAdvanceTime keeps waitFor and React Query running on real time.
    vi.useFakeTimers({ shouldAdvanceTime: true });

    await renderTableState();
    // Well past the 20 ms debounce, however slow the runner
    await act(() => vi.advanceTimersByTimeAsync(100));

    expect(savesOf(PAGE)).toEqual([]);
  });

  test('saves once, after the debounce, when several things change', async () => {
    const { result } = await renderTableState(20);

    act(() => result.current.setViewMode('grid'));
    act(() => result.current.setFilterInputs({ status: 'draft' }));
    expect(savesOf(PAGE)).toEqual([]);

    await waitFor(() => expect(savesOf(PAGE)).toHaveLength(1));
    expect(savesOf(PAGE)[0]!.value).toMatchObject({
      viewMode: 'grid',
      filterInputs: { status: 'draft' },
      globalFilter: '',
      time: expect.any(Number),
    });
  });

  test('saves the search text', async () => {
    const { result } = await renderTableState(20);

    act(() => result.current.setGlobalFilter('lobby'));

    await waitFor(() => expect(savesOf(PAGE)).toHaveLength(1));
    expect(savesOf(PAGE)[0]!.value).toMatchObject({ globalFilter: 'lobby' });
  });

  test('does not save the page number', async () => {
    const { result } = await renderTableState(20);

    act(() => result.current.setPagination({ pageIndex: 2, pageSize: 10 }));
    act(() => result.current.setViewMode('grid'));

    await waitFor(() => expect(savesOf(PAGE)).toHaveLength(1));
    expect(savesOf(PAGE)[0]!.value).not.toHaveProperty('pagination');
  });

  test('a change still waiting to be saved is saved when the page closes', async () => {
    const { result, unmount } = await renderTableState(10_000);

    act(() => result.current.setViewMode('grid'));
    unmount();

    await waitFor(() => expect(savesOf(PAGE)).toHaveLength(1));
    expect(savesOf(PAGE)[0]!.value).toMatchObject({ viewMode: 'grid' });
  });

  test('a saved change is what the page restores next time, without asking the server', async () => {
    const first = await renderTableState(20);
    act(() => first.result.current.setViewMode('grid'));
    await waitFor(() => expect(savesOf(PAGE)).toHaveLength(1));
    first.unmount();
    vi.mocked(fetchUserPreference).mockClear();

    const second = await renderTableState(20);

    expect(second.result.current.viewMode).toBe('grid');
    expect(fetchUserPreference).not.toHaveBeenCalledWith(PAGE);
  });
});

describe('sorting', () => {
  test('changing the sort goes back to the first page', async () => {
    const { result } = await renderTableState();

    act(() => result.current.setPagination({ pageIndex: 4, pageSize: 10 }));
    act(() => result.current.setSorting([{ id: 'name', desc: false }]));

    expect(result.current.pagination.pageIndex).toBe(0);
    expect(result.current.sorting).toEqual([{ id: 'name', desc: false }]);
  });
});
