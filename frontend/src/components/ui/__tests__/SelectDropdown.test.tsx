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
// SelectDropdown with a local option list — the closed field, choosing an
// option, disabled options, dividers, clearing and local search. No module
// mocks: about 28 other test files replace this component with a fake, so this
// file and its siblings are the only ones that run the real thing.
//
// Accessibility fallback (xibosignage/xibo#4069): the search box has no
// accessible name, so it is found by its placeholder.
// =============================================================================

import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { vi, describe, test, expect } from 'vitest';

import SelectDropdown, { type SelectOption } from '@/components/ui/forms/SelectDropdown';

const OPTIONS: SelectOption[] = [
  { value: '1', label: 'Morning' },
  { value: '2', label: 'Evening' },
  { value: '3', label: 'Night', disabled: true },
];

const field = () => screen.getByRole('combobox', { name: /^Daypart/ });

// aria-required, aria-disabled and aria-selected are asserted on purpose: they are how a
// screen reader learns these states, and JSDOM shows no other sign of them.

describe('SelectDropdown - the closed field', () => {
  test('shows the placeholder when nothing is selected, or the caller’s own', () => {
    const { rerender } = render(
      <SelectDropdown label="Daypart" options={OPTIONS} onSelect={vi.fn()} />,
    );
    expect(field()).toHaveTextContent('Select');

    rerender(
      <SelectDropdown
        label="Daypart"
        options={OPTIONS}
        onSelect={vi.fn()}
        placeholder="Pick one"
      />,
    );
    expect(field()).toHaveTextContent('Pick one');
  });

  test('shows the name of the selected option, not its value', () => {
    render(<SelectDropdown label="Daypart" value="2" options={OPTIONS} onSelect={vi.fn()} />);

    expect(field()).toHaveTextContent('Evening');
    expect(field()).not.toHaveTextContent('2');
  });

  test('a required field is marked required, and an optional one says so', () => {
    const { rerender } = render(
      <SelectDropdown label="Daypart" options={OPTIONS} onSelect={vi.fn()} required />,
    );
    expect(field()).toHaveAttribute('aria-required', 'true');
    expect(screen.queryByText('Optional')).not.toBeInTheDocument();

    rerender(<SelectDropdown label="Daypart" options={OPTIONS} onSelect={vi.fn()} optional />);
    expect(field()).not.toHaveAttribute('aria-required');
    expect(screen.getByText('Optional')).toBeInTheDocument();
  });

  test('an error replaces the help text', () => {
    const { rerender } = render(
      <SelectDropdown
        label="Daypart"
        options={OPTIONS}
        onSelect={vi.fn()}
        helpText="Pick when it plays"
      />,
    );
    expect(screen.getByText('Pick when it plays')).toBeInTheDocument();

    rerender(
      <SelectDropdown
        label="Daypart"
        options={OPTIONS}
        onSelect={vi.fn()}
        helpText="Pick when it plays"
        error="A daypart is required"
      />,
    );
    expect(screen.getByText('A daypart is required')).toBeInTheDocument();
    expect(screen.queryByText('Pick when it plays')).not.toBeInTheDocument();
  });

  test('shows "Loading..." instead of the selected name while the caller is loading', () => {
    render(
      <SelectDropdown label="Daypart" value="2" options={OPTIONS} onSelect={vi.fn()} isLoading />,
    );

    expect(field()).toHaveTextContent('Loading...');
    expect(field()).not.toHaveTextContent('Evening');
  });

  test('a disabled field does not open', async () => {
    const user = userEvent.setup();
    render(<SelectDropdown label="Daypart" options={OPTIONS} onSelect={vi.fn()} disabled />);

    await user.click(field());

    expect(field()).toHaveAttribute('aria-disabled', 'true');
    expect(screen.queryByRole('listbox')).not.toBeInTheDocument();
  });
});

describe('SelectDropdown - the open list', () => {
  test('clicking the field opens the list, with the selected option marked', async () => {
    const user = userEvent.setup();
    render(<SelectDropdown label="Daypart" value="2" options={OPTIONS} onSelect={vi.fn()} />);

    await user.click(field());

    expect(screen.getByRole('option', { name: 'Evening' })).toHaveAttribute(
      'aria-selected',
      'true',
    );
    expect(screen.getByRole('option', { name: 'Morning' })).toHaveAttribute(
      'aria-selected',
      'false',
    );
  });

  test('choosing an option reports its value and closes the list', async () => {
    const user = userEvent.setup();
    const onSelect = vi.fn();
    render(<SelectDropdown label="Daypart" options={OPTIONS} onSelect={onSelect} />);

    await user.click(field());
    await user.click(screen.getByRole('option', { name: 'Morning' }));

    expect(onSelect).toHaveBeenCalledTimes(1);
    expect(onSelect).toHaveBeenCalledWith('1');
    expect(screen.queryByRole('listbox')).not.toBeInTheDocument();
  });

  // Asserts the behaviour, not how it is achieved. Today the option is a disabled <button>, so the
  // browser swallows the click and the component's own `if (option.disabled)` guard is never
  // reached. If the option ever becomes an element the browser doesn't disable, this test reaches
  // that guard and fails without it.
  test('a disabled option cannot be chosen, and the list stays open', async () => {
    const user = userEvent.setup();
    const onSelect = vi.fn();
    render(<SelectDropdown label="Daypart" options={OPTIONS} onSelect={onSelect} />);

    await user.click(field());
    await user.click(screen.getByRole('option', { name: 'Night' }));

    expect(onSelect).not.toHaveBeenCalled();
    expect(screen.getByRole('listbox')).toBeInTheDocument();
  });

  test('a divider is a separator, not an option', async () => {
    const user = userEvent.setup();
    render(
      <SelectDropdown
        label="Daypart"
        options={[...OPTIONS, { value: 'divider', label: '', isDivider: true }]}
        onSelect={vi.fn()}
      />,
    );

    await user.click(field());

    expect(screen.getByRole('separator')).toBeInTheDocument();
    expect(screen.getAllByRole('option')).toHaveLength(3);
  });

  test('a clearable list offers the placeholder, which clears the value', async () => {
    const user = userEvent.setup();
    const onSelect = vi.fn();
    render(
      <SelectDropdown label="Daypart" value="1" options={OPTIONS} onSelect={onSelect} clearable />,
    );

    await user.click(field());
    await user.click(screen.getByRole('button', { name: 'Select' }));

    expect(onSelect).toHaveBeenCalledWith('');
    expect(screen.queryByRole('listbox')).not.toBeInTheDocument();
  });

  test('says "No results" when there are no options', async () => {
    const user = userEvent.setup();
    render(<SelectDropdown label="Daypart" options={[]} onSelect={vi.fn()} />);

    await user.click(field());

    expect(screen.getByText('No results')).toBeInTheDocument();
    expect(screen.queryByRole('option')).not.toBeInTheDocument();
  });

  test('search narrows the options ignoring case, and closing the list clears it', async () => {
    const user = userEvent.setup();
    render(<SelectDropdown label="Daypart" options={OPTIONS} onSelect={vi.fn()} searchable />);

    await user.click(field());
    await user.type(screen.getByPlaceholderText('Search'), 'EVE');

    expect(screen.getAllByRole('option').map((o) => o.textContent)).toEqual(['Evening']);

    await user.keyboard('{Escape}');
    expect(screen.queryByRole('listbox')).not.toBeInTheDocument();

    await user.click(field());
    expect(screen.getByPlaceholderText('Search')).toHaveValue('');
    expect(screen.getAllByRole('option')).toHaveLength(3);
  });
});
