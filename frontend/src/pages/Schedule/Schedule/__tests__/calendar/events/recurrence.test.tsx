/* Copyright (C) 2026 Xibo Signage Ltd — AGPL-3.0 */

import { screen } from '@testing-library/react';
import { vi, beforeEach, describe, test, expect } from 'vitest';

import {
  CALENDAR_DATE,
  buildRecurringEvent,
  buildAlwaysEvent,
} from '../helpers/buildCalendarEvents';
import { renderCalendar } from '../helpers/renderCalendar';

import { testQueryClient } from '@/setupTests';

const t = (key: string) => key;
vi.mock('react-i18next', () => ({
  useTranslation: () => ({ t, i18n: { changeLanguage: vi.fn() } }),
  Trans: ({ children }: { children: React.ReactNode }) => children,
}));

vi.mock('@/context/UserContext', () => ({
  useUserContext: vi.fn(() => ({
    user: { settings: { defaultTimezone: 'UTC' } },
    isAuthenticated: true,
    logout: vi.fn(),
    updateUser: vi.fn(),
  })),
}));

// April 1 2026 00:00 UTC (Wednesday)
const APR_1_2026 = 1775001600;
// April 3 2026 00:00 UTC (Friday)
const APR_3_2026 = 1775174400;
// March 25 2026 00:00 UTC (Wednesday)
const MAR_25_2026 = 1774396800;

beforeEach(() => {
  testQueryClient.clear();
  vi.clearAllMocks();
});

describe('EventCalendar – recurring event rendering', () => {
  test('weekly recurring event appears on each occurrence day in the month', () => {
    const event = buildRecurringEvent('Week', 1, {
      name: 'Weekly Wednesday',
      fromDt: APR_1_2026,
      toDt: APR_1_2026 + 3600,
    });

    renderCalendar({ date: CALENDAR_DATE, events: [event] });

    // April 2026 Wednesdays: 1, 8, 15, 22, 29 = 5 occurrences
    expect(screen.getAllByTitle('Weekly Wednesday')).toHaveLength(5);
  });

  test('recurring expansion is correct across month boundaries', () => {
    const event = buildRecurringEvent('Week', 1, {
      name: 'Cross-Month Wednesday',
      fromDt: MAR_25_2026,
      toDt: MAR_25_2026 + 3600,
    });

    renderCalendar({ date: CALENDAR_DATE, events: [event] });

    // March 25 is before the April grid start (March 30), so not shown
    // April Wednesdays in view: Apr 1, 8, 15, 22, 29 = 5 occurrences
    expect(screen.getAllByTitle('Cross-Month Wednesday')).toHaveLength(5);
  });

  test('always event appears on every day of the month', () => {
    const event = buildAlwaysEvent({ name: 'Always Showing' });

    renderCalendar({ date: CALENDAR_DATE, events: [event] });

    // April has 30 days; the calendar grid also has overflow days
    // Always events appear on every calendar cell
    expect(screen.getAllByTitle('Always Showing').length).toBeGreaterThanOrEqual(30);
  });

  test('daily recurring event appears every day', () => {
    const event = buildRecurringEvent('Day', 1, {
      name: 'Daily Event',
      fromDt: APR_1_2026,
      toDt: APR_1_2026 + 3600,
    });

    renderCalendar({ date: CALENDAR_DATE, events: [event] });

    // Should appear on many days throughout April
    expect(screen.getAllByTitle('Daily Event').length).toBeGreaterThan(10);
  });

  test('recurring event with no end date shows across the whole visible month', () => {
    const event = buildRecurringEvent('Week', 1, {
      name: 'Endless Weekly',
      fromDt: APR_1_2026,
      toDt: APR_1_2026 + 3600,
      recurrenceRange: null,
    });

    renderCalendar({ date: CALENDAR_DATE, events: [event] });

    // Should appear on all 5 Wednesdays in April
    expect(screen.getAllByTitle('Endless Weekly')).toHaveLength(5);
  });

  test('two different recurring events both appear on their respective days', () => {
    const wednesdayEvent = buildRecurringEvent('Week', 1, {
      name: 'Wednesday Event',
      fromDt: APR_1_2026,
      toDt: APR_1_2026 + 3600,
    });

    const fridayEvent = buildRecurringEvent('Week', 1, {
      name: 'Friday Event',
      fromDt: APR_3_2026,
      toDt: APR_3_2026 + 3600,
    });

    renderCalendar({ date: CALENDAR_DATE, events: [wednesdayEvent, fridayEvent] });

    // April 2026 Wednesdays: 1, 8, 15, 22, 29 = 5
    expect(screen.getAllByTitle('Wednesday Event')).toHaveLength(5);
    // April 2026 Fridays in view: 3, 10, 17, 24 + May 1 overflow = 5
    expect(screen.getAllByTitle('Friday Event')).toHaveLength(5);
  });
});
