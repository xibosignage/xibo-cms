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
import React from 'react';
import { vi, beforeEach, afterEach, describe, test, expect } from 'vitest';

import DatePicker from '../DatePicker';

// -----------------------------------------------------------------------------
// Clock — Set the system clock so the calendar always shows March 2026 and we
// know exactly which days are enabled
// -----------------------------------------------------------------------------
beforeEach(() => {
  vi.useFakeTimers({ shouldAdvanceTime: true });
  vi.setSystemTime(new Date('2026-03-15T12:00:00'));
});

afterEach(() => {
  vi.useRealTimers();
});

// -----------------------------------------------------------------------------
// Helper — find a calendar day button by its visible number
// -----------------------------------------------------------------------------
function findDayButton(day: number) {
  return screen.getAllByRole('button').find((btn) => btn.textContent?.trim() === String(day));
}

// -----------------------------------------------------------------------------
// Render helpers
// -----------------------------------------------------------------------------
const renderSingle = (
  onApply = vi.fn(),
  onCancel = vi.fn(),
  propOverrides: Partial<React.ComponentProps<typeof DatePicker>> = {},
) => render(<DatePicker mode="single" onApply={onApply} onCancel={onCancel} {...propOverrides} />);

const renderRange = (onApply = vi.fn(), onCancel = vi.fn()) =>
  render(<DatePicker mode="range" onApply={onApply} onCancel={onCancel} />);

// -----------------------------------------------------------------------------
// Action helpers
// -----------------------------------------------------------------------------
const clickDay = (day: number) => fireEvent.click(findDayButton(day)!);
const clickApply = () => fireEvent.click(screen.getByRole('button', { name: 'Apply' }));
const clickCancel = () => fireEvent.click(screen.getByRole('button', { name: 'Cancel' }));

// The period select always defaults to 'PM' on fresh render.
// The hour select always defaults to '12' on fresh render.
// The minute select always defaults to '00' on fresh render.
const selectPeriod = (value: 'AM' | 'PM') =>
  fireEvent.change(screen.getByDisplayValue(/^(AM|PM)$/), { target: { value } });
const selectHour = (value: string) =>
  fireEvent.change(screen.getByDisplayValue('12'), { target: { value } });
const selectMinute = (value: string) =>
  fireEvent.change(screen.getByDisplayValue('00'), { target: { value } });
const selectRange = (from: number, to: number) => {
  clickDay(from);
  clickDay(to);
};

// =============================================================================
// Tests
// =============================================================================

describe('DatePicker', () => {
  // ---------------------------------------------------------------------------
  // Apply button is disabled before a day is selected (single mode)
  // ---------------------------------------------------------------------------
  test('Apply button is disabled before a day is selected', () => {
    renderSingle();

    expect(screen.getByRole('button', { name: 'Apply' })).toBeDisabled();
  });

  // ---------------------------------------------------------------------------
  // Apply button is enabled after a day is selected
  // ---------------------------------------------------------------------------
  test('Apply button is enabled after a day is selected', () => {
    renderSingle();

    // Day 20 is in the future (today = 15th) so it is not disabled.
    clickDay(20);

    expect(screen.getByRole('button', { name: 'Apply' })).not.toBeDisabled();
  });

  // ---------------------------------------------------------------------------
  // Clicking Apply calls onApply with type 'single' and the selected date
  // ---------------------------------------------------------------------------
  test('clicking Apply calls onApply with { type: "single", date }', () => {
    const onApply = vi.fn();
    renderSingle(onApply);

    clickDay(20);
    clickApply();

    expect(onApply).toHaveBeenCalledTimes(1);
    const [result] = onApply.mock.calls[0]!;
    expect(result.type).toBe('single');
    expect(result.date).toBeInstanceOf(Date);
    expect(result.date.getDate()).toBe(20);
  });

  // ---------------------------------------------------------------------------
  // Tests every edge case of to24Hour(hour, period):
  //   12 AM = 0   (midnight — the edge case)
  //   12 PM = 12  (noon — must NOT become 24)
  //   01 PM = 13  (normal PM: add 12)
  //   01 AM = 1   (normal AM: pass through unchanged)
  //   11 AM = 11  (upper AM boundary)
  //   11 PM = 23  (upper PM boundary)
  //
  // hour=null (leave the select at its default value of 12)
  // ---------------------------------------------------------------------------
  test.each([
    { label: '12 AM → 0', hour: null, period: 'AM' as const, expectedHour: 0 },
    { label: '12 PM → 12', hour: null, period: 'PM' as const, expectedHour: 12 },
    { label: '01 PM → 13', hour: '01', period: 'PM' as const, expectedHour: 13 },
    { label: '01 AM → 1', hour: '01', period: 'AM' as const, expectedHour: 1 },
    { label: '11 AM → 11', hour: '11', period: 'AM' as const, expectedHour: 11 },
    { label: '11 PM → 23', hour: '11', period: 'PM' as const, expectedHour: 23 },
  ])('to24Hour: $label', ({ hour, period, expectedHour }) => {
    const onApply = vi.fn();
    renderSingle(onApply);

    clickDay(20);
    if (hour) selectHour(hour);
    selectPeriod(period);
    clickApply();

    const [result] = onApply.mock.calls[0]!;
    expect(result.date.getHours()).toBe(expectedHour);
  });

  // ---------------------------------------------------------------------------
  // Clicking Cancel calls onCancel
  // ---------------------------------------------------------------------------
  test('clicking Cancel calls onCancel', () => {
    const onCancel = vi.fn();
    renderSingle(vi.fn(), onCancel);

    clickCancel();

    expect(onCancel).toHaveBeenCalledTimes(1);
  });

  // ---------------------------------------------------------------------------
  // Time picker is shown in single mode
  //
  // The time picker is three <select> elements: hour (default '12'),
  // minute (default '00'), and period (default 'PM').
  // ---------------------------------------------------------------------------
  test('shows the hour, minute, and AM/PM selects in single mode', () => {
    renderSingle();

    expect(screen.getByDisplayValue('12')).toBeInTheDocument(); // hour
    expect(screen.getByDisplayValue('00')).toBeInTheDocument(); // minute
    expect(screen.getByDisplayValue('PM')).toBeInTheDocument(); // period
  });

  // ---------------------------------------------------------------------------
  // Time picker is hidden in range mode
  // ---------------------------------------------------------------------------
  test('does not show the time picker in range mode', () => {
    renderRange();

    // Neither AM nor PM select should exist.
    expect(screen.queryByDisplayValue('AM')).not.toBeInTheDocument();
    expect(screen.queryByDisplayValue('PM')).not.toBeInTheDocument();
  });

  // ---------------------------------------------------------------------------
  // Apply is disabled in range mode until both dates are selected
  // ---------------------------------------------------------------------------
  test('Apply is disabled in range mode when no days are selected', () => {
    renderRange();

    expect(screen.getByRole('button', { name: 'Apply' })).toBeDisabled();
  });

  // ---------------------------------------------------------------------------
  // Apply is enabled in range mode once both from and to are selected
  //
  // Day 5 and day 10 are both before March 15, so they are not disabled
  // (range mode disables dates after today, not before).
  // ---------------------------------------------------------------------------
  test('Apply is enabled in range mode after two days are selected', () => {
    renderRange();

    selectRange(5, 10);

    expect(screen.getByRole('button', { name: 'Apply' })).not.toBeDisabled();
  });

  // ---------------------------------------------------------------------------
  // Clicking Apply in range mode calls onApply with type 'range'
  // ---------------------------------------------------------------------------
  test('clicking Apply in range mode calls onApply with { type: "range", from, to }', () => {
    const onApply = vi.fn();
    renderRange(onApply);

    selectRange(5, 10);
    clickApply();

    expect(onApply).toHaveBeenCalledTimes(1);
    const [result] = onApply.mock.calls[0]!;
    expect(result.type).toBe('range');
    expect(result.from).toBeInstanceOf(Date);
    expect(result.to).toBeInstanceOf(Date);
  });

  // ---------------------------------------------------------------------------
  // Minute value is passed correctly to onApply
  // ---------------------------------------------------------------------------
  test('changing the minute select to 30 passes minutes: 30 to onApply', () => {
    const onApply = vi.fn();
    renderSingle(onApply);

    clickDay(20);
    selectMinute('30');
    clickApply();

    const [result] = onApply.mock.calls[0]!;
    expect(result.date.getMinutes()).toBe(30);
  });

  // ---------------------------------------------------------------------------
  // onApply always receives a date with seconds set to 0
  // prevention from the system clock bleed through.
  // ---------------------------------------------------------------------------
  test('the date passed to onApply always has seconds set to 0', () => {
    const onApply = vi.fn();
    renderSingle(onApply);

    clickDay(20);
    clickApply();

    const [result] = onApply.mock.calls[0]!;
    expect(result.date.getSeconds()).toBe(0);
  });

  // ---------------------------------------------------------------------------
  // When value.date is provided Apply is enabled immediately
  // ---------------------------------------------------------------------------
  test('Apply is enabled immediately when an initial value.date is provided', () => {
    renderSingle(vi.fn(), vi.fn(), { value: { date: new Date('2026-03-20T12:00:00') } });

    // No day click needed — value.date pre-selects the date.
    expect(screen.getByRole('button', { name: 'Apply' })).not.toBeDisabled();
  });

  // ---------------------------------------------------------------------------
  // Footer shows the selected date as text after a day is clicked
  // After clicking a day the footer should show a non-empty date string.
  // ---------------------------------------------------------------------------
  test('footer shows a date string after a day is selected in single mode', () => {
    renderSingle();

    const selected = screen.getByTestId('datepicker-selected');

    // Before selection the paragraph is empty.
    expect(selected.textContent?.trim()).toBe('');

    clickDay(20);

    // After selection the paragraph should contain a date string.
    expect(selected.textContent?.trim()).not.toBe('');
  });

  // ---------------------------------------------------------------------------
  // Footer shows both dates after a range is selected
  //
  // Range mode renders the from date and, once to is set, " – to date".
  // After clicking two days the footer should contain a "–" separator.
  // ---------------------------------------------------------------------------
  test('footer shows both dates separated by a dash after a range is selected', () => {
    renderRange();

    selectRange(5, 10);

    // The footer paragraph should contain "–" between the two formatted dates.
    expect(screen.getByTestId('datepicker-selected').textContent).toContain('–');
  });

  // ---------------------------------------------------------------------------
  // Range onApply receives the correct from and to day numbers
  //
  // The previous test checks only the type. This checks the actual day values
  // so that a bug swapping from/to would be caught.
  // ---------------------------------------------------------------------------
  test('range onApply receives from=5 and to=10 when those days are clicked', () => {
    const onApply = vi.fn();
    renderRange(onApply);

    selectRange(5, 10);
    clickApply();

    const [result] = onApply.mock.calls[0]!;
    expect(result.from.getDate()).toBe(5);
    expect(result.to.getDate()).toBe(10);
  });
});
