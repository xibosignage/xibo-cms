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
// Test type: Component — PaginatedMultiSelectDropdown
// =============================================================================

import { render, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { useState } from 'react';
import { vi, beforeEach, describe, test, expect } from 'vitest';

import {
  intersectionCallbacks,
  stubIntersectionObserver,
  triggerIntersection,
} from '../../__tests__/helpers/domStubs';
import PaginatedMultiSelectDropdown from '../components/PaginatedMultiSelectDropdown';

const OPTIONS = [
  { value: '301', label: 'Lobby Layout' },
  { value: '302', label: 'Layout 2' },
];

const onSearch = vi.fn();
const onLoadMore = vi.fn();

// Holds the selection like the page does, so chips follow ticks.
function Harness({
  options = OPTIONS,
  isLoading = false,
  isLoadingMore = false,
  hasMore = false,
}: {
  options?: typeof OPTIONS;
  isLoading?: boolean;
  isLoadingMore?: boolean;
  hasMore?: boolean;
}) {
  const [value, setValue] = useState<string[]>([]);
  return (
    <PaginatedMultiSelectDropdown
      label="Layout"
      value={value}
      options={options}
      onChange={setValue}
      placeholder="All Layouts"
      showTags
      isLoading={isLoading}
      isLoadingMore={isLoadingMore}
      hasMore={hasMore}
      onSearch={onSearch}
      onLoadMore={onLoadMore}
    />
  );
}

const combobox = () => screen.getByRole('combobox', { name: 'Layout' });

describe('PaginatedMultiSelectDropdown', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    stubIntersectionObserver();
  });

  test('opening searches from empty, typing searches, and closing clears the search', async () => {
    const user = userEvent.setup({ delay: null });
    render(<Harness />);

    await user.click(combobox());
    expect(onSearch).toHaveBeenLastCalledWith('');
    await user.type(screen.getByRole('textbox', { name: 'Search…' }), 'lob');
    expect(onSearch).toHaveBeenLastCalledWith('lob');

    await user.keyboard('{Escape}');
    await user.click(combobox());
    expect(screen.getByRole('textbox', { name: 'Search…' })).toHaveValue('');
  });

  test('scrolling to the end loads more while more exist', async () => {
    const user = userEvent.setup({ delay: null });
    render(<Harness hasMore />);

    await user.click(combobox());
    triggerIntersection();

    expect(onLoadMore).toHaveBeenCalled();
  });

  test('no observer is set up when there is nothing more to load', async () => {
    const user = userEvent.setup({ delay: null });
    render(<Harness hasMore={false} />);

    await user.click(combobox());

    expect(intersectionCallbacks).toHaveLength(0);
  });

  test('"No results" when empty; "Loading..." while the first page loads', async () => {
    const user = userEvent.setup({ delay: null });
    const { rerender } = render(<Harness options={[]} />);

    await user.click(combobox());
    expect(screen.getByText('No results')).toBeInTheDocument();

    rerender(<Harness options={[]} isLoading />);
    expect(screen.getByText('Loading...')).toBeInTheDocument();
    expect(screen.queryByText('No results')).not.toBeInTheDocument();
  });

  // Chip remove buttons have no accessible name (Known issue 19).
  test("ticking options adds chips; a chip's remove button unticks it", async () => {
    const user = userEvent.setup({ delay: null });
    render(<Harness />);

    await user.click(combobox());
    await user.click(screen.getByRole('checkbox', { name: 'Lobby Layout' }));
    await user.click(screen.getByRole('checkbox', { name: 'Layout 2' }));
    await user.keyboard('{Escape}');

    expect(combobox()).toHaveTextContent('Lobby Layout');
    expect(combobox()).toHaveTextContent('Layout 2');

    await user.click(within(combobox()).getAllByRole('button')[0]!);

    expect(combobox()).not.toHaveTextContent('Lobby Layout');
    expect(combobox()).toHaveTextContent('Layout 2');
  });
});
