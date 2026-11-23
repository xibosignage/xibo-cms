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
// MultiSelectDropdown when the caller owns the data — server-side search,
// looking up names for selected values that aren't in the option list, and
// loading more options when the list is scrolled to the end.
//
// JSDOM has no IntersectionObserver; the stub below keeps the component's
// callback so a test can report "the end of the list is visible".
// The search box has no accessible name (xibosignage/xibo#4069),
// so it is the only textbox on screen while the list is open.
// =============================================================================

import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { vi, beforeEach, afterEach, describe, test, expect } from 'vitest';

import MultiSelectDropdown, {
  type MultiSelectOption,
} from '@/components/ui/forms/MultiSelectDropdown';

const OPTIONS: MultiSelectOption[] = [
  { value: 'a', label: 'Option A' },
  { value: 'b', label: 'Option B' },
  { value: 'c', label: 'Option C' },
];

let reportEndOfList: (visible: boolean) => void = () => {};

class IntersectionObserverStub {
  constructor(callback: (entries: Partial<IntersectionObserverEntry>[]) => void) {
    reportEndOfList = (visible) => callback([{ isIntersecting: visible }]);
  }
  observe() {}
  unobserve() {}
  disconnect() {}
}

const field = () => screen.getByRole('combobox', { name: 'Displays' });

beforeEach(() => {
  vi.clearAllMocks();
  vi.spyOn(HTMLCanvasElement.prototype, 'getContext').mockReturnValue(null);
  vi.stubGlobal('IntersectionObserver', IntersectionObserverStub);
  reportEndOfList = () => {};
});

afterEach(() => {
  vi.unstubAllGlobals();
});

describe('MultiSelectDropdown with caller-owned data', () => {
  test('server search passes the typed text on and does not narrow the list itself', async () => {
    const user = userEvent.setup();
    const onSearch = vi.fn();
    render(
      <MultiSelectDropdown
        label="Displays"
        value={[]}
        options={OPTIONS}
        onChange={vi.fn()}
        onSearch={onSearch}
      />,
    );

    await user.click(field());
    await user.type(screen.getByRole('textbox'), 'zzz');

    expect(onSearch).toHaveBeenLastCalledWith('zzz');
    expect(screen.getAllByRole('checkbox')).toHaveLength(3);
  });

  test('selected values missing from the options get their names looked up, once each', async () => {
    const resolveLabel = vi.fn().mockResolvedValue('Remote Display');
    const props = {
      label: 'Displays',
      options: [OPTIONS[0]!],
      onChange: vi.fn(),
      resolveLabel,
    };
    const { rerender } = render(<MultiSelectDropdown {...props} value={['a', '99']} />);

    await waitFor(() => expect(field()).toHaveTextContent('Option A, Remote Display'));

    rerender(<MultiSelectDropdown {...props} value={['a', '99']} />);
    expect(resolveLabel).toHaveBeenCalledTimes(1);
    expect(resolveLabel).toHaveBeenCalledWith('99');
  });

  test('if a name lookup fails, the raw value is shown', async () => {
    const resolveLabel = vi.fn().mockRejectedValue(new Error('lookup failed'));
    render(
      <MultiSelectDropdown
        label="Displays"
        value={['a', '99']}
        options={[OPTIONS[0]!]}
        onChange={vi.fn()}
        resolveLabel={resolveLabel}
      />,
    );

    await waitFor(() => expect(resolveLabel).toHaveBeenCalled());
    await waitFor(() => expect(field()).toHaveTextContent('Option A, 99'));
  });

  test('reaching the end of the list asks the caller for more, once', async () => {
    const user = userEvent.setup();
    const onLoadMore = vi.fn();
    render(
      <MultiSelectDropdown
        label="Displays"
        value={[]}
        options={OPTIONS}
        onChange={vi.fn()}
        hasMore
        onLoadMore={onLoadMore}
      />,
    );

    await user.click(field());
    await screen.findByRole('listbox');
    reportEndOfList(true);
    reportEndOfList(true);

    expect(onLoadMore).toHaveBeenCalledTimes(1);
  });

  test('no more are requested while more are already loading', async () => {
    const user = userEvent.setup();
    const onLoadMore = vi.fn();
    render(
      <MultiSelectDropdown
        label="Displays"
        value={[]}
        options={OPTIONS}
        onChange={vi.fn()}
        hasMore
        isLoadingMore
        onLoadMore={onLoadMore}
      />,
    );

    await user.click(field());
    await screen.findByRole('listbox');
    reportEndOfList(true);

    expect(onLoadMore).not.toHaveBeenCalled();
    expect(screen.getByText('Loading…')).toBeInTheDocument();
  });
});
