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

// The Events page as a scheduler who isn't an admin. Every other Events test
// logs in as a super admin, and a super admin passes every feature check, so
// none of them can tell whether Add Event is gated at all.

import { screen } from '@testing-library/react';
import type React from 'react';
import { vi, beforeEach, describe, test, expect } from 'vitest';

import { useEventActions } from '../../../hooks/useEventActions';
import { mockEvent, SINGLE_EVENT } from '../../fixtures/event';
import { mockUser } from '../../fixtures/user';
import { defaultEventActions, mockEventData } from '../helpers/eventActions';
import { renderEventsPage } from '../helpers/renderEventsPage';

import { fetchUserPreference } from '@/services/userApi';
import { testQueryClient } from '@/setupTests';
import { buildCurrentUser, PERSONAS } from '@/testUtils/personas';

// ─── Mocks ────────────────────────────────────────────────────────────────────

vi.mock('@/services/userApi', () => ({
  fetchUserPreference: vi.fn().mockResolvedValue(null),
  saveUserPreference: vi.fn().mockResolvedValue(undefined),
}));
vi.mock('@/services/eventApi');

vi.mock('../../../hooks/useEventData', () => ({
  useEventData: vi.fn(),
  useAllEventData: vi.fn(() => ({ data: { rows: [], totalCount: 0 }, isFetching: false })),
}));
vi.mock('../../../hooks/useEventActions', () => ({ useEventActions: vi.fn() }));
vi.mock('../../../hooks/useEventFilterOptions', () => ({
  useEventFilterOptions: vi.fn(() => ({ filterOptions: [] })),
}));

vi.mock('../../../components/DateRangeController', () => ({
  DateRangeController: () => <div data-testid="date-range" />,
}));
vi.mock('../../../components/DisplayGroupMultiSelect', () => ({
  DisplayGroupMultiSelect: () => <div data-testid="display-group-select" />,
}));
vi.mock('../../../components/EventCalendar', () => ({
  EventCalendar: () => <div data-testid="event-calendar" />,
}));
vi.mock('@/components/ui/modals/ScheduleEventModal', () => ({ default: () => null }));
vi.mock('@/components/ui/modals/Modal');

// =============================================================================
// Users
// =============================================================================

// A scheduler who can add events, and the same scheduler without that feature.
const scheduler = buildCurrentUser(PERSONAS.scheduleManager, { settings: mockUser.settings });
const schedulerWithoutAdd = buildCurrentUser(PERSONAS.scheduleManager, {
  settings: mockUser.settings,
  features: { 'schedule.add': false },
});

// =============================================================================
// Tests
// =============================================================================

describe('Events page - a scheduler who is not an admin', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    vi.mocked(useEventActions).mockReturnValue(defaultEventActions());
    vi.mocked(fetchUserPreference).mockResolvedValue(null);
    mockEventData(SINGLE_EVENT);
  });

  test('a user without Add schedule gets no Add Event button', async () => {
    renderEventsPage(schedulerWithoutAdd);

    // Wait for the table, so "absent" can't pass because the page isn't drawn yet.
    await screen.findByText(mockEvent.name as string);
    expect(screen.queryByRole('button', { name: /add event/i })).not.toBeInTheDocument();
  });

  test('a user with Add schedule gets the Add Event button', async () => {
    renderEventsPage(scheduler);

    await screen.findByText(mockEvent.name as string);
    expect(screen.getByRole('button', { name: /add event/i })).toBeInTheDocument();
  });
});
