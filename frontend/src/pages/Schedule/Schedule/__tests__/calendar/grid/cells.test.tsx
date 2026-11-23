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

import { screen } from '@testing-library/react';
import { vi, afterEach, beforeEach, describe, test, expect } from 'vitest';

import { EVENT_LEGEND_BADGES } from '../../../EventsConfig';
import { CALENDAR_DATE } from '../helpers/buildCalendarEvents';
import { renderCalendar } from '../helpers/renderCalendar';

import { testQueryClient } from '@/setupTests';
import { buildCurrentUser, PERSONAS } from '@/testUtils/personas';

// ─── Mocks ────────────────────────────────────────────────────────────────────

vi.mock('@/context/UserContext', () => ({
  useUserContext: () => ({
    user: buildCurrentUser(PERSONAS.superAdmin, {
      userId: 1,
      userName: 'TestUser',
      groupId: 1,
      settings: { defaultTimezone: 'UTC' },
    }),
    isAuthenticated: true,
    logout: vi.fn(),
    updateUser: vi.fn(),
  }),
}));

// ─── Setup ────────────────────────────────────────────────────────────────────

beforeEach(() => {
  testQueryClient.clear();
  vi.clearAllMocks();
});

afterEach(() => {
  vi.useRealTimers();
});

// ─── Tests ────────────────────────────────────────────────────────────────────

describe('EventCalendar – grid structure', () => {
  test('today is highlighted with a blue circle on the day number', () => {
    // Mid-day UTC, so the local and UTC dates agree in every timezone
    vi.useFakeTimers({ toFake: ['Date'] });
    vi.setSystemTime(new Date('2026-04-15T12:00:00Z'));
    const today = new Date();
    renderCalendar({ date: today });

    const todayDay = today.getUTCDate();
    const allSpans = document.querySelectorAll(
      'span.inline-flex.items-center.justify-center.rounded-full.bg-xibo-blue-600',
    );

    const highlighted = Array.from(allSpans).find(
      (el) => el.textContent?.trim() === String(todayDay),
    );
    expect(highlighted).toBeTruthy();
  });

  test('days outside the current month are rendered in grey', () => {
    // April 2026 starts on Wednesday. The grid week starts on Monday March 30.
    // March 30 and 31 are overflow days and should carry text-gray-300.
    renderCalendar({ date: CALENDAR_DATE });

    const dayNumberWrappers = document.querySelectorAll('div.text-gray-300');

    expect(dayNumberWrappers.length).toBeGreaterThan(0);

    const texts = Array.from(dayNumberWrappers).map((el) => el.textContent?.trim());
    // March 30 and 31 appear as "30" and "31" in the overflow area
    expect(texts).toContain('30');
    expect(texts).toContain('31');
  });

  test('days with no events are not clickable', () => {
    renderCalendar({ date: CALENDAR_DATE, events: [] });

    const dayCells = document.querySelectorAll('.grid-cols-7 > div');

    dayCells.forEach((cell) => {
      expect(cell.classList.contains('cursor-pointer')).toBe(false);
    });
  });

  test('loading overlay is shown while fetching', () => {
    renderCalendar({ date: CALENDAR_DATE, isLoading: true });

    expect(screen.getByText('Loading...')).toBeInTheDocument();
  });

  test('legend shows all event type groups', () => {
    renderCalendar({ date: CALENDAR_DATE });

    expect(screen.getByText('Legend')).toBeInTheDocument();

    const allLabels = EVENT_LEGEND_BADGES.flat().map((b) => b.label);
    const visibleLabels = allLabels.filter((label) => screen.queryByText(label) !== null);

    expect(visibleLabels.length).toBeGreaterThanOrEqual(4);
  });
});
