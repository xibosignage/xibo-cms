/* Copyright (C) 2026 Xibo Signage Ltd — AGPL-3.0 */

import { fireEvent, screen, within } from '@testing-library/react';
import { vi, beforeEach, describe, test, expect } from 'vitest';

import { buildRecurringEvent, CALENDAR_DATE } from '../helpers/buildCalendarEvents';
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

// April 1 2026 00:00 UTC (Wednesday) — matches APRIL_2026_START used elsewhere in these fixtures
const APR_1_2026 = 1775001600;

beforeEach(() => {
  testQueryClient.clear();
  vi.clearAllMocks();
});

describe('EventCalendar – deleting a specific occurrence of a recurring event', () => {
  test('right-clicking a LATER day\'s occurrence and deleting it hands back that day\'s own fromDt/toDt, not the first occurrence\'s', () => {
    const onDeleteEvent = vi.fn();
    // Daily 8am-12pm event, first occurrence April 1
    const fromDt = APR_1_2026 + 8 * 3600; // Apr 1 08:00 UTC
    const toDt = APR_1_2026 + 12 * 3600; // Apr 1 12:00 UTC

    const event = buildRecurringEvent('Day', 1, {
      name: 'Daily 8am Show',
      fromDt,
      toDt,
    });

    renderCalendar({ date: CALENDAR_DATE, events: [event], onDeleteEvent });

    // Pick a day well after the anchor occurrence — April 8 (one week later)
    const targetCell = screen.getByRole('gridcell', { name: /^8 April 2026/ });
    const badge = within(targetCell).getByRole('button', { name: 'Daily 8am Show' });

    fireEvent.contextMenu(badge);
    const deleteButton = screen.getByRole('menuitem', { name: /delete/i });
    fireEvent.click(deleteButton);

    expect(onDeleteEvent).toHaveBeenCalledTimes(1);
    const deletedEvent = onDeleteEvent.mock.calls[0][0];

    // April 8 08:00-12:00 UTC, NOT April 1's original fromDt/toDt
    const expectedFromDt = APR_1_2026 + 7 * 86400 + 8 * 3600;
    const expectedToDt = APR_1_2026 + 7 * 86400 + 12 * 3600;

    expect(Number(deletedEvent.fromDt)).toBe(expectedFromDt);
    expect(Number(deletedEvent.toDt)).toBe(expectedToDt);
    expect(Number(deletedEvent.fromDt)).not.toBe(fromDt);
  });
});
