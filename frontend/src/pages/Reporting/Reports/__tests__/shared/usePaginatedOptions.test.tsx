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
// Test type: Hook — the paged, searchable option lists behind report dropdowns
// =============================================================================

import { act, renderHook, waitFor } from '@testing-library/react';
import { vi, beforeEach, describe, test, expect } from 'vitest';

import { usePaginatedOptions } from '../../shared/hooks/usePaginatedOptions';
import type { OptionsLoader } from '../../shared/hooks/usePaginatedOptions';

import { deferred } from '@/testUtils/deferred';
import { knownFailure } from '@/testUtils/knownFailure';

type Answer = Awaited<ReturnType<OptionsLoader>>;

// Options named after the search and position, so a test can tell which answer won.
const answer = (search: string, start: number, count: number, totalCount: number): Answer => ({
  options: Array.from({ length: count }, (_, i) => ({
    value: `${search || 'all'}-${start + i}`,
    label: `${search || 'all'} ${start + i}`,
  })),
  totalCount,
});

// 30 options in total, served 20 at a time.
const FIRST_PAGE = answer('', 0, 20, 30);
const SECOND_PAGE = answer('', 20, 10, 30);

const loader = vi.fn<OptionsLoader>();
const values = (result: { current: { options: { value: string }[] } }) =>
  result.current.options.map((o) => o.value);

describe('usePaginatedOptions', () => {
  beforeEach(() => {
    loader.mockReset();
    loader.mockResolvedValue(FIRST_PAGE);
  });

  test('loads the first page, and offers more while the total is larger', async () => {
    const { result } = renderHook(() => usePaginatedOptions({ loader }));

    await waitFor(() => expect(result.current.options).toHaveLength(20));
    expect(loader).toHaveBeenCalledWith('', 0, expect.any(AbortSignal));
    expect(result.current.hasMore).toBe(true);
  });

  test('loading more asks from the loaded count and appends', async () => {
    const { result } = renderHook(() => usePaginatedOptions({ loader }));
    await waitFor(() => expect(result.current.options).toHaveLength(20));
    loader.mockResolvedValueOnce(SECOND_PAGE);

    act(() => result.current.onLoadMore());

    await waitFor(() => expect(result.current.options).toHaveLength(30));
    expect(loader).toHaveBeenLastCalledWith('', 20, expect.any(AbortSignal));
    expect(values(result)[20]).toBe('all-20');
    expect(result.current.hasMore).toBe(false);
  });

  test('a search replaces the list from the first page', async () => {
    const { result } = renderHook(() => usePaginatedOptions({ loader }));
    await waitFor(() => expect(result.current.options).toHaveLength(20));
    loader.mockResolvedValueOnce(answer('lob', 0, 20, 30));

    act(() => result.current.onSearch('lob'));

    await waitFor(() => expect(values(result)[0]).toBe('lob-0'));
    expect(loader).toHaveBeenLastCalledWith('lob', 0, expect.any(AbortSignal));
    expect(values(result)).not.toContain('all-0');
  });

  test('a slow older answer cannot overwrite a newer one', async () => {
    const { result } = renderHook(() => usePaginatedOptions({ loader }));
    await waitFor(() => expect(result.current.options).toHaveLength(20));
    const first = deferred<Answer>();
    const second = deferred<Answer>();
    loader.mockReturnValueOnce(first.promise).mockReturnValueOnce(second.promise);

    act(() => result.current.onSearch('l'));
    act(() => result.current.onSearch('lo'));
    await act(async () => second.resolve(answer('lo', 0, 2, 2)));
    await act(async () => first.resolve(answer('l', 0, 5, 5)));

    expect(values(result)).toEqual(['lo-0', 'lo-1']);
  });

  test('changing the reset key reloads from the first page', async () => {
    const { result, rerender } = renderHook(
      ({ resetKey }) => usePaginatedOptions({ loader, resetKey }),
      { initialProps: { resetKey: 'layout' } },
    );
    await waitFor(() => expect(result.current.options).toHaveLength(20));
    loader.mockClear();

    rerender({ resetKey: 'media' });

    await waitFor(() => expect(loader).toHaveBeenCalledWith('', 0, expect.any(AbortSignal)));
  });

  test('disabling the list clears its options', async () => {
    const { result, rerender } = renderHook(
      ({ enabled }) => usePaginatedOptions({ loader, enabled }),
      { initialProps: { enabled: true } },
    );
    await waitFor(() => expect(result.current.options).toHaveLength(20));

    rerender({ enabled: false });

    expect(result.current.options).toEqual([]);
  });

  test('nothing is loaded while disabled', () => {
    renderHook(() => usePaginatedOptions({ loader, enabled: false }));

    expect(loader).not.toHaveBeenCalled();
  });

  test('a failed load keeps the options already shown', async () => {
    const { result } = renderHook(() => usePaginatedOptions({ loader }));
    await waitFor(() => expect(result.current.options).toHaveLength(20));
    loader.mockRejectedValueOnce(new Error('Network Error'));

    act(() => result.current.onSearch('lob'));

    await waitFor(() => expect(result.current.isLoading).toBe(false));
    expect(result.current.options).toHaveLength(20);
    expect(values(result)[0]).toBe('all-0');
  });

  // Known issue 13: xibosignage/xibo#4104 — load-more during a search appends to the old list.
  describe('Pinned until https://github.com/xibosignage/xibo/issues/4104 is fixed', () => {
    knownFailure(
      'loading more while a search is running keeps the list to that search',
      async () => {
        const { result } = renderHook(() => usePaginatedOptions({ loader }));
        await waitFor(() => expect(result.current.options).toHaveLength(20));
        const search = deferred<Answer>();
        // The search is still running when the old list's next page arrives.
        loader.mockReturnValueOnce(search.promise).mockResolvedValueOnce(SECOND_PAGE);

        act(() => result.current.onSearch('lob'));
        act(() => result.current.onLoadMore());
        // Control: the load-more answered and the list grew.
        await waitFor(() => expect(result.current.isLoadingMore).toBe(false));
        await act(async () => search.resolve(answer('lob', 0, 20, 30)));
        return values(result);
      },
      (shown) => expect(shown.every((value) => value.startsWith('lob-'))).toBe(true),
    );
  });
});
