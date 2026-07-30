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

import { QueryClientProvider } from '@tanstack/react-query';
import { render, screen, act, within } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import { vi, beforeEach, describe, test, expect } from 'vitest';

import Events from '../../../Events';
import { useEventActions } from '../../../hooks/useEventActions';
import { buildEvent } from '../../fixtures/event';
import { mockUser } from '../../fixtures/user';
import { defaultEventActions, mockEventData } from '../helpers/eventActions';

import { UserProvider } from '@/context/UserContext';
import { fetchUserPreference, saveUserPreference } from '@/services/userApi';
import { testQueryClient } from '@/setupTests';

vi.mock('@/services/userApi', () => ({
  fetchUserPreference: vi.fn().mockResolvedValue(null),
  saveUserPreference: vi.fn().mockResolvedValue(undefined),
}));
vi.mock('@/services/eventApi');

vi.mock('../../../hooks/useEventData', () => ({ useEventData: vi.fn() }));
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
vi.mock('../../../components/DeleteEventModal', () => ({ default: () => null }));
vi.mock('../../../components/CopyEventModal', () => ({ default: () => null }));

vi.mock('@/components/ui/modals/Modal');

vi.mock('@/components/ui/table/DataTableRowActions', () => ({
  default: () => null,
}));

// Weekly recurring event repeating on Mon-Fri. The backend sends
// recurrenceRepeatsOn as a raw comma-separated list of ISO weekday numbers
// (1=Monday ... 7=Sunday) - see lib/Controller/Schedule.php's
// implode(',', $recurrenceRepeatsOn).
const WEEKDAY_EVENT = buildEvent({
  eventId: 2001,
  name: 'Weekday Promo',
  recurrenceType: 'Week',
  recurrenceDetail: 1,
  recurrenceRepeatsOn: '1,2,3,4,5',
  recurringEvent: true,
});

const renderWithColumnVisible = () => {
  testQueryClient.setQueryData(['userPref', 'event_page'], {
    columnVisibility: { recurrenceRepeatsOn: true },
  });
  return render(
    <QueryClientProvider client={testQueryClient}>
      <UserProvider initialUser={mockUser}>
        <MemoryRouter>
          <Events />
        </MemoryRouter>
      </UserProvider>
    </QueryClientProvider>,
  );
};

describe('Events table - Recurrence Repeats On column', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    vi.mocked(useEventActions).mockReturnValue(defaultEventActions());
    vi.mocked(fetchUserPreference).mockResolvedValue(null);
    vi.mocked(saveUserPreference).mockResolvedValue(undefined);
    mockEventData({ rows: [WEEKDAY_EVENT], totalCount: 1 });
  });

  // Regression: release44's equivalent grid column converted the raw
  // "1,2,3,4,5" weekday-number CSV into day names before rendering
  // (ui/src/pages/schedule/schedule-page.js, recurrenceRepeatsOn column
  // definition). The React port's EventsConfig.tsx column renders the raw
  // API value verbatim with no such conversion, so a Mon-Fri weekly event
  // shows the digits "1,2,3,4,5" instead of "Monday, Tuesday, Wednesday,
  // Thursday, Friday".
  test('shows day names for a weekly recurring event, not raw weekday numbers', async () => {
    await act(async () => {
      renderWithColumnVisible();
    });

    const table = screen.getByRole('table');
    const row = within(table).getByText('Weekday Promo').closest('tr')!;

    expect(within(row).getByText('Monday, Tuesday, Wednesday, Thursday, Friday')).toBeInTheDocument();
    expect(within(row).queryByText('1,2,3,4,5')).not.toBeInTheDocument();
  });
});
