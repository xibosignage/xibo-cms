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

import { render, screen, fireEvent } from '@testing-library/react';
import { describe, test, expect, vi } from 'vitest';

import FilterInputs from '../FilterInputs';
import type { FilterConfigItem } from '../FilterInputs';

vi.mock('react-i18next', () => ({
  useTranslation: () => ({ t: (key: string) => key, i18n: { changeLanguage: vi.fn() } }),
}));

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
        showAllOption: false,
      },
    ];
    renderFilters({ options });

    expect(screen.getByRole('button')).toBeInTheDocument();
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
    const onChange = vi.fn();
    const options: FilterConfigItem<Filters>[] = [
      { label: 'Name', name: 'name', type: 'text', placeholder: 'Search' },
    ];
    renderFilters({ options, onChange });

    fireEvent.change(screen.getByPlaceholderText('Search'), { target: { value: 'xibo' } });

    expect(onChange).toHaveBeenCalledWith('name', 'xibo');
  });
});
