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
import { vi, beforeEach, describe, test, expect } from 'vitest';

import { EVENT_LEGEND_BADGES } from '../../../EventsConfig';
import { CALENDAR_DATE } from '../helpers/buildCalendarEvents';
import { renderCalendar } from '../helpers/renderCalendar';

import { testQueryClient } from '@/setupTests';

// ─── Mocks ────────────────────────────────────────────────────────────────────

const t = (key: string) => key;
vi.mock('react-i18next', () => ({
  useTranslation: () => ({ t, i18n: { changeLanguage: vi.fn() } }),
  Trans: ({ children }: { children: React.ReactNode }) => children,
}));

vi.mock('@/context/UserContext', () => ({
  useUserContext: () => ({
    user: {
      userId: 1,
      userName: 'TestUser',
      userTypeId: 1,
      groupId: 1,
      features: {},
      settings: { defaultTimezone: 'UTC' },
    },
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

// ─── Tests ────────────────────────────────────────────────────────────────────

describe('EventCalendar – grid structure', () => {
  test('today is highlighted with a blue circle on the day number', () => {
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
