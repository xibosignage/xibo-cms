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
import { describe, test, expect, vi, beforeEach } from 'vitest';

import SelectFilter from '../SelectFilter';
import type { FilterOption } from '../SelectFilter';

// Replace date picker calendar.
// Simulate a user completing a date selection.
let capturedOnApply: ((v: { type: string; from?: Date; to?: Date }) => void) | undefined;

vi.mock('../DatePicker', () => ({
  default: ({ onApply }: { onApply: (v: { type: string; from?: Date; to?: Date }) => void }) => {
    capturedOnApply = onApply;
    return <div data-testid="date-picker" />;
  },
}));

vi.mock('react-i18next', () => ({
  useTranslation: () => ({ t: (key: string) => key, i18n: { changeLanguage: vi.fn() } }),
}));

// Two sample options reused across tests.
const OPTIONS: FilterOption[] = [
  { label: 'Active', value: 'active' },
  { label: 'Inactive', value: 'inactive' },
];

// The arrow icon on the button rotates when the dropdown is open.
const isOpen = () =>
  (document.querySelector('button > div') as HTMLElement).classList.contains('rotate-180');

type RenderProps = Partial<React.ComponentProps<typeof SelectFilter>>;

// Renders a SelectFilter with defaults that a test can override.
const renderFilter = (props: RenderProps = {}) =>
  render(
    <SelectFilter
      label="Status"
      name="status"
      value=""
      options={OPTIONS}
      onChange={vi.fn()}
      {...props}
    />,
  );

describe('SelectFilter', () => {
  beforeEach(() => {
    capturedOnApply = undefined;
  });

  describe('button label', () => {
    // When no filter has been applied yet, the button reads "All"
    // everything is shown and nothing is being filtered out.
    test('shows "All" when no value is selected', () => {
      renderFilter();

      expect(screen.getByRole('button')).toHaveTextContent('All');
    });

    // Once a user picks a specific option, the button updates to reflect that choice.
    test('shows the selected option label', () => {
      renderFilter({ value: 'active' });

      expect(screen.getByRole('button')).toHaveTextContent('Active');
    });

    // When the "All" option is turned off and nothing has been chosen yet, the
    // button falls back to showing the first available option.
    test('shows the first option when All is turned off and nothing is selected', () => {
      renderFilter({ showAllOption: false });

      expect(screen.getByRole('button')).toHaveTextContent('Active');
    });

    // When a custom date range has been picked, the button summarises it as
    // "start date – end date" (e.g. "3/1/2026 - 3/15/2026").
    test('shows a readable date range instead of a raw timestamp', () => {
      const from = new Date('2026-03-01T00:00:00.000Z');
      const to = new Date('2026-03-15T00:00:00.000Z');
      const rangeValue = `range:${from.toISOString()}|${to.toISOString()}`;

      renderFilter({ name: 'lastModified', value: rangeValue });

      expect(screen.getByRole('button').textContent).toContain(' - ');
    });
  });

  describe('dropdown contents', () => {
    test('All appears in the dropdown list so the user can clear the filter', () => {
      renderFilter();

      const allListItem = screen.getAllByText('All').find((el) => el.tagName === 'LI');
      expect(allListItem).toBeInTheDocument();
    });

    test('All is hidden from the dropdown when it is turned off', () => {
      renderFilter({ showAllOption: false });

      expect(screen.queryByText('All')).not.toBeInTheDocument();
    });

    // The date-modified filter has an extra "Custom Range" option so users can
    // pick their own start and end dates. Other filters do not.
    test('Custom Range appears in the date filter so the user can pick their own dates', () => {
      renderFilter({ label: 'Modified', name: 'lastModified' });

      expect(screen.getByText('Custom Range')).toBeInTheDocument();
    });

    // "Custom Range" is specific to the date filter — it must not show up on
    // filters like Status or Media Type where dates make no sense.
    test('Custom Range does not appear on filters that are not date filters', () => {
      renderFilter();

      expect(screen.queryByText('Custom Range')).not.toBeInTheDocument();
    });

    // When custom date ranges are switched on, a calendar / date picker appears
    // inside the dropdown so the user can pick exact dates.
    test('calendar appears inside the dropdown when custom date ranges are enabled', () => {
      renderFilter({ label: 'Modified', name: 'lastModified', allowCustomRange: true });

      expect(screen.getByTestId('date-picker')).toBeInTheDocument();
    });

    // By default the calendar does not appear.
    test('calendar is hidden by default when custom date ranges are not enabled', () => {
      renderFilter({ label: 'Modified', name: 'lastModified' });

      expect(screen.queryByTestId('date-picker')).not.toBeInTheDocument();
    });
  });

  describe('interactions', () => {
    // Clicking an option tells the rest of the app which field was filtered
    // and what value was chosen (e.g. field "status", value "active").
    test('picking an option tells the page which filter changed and what was chosen', () => {
      const onChange = vi.fn();
      renderFilter({ onChange });

      fireEvent.click(screen.getByRole('button'));
      fireEvent.click(screen.getByText('Active'));

      expect(onChange).toHaveBeenCalledWith('status', 'active');
    });

    // Clicking "All" tells the rest of the app to clear the filter — an empty
    // value means "show everything, no filter applied".
    test('picking All clears the filter and shows everything again', () => {
      const onChange = vi.fn();
      renderFilter({ value: 'active', onChange });

      fireEvent.click(screen.getByRole('button'));
      const allItem = screen.getAllByText('All').find((el) => el.tagName === 'LI');
      fireEvent.click(allItem!);

      expect(onChange).toHaveBeenCalledWith('status', '');
    });

    // After a user picks a start and end date from the calendar, the rest of the
    // app is told the chosen range in a standard format starting with "range:".
    test('confirming a date range in the calendar sends the correct range to the page', () => {
      const onChange = vi.fn();
      renderFilter({ label: 'Modified', name: 'lastModified', onChange, allowCustomRange: true });

      capturedOnApply!({
        type: 'range',
        from: new Date('2026-03-01T10:00:00'),
        to: new Date('2026-03-15T10:00:00'),
      });

      expect(onChange).toHaveBeenCalledWith('lastModified', expect.stringMatching(/^range:/));
    });

    test('pressing Escape on the keyboard closes the dropdown', () => {
      renderFilter();

      fireEvent.click(screen.getByRole('button'));
      expect(isOpen()).toBe(true);

      fireEvent.keyDown(document, { key: 'Escape' });

      expect(isOpen()).toBe(false);
    });

    test('clicking anywhere else on the page closes the dropdown', () => {
      render(
        <div>
          <SelectFilter
            label="Status"
            name="status"
            value=""
            options={OPTIONS}
            onChange={vi.fn()}
          />
          <button data-testid="outside">Outside</button>
        </div>,
      );

      fireEvent.click(screen.getByRole('button', { name: /All/i }));
      expect(isOpen()).toBe(true);

      fireEvent.mouseDown(screen.getByTestId('outside'));

      expect(isOpen()).toBe(false);
    });
  });
});
