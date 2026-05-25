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

import { screen, fireEvent, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, test, vi, expect, beforeEach } from 'vitest';

import { buildEvent } from '../../fixtures/event';
import { CALENDAR_DATE } from '../helpers/buildCalendarEvents';
import { renderCalendar } from '../helpers/renderCalendar';

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
// April 20 2026 10:00 UTC
const APR20_10H = Math.floor(new Date('2026-04-20T10:00:00Z').getTime() / 1000);

/**
 * Find the clickable day cell for a given day number.
 * Day cells with events have the `cursor-pointer` class.
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
 * Return the open panel element by its dialog role.
 */
function getPanel(): HTMLElement {
  return screen.getByRole('dialog') as HTMLElement;
}

describe('EventCalendar — panel open/close/navigation', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  test('clicking a day with events opens the detail panel', async () => {
    const user = userEvent.setup();
    const event = buildEvent({
      name: 'Panel Test Event',
      fromDt: APR14_10H,
      toDt: APR14_10H + 3600,
    });

    renderCalendar({ date: CALENDAR_DATE, events: [event] });

    const cell = getDayCell(14);
    await user.click(cell);

    await waitFor(() => {
      expect(screen.getByText('1 Event')).toBeInTheDocument();
    });
  });

  test('clicking an event in the panel triggers onEditEvent', async () => {
    const user = userEvent.setup();
    const onEditEvent = vi.fn();
    const event = buildEvent({
      name: 'Editable Event',
      fromDt: APR14_10H,
      toDt: APR14_10H + 3600,
    });

    renderCalendar({ date: CALENDAR_DATE, events: [event], onEditEvent });

    const cell = getDayCell(14);
    await user.click(cell);

    await waitFor(() => screen.getByText('1 Event'));

    // Click the event list item inside the panel (the li element)
    const panel = getPanel();
    const eventListItem = within(panel).getByText('Editable Event').closest('li')!;
    await user.click(eventListItem);

    expect(onEditEvent).toHaveBeenCalledWith(expect.objectContaining({ name: 'Editable Event' }));
  });

  test('clicking outside the panel closes it', async () => {
    const user = userEvent.setup();
    const event = buildEvent({
      name: 'Close On Outside Click',
      fromDt: APR14_10H,
      toDt: APR14_10H + 3600,
    });

    renderCalendar({ date: CALENDAR_DATE, events: [event] });

    const cell = getDayCell(14);
    await user.click(cell);

    await waitFor(() => screen.getByText('1 Event'));

    await user.click(document.body);

    await waitFor(() => {
      expect(screen.queryByText('1 Event')).not.toBeInTheDocument();
    });
  });

  test('clicking the same day twice closes the panel', async () => {
    const user = userEvent.setup();
    const event = buildEvent({
      name: 'Toggle Panel Event',
      fromDt: APR14_10H,
      toDt: APR14_10H + 3600,
    });

    renderCalendar({ date: CALENDAR_DATE, events: [event] });

    const cell = getDayCell(14);
    await user.click(cell);

    await waitFor(() => screen.getByText('1 Event'));

    await user.click(cell);

    await waitFor(() => {
      expect(screen.queryByText('1 Event')).not.toBeInTheDocument();
    });
  });

  test('clicking a different day moves the panel to show new events', async () => {
    const user = userEvent.setup();
    const event14 = buildEvent({
      name: 'April 14 Event',
      fromDt: APR14_10H,
      toDt: APR14_10H + 3600,
    });
    const event20 = buildEvent({
      name: 'April 20 Event',
      fromDt: APR20_10H,
      toDt: APR20_10H + 3600,
    });

    renderCalendar({ date: CALENDAR_DATE, events: [event14, event20] });

    const cell14 = getDayCell(14);
    await user.click(cell14);

    // Panel opens for April 14: verify it shows April 14 event name (in the list) not April 20
    await waitFor(() => screen.getByText('1 Event'));
    const panel14 = getPanel();
    expect(within(panel14).getByText('April 14 Event')).toBeInTheDocument();
    expect(within(panel14).queryByText('April 20 Event')).not.toBeInTheDocument();

    const cell20 = getDayCell(20);
    await user.click(cell20);

    // Panel updates to show April 20 event
    await waitFor(() => {
      const panel20 = getPanel();
      expect(within(panel20).getByText('April 20 Event')).toBeInTheDocument();
    });
    const panel20 = getPanel();
    expect(within(panel20).queryByText('April 14 Event')).not.toBeInTheDocument();
  });

  test('clicking a day with no events does nothing', async () => {
    const event = buildEvent({
      name: 'Only On April 14',
      fromDt: APR14_10H,
      toDt: APR14_10H + 3600,
    });

    renderCalendar({ date: CALENDAR_DATE, events: [event] });

    // April 7 has no events — find the day 7 number element that has no cursor-pointer ancestor
    const spans = screen.getAllByText('7');
    const noEventSpan = spans.find((el) => !el.closest('[class*="cursor-pointer"]'));

    if (noEventSpan) {
      const parentCell =
        noEventSpan.closest('div[class*="overflow-hidden"]') ?? noEventSpan.parentElement;
      if (parentCell) {
        fireEvent.click(parentCell);
      }
    }

    expect(screen.queryByText('1 Event')).not.toBeInTheDocument();
    expect(screen.queryByText(/\d+ Events/)).not.toBeInTheDocument();
  });
});
