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
// TimePickerInput — a read-only field that opens an hour/minute picker and
// reports the chosen time as "HH:mm:ss" (or "HH:mm"). No module mocks.
//
// Accessibility fallbacks (xibosignage/xibo#4070):
// - the label is not tied to the field, so the field is the only textbox
// - the clear button has no name; with the picker closed it is the last button
// - the hour and minute columns are unnamed and share "00"–"23"; the hour
//   column comes first, so for a shared number index 0 is the hour and
//   index 1 the minute
//
// JSDOM does not implement scrollIntoView, which the picker calls on open.
// =============================================================================

import { render, screen, waitFor } from '@testing-library/react';
import userEvent, { type UserEvent } from '@testing-library/user-event';
import { type ComponentProps } from 'react';
import { vi, beforeAll, beforeEach, describe, test, expect } from 'vitest';

import TimePickerInput from '@/components/ui/forms/TimePickerInput';

beforeAll(() => {
  Element.prototype.scrollIntoView = vi.fn();
});

beforeEach(() => {
  vi.clearAllMocks();
});

const renderTimePicker = (props: Partial<ComponentProps<typeof TimePickerInput>> = {}) => {
  const onChange = vi.fn();
  const result = render(<TimePickerInput label="Start time" onChange={onChange} {...props} />);
  return { ...result, onChange };
};

const timeField = () => screen.getByRole('textbox');

const openPicker = async (user: UserEvent) => {
  await user.click(timeField());
  return screen.findByRole('button', { name: 'Apply' });
};

const hourButton = (hh: string) => screen.getAllByRole('button', { name: hh })[0]!;
const minuteButton = (mm: string) => {
  const matches = screen.getAllByRole('button', { name: mm });
  return matches[matches.length - 1]!;
};

describe('TimePickerInput', () => {
  describe('field', () => {
    test('shows the label and the Optional marker', () => {
      renderTimePicker({ optional: true });
      expect(screen.getByText('Start time')).toBeInTheDocument();
      expect(screen.getByText('Optional')).toBeInTheDocument();
    });

    test('an empty field shows "Select time", cannot be typed into and has no clear button', () => {
      renderTimePicker();
      expect(timeField()).toHaveValue('');
      expect(timeField()).toHaveAttribute('placeholder', 'Select time');
      expect(timeField()).toHaveAttribute('readonly');
      // Only the clock button — no clear button.
      expect(screen.getAllByRole('button')).toHaveLength(1);
    });

    test('the clear button empties the time without opening the picker', async () => {
      const user = userEvent.setup();
      const { onChange } = renderTimePicker({ value: '09:30:00' });
      expect(timeField()).toHaveValue('09:30:00');

      const buttons = screen.getAllByRole('button');
      await user.click(buttons[buttons.length - 1]!);

      expect(onChange).toHaveBeenCalledTimes(1);
      expect(onChange).toHaveBeenCalledWith('');
      expect(screen.queryByRole('button', { name: 'Apply' })).not.toBeInTheDocument();
    });

    test('an error replaces the help text', () => {
      const onChange = vi.fn();
      const { rerender } = render(
        <TimePickerInput label="Start time" onChange={onChange} helpText="24-hour clock" />,
      );
      expect(screen.getByText('24-hour clock')).toBeInTheDocument();

      rerender(
        <TimePickerInput
          label="Start time"
          onChange={onChange}
          helpText="24-hour clock"
          error="Start time is required"
        />,
      );
      expect(screen.getByText('Start time is required')).toBeInTheDocument();
      expect(screen.queryByText('24-hour clock')).not.toBeInTheDocument();
    });
  });

  describe('picker', () => {
    test('clicking the field opens a picker with every hour and minute', async () => {
      const user = userEvent.setup();
      renderTimePicker();

      await openPicker(user);

      // 24 hours + 60 minutes; numbers 00–23 appear in both columns.
      expect(screen.getAllByRole('button', { name: /^\d{2}$/ })).toHaveLength(84);
      expect(screen.getAllByRole('button', { name: '23' })).toHaveLength(2);
      expect(screen.getAllByRole('button', { name: '59' })).toHaveLength(1);
      expect(screen.getByRole('button', { name: 'Cancel' })).toBeInTheDocument();
    });

    test('opening with a time set starts from that time', async () => {
      const user = userEvent.setup();
      const { onChange } = renderTimePicker({ value: '09:30:00' });

      await user.click(await openPicker(user));

      expect(onChange).toHaveBeenCalledWith('09:30:00');
    });

    test('choosing a time and pressing Apply sets it with seconds and closes the picker', async () => {
      const user = userEvent.setup();
      const { onChange } = renderTimePicker();

      const apply = await openPicker(user);
      await user.click(hourButton('14'));
      await user.click(minuteButton('05'));
      await user.click(apply);

      expect(onChange).toHaveBeenCalledTimes(1);
      expect(onChange).toHaveBeenCalledWith('14:05:00');
      await waitFor(() =>
        expect(screen.queryByRole('button', { name: 'Apply' })).not.toBeInTheDocument(),
      );
    });

    test('the hours-and-minutes format leaves out seconds', async () => {
      const user = userEvent.setup();
      const { onChange } = renderTimePicker({ timeFormat: 'HH:mm' });

      const apply = await openPicker(user);
      await user.click(hourButton('08'));
      await user.click(minuteButton('45'));
      await user.click(apply);

      expect(onChange).toHaveBeenCalledWith('08:45');
    });

    test('Apply on an empty field sets midnight', async () => {
      const user = userEvent.setup();
      const { onChange } = renderTimePicker();

      await user.click(await openPicker(user));

      expect(onChange).toHaveBeenCalledWith('00:00:00');
    });

    test('Cancel closes the picker without changing the time', async () => {
      const user = userEvent.setup();
      const { onChange } = renderTimePicker({ value: '09:30:00' });

      await openPicker(user);
      await user.click(hourButton('14'));
      await user.click(screen.getByRole('button', { name: 'Cancel' }));

      await waitFor(() =>
        expect(screen.queryByRole('button', { name: 'Apply' })).not.toBeInTheDocument(),
      );
      expect(onChange).not.toHaveBeenCalled();

      // Reopening starts from the saved time again, not the abandoned choice.
      await user.click(await openPicker(user));
      expect(onChange).toHaveBeenCalledWith('09:30:00');
    });

    test('Escape closes the picker', async () => {
      const user = userEvent.setup();
      const { onChange } = renderTimePicker();

      await openPicker(user);
      await user.keyboard('{Escape}');

      await waitFor(() =>
        expect(screen.queryByRole('button', { name: 'Apply' })).not.toBeInTheDocument(),
      );
      expect(onChange).not.toHaveBeenCalled();
    });
  });
});
