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

import { render, screen, fireEvent } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterEach, describe, test, expect, vi } from 'vitest';

import FilterInputs from '../FilterInputs';
import type { FilterConfigItem } from '../FilterInputs';

type Filters = { name: string; status: string };

const defaultValues: Filters = { name: '', status: '' };

type RenderProps = Partial<React.ComponentProps<typeof FilterInputs<Filters>>>;

// Renders a FilterInputs bar with defaults that a test can override.
const renderFilters = (props: RenderProps = {}) =>
  render(
    <FilterInputs
      isOpen={true}
      options={[]}
      values={defaultValues}
      onChange={vi.fn()}
      {...props}
    />,
  );

describe('FilterInputs', () => {
  // Restore real timers even when a fake-timer test fails mid-way
  afterEach(() => {
    vi.useRealTimers();
  });

  // When the user hasn't opened the filter bar yet, it is hidden from view.
  test('collapsed filter bar is invisible to screen readers', () => {
    const { container } = renderFilters({ isOpen: false });

    expect(container.firstChild).toHaveAttribute('aria-hidden', 'true');
  });

  test('Reset button is hidden when there is nothing to reset', () => {
    renderFilters();

    expect(screen.queryByRole('button', { name: 'Reset' })).not.toBeInTheDocument();
  });

  test('Reset button appears when a reset action is available', () => {
    renderFilters({ onReset: vi.fn() });

    expect(screen.getByRole('button', { name: 'Reset' })).toBeInTheDocument();
  });

  // A filter for something like "Name" lets the user type anything they want
  test('text filter shows a box where the user can type anything', () => {
    const options: FilterConfigItem<Filters>[] = [
      { label: 'Name', name: 'name', type: 'text', placeholder: 'Search' },
    ];
    renderFilters({ options });

    expect(screen.getByPlaceholderText('Search')).toHaveAttribute('type', 'text');
  });

  // A filter for something like "File Size" should only accept numbers
  test('number filter shows a box that only accepts numbers', () => {
    const options: FilterConfigItem<Filters>[] = [{ label: 'Count', name: 'name', type: 'number' }];
    renderFilters({ options });

    expect(screen.getByRole('spinbutton')).toHaveAttribute('type', 'number');
  });

  // A filter for something like "Status" has a fixed set of choices
  test('choice filter shows a dropdown to pick from a fixed list', () => {
    const options: FilterConfigItem<Filters>[] = [
      {
        label: 'Status',
        name: 'status',
        options: [{ label: 'Active', value: 'active' }],
      },
    ];
    renderFilters({ options });

    expect(screen.getByRole('combobox')).toBeInTheDocument();
  });

  // If the user already typed "hello" in the Name filter and then opens the
  // filter bar again, the word "hello" should still be visible in the box
  test('filter box shows the value the user already typed', () => {
    const options: FilterConfigItem<Filters>[] = [
      { label: 'Name', name: 'name', type: 'text', placeholder: 'Search' },
    ];
    renderFilters({ options, values: { ...defaultValues, name: 'hello' } });

    expect(screen.getByPlaceholderText('Search')).toHaveValue('hello');
  });

  // When the user types in a filter box, the page needs to know two things:
  // which filter changed (e.g. "name") and what the new value is (e.g. "xibo")
  test('typing in a filter tells the page which filter changed and what was typed', () => {
    vi.useFakeTimers();
    const onChange = vi.fn();
    const options: FilterConfigItem<Filters>[] = [
      { label: 'Name', name: 'name', type: 'text', placeholder: 'Search' },
    ];
    renderFilters({ options, onChange });

    fireEvent.change(screen.getByPlaceholderText('Search'), { target: { value: 'xibo' } });
    vi.runAllTimers();

    expect(onChange).toHaveBeenCalledWith('name', 'xibo');
  });

  // Fixed in xibosignage/xibo#4107: the grid stayed filtered by the last valid pattern.
  test('turning regex off sends text that was held back as an invalid pattern', async () => {
    const user = userEvent.setup({ delay: null });
    const onChange = vi.fn();
    type RegexFilters = { name: string; useRegexForName: boolean };
    render(
      <FilterInputs<RegexFilters>
        isOpen={true}
        options={[
          {
            label: 'Name',
            name: 'name',
            type: 'text',
            showRegex: true,
            regexKey: 'useRegexForName',
          },
        ]}
        values={{ name: '', useRegexForName: true }}
        onChange={onChange}
      />,
    );
    await user.type(screen.getByRole('textbox', { name: 'Name' }), 'a(');
    expect(onChange).not.toHaveBeenCalledWith('name', 'a(');

    await user.click(screen.getByRole('button', { name: /use regex pattern matching/i }));

    expect(onChange).toHaveBeenCalledWith('name', 'a(');
  });
});
