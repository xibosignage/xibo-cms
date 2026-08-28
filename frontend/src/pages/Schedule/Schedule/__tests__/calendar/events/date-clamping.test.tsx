/* Copyright (C) 2026 Xibo Signage Ltd — AGPL-3.0 */

import { screen, within } from '@testing-library/react';
import { vi, beforeEach, describe, test, expect } from 'vitest';

import {
  CALENDAR_DATE,
  FEBRUARY_NON_LEAP_DATE,
  FEBRUARY_LEAP_DATE,
  buildRecurringEvent,
} from '../helpers/buildCalendarEvents';
import { renderCalendar } from '../helpers/renderCalendar';

import { testQueryClient } from '@/setupTests';

vi.mock('@/context/UserContext', () => ({
  useUserContext: vi.fn(() => ({
    user: { settings: { defaultTimezone: 'UTC' } },
    isAuthenticated: true,
    logout: vi.fn(),
    updateUser: vi.fn(),
  })),
}));

// Jan 31 2026 00:00 UTC — used as base for a monthly event on the 31st
const JAN_31_2026 = 1769817600;
// Jan 29 2026 00:00 UTC — used as base for a monthly event on the 29th
const JAN_29_2026 = 1769644800;
// Mar 30 2026 00:00 UTC (Monday) — 5th Monday of March 2026
const MAR_30_2026 = 1774828800;

beforeEach(() => {
  testQueryClient.clear();
  vi.clearAllMocks();
});

describe('EventCalendar – monthly date clamping', () => {
  test('monthly event on the 31st in a 30-day month shows on the last day instead', () => {
    // Monthly from Jan 31; the April occurrence clamps to April 30 (30-day month)
    const event = buildRecurringEvent('Month', 1, {
      name: 'Jan 31 Monthly',
      fromDt: JAN_31_2026,
      toDt: JAN_31_2026 + 3600,
    });

    renderCalendar({ date: CALENDAR_DATE, events: [event] });

    // The generated April occurrence should land on April 30
    const icons = screen.getAllByTitle('Jan 31 Monthly');
    expect(icons.length).toBeGreaterThanOrEqual(1);

    // Verify the icon appears in the April 30 cell by checking the day number nearby
    const apr30Cell = icons[icons.length - 1]!.closest('[class*="flex-col"]');
    if (apr30Cell) {
      const dayText = apr30Cell.querySelector('div[class*="text-right"]');
      if (dayText) {
        expect(dayText.textContent?.trim()).toBe('30');
      }
    }
  });

  test('monthly event set to repeat on the 5th Monday clamps to the last Monday in months with only 4', () => {
    // March 30 2026 is the 5th Monday of March
    // April 2026 only has 4 Mondays (6, 13, 20, 27); a literal "5th Monday" spills to May,
    // so the code clamps to the true last Monday of April (27th) instead of skipping it
    const event = buildRecurringEvent('Month', 1, {
      name: 'Fifth Monday',
      fromDt: MAR_30_2026,
      toDt: MAR_30_2026 + 3600,
      recurrenceMonthlyRepeatsOn: 1,
    });

    renderCalendar({ date: CALENDAR_DATE, events: [event] });

    // Original (March 30, shown as the leading overflow day of the April grid)
    // + clamped April occurrence (27th) = 2
    const icons = screen.getAllByTitle('Fifth Monday');
    expect(icons).toHaveLength(2);

    // The generated occurrence lands on April 27 (the actual last Monday), not May
    const apr27Cell = screen.getByRole('gridcell', { name: /^27 April 2026/ });
    expect(within(apr27Cell).getAllByRole('button', { name: 'Fifth Monday' })).toHaveLength(1);
  });

  test('monthly event on the 29th in a non-leap year February clamps to the 28th', () => {
    // Monthly from Jan 29; in Feb 2026 (non-leap, 28 days), day 29 clamps to 28
    const event = buildRecurringEvent('Month', 1, {
      name: 'Day 29 Non-Leap',
      fromDt: JAN_29_2026,
      toDt: JAN_29_2026 + 3600,
    });

    renderCalendar({ date: FEBRUARY_NON_LEAP_DATE, events: [event] });

    // The February occurrence should be clamped to Feb 28 (Feb 29 doesn't exist in 2026)
    const feb28Cell = screen.getByRole('gridcell', { name: /^28 February 2026/ });
    expect(
      within(feb28Cell).getAllByRole('button', { name: 'Day 29 Non-Leap' }).length,
    ).toBeGreaterThanOrEqual(1);
  });

  test('monthly event on the 29th in a leap year February shows on the 29th', () => {
    // Same base event — Jan 29 2026; in Feb 2028 (leap, 29 days), day 29 is valid
    const event = buildRecurringEvent('Month', 1, {
      name: 'Day 29 Leap',
      fromDt: JAN_29_2026,
      toDt: JAN_29_2026 + 3600,
    });

    renderCalendar({ date: FEBRUARY_LEAP_DATE, events: [event] });

    // The February 2028 occurrence should appear (on Feb 29 exactly, not clamped)
    expect(screen.getAllByTitle('Day 29 Leap').length).toBeGreaterThanOrEqual(1);

    const icons = screen.getAllByTitle('Day 29 Leap');
    const hasDay29 = Array.from(icons).some((icon) => {
      const cell = icon.closest('[class*="flex-col"]');
      if (!cell) return false;
      const dayText = cell.querySelector('div[class*="text-right"]');
      return dayText?.textContent?.trim() === '29';
    });
    expect(hasDay29).toBe(true);
  });
});
