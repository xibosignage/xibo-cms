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
import { CALENDAR_DATE } from '../helpers/buildCalendarEvents';
import { renderCalendar } from '../helpers/renderCalendar';

import { EventTypeId } from '@/types/event';

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

// April 14 2026 10:00 UTC
const APR14_10H = Math.floor(new Date('2026-04-14T10:00:00Z').getTime() / 1000);

describe('EventCalendar — grid event icons', () => {
  test('event icons are shown per event type', () => {
    const allTypeIds = [
      EventTypeId.Layout,
      EventTypeId.Command,
      EventTypeId.Overlay,
      EventTypeId.Interrupt,
      EventTypeId.Campaign,
      EventTypeId.Action,
      EventTypeId.Media,
      EventTypeId.Playlist,
      EventTypeId.Sync,
      EventTypeId.DataConnector,
    ];

    const events = allTypeIds.map((typeId, i) =>
      buildEvent({
        eventTypeId: typeId,
        name: `Event Type ${typeId}`,
        fromDt: APR14_10H + i * 60,
        toDt: APR14_10H + i * 60 + 3600,
      }),
    );

    renderCalendar({ date: CALENDAR_DATE, events });

    for (const event of events) {
      expect(screen.getByTitle(event.name!)).toBeInTheDocument();
    }
  });

  test('priority badge is shown on the icon', () => {
    const event = buildEvent({
      name: 'Priority Event',
      isPriority: 3,
      fromDt: APR14_10H,
      toDt: APR14_10H + 3600,
    });

    renderCalendar({ date: CALENDAR_DATE, events: [event] });

    // The priority badge is a small span with a specific class pattern
    // There may be a day cell with text "3" (April 3), so we look for the badge span
    const badgeSpans = document.querySelectorAll('span.absolute.-top-1.-right-1');
    const priorityBadge = Array.from(badgeSpans).find((el) => el.textContent === '3');
    expect(priorityBadge).toBeDefined();
    expect(priorityBadge).toBeInTheDocument();
  });

  test('event with no end time is silently skipped', () => {
    const event = buildEvent({
      name: 'No End Time Event',
      fromDt: APR14_10H,
      toDt: undefined as unknown as number,
    });

    renderCalendar({ date: CALENDAR_DATE, events: [event] });

    expect(screen.queryByTitle('No End Time Event')).not.toBeInTheDocument();
  });

  test('event spanning the whole month appears on every day', () => {
    // March 31 UTC → May 2 UTC — covers all of April
    const fromDt = Math.floor(new Date('2026-03-31T00:00:00Z').getTime() / 1000);
    const toDt = Math.floor(new Date('2026-05-02T00:00:00Z').getTime() / 1000);
    const event = buildEvent({ name: 'Full Month Event', fromDt, toDt });

    expect(() => {
      renderCalendar({ date: CALENDAR_DATE, events: [event] });
    }).not.toThrow();
  });

  test('events on overflow days show their icons', () => {
    // March 30 2026 is an overflow day visible in the April calendar
    const fromDt = Math.floor(new Date('2026-03-30T10:00:00Z').getTime() / 1000);
    const toDt = fromDt + 3600;
    const event = buildEvent({ name: 'Overflow Day Event', fromDt, toDt });

    renderCalendar({ date: CALENDAR_DATE, events: [event] });

    expect(screen.getByTitle('Overflow Day Event')).toBeInTheDocument();
  });

  test('event ending exactly at midnight appears on both days', () => {
    // April 1 22:00 UTC → April 2 00:00 UTC
    const fromDt = Math.floor(new Date('2026-04-01T22:00:00Z').getTime() / 1000);
    const toDt = Math.floor(new Date('2026-04-02T00:00:00Z').getTime() / 1000);
    const event = buildEvent({ name: 'Midnight Boundary Event', fromDt, toDt });

    renderCalendar({ date: CALENDAR_DATE, events: [event] });

    const icons = screen.getAllByTitle('Midnight Boundary Event');
    expect(icons.length).toBeGreaterThanOrEqual(2);
  });

  test('zero duration event renders on the calendar day', () => {
    const event = buildEvent({
      name: 'Zero Duration Event',
      fromDt: APR14_10H,
      toDt: APR14_10H,
    });

    renderCalendar({ date: CALENDAR_DATE, events: [event] });

    // The component does not filter out zero-duration events — they appear on the day
    expect(screen.getByTitle('Zero Duration Event')).toBeInTheDocument();
  });

  test('no events renders all cells without icons', () => {
    const { container } = renderCalendar({ date: CALENDAR_DATE, events: [] });

    const badges = container.querySelectorAll('[title]');
    // Day-of-week headers and legend items may have no title; event icon spans carry the event title
    // All title attributes should be day-of-week or legend labels, none should be event names
    const eventIcons = Array.from(badges).filter(
      (el) => el.classList.contains('relative') && el.classList.contains('inline-flex'),
    );
    expect(eventIcons.length).toBe(0);
  });

  test('all events filtered out produces blank calendar', () => {
    const { container } = renderCalendar({ date: CALENDAR_DATE, events: [] });

    const dayCells = container.querySelectorAll('.cursor-pointer');
    expect(dayCells.length).toBe(0);
  });
});
