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
// Test type: Component test (wired)
// SelectDropdown fed by the shared paging hook, usePaginatedOptions, over a
// list longer than one page. The dropdown only asks for "more" and passes
// search text on; the hook owns the page number. Only together can they show
// that a new search starts again from the first page and that a choice made on
// page 2 keeps its name when the list is replaced.
//
// JSDOM has no IntersectionObserver; the shared stub reports "the end of the
// list is visible" to whatever is watching inside the open list.
// The search box has no accessible name (xibosignage/xibo#4069), so it is
// found by its placeholder.
// =============================================================================

import { act, cleanup, render, screen, waitFor } from '@testing-library/react';
import userEvent, { type UserEvent } from '@testing-library/user-event';
import { useState } from 'react';
import { vi, beforeEach, afterEach, describe, test, expect } from 'vitest';

import { usePaginatedOptions } from '../../shared/hooks/usePaginatedOptions';
import type { OptionsLoader } from '../../shared/hooks/usePaginatedOptions';

import SelectDropdown from '@/components/ui/forms/SelectDropdown';
import { stubWatchedIntersectionObserver } from '@/testUtils/intersectionObserver';

const PAGE_SIZE = 20;

// 45 layouts: more than two pages, so the end of page 2 still offers more.
const ROWS = Array.from({ length: 45 }, (_, i) => ({
  value: String(i + 1),
  label: `Layout ${i + 1}`,
}));

const loader = vi.fn<OptionsLoader>(async (search, start) => {
  const matching = ROWS.filter((r) => r.label.includes(search));
  return { options: matching.slice(start, start + PAGE_SIZE), totalCount: matching.length };
});

let observers: ReturnType<typeof stubWatchedIntersectionObserver>;
const scrollToEnd = () => observers.showEndOf(screen.getByRole('listbox'));

/** The names of the layouts whose name contains `term`, as one page of search results. */
const searchResults = (term: string) =>
  ROWS.filter((r) => r.label.includes(term))
    .slice(0, PAGE_SIZE)
    .map((r) => r.label);

function LayoutPicker() {
  const [value, setValue] = useState('');
  const paging = usePaginatedOptions({ loader });
  return <SelectDropdown label="Layout" searchable value={value} onSelect={setValue} {...paging} />;
}

const field = () => screen.getByRole('combobox', { name: 'Layout' });
const optionNames = () => screen.getAllByRole('option').map((o) => o.textContent);

/**
 * Open the list and wait for the first page it asks for. The list already holds a first page from
 * an earlier load, so waiting on the option count alone would pass before the new answer arrives.
 */
const openFirstPage = async (user: UserEvent) => {
  const callsBefore = loader.mock.calls.length;
  await user.click(field());
  await waitFor(() => expect(loader).toHaveBeenCalledTimes(callsBefore + 1));
  await act(async () => {
    await loader.mock.results[callsBefore]!.value;
  });
  await waitFor(() => expect(optionNames()).toHaveLength(PAGE_SIZE));
};

beforeEach(() => {
  vi.clearAllMocks();
  observers = stubWatchedIntersectionObserver();
});

// Unmount before removing the stub: an open list still disconnects its observer while
// unmounting, and this hook runs before Testing Library's own cleanup would.
afterEach(() => {
  cleanup();
  vi.unstubAllGlobals();
});

describe('SelectDropdown with usePaginatedOptions, over more than one page', () => {
  test('opening the list asks for the first page again, and shows the fresh 20', async () => {
    const user = userEvent.setup();
    render(<LayoutPicker />);
    // The hook loads the first page on mount. Forget that request, then let a layout be added
    // before the list is opened, so the list only passes if the fresh answer replaces the old one.
    await waitFor(() => expect(loader).toHaveBeenCalled());
    loader.mockClear();
    loader.mockImplementationOnce(async () => ({
      options: [{ value: '46', label: 'Layout 46' }, ...ROWS.slice(0, PAGE_SIZE - 1)],
      totalCount: ROWS.length + 1,
    }));

    await openFirstPage(user);

    expect(loader).toHaveBeenCalledWith('', 0, expect.any(AbortSignal));
    expect(optionNames()[0]).toBe('Layout 46');
    expect(optionNames()[optionNames().length - 1]).toBe('Layout 19');
  });

  test('scrolling to the end loads the next page and adds it to the list', async () => {
    const user = userEvent.setup();
    render(<LayoutPicker />);
    await openFirstPage(user);

    scrollToEnd();

    await waitFor(() => expect(optionNames()).toHaveLength(2 * PAGE_SIZE));
    expect(loader).toHaveBeenLastCalledWith('', PAGE_SIZE, expect.any(AbortSignal));
    expect(optionNames()[0]).toBe('Layout 1');
    expect(optionNames()[optionNames().length - 1]).toBe('Layout 40');
  });

  test('scrolling to the end again loads the last page, and then nothing more is offered', async () => {
    const user = userEvent.setup();
    render(<LayoutPicker />);
    await openFirstPage(user);
    scrollToEnd();
    await waitFor(() => expect(optionNames()).toHaveLength(2 * PAGE_SIZE));

    scrollToEnd();

    await waitFor(() => expect(optionNames()).toHaveLength(ROWS.length));
    expect(loader).toHaveBeenLastCalledWith('', 2 * PAGE_SIZE, expect.any(AbortSignal));
    expect(observers.watchedInside(screen.getByRole('listbox'))).toBe(0);
  });

  test('a new search starts again from the first page and replaces the list', async () => {
    const user = userEvent.setup();
    render(<LayoutPicker />);
    await openFirstPage(user);
    scrollToEnd();
    await waitFor(() => expect(optionNames()).toHaveLength(2 * PAGE_SIZE));

    await user.type(screen.getByPlaceholderText('Search'), '4');

    await waitFor(() => expect(loader).toHaveBeenLastCalledWith('4', 0, expect.any(AbortSignal)));
    await waitFor(() =>
      expect(optionNames()).toEqual([
        'Layout 4',
        'Layout 14',
        'Layout 24',
        'Layout 34',
        'Layout 40',
        'Layout 41',
        'Layout 42',
        'Layout 43',
        'Layout 44',
        'Layout 45',
      ]),
    );
  });

  test('a layout chosen on page 2 keeps its name after a search replaces the list', async () => {
    const user = userEvent.setup();
    render(<LayoutPicker />);
    await openFirstPage(user);
    scrollToEnd();
    await user.click(await screen.findByRole('option', { name: 'Layout 33' }));
    expect(field()).toHaveTextContent('Layout 33');

    await openFirstPage(user);
    await user.type(screen.getByPlaceholderText('Search'), '1');
    // The search results have replaced the list, and Layout 33 is not among them.
    await waitFor(() => expect(optionNames()).toEqual(searchResults('1')));
    expect(optionNames()).not.toContain('Layout 33');
    await user.keyboard('{Escape}');

    expect(field()).toHaveTextContent('Layout 33');
  });
});
