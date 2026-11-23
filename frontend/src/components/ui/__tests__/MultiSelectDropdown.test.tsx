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
// MultiSelectDropdown with a local option list — opening, ticking, local
// search, select-all and tag mode. No module mocks.
//
// Accessibility fallbacks (xibosignage/xibo#4069):
// - the search box has no accessible name; it is the only textbox on screen
//   while the list is open
// - chip remove buttons have no accessible name; each is found inside its chip
//
// The summary label is measured on a canvas. JSDOM has no canvas, so the
// component falls back to joining every label with ", " — stubbed explicitly
// below so the fallback is deliberate, not an accident of the environment.
// =============================================================================

import { render, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { useState, type ComponentProps } from 'react';
import { vi, beforeEach, describe, test, expect } from 'vitest';

import MultiSelectDropdown, {
  type MultiSelectOption,
} from '@/components/ui/forms/MultiSelectDropdown';

const OPTIONS: MultiSelectOption[] = [
  { value: 'a', label: 'Option A' },
  { value: 'b', label: 'Option B' },
  { value: 'c', label: 'Option C' },
];

type HarnessProps = Partial<Omit<ComponentProps<typeof MultiSelectDropdown>, 'value'>> & {
  initial?: string[];
};

// Keeps the selection in state so ticks show up, and reports every change.
function Harness({ initial = [], onChange, ...props }: HarnessProps) {
  const [value, setValue] = useState<string[]>(initial);
  return (
    <MultiSelectDropdown
      label="Displays"
      options={OPTIONS}
      {...props}
      value={value}
      onChange={(next) => {
        setValue(next);
        onChange?.(next);
      }}
    />
  );
}

const renderMultiSelect = (props: HarnessProps = {}) => render(<Harness {...props} />);

const field = () => screen.getByRole('combobox', { name: /Displays/ });
const box = (name: string) => screen.getByRole('checkbox', { name });

beforeEach(() => {
  vi.clearAllMocks();
  vi.spyOn(HTMLCanvasElement.prototype, 'getContext').mockReturnValue(null);
});

describe('MultiSelectDropdown', () => {
  describe('closed field', () => {
    test('shows the label and the Optional marker', () => {
      renderMultiSelect({ optional: true });
      expect(field()).toBeInTheDocument();
      expect(screen.getByText('Optional')).toBeInTheDocument();
    });

    test('shows "None selected", or the caller placeholder, when nothing is selected', () => {
      const { unmount } = renderMultiSelect();
      expect(field()).toHaveTextContent('None selected');
      unmount();

      renderMultiSelect({ placeholder: 'Pick displays' });
      expect(field()).toHaveTextContent('Pick displays');
    });

    test('shows the selected names separated by commas', async () => {
      renderMultiSelect({ initial: ['a', 'c'] });
      await waitFor(() => expect(field()).toHaveTextContent('Option A, Option C'));
    });

    test('an error replaces the help text', () => {
      const { rerender } = render(<Harness helpText="Pick one or more" />);
      expect(screen.getByText('Pick one or more')).toBeInTheDocument();

      rerender(<Harness helpText="Pick one or more" error="Required" />);
      expect(screen.getByText('Required')).toBeInTheDocument();
      expect(screen.queryByText('Pick one or more')).not.toBeInTheDocument();
    });
  });

  describe('selecting', () => {
    test('clicking the field opens the option list', async () => {
      const user = userEvent.setup();
      renderMultiSelect();

      await user.click(field());

      const listbox = await screen.findByRole('listbox');
      expect(within(listbox).getAllByRole('checkbox')).toHaveLength(3);
      expect(box('Option A')).toBeInTheDocument();
      expect(box('Option C')).toBeInTheDocument();
    });

    test('selected options are ticked', async () => {
      const user = userEvent.setup();
      renderMultiSelect({ initial: ['b'] });

      await user.click(field());

      expect(box('Option B')).toBeChecked();
      expect(box('Option A')).not.toBeChecked();
      expect(box('Option C')).not.toBeChecked();
    });

    test('ticking adds an option and unticking removes it', async () => {
      const user = userEvent.setup();
      const onChange = vi.fn();
      renderMultiSelect({ initial: ['b'], onChange });

      await user.click(field());
      await user.click(box('Option A'));
      expect(onChange).toHaveBeenLastCalledWith(['b', 'a']);

      await user.click(box('Option B'));
      expect(onChange).toHaveBeenLastCalledWith(['a']);
    });

    test('search narrows the options ignoring case, and says when nothing matches', async () => {
      const user = userEvent.setup();
      renderMultiSelect();

      await user.click(field());
      await user.type(screen.getByRole('textbox'), 'option b');

      expect(screen.getAllByRole('checkbox')).toHaveLength(1);
      expect(box('Option B')).toBeInTheDocument();

      await user.clear(screen.getByRole('textbox'));
      await user.type(screen.getByRole('textbox'), 'zzz');
      expect(screen.queryAllByRole('checkbox')).toHaveLength(0);
      expect(screen.getByText('No results')).toBeInTheDocument();
    });

    test('"All" shows partial and full state, and selects everything or nothing', async () => {
      const user = userEvent.setup();
      const onChange = vi.fn();
      renderMultiSelect({ initial: ['a'], selectAllOption: true, onChange });

      await user.click(field());
      expect(box('All')).toBePartiallyChecked();

      await user.click(box('All'));
      expect(onChange).toHaveBeenLastCalledWith(['a', 'b', 'c']);
      expect(box('All')).toBeChecked();

      await user.click(box('All'));
      expect(onChange).toHaveBeenLastCalledWith([]);
      expect(box('All')).not.toBeChecked();
    });

    test('"All" selects every option, even ones hidden by the search', async () => {
      // Current behaviour, pending a product decision on whether "All" should
      // only tick the options that match the search.
      const user = userEvent.setup();
      const onChange = vi.fn();
      renderMultiSelect({ selectAllOption: true, onChange });

      await user.click(field());
      await user.type(screen.getByRole('textbox'), 'Option B');
      await user.click(box('All'));

      expect(onChange).toHaveBeenLastCalledWith(['a', 'b', 'c']);
    });

    test('"All" can use custom text', async () => {
      const user = userEvent.setup();
      renderMultiSelect({ selectAllOption: true, selectAllText: 'Every display' });

      await user.click(field());

      expect(box('Every display')).toBeInTheDocument();
    });

    test('closing the list clears the search and tells the caller', async () => {
      const user = userEvent.setup();
      const onDropdownClose = vi.fn();
      renderMultiSelect({ onDropdownClose });

      await user.click(field());
      await user.type(screen.getByRole('textbox'), 'B');
      await user.keyboard('{Escape}');

      await waitFor(() => expect(screen.queryByRole('listbox')).not.toBeInTheDocument());
      expect(onDropdownClose).toHaveBeenCalledTimes(1);

      await user.click(field());
      expect(screen.getByRole('textbox')).toHaveValue('');
      expect(screen.getAllByRole('checkbox')).toHaveLength(3);
    });
  });

  describe('tag mode', () => {
    test('each selected item is a chip whose remove button deselects it without opening the list', async () => {
      const user = userEvent.setup();
      const onChange = vi.fn();
      renderMultiSelect({ showTags: true, initial: ['a', 'b'], onChange });

      const chipA = screen.getByText('Option A');
      expect(screen.getByText('Option B')).toBeInTheDocument();

      await user.click(within(chipA).getByRole('button'));

      expect(onChange).toHaveBeenCalledWith(['b']);
      expect(screen.queryByRole('listbox')).not.toBeInTheDocument();
      expect(screen.queryByText('Option A')).not.toBeInTheDocument();
    });
  });
});
