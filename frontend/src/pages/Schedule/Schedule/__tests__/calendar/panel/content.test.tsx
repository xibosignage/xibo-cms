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
import userEvent from '@testing-library/user-event';
import { describe, test, vi, expect, beforeEach } from 'vitest';

import { buildEvent } from '../../fixtures/event';
import { CALENDAR_DATE } from '../helpers/buildCalendarEvents';
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

/**
 * Find the clickable day cell for a given day number (has events → cursor-pointer).
 */
function getDayCell(dayNumber: number): HTMLElement {
  const cells = screen
    .getAllByText(String(dayNumber))
    .map(
      (el) =>
        el.closest('[class*="cursor-pointer"]') ??
        el.parentElement?.closest('[class*="cursor-pointer"]'),
    )
    .filter(Boolean) as HTMLElement[];
  if (cells.length === 0) {
    throw new Error(`No clickable day cell found for day ${dayNumber}`);
  }
  return cells[0]!;
}

/**
 * Open the panel for the given day number and wait for it to appear.
 */
async function openPanel(user: ReturnType<typeof userEvent.setup>, dayNumber: number) {
  const cell = getDayCell(dayNumber);
  await user.click(cell);
  await screen.findByRole('dialog');
}

describe('EventCalendar — panel content', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    vi.mocked(useUserContext).mockReturnValue({
      user: { settings: { defaultTimezone: 'UTC' } } as unknown as ReturnType<
        typeof useUserContext
      >['user'],
      isAuthenticated: true,
      logout: vi.fn(),
      updateUser: vi.fn(),
    });
  });

  test('same-day event shows full time range in panel', async () => {
    const user = userEvent.setup();
    // April 14 10:00 UTC → April 14 17:00 UTC
    const fromDt = Math.floor(new Date('2026-04-14T10:00:00Z').getTime() / 1000);
    const toDt = Math.floor(new Date('2026-04-14T17:00:00Z').getTime() / 1000);
    const event = buildEvent({ name: 'Same Day Event', fromDt, toDt });

    renderCalendar({ date: CALENDAR_DATE, events: [event] });
    await openPanel(user, 14);

    expect(screen.getByText('10:00am – 5:00pm')).toBeInTheDocument();
  });

  test('multi-day event on first day shows start time with trailing dash', async () => {
    const user = userEvent.setup();
    // April 14 10:00 UTC → April 16 17:00 UTC
    const fromDt = Math.floor(new Date('2026-04-14T10:00:00Z').getTime() / 1000);
    const toDt = Math.floor(new Date('2026-04-16T17:00:00Z').getTime() / 1000);
    const event = buildEvent({ name: 'Multi Day Event', fromDt, toDt });

    renderCalendar({ date: CALENDAR_DATE, events: [event] });
    await openPanel(user, 14);

    expect(screen.getByText('10:00am –')).toBeInTheDocument();
  });

  test('multi-day event on last day shows end time with leading dash', async () => {
    const user = userEvent.setup();
    // April 14 10:00 UTC → April 16 17:00 UTC
    const fromDt = Math.floor(new Date('2026-04-14T10:00:00Z').getTime() / 1000);
    const toDt = Math.floor(new Date('2026-04-16T17:00:00Z').getTime() / 1000);
    const event = buildEvent({ name: 'Multi Day End Event', fromDt, toDt });

    renderCalendar({ date: CALENDAR_DATE, events: [event] });
    await openPanel(user, 16);

    expect(screen.getByText('– 5:00pm')).toBeInTheDocument();
  });

  test('multi-day event on a middle day shows no time label', async () => {
    const user = userEvent.setup();
    // April 14 10:00 UTC → April 16 17:00 UTC
    const fromDt = Math.floor(new Date('2026-04-14T10:00:00Z').getTime() / 1000);
    const toDt = Math.floor(new Date('2026-04-16T17:00:00Z').getTime() / 1000);
    const event = buildEvent({ name: 'Multi Day Middle Event', fromDt, toDt });

    renderCalendar({ date: CALENDAR_DATE, events: [event] });
    await openPanel(user, 15);

    // No time label span should be rendered for the middle day
    const panel = screen.getByRole('dialog');
    expect(panel).not.toHaveTextContent('–');
  });

  test('midnight boundary: second day shows "– 12:00am" time label', async () => {
    const user = userEvent.setup();
    // April 14 22:00 UTC → April 15 00:00 UTC
    const fromDt = Math.floor(new Date('2026-04-14T22:00:00Z').getTime() / 1000);
    const toDt = Math.floor(new Date('2026-04-15T00:00:00Z').getTime() / 1000);
    const event = buildEvent({ name: 'Midnight Event', fromDt, toDt });

    renderCalendar({ date: CALENDAR_DATE, events: [event] });
    await openPanel(user, 15);

    expect(screen.getByText('– 12:00am')).toBeInTheDocument();
  });

  test('fractional timezone time label formats correctly', async () => {
    const user = userEvent.setup();
    mockTimezone('Asia/Kolkata');

    // April 14 04:30 UTC = April 14 10:00 IST
    // April 14 11:30 UTC = April 14 17:00 IST
    const fromDt = Math.floor(new Date('2026-04-14T04:30:00Z').getTime() / 1000);
    const toDt = Math.floor(new Date('2026-04-14T11:30:00Z').getTime() / 1000);
    const event = buildEvent({ name: 'IST Event', fromDt, toDt });

    renderCalendar({ date: CALENDAR_DATE, events: [event] });
    await openPanel(user, 14);

    expect(screen.getByText('10:00am – 5:00pm')).toBeInTheDocument();
  });

  test('event with no name and no content renders without crashing', async () => {
    const user = userEvent.setup();
    const fromDt = Math.floor(new Date('2026-04-14T10:00:00Z').getTime() / 1000);
    const toDt = fromDt + 3600;
    const event = buildEvent({
      name: null,
      campaign: undefined,
      command: undefined,
      fromDt,
      toDt,
    });

    renderCalendar({ date: CALENDAR_DATE, events: [event] });
    await openPanel(user, 14);

    expect(screen.getByText('1 Event')).toBeInTheDocument();
  });

  test('event with one display group shows no +N badge', async () => {
    const user = userEvent.setup();
    const fromDt = Math.floor(new Date('2026-04-14T10:00:00Z').getTime() / 1000);
    const toDt = fromDt + 3600;
    const event = buildEvent({
      name: 'Single Display Event',
      fromDt,
      toDt,
      displayGroups: [{ displayGroupId: 1, displayGroup: 'Lobby Screen' } as never],
    });

    renderCalendar({ date: CALENDAR_DATE, events: [event] });
    await openPanel(user, 14);

    expect(screen.queryByText('+1')).not.toBeInTheDocument();
    expect(screen.queryByText('+2')).not.toBeInTheDocument();
  });

  test('event with three display groups shows first group and +2 badge', async () => {
    const user = userEvent.setup();
    const fromDt = Math.floor(new Date('2026-04-14T10:00:00Z').getTime() / 1000);
    const toDt = fromDt + 3600;
    const event = buildEvent({
      name: 'Multi Display Event',
      fromDt,
      toDt,
      displayGroups: [
        { displayGroupId: 1, displayGroup: 'Lobby Screen' },
        { displayGroupId: 2, displayGroup: 'Reception Screen' },
        { displayGroupId: 3, displayGroup: 'Boardroom Screen' },
      ] as never,
    });

    renderCalendar({ date: CALENDAR_DATE, events: [event] });
    await openPanel(user, 14);

    expect(screen.getByText('Lobby Screen')).toBeInTheDocument();
    expect(screen.getByText('+2')).toBeInTheDocument();
  });

  test('event with no display groups renders row without badge and without crashing', async () => {
    const user = userEvent.setup();
    const fromDt = Math.floor(new Date('2026-04-14T10:00:00Z').getTime() / 1000);
    const toDt = fromDt + 3600;
    const event = buildEvent({
      name: 'No Displays Event',
      fromDt,
      toDt,
      displayGroups: [],
    });

    renderCalendar({ date: CALENDAR_DATE, events: [event] });
    await openPanel(user, 14);

    expect(screen.getByText('1 Event')).toBeInTheDocument();
  });
});
