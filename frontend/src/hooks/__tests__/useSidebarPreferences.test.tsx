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

import { useSidebarCollapsed, useSidebarOpenMenus } from '../useSidebarPreferences';

import { fetchUserPreference, saveUserPreference } from '@/services/userApi';
import { deferred } from '@/testUtils/deferred';
import { knownFailure } from '@/testUtils/knownFailure';

// The sidebar remembers whether it is collapsed and which menus are open
// (added by xibo-cms PR #3886): in localStorage, so it paints right away, and as
// a user preference, so it follows the user to another browser. The server
// value wins once loaded.
// https://github.com/xibosignage/xibo-cms/pull/3886

// ─── Mocks ────────────────────────────────────────────────────────────────────

vi.mock('@/services/userApi', () => ({
  fetchUserPreference: vi.fn(),
  saveUserPreference: vi.fn(),
}));

// ─── Fixtures ─────────────────────────────────────────────────────────────────

const COLLAPSED = 'sidebar.isCollapsed';
const OPEN_MENUS = 'sidebar.openMenus';
const LOCAL_COLLAPSED = 'xibo.sidebar.isCollapsed';
const LOCAL_OPEN_MENUS = 'xibo.sidebar.openMenus';

let stored: Record<string, unknown>;

// ─── Helpers ──────────────────────────────────────────────────────────────────

let queryClient: QueryClient;

const wrapper = ({ children }: { children: ReactNode }) => (
  <QueryClientProvider client={queryClient}>{children}</QueryClientProvider>
);

// Waits for the server value itself to arrive and be applied, not for the
// query to look done: a toggle writes the cache with setQueryData, which marks
// the query a success while the real fetch is still in flight. Resolve any
// deferred fetch before calling this.
const settled = async (key: string) => {
  await act(async () => {
    await Promise.allSettled(vi.mocked(fetchUserPreference).mock.results.map((r) => r.value));
  });
  await waitFor(() => expect(queryClient.isFetching({ queryKey: ['userPref', key] })).toBe(0));
  await act(() => new Promise((r) => setTimeout(r, 0)));
};

const savesOf = (option: string) =>
  vi
    .mocked(saveUserPreference)
    .mock.calls.map(([arg]) => arg)
    .filter((arg) => arg.option === option);

// Moves the fake clock on, past the 500 ms open-menus debounce where needed.
const tick = (ms: number) => act(() => vi.advanceTimersByTimeAsync(ms));

// ─── Tests ────────────────────────────────────────────────────────────────────

beforeEach(() => {
  // shouldAdvanceTime keeps waitFor and React Query running on real time.
  vi.useFakeTimers({ shouldAdvanceTime: true });
  queryClient = new QueryClient({ defaultOptions: { queries: { retry: false } } });
  stored = {};
  localStorage.clear();
  vi.mocked(fetchUserPreference).mockImplementation(async (key) => stored[key] as never);
  vi.mocked(saveUserPreference).mockResolvedValue();
});

afterEach(() => {
  vi.useRealTimers();
  queryClient.clear();
  vi.clearAllMocks();
  vi.restoreAllMocks();
});

describe('useSidebarCollapsed', () => {
  test('starts from what this browser last showed', () => {
    localStorage.setItem(LOCAL_COLLAPSED, 'true');
    stored[COLLAPSED] = true;

    const { result } = renderHook(() => useSidebarCollapsed(), { wrapper });

    expect(result.current[0]).toBe(true);
  });

  test('takes the saved preference once it loads, and remembers it locally', async () => {
    localStorage.setItem(LOCAL_COLLAPSED, 'false');
    stored[COLLAPSED] = true;

    const { result } = renderHook(() => useSidebarCollapsed(), { wrapper });

    await waitFor(() => expect(result.current[0]).toBe(true));
    expect(localStorage.getItem(LOCAL_COLLAPSED)).toBe('true');
  });

  test('keeps the local state when nothing is saved on the server', async () => {
    localStorage.setItem(LOCAL_COLLAPSED, 'true');

    const { result } = renderHook(() => useSidebarCollapsed(), { wrapper });
    await settled(COLLAPSED);

    expect(result.current[0]).toBe(true);
  });

  test('a toggle before the saved value loads is not undone by it', async () => {
    const pending = deferred<unknown>();
    vi.mocked(fetchUserPreference).mockReturnValue(pending.promise as never);
    const { result } = renderHook(() => useSidebarCollapsed(), { wrapper });

    act(() => result.current[1](true));
    pending.resolve(false);
    await settled(COLLAPSED);

    expect(result.current[0]).toBe(true);
  });

  test('a toggle is saved to the server and locally', async () => {
    const { result } = renderHook(() => useSidebarCollapsed(), { wrapper });
    await settled(COLLAPSED);

    act(() => result.current[1](true));

    expect(result.current[0]).toBe(true);
    expect(localStorage.getItem(LOCAL_COLLAPSED)).toBe('true');
    await waitFor(() => expect(savesOf(COLLAPSED)).toEqual([{ option: COLLAPSED, value: true }]));
  });

  test('loading the saved value does not save it again', async () => {
    stored[COLLAPSED] = true;

    renderHook(() => useSidebarCollapsed(), { wrapper });
    await settled(COLLAPSED);
    await tick(20);

    expect(saveUserPreference).not.toHaveBeenCalled();
  });

  test('still works when the browser blocks localStorage', async () => {
    vi.spyOn(Storage.prototype, 'getItem').mockImplementation(() => {
      throw new Error('blocked');
    });
    vi.spyOn(Storage.prototype, 'setItem').mockImplementation(() => {
      throw new Error('blocked');
    });
    stored[COLLAPSED] = true;

    const { result } = renderHook(() => useSidebarCollapsed(), { wrapper });

    expect(result.current[0]).toBe(false);
    await waitFor(() => expect(result.current[0]).toBe(true));
  });
});

describe('useSidebarOpenMenus', () => {
  test('starts with the menus this browser last had open', () => {
    localStorage.setItem(LOCAL_OPEN_MENUS, '["/design"]');

    const { result } = renderHook(() => useSidebarOpenMenus(), { wrapper });

    expect([...result.current[0]]).toEqual(['/design']);
  });

  test('ignores unreadable local data', () => {
    localStorage.setItem(LOCAL_OPEN_MENUS, '{broken');

    const { result } = renderHook(() => useSidebarOpenMenus(), { wrapper });

    expect(result.current[0].size).toBe(0);
  });

  test('adds the saved menus to the local ones once they load', async () => {
    localStorage.setItem(LOCAL_OPEN_MENUS, '["/design"]');
    stored[OPEN_MENUS] = ['/displays'];

    const { result } = renderHook(() => useSidebarOpenMenus(), { wrapper });

    await waitFor(() => expect([...result.current[0]].sort()).toEqual(['/design', '/displays']));
  });

  test('a menu closed before the saved menus load is not reopened by them', async () => {
    localStorage.setItem(LOCAL_OPEN_MENUS, '["/design"]');
    const pending = deferred<unknown>();
    vi.mocked(fetchUserPreference).mockReturnValue(pending.promise as never);
    const { result } = renderHook(() => useSidebarOpenMenus(), { wrapper });

    act(() => result.current[1](new Set()));
    pending.resolve(['/design']);
    await settled(OPEN_MENUS);

    expect(result.current[0].size).toBe(0);
  });

  // Opening the menu for the current page on load must not overwrite the
  // saved menus with just that one before they have loaded.
  test('saves nothing until the saved menus have loaded', async () => {
    const pending = deferred<unknown>();
    vi.mocked(fetchUserPreference).mockReturnValue(pending.promise as never);
    const { result } = renderHook(() => useSidebarOpenMenus(), { wrapper });

    act(() => result.current[1]((prev) => new Set([...prev, '/library'])));
    await tick(600);

    expect(savesOf(OPEN_MENUS)).toEqual([]);
  });

  test('loading when nothing is saved anywhere saves nothing', async () => {
    renderHook(() => useSidebarOpenMenus(), { wrapper });
    await settled(OPEN_MENUS);
    await tick(600);

    expect(saveUserPreference).not.toHaveBeenCalled();
  });

  // Open. The merge always builds a new Set, so the menus are saved back on
  // every page load even when the server already has exactly these menus. The
  // app mounts the sidebar twice (desktop, and the hidden mobile drawer), so
  // that is two needless POST /user/pref per load (the same class of extra save
  // as xibo-cms PR #3945).
  // https://github.com/xibosignage/xibo/issues/4078
  knownFailure(
    'loading saved menus that match the local ones does not save them again',
    async () => {
      localStorage.setItem(LOCAL_OPEN_MENUS, '["/design"]');
      stored[OPEN_MENUS] = ['/design'];
      renderHook(() => useSidebarOpenMenus(), { wrapper });
      await settled(OPEN_MENUS);
      await tick(600);
    },
    () => expect(saveUserPreference).not.toHaveBeenCalled(),
  );

  test('saves the open menus once, half a second after the last change', async () => {
    const { result } = renderHook(() => useSidebarOpenMenus(), { wrapper });
    await settled(OPEN_MENUS);

    act(() => result.current[1](new Set(['/design'])));
    await tick(100);
    act(() => result.current[1](new Set(['/design', '/library'])));
    await tick(100);
    expect(savesOf(OPEN_MENUS)).toEqual([]);

    await waitFor(() =>
      expect(savesOf(OPEN_MENUS)).toEqual([{ option: OPEN_MENUS, value: ['/design', '/library'] }]),
    );
    expect(localStorage.getItem(LOCAL_OPEN_MENUS)).toBe('["/design","/library"]');
  });

  // The fix in xibo-cms PR #3945 relies on this: SideBar's current-page
  // auto-open now hands back the same set when the menu is already open, so
  // nothing is saved.
  // https://github.com/xibosignage/xibo-cms/pull/3945
  test('an update that returns the same set saves nothing', async () => {
    const { result } = renderHook(() => useSidebarOpenMenus(), { wrapper });
    await settled(OPEN_MENUS);

    act(() => result.current[1]((prev) => prev));
    await tick(600);

    expect(savesOf(OPEN_MENUS)).toEqual([]);
  });

  test('a change still waiting to be saved is saved when the sidebar unmounts', async () => {
    const { result, unmount } = renderHook(() => useSidebarOpenMenus(), { wrapper });
    await settled(OPEN_MENUS);

    act(() => result.current[1](new Set(['/design'])));
    unmount();

    await waitFor(() =>
      expect(savesOf(OPEN_MENUS)).toEqual([{ option: OPEN_MENUS, value: ['/design'] }]),
    );
  });
});
