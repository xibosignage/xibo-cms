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

import { screen, act } from '@testing-library/react';
import type React from 'react';
import { vi, beforeEach, describe, test, expect } from 'vitest';

import { useEventActions } from '../../../hooks/useEventActions';
import { useEventData } from '../../../hooks/useEventData';
import { EMPTY_EVENT_TABLE, SINGLE_EVENT } from '../../fixtures/event';
import { defaultEventActions, mockEventData } from '../helpers/eventActions';
import { renderEventsPage } from '../helpers/renderEventsPage';

import { fetchUserPreference } from '@/services/userApi';
import { testQueryClient } from '@/setupTests';

// ─── Mocks ────────────────────────────────────────────────────────────────────

vi.mock('react-i18next', () => ({
  useTranslation: () => ({ t: (key: string) => key, i18n: { changeLanguage: vi.fn() } }),
  Trans: ({ children }: { children: React.ReactNode }) => children,
}));

// Replace the user-preferences API. By default we make it return "no saved
// preferences" right away, which is what most tests want. A specific test
// can override this if it wants to simulate the user's preferences still
// loading (for example, to check the loading state).
vi.mock('@/services/userApi', () => ({
  fetchUserPreference: vi.fn().mockResolvedValue(null),
  saveUserPreference: vi.fn().mockResolvedValue(undefined),
}));

// Safety net: stop the real event API from being called over the network.
// We're mocking the data hook directly below, so this should never fire,
// but we mock the API too just in case something slips through.
vi.mock('@/services/eventApi');

// Replace the data and action hooks with fake versions the tests can
// control directly.
vi.mock('../../../hooks/useEventData', () => ({ useEventData: vi.fn() }));
vi.mock('../../../hooks/useEventActions', () => ({ useEventActions: vi.fn() }));
vi.mock('../../../hooks/useEventFilterOptions', () => ({
  useEventFilterOptions: vi.fn(() => ({ filterOptions: [] })),
}));

// Replace the big sub-components (filters, calendar view, modals) with
// tiny placeholders so we can focus this test file on what the table
// renders. Each of those sub-components has its own dedicated test file.
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

// Replace the row-actions dropdown with simple plain buttons. The real
// dropdown uses a popup library that doesn't open properly in the test
// environment, so we render every action straight onto the page as a
// button the tests can click directly. (Same trick as in
// Layouts.delete.modal.test.tsx.)
vi.mock('@/components/ui/table/DataTableRowActions', () => ({
  default: ({
    row,
    actions,
  }: {
    row: unknown;
    actions: Array<{ label?: string; onClick?: (r: unknown) => void; isSeparator?: boolean }>;
  }) => (
    <div>
      <button aria-label="More actions" />
      {actions
        .filter((a) => !a.isSeparator && a.label)
        .map((action, i) => (
          <button key={i} onClick={() => action.onClick?.(row)}>
            {action.label}
          </button>
        ))}
    </div>
  ),
}));

// =============================================================================
// Tests
// =============================================================================

describe('Events page - render', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    vi.mocked(useEventActions).mockReturnValue(defaultEventActions());
    vi.mocked(fetchUserPreference).mockResolvedValue(null);
  });

  // ---------------------------------------------------------------------------
  // The loading placeholder that's shown while the user's saved
  // preferences are still being fetched.
  // ---------------------------------------------------------------------------

  // Make the saved-preferences fetch hang forever so the page is stuck in
  // its loading state. The animated loading message should be on screen,
  // and the table itself should NOT be rendered yet. We also tell the
  // helper to skip its usual pre-seeding step, because pre-seeding would
  // make the page think the preferences are already loaded.
  test('shows a loading message and hides the table while saved preferences are loading', () => {
    vi.mocked(fetchUserPreference).mockImplementation(() => new Promise(() => {}));
    mockEventData(EMPTY_EVENT_TABLE);

    renderEventsPage(undefined, { hydrate: false });

    expect(screen.getByText('Loading your events preferences...')).toBeInTheDocument();
    // None of the table's column headers (Name, Event Type, etc.) should
    // be on the page yet because the table itself hasn't rendered.
    expect(screen.queryByRole('columnheader', { name: 'Name' })).not.toBeInTheDocument();
  });

  // ---------------------------------------------------------------------------
  // The page can show events as either a table or a calendar. When the
  // user first lands on the page it should be in table view, not calendar
  // view.
  // ---------------------------------------------------------------------------

  test('the page opens in table view by default, not calendar view', async () => {
    mockEventData(SINGLE_EVENT);

    await act(async () => {
      renderEventsPage();
    });

    // The table's column headers should be on screen because the table
    // is the default view.
    expect(screen.getByRole('columnheader', { name: 'Name' })).toBeInTheDocument();
    // The calendar component should NOT be rendered. (Our test setup
    // replaces the real calendar with a stand-in carrying this test id;
    // if the calendar branch had been chosen it would be on the page.)
    expect(screen.queryByTestId('event-calendar')).not.toBeInTheDocument();
  });

  // ---------------------------------------------------------------------------
  // The default page size: when the page first opens, the table should
  // ask for the first page of results, with 10 events per page.
  // ---------------------------------------------------------------------------

  // When the page first opens, it should request page 1 of results, with
  // 10 events per page (which is what the spec calls for).
  test('the page starts on page 1 with 10 events per page', async () => {
    mockEventData(EMPTY_EVENT_TABLE);

    await act(async () => {
      renderEventsPage();
    });

    expect(useEventData).toHaveBeenCalledWith(
      expect.objectContaining({
        pagination: { pageIndex: 0, pageSize: 10 },
      }),
    );
  });

  // ---------------------------------------------------------------------------
  // The columns that should appear by default (the user can show or hide
  // others through the column-visibility menu, but these are the ones we
  // pick for them out of the box).
  // ---------------------------------------------------------------------------

  test('the table shows the default set of columns when the page first opens', async () => {
    mockEventData(SINGLE_EVENT);

    await act(async () => {
      renderEventsPage();
    });

    for (const header of ['ID', 'Event Type', 'Name', 'Start', 'End', 'Event', 'Criteria']) {
      expect(screen.getByRole('columnheader', { name: header })).toBeInTheDocument();
    }
  });

  // The columns that are hidden by default should NOT show up unless the
  // user explicitly turns them on through the column-visibility menu.
  // (That toggling behaviour is covered in Events.columns.test.tsx.)
  test('columns that are hidden by default do not appear when the page first opens', async () => {
    mockEventData(SINGLE_EVENT);

    await act(async () => {
      renderEventsPage();
    });

    const hiddenHeaders = [
      'Campaign ID',
      'Display Groups',
      'SoV',
      'Max Plays per Hour',
      'Geo Aware',
      'Recurring',
      'Priority',
      'Created On',
      'Updated On',
      'Modified By',
    ];
    for (const header of hiddenHeaders) {
      expect(screen.queryByRole('columnheader', { name: header })).not.toBeInTheDocument();
    }
  });

  // ---------------------------------------------------------------------------
  // When events come back from the API, they should appear as rows in
  // the table.
  // ---------------------------------------------------------------------------

  test('an event returned by the API shows up as a row in the table', async () => {
    mockEventData(SINGLE_EVENT);

    await act(async () => {
      renderEventsPage();
    });

    // The event's name should appear inside the Name column's cell.
    expect(await screen.findByRole('cell', { name: 'Morning Promo' })).toBeInTheDocument();
  });

  // ---------------------------------------------------------------------------
  // When the API returns no events at all, the page should still load
  // cleanly and show the empty table (with column headers but no rows).
  // ---------------------------------------------------------------------------

  test('the table still loads cleanly when there are no events to show', async () => {
    mockEventData(EMPTY_EVENT_TABLE);

    await act(async () => {
      renderEventsPage();
    });

    expect(screen.getByRole('columnheader', { name: 'Name' })).toBeInTheDocument();
    // None of the test event's data should be on the page (because no
    // events were returned).
    expect(screen.queryByText('Morning Promo')).not.toBeInTheDocument();
  });
});
