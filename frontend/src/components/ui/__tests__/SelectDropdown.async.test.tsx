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
// Test type: Component test
// SelectDropdown when the caller owns the data — search handed to the caller,
// loading more when the list is scrolled to the end, and showing the name of a
// selected value that isn't in the loaded options (the class of
// xibo-cms PR #3936, where a Custom daypart past the first page was lost).
//
// JSDOM has no IntersectionObserver; the shared stub reports "the end of the
// list is visible" to whatever is watching inside the open list.
// The search box has no accessible name (xibosignage/xibo#4069), so it is
// found by its placeholder.
// =============================================================================

import { act, cleanup, render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type { ComponentProps } from 'react';
import { vi, beforeEach, afterEach, describe, test, expect } from 'vitest';

import SelectDropdown, { type SelectOption } from '@/components/ui/forms/SelectDropdown';
import { deferred } from '@/testUtils/deferred';
import { stubWatchedIntersectionObserver } from '@/testUtils/intersectionObserver';

const OPTIONS: SelectOption[] = [
  { value: '1', label: 'Morning' },
  { value: '2', label: 'Evening' },
];

let observers: ReturnType<typeof stubWatchedIntersectionObserver>;
const list = () => screen.getByRole('listbox');

const field = () => screen.getByRole('combobox', { name: 'Daypart' });

type PaginatedProps = Extract<ComponentProps<typeof SelectDropdown>, { hasMore: boolean }>;

const paginated = (overrides: Partial<PaginatedProps> = {}): PaginatedProps => ({
  label: 'Daypart',
  options: OPTIONS,
  onSelect: vi.fn(),
  onSearch: vi.fn(),
  onLoadMore: vi.fn(),
  hasMore: false,
  isLoadingMore: false,
  ...overrides,
});

beforeEach(() => {
  observers = stubWatchedIntersectionObserver();
});

// Unmount before removing the stub: an open list still disconnects its observer while
// unmounting, and this hook runs before Testing Library's own cleanup would.
afterEach(() => {
  cleanup();
  vi.unstubAllGlobals();
});

describe('SelectDropdown - search handed to the caller', () => {
  test('opening the list asks the caller for the first page', async () => {
    const user = userEvent.setup();
    const props = paginated();
    render(<SelectDropdown {...props} />);

    await user.click(field());

    expect(props.onSearch).toHaveBeenCalledTimes(1);
    expect(props.onSearch).toHaveBeenCalledWith('');
  });

  test('typing passes the text on, and does not narrow the loaded options itself', async () => {
    const user = userEvent.setup();
    const props = paginated({ searchable: true });
    render(<SelectDropdown {...props} />);

    await user.click(field());
    await user.type(screen.getByPlaceholderText('Search'), 'Eve');

    expect(props.onSearch).toHaveBeenLastCalledWith('Eve');
    expect(screen.getAllByRole('option')).toHaveLength(2);
  });

  test('a paginated list with no way to search the server shows no search box', async () => {
    const user = userEvent.setup();
    // The prop types forbid this combination; it is cast to prove the component also guards it,
    // since a search box here could only filter the page already loaded.
    const props = { ...paginated({ searchable: true }), onSearch: undefined };
    render(<SelectDropdown {...(props as unknown as PaginatedProps)} />);

    await user.click(field());

    expect(screen.queryByPlaceholderText('Search')).not.toBeInTheDocument();
    expect(screen.getAllByRole('option')).toHaveLength(2);
  });
});

describe('SelectDropdown - loading more', () => {
  test('reaching the end of the list asks the caller for more, once', async () => {
    const user = userEvent.setup();
    const props = paginated({ hasMore: true });
    render(<SelectDropdown {...props} />);

    await user.click(field());
    expect(observers.watchedInside(list())).toBe(1);
    observers.showEndOf(list());
    observers.showEndOf(list());

    expect(props.onLoadMore).toHaveBeenCalledTimes(1);
  });

  test('no more are requested while more are already loading', async () => {
    const user = userEvent.setup();
    const props = paginated({ hasMore: true, isLoadingMore: true });
    render(<SelectDropdown {...props} />);

    await user.click(field());
    expect(observers.watchedInside(list())).toBe(1);
    observers.showEndOf(list());

    expect(props.onLoadMore).not.toHaveBeenCalled();
    expect(screen.getByText('Loading…')).toBeInTheDocument();
  });

  test('when there is nothing more to load, nothing is watched or requested', async () => {
    const user = userEvent.setup();
    const props = paginated({ hasMore: false });
    render(<SelectDropdown {...props} />);

    await user.click(field());
    expect(observers.watchedInside(list())).toBe(0);
    observers.showEndOf(list());

    expect(props.onLoadMore).not.toHaveBeenCalled();
  });
});

describe('SelectDropdown - a selected value that is not in the loaded options', () => {
  test('shows the name the caller already knows', () => {
    render(
      <SelectDropdown
        label="Daypart"
        value="42"
        initialLabel="Late Slot"
        options={OPTIONS}
        onSelect={vi.fn()}
      />,
    );

    expect(field()).toHaveTextContent('Late Slot');
  });

  test('keeps a name it has already shown after the options change to another page', () => {
    const { rerender } = render(
      <SelectDropdown label="Daypart" value="2" options={OPTIONS} onSelect={vi.fn()} />,
    );
    expect(field()).toHaveTextContent('Evening');

    rerender(
      <SelectDropdown
        label="Daypart"
        value="2"
        options={[{ value: '7', label: 'Other' }]}
        onSelect={vi.fn()}
      />,
    );

    expect(field()).toHaveTextContent('Evening');
  });

  test('looks the name up once, shows it when it arrives, and remembers it', async () => {
    const resolveLabel = vi.fn().mockResolvedValue('Remote Daypart');
    const props = { label: 'Daypart', options: OPTIONS, onSelect: vi.fn(), resolveLabel };
    const { rerender } = render(<SelectDropdown {...props} value="99" />);

    await waitFor(() => expect(field()).toHaveTextContent('Remote Daypart'));

    // Choose something else, then come back: the name is remembered, not looked up again.
    rerender(<SelectDropdown {...props} value="1" />);
    rerender(<SelectDropdown {...props} value="99" />);
    expect(field()).toHaveTextContent('Remote Daypart');
    expect(resolveLabel).toHaveBeenCalledTimes(1);
    expect(resolveLabel).toHaveBeenCalledWith('99');
  });

  test('if the lookup fails, the placeholder is shown, and the name is looked up again next time', async () => {
    const lookup = deferred<string>();
    const resolveLabel = vi
      .fn<(value: string) => Promise<string>>()
      .mockReturnValueOnce(lookup.promise)
      .mockResolvedValue('Remote Daypart');
    const props = { label: 'Daypart', options: OPTIONS, onSelect: vi.fn(), resolveLabel };
    const { rerender } = render(<SelectDropdown {...props} value="99" />);

    expect(resolveLabel).toHaveBeenCalledWith('99');

    // Let the rejection be handled before looking: before that, the placeholder shows anyway.
    await act(async () => {
      lookup.reject(new Error('lookup failed'));
      await lookup.promise.catch(() => {});
      await new Promise((settled) => setTimeout(settled, 0));
    });

    expect(field()).toHaveTextContent(/^Select$/);

    // The placeholder alone would show whatever the failure did. Coming back to the value proves
    // the failure was neither remembered as an empty name nor left the lookup marked as running.
    rerender(<SelectDropdown {...props} value="1" />);
    rerender(<SelectDropdown {...props} value="99" />);

    await waitFor(() => expect(field()).toHaveTextContent('Remote Daypart'));
    expect(resolveLabel).toHaveBeenCalledTimes(2);
  });
});
