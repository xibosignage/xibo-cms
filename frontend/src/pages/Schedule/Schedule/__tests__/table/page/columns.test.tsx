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
import { render, screen, act, fireEvent } from '@testing-library/react';
import type React from 'react';
import { MemoryRouter } from 'react-router-dom';
import { vi, beforeEach, describe, test, expect } from 'vitest';

import Events from '../../../Events';
import { useEventActions } from '../../../hooks/useEventActions';
import { SINGLE_EVENT } from '../../fixtures/event';
import { mockUser } from '../../fixtures/user';
import { defaultEventActions, mockEventData } from '../helpers/eventActions';

import { UserProvider } from '@/context/UserContext';
import { fetchUserPreference, saveUserPreference } from '@/services/userApi';
import { testQueryClient } from '@/setupTests';

// ─── Mocks ────────────────────────────────────────────────────────────────────

vi.mock('react-i18next', () => ({
  useTranslation: () => ({ t: (key: string) => key, i18n: { changeLanguage: vi.fn() } }),
  Trans: ({ children }: { children: React.ReactNode }) => children,
}));

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

// =============================================================================
// Helpers
// =============================================================================

// Renders the Events page after pretending the user has some saved column
// preferences in their account. The shared renderEventsPage helper assumes
// no saved preferences (a fresh user). Here we want to test that the page
// correctly applies a real saved state when it loads, so we seed the
// preferences cache with whatever the test passes in.
const renderWithPrefs = (savedPrefs: unknown) => {
  testQueryClient.setQueryData(['userPref', 'event_page'], savedPrefs);
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

// =============================================================================
// Tests
// =============================================================================

describe('Events page - column visibility persistence', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    vi.mocked(useEventActions).mockReturnValue(defaultEventActions());
    vi.mocked(fetchUserPreference).mockResolvedValue(null);
    mockEventData(SINGLE_EVENT);
  });

  // ---------------------------------------------------------------------------
  // Baseline: a fresh user with no saved preferences. The columns that are
  // hidden by default should stay hidden.
  // ---------------------------------------------------------------------------
  test('uses the default column visibility when the user has no saved preferences', async () => {
    await act(async () => {
      renderWithPrefs(null);
    });

    expect(screen.getByRole('columnheader', { name: 'Name' })).toBeInTheDocument();
    expect(screen.queryByRole('columnheader', { name: 'Campaign ID' })).not.toBeInTheDocument();
    expect(screen.queryByRole('columnheader', { name: 'Display Groups' })).not.toBeInTheDocument();
  });

  // ---------------------------------------------------------------------------
  // The "after refresh" half of the test plan's "toggling a column on/off
  // persists after page refresh" requirement. We pretend the user has
  // already turned a hidden column ON in a previous session, then load the
  // page fresh and check that the column is showing.
  //
  // The matching SAVE side - "the user's toggle is actually written to
  // their saved preferences" - is exercised in a separate test further
  // down, using fake timers to fast-forward the 500ms debounce.
  // ---------------------------------------------------------------------------
  test('a column that was turned ON in saved preferences shows up after the page reloads', async () => {
    await act(async () => {
      renderWithPrefs({
        columnVisibility: {
          // These two columns are hidden by default. The user's saved
          // preferences should override that and turn them ON.
          campaignId: true,
          displayGroups: true,
          // We leave every other column at its default and only assert on
          // the two we explicitly turned on.
        },
      });
    });

    expect(screen.getByRole('columnheader', { name: 'Campaign ID' })).toBeInTheDocument();
    expect(screen.getByRole('columnheader', { name: 'Display Groups' })).toBeInTheDocument();
  });

  // The opposite direction: if the user previously turned a normally-visible
  // column OFF, that column should stay hidden after the page reloads.
  test('a column that was turned OFF in saved preferences stays hidden after the page reloads', async () => {
    await act(async () => {
      renderWithPrefs({
        columnVisibility: {
          // The "Criteria" column is normally visible. Here the user's
          // saved preferences turn it off.
          criteria: false,
        },
      });
    });

    expect(screen.queryByRole('columnheader', { name: 'Criteria' })).not.toBeInTheDocument();
    // Quick sanity check: the other default-visible columns (like Name)
    // should still be showing.
    expect(screen.getByRole('columnheader', { name: 'Name' })).toBeInTheDocument();
  });

  // ---------------------------------------------------------------------------
  // The SAVE side of column persistence: when the user toggles a column
  // on or off, the new visibility should be written back to their saved
  // preferences. The save is debounced by 500ms (so rapid clicks don't
  // hammer the API), so this test uses fake timers to fast-forward through
  // the debounce.
  // ---------------------------------------------------------------------------
  test('toggling a column off triggers a debounced save of the new column visibility', async () => {
    // Render with no saved preferences. The page hydrates with the
    // default column visibility and isHydrated flips to true.
    await act(async () => {
      renderWithPrefs(null);
    });

    // No save should have been triggered just from the page loading -
    // saving only happens in response to user changes.
    expect(saveUserPreference).not.toHaveBeenCalled();

    // Open the "Columns" dropdown (the button has aria-label "Toggle
    // columns") so the column checkboxes become reachable.
    const columnsToggle = await screen.findByRole('button', { name: /Toggle columns/i });
    await act(async () => {
      fireEvent.click(columnsToggle);
    });

    // Switch to fake timers AFTER the dropdown is open. Doing it earlier
    // could interfere with React Query's hydration; doing it now lets us
    // advance only the 500ms debounce we care about.
    vi.useFakeTimers();
    try {
      // Untick the "Criteria" column checkbox - one of the columns that
      // is visible by default.
      const criteriaCheckbox = screen.getByLabelText('Criteria');
      await act(async () => {
        fireEvent.click(criteriaCheckbox);
      });

      // The save is debounced - it must NOT fire immediately on toggle.
      expect(saveUserPreference).not.toHaveBeenCalled();

      // Fast-forward past the 500ms debounce window.
      await act(async () => {
        vi.advanceTimersByTime(500);
      });
    } finally {
      // Restore real timers so subsequent tests aren't affected.
      vi.useRealTimers();
    }

    // After the debounce, the save should have been called with the new
    // column visibility - "criteria" should now be false.
    expect(saveUserPreference).toHaveBeenCalled();
    const calls = vi.mocked(saveUserPreference).mock.calls;
    const lastCall = calls[calls.length - 1]!;
    const arg = lastCall[0] as {
      option: string;
      value: { columnVisibility?: Record<string, boolean> };
    };
    expect(arg.option).toBe('event_page');
    expect(arg.value.columnVisibility?.criteria).toBe(false);
  });
});
