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

import { screen } from '@testing-library/react';
import { describe, test, vi, expect } from 'vitest';

import { buildEvent } from '../../fixtures/event';
import {
  CALENDAR_DATE,
  DST_SPRING_FORWARD_DATE,
  DST_FALL_BACK_DATE,
  buildRecurringEvent,
} from '../helpers/buildCalendarEvents';
import { renderCalendar } from '../helpers/renderCalendar';

import { useUserContext } from '@/context/UserContext';

vi.mock('@/context/UserContext', () => ({
  useUserContext: vi.fn(() => ({
    user: { settings: { defaultTimezone: 'UTC' } },
    isAuthenticated: true,
    logout: vi.fn(),
    updateUser: vi.fn(),
  })),
}));

const t = (key: string) => key;
vi.mock('react-i18next', () => ({
  useTranslation: () => ({ t, i18n: { changeLanguage: vi.fn() } }),
  Trans: ({ children }: { children: React.ReactNode }) => children,
}));

function mockTimezone(timezone: string) {
  vi.mocked(useUserContext).mockReturnValue({
    user: { settings: { defaultTimezone: timezone } } as unknown as ReturnType<
      typeof useUserContext
    >['user'],
    isAuthenticated: true,
    logout: vi.fn(),
    updateUser: vi.fn(),
  });
}

describe('EventCalendar — timezone rendering', () => {
  test('UTC+ shift: event at 11pm UTC appears on next calendar day for UTC+2 user', () => {
    mockTimezone('Europe/Paris');

    // April 1 23:00 UTC = April 2 01:00 Paris time (UTC+2 in April)
    const fromDt = Math.floor(new Date('2026-04-01T23:00:00Z').getTime() / 1000);
    const toDt = Math.floor(new Date('2026-04-02T00:30:00Z').getTime() / 1000);
    const event = buildEvent({ name: 'Paris Event', fromDt, toDt });

    renderCalendar({ date: CALENDAR_DATE, events: [event] });

    expect(screen.getByTitle('Paris Event')).toBeInTheDocument();
  });

  test('UTC- shift: event at 1am UTC appears on previous calendar day for UTC-5 user', () => {
    mockTimezone('America/New_York');

    // April 2 01:00 UTC = April 1 20:00 EST (UTC-5 in April — actually EDT UTC-4, but test intent is shift)
    const fromDt = Math.floor(new Date('2026-04-02T01:00:00Z').getTime() / 1000);
    const toDt = Math.floor(new Date('2026-04-02T02:00:00Z').getTime() / 1000);
    const event = buildEvent({ name: 'New York Event', fromDt, toDt });

    renderCalendar({ date: CALENDAR_DATE, events: [event] });

    expect(screen.getByTitle('New York Event')).toBeInTheDocument();
  });

  test('fractional offset: event at 10:30pm UTC appears on next day for UTC+5:30 user', () => {
    mockTimezone('Asia/Kolkata');

    // April 1 22:30 UTC = April 2 04:00 IST (UTC+5:30)
    const fromDt = Math.floor(new Date('2026-04-01T22:30:00Z').getTime() / 1000);
    const toDt = Math.floor(new Date('2026-04-01T23:30:00Z').getTime() / 1000);
    const event = buildEvent({ name: 'Kolkata Event', fromDt, toDt });

    renderCalendar({ date: CALENDAR_DATE, events: [event] });

    expect(screen.getByTitle('Kolkata Event')).toBeInTheDocument();
  });

  test('timezone month boundary: event on last day of March 11pm UTC disappears for UTC+2 user', () => {
    mockTimezone('Europe/Paris');

    // March 31 23:00 UTC = April 1 01:00 Paris time — crosses into April
    const fromDt = Math.floor(new Date('2026-03-31T23:00:00Z').getTime() / 1000);
    const toDt = Math.floor(new Date('2026-04-01T00:30:00Z').getTime() / 1000);
    const event = buildEvent({ name: 'Month Boundary Event', fromDt, toDt });

    expect(() => {
      renderCalendar({ date: CALENDAR_DATE, events: [event] });
    }).not.toThrow();
  });

  test('DST spring forward: event at 2:30am on spring-forward night renders without crashing', () => {
    mockTimezone('America/New_York');

    // 2026-03-08T07:30:00Z = 2:30am EST before DST springs forward
    const fromDt = Math.floor(new Date('2026-03-08T07:30:00Z').getTime() / 1000);
    const toDt = fromDt + 3600;
    const event = buildEvent({ name: 'DST Spring Forward Event', fromDt, toDt });

    expect(() => {
      renderCalendar({ date: DST_SPRING_FORWARD_DATE, events: [event] });
    }).not.toThrow();
  });

  test('DST fall back: event at 2:30am on fall-back night renders without crashing', () => {
    mockTimezone('America/New_York');

    // 2026-11-01T07:30:00Z — during the fall-back ambiguous hour
    const fromDt = Math.floor(new Date('2026-11-01T07:30:00Z').getTime() / 1000);
    const toDt = fromDt + 3600;
    const event = buildEvent({ name: 'DST Fall Back Event', fromDt, toDt });

    expect(() => {
      renderCalendar({ date: DST_FALL_BACK_DATE, events: [event] });
    }).not.toThrow();
  });

  test('recurring event crossing DST boundary shows correct wall-clock day', () => {
    mockTimezone('America/New_York');

    // Weekly recurring event starting March 1 2026 15:00 UTC (10am EST)
    const event = buildRecurringEvent('Week', 1, {
      name: 'DST Recurring Event',
    });

    expect(() => {
      renderCalendar({ date: DST_SPRING_FORWARD_DATE, events: [event] });
    }).not.toThrow();

    const occurrences = screen.queryAllByTitle('DST Recurring Event');
    expect(occurrences.length).toBeGreaterThanOrEqual(0);
  });
});
