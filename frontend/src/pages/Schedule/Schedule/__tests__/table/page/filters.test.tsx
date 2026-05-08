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

import { screen, fireEvent, act, waitFor } from '@testing-library/react';
import type React from 'react';
import { vi, beforeEach, describe, test, expect } from 'vitest';

import { useEventActions } from '../../../hooks/useEventActions';
import { useEventData } from '../../../hooks/useEventData';
import { mockEvent } from '../../fixtures/event';
import { defaultEventActions, mockEventData } from '../helpers/eventActions';
import { renderEventsPage } from '../helpers/renderEventsPage';

import { fetchUserPreference } from '@/services/userApi';
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

// Replace the real date-range picker with a single button. Clicking the
// button fires the page's date-range handler with a fixed start/end date.
// This lets each test simulate the user picking a date range without us
// having to operate the real (complex) date picker.
//
// We also copy whatever the page passed in as the picker's "initial
// state" onto a data attribute on the button. That way the "restore on
// load" test can read what the page handed to the picker - the page
// should be passing the user's previously-saved date range, if there
// was one.
vi.mock('../../../components/DateRangeController', () => ({
  DateRangeController: ({
    onDateRangeChange,
    initialState,
  }: {
    onDateRangeChange: (from: string | undefined, to: string | undefined) => void;
    initialState?: unknown;
  }) => (
    <button
      data-testid="set-date-range"
      data-initial-state={initialState ? JSON.stringify(initialState) : ''}
      onClick={() => onDateRangeChange('2026-05-01', '2026-05-31')}
    >
      Set range
    </button>
  ),
}));

// Same idea for the "display group" filter: replace the real multi-select
// dropdown with simple buttons.
//   - "Set group" pretends the user picked a specific group.
//   - "Clear group" pretends the user removed their selection (used by
//     the "clearing removes the filter" test).
vi.mock('../../../components/DisplayGroupMultiSelect', () => ({
  DisplayGroupMultiSelect: ({
    onChange,
  }: {
    onChange: (v: { displaySpecificGroupIds: number[]; displayGroupIds: number[] }) => void;
  }) => (
    <>
      <button
        data-testid="set-display-group"
        onClick={() => onChange({ displaySpecificGroupIds: [10], displayGroupIds: [] })}
      >
        Set group
      </button>
      <button
        data-testid="clear-display-group"
        onClick={() => onChange({ displaySpecificGroupIds: [], displayGroupIds: [] })}
      >
        Clear group
      </button>
    </>
  ),
}));

// Replace the filter-inputs panel with a thin stub. The stub records
// whether the panel is "open" (via a data attribute) and gives us two
// buttons - one to simulate the user typing a name filter, and one to
// simulate clicking Reset. We just want to check that the page wires
// these up correctly; the panel's real innards are not under test here.
vi.mock('@/components/ui/FilterInputs', () => ({
  default: ({
    isOpen,
    onChange,
    onReset,
  }: {
    isOpen: boolean;
    onChange: (name: string, value: unknown) => void;
    onReset: () => void;
  }) => (
    <div data-testid="filter-inputs" data-is-open={String(isOpen)}>
      <button data-testid="filter-set-name" onClick={() => onChange('name', 'foo')}>
        Set name
      </button>
      <button data-testid="filter-reset" onClick={onReset}>
        Reset
      </button>
    </div>
  ),
}));

vi.mock('../../../components/EventCalendar', () => ({
  EventCalendar: () => <div data-testid="event-calendar" />,
}));
vi.mock('@/components/ui/modals/ScheduleEventModal', () => ({ default: () => null }));
vi.mock('../../../components/DeleteEventModal', () => ({ default: () => null }));
vi.mock('../../../components/CopyEventModal', () => ({ default: () => null }));
vi.mock('@/components/ui/modals/Modal');
vi.mock('@/components/ui/table/DataTableRowActions', () => ({ default: () => null }));

// =============================================================================
// Helpers
// =============================================================================

// The Events page asks for data twice every time it renders: once for the
// table (10 rows per page) and once for the calendar view (500 rows per
// page). Our filter and pagination tests only care about the table's
// request, so we filter for the calls where pageSize is 10.
type TableCall = {
  pagination: { pageIndex: number; pageSize: number };
  advancedFilters: Record<string, unknown>;
};

const lastTableCall = (): TableCall => {
  const calls = vi.mocked(useEventData).mock.calls as unknown as TableCall[][];
  const tableCalls = calls.filter((c) => c[0]?.pagination.pageSize === 10);
  const last = tableCalls[tableCalls.length - 1];
  if (!last) {
    throw new Error('Expected at least one useEventData call with pageSize=10');
  }
  return last[0]!;
};

const lastFilters = () => lastTableCall().advancedFilters;
const lastPagination = () => lastTableCall().pagination;

// Pretend the API returned 25 events. With 10 events per page, that's
// three pages of results - which is enough for the table to actually
// show a "Next" button (something we need for the pagination tests).
const PAGINATED_EVENTS = {
  rows: Array.from({ length: 10 }, (_, i) => ({
    ...mockEvent,
    eventId: 1000 + i,
    name: `Event ${i + 1}`,
  })),
  totalCount: 25,
};

// =============================================================================
// Tests
// =============================================================================

describe('Events page - filters', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    vi.mocked(useEventActions).mockReturnValue(defaultEventActions());
    vi.mocked(fetchUserPreference).mockResolvedValue(null);
    mockEventData(PAGINATED_EVENTS);
  });

  // ---------------------------------------------------------------------------
  // The collapsible filter panel - it should start closed and toggle when
  // the user clicks the "Filters" button.
  // ---------------------------------------------------------------------------
  describe('filter panel', () => {
    // The filter panel should start closed when the page first loads.
    test('the filter panel is closed when the page first loads', async () => {
      await act(async () => {
        renderEventsPage();
      });

      expect(screen.getByTestId('filter-inputs')).toHaveAttribute('data-is-open', 'false');
    });

    // Clicking the "Filters" button should open the panel. Clicking it a
    // second time should close it again.
    //
    // Bumped timeout to match the sibling pagination-reset tests in this
    // file: when the whole Schedule test suite runs in parallel the JSDOM
    // worker is heavily loaded and a couple of `act` cycles can exceed the
    // 5s default. The test runs in ~1s in isolation.
    test('clicking the Filters button opens the panel, clicking again closes it', async () => {
      await act(async () => {
        renderEventsPage();
      });

      await act(async () => {
        fireEvent.click(screen.getByRole('button', { name: 'Filters' }));
      });
      expect(screen.getByTestId('filter-inputs')).toHaveAttribute('data-is-open', 'true');

      await act(async () => {
        fireEvent.click(screen.getByRole('button', { name: 'Filters' }));
      });
      expect(screen.getByTestId('filter-inputs')).toHaveAttribute('data-is-open', 'false');
    }, 20_000);
  });

  // ---------------------------------------------------------------------------
  // The date-range filter - picking a range should narrow the results to
  // events inside those dates.
  // ---------------------------------------------------------------------------
  describe('date range', () => {
    // After the user picks a date range, the page should re-fetch events
    // using those dates as the from/to filter.
    test('picking a date range re-fetches events using the new from/to dates', async () => {
      await act(async () => {
        renderEventsPage();
      });

      await act(async () => {
        fireEvent.click(screen.getByTestId('set-date-range'));
      });

      await waitFor(() => {
        expect(lastFilters()).toMatchObject({ fromDt: '2026-05-01', toDt: '2026-05-31' });
      });
    });

    // If the user picked a date range in a previous session, the page
    // should remember it and show it again next time. We pretend the
    // saved range is already sitting in the cache (this is what would
    // normally have been written there in an earlier session), then
    // load the page and check that the date picker received that saved
    // range.
    test('if the user had a saved date range from a previous session, the page restores it on load', async () => {
      const savedRange = { from: '2026-05-01', to: '2026-05-31', mode: 'custom' };
      testQueryClient.setQueryData(['userPref', 'event_page_date_range'], savedRange);

      await act(async () => {
        renderEventsPage();
      });

      // The stub records its `initialState` prop on a data attribute -
      // the saved range should have flowed straight through to the picker.
      const dateRangeStub = screen.getByTestId('set-date-range');
      const restored = JSON.parse(dateRangeStub.getAttribute('data-initial-state') ?? 'null');
      expect(restored).toEqual(savedRange);
    });

    // If the user is on page 2 of results and then changes the date range,
    // the page should jump back to the first page of the new results -
    // otherwise they might be stuck on a page that no longer exists.
    // (We give this test a longer timeout because it can be slow when many
    // test files are running at once.)
    test('changing the date range jumps back to page 1', async () => {
      await act(async () => {
        renderEventsPage();
      });

      await act(async () => {
        fireEvent.click(await screen.findByRole('button', { name: /Next/i }));
      });
      await waitFor(
        () => {
          expect(lastPagination()).toMatchObject({ pageIndex: 1 });
        },
        { timeout: 10_000 },
      );

      await act(async () => {
        fireEvent.click(screen.getByTestId('set-date-range'));
      });

      await waitFor(
        () => {
          expect(lastPagination()).toMatchObject({ pageIndex: 0 });
        },
        { timeout: 10_000 },
      );
    }, 20_000);
  });

  // ---------------------------------------------------------------------------
  // The display-group filter - picking a group should narrow the results
  // to events for that group.
  // ---------------------------------------------------------------------------
  describe('display group filter', () => {
    // After the user picks a display group, the page should re-fetch
    // events filtered to that group's ID.
    test('picking a display group re-fetches events filtered to that group', async () => {
      await act(async () => {
        renderEventsPage();
      });

      await act(async () => {
        fireEvent.click(screen.getByTestId('set-display-group'));
      });

      await waitFor(() => {
        expect(lastFilters()).toMatchObject({ displaySpecificGroupIds: [10] });
      });
    });

    // After the user clears their display-group selection (e.g. by
    // unticking everything), the filter should be removed entirely so
    // all events show again - it shouldn't stay locked to whatever was
    // previously selected.
    test('clearing the display group selection removes the filter so all events show again', async () => {
      await act(async () => {
        renderEventsPage();
      });

      // Pick a group first so we have something to clear.
      await act(async () => {
        fireEvent.click(screen.getByTestId('set-display-group'));
      });
      await waitFor(() => {
        expect(lastFilters()).toMatchObject({ displaySpecificGroupIds: [10] });
      });

      // Now clear the selection - the stub fires the page's onChange
      // handler with empty arrays, which the page should turn into "no
      // filter".
      await act(async () => {
        fireEvent.click(screen.getByTestId('clear-display-group'));
      });

      // Both display-group filters should be back to null (i.e. no
      // filter applied), so the next data fetch is unfiltered.
      await waitFor(() => {
        const f = lastFilters();
        expect(f.displaySpecificGroupIds).toBeNull();
        expect(f.displayGroupIds).toBeNull();
      });
    });

    // Same idea as the date-range test: if the user is on page 2 and then
    // picks a display group, the page should jump back to page 1 of the
    // new filtered results.
    test('picking a display group jumps back to page 1', async () => {
      await act(async () => {
        renderEventsPage();
      });

      await act(async () => {
        fireEvent.click(await screen.findByRole('button', { name: /Next/i }));
      });
      await waitFor(
        () => {
          expect(lastPagination()).toMatchObject({ pageIndex: 1 });
        },
        { timeout: 10_000 },
      );

      await act(async () => {
        fireEvent.click(screen.getByTestId('set-display-group'));
      });

      await waitFor(
        () => {
          expect(lastPagination()).toMatchObject({ pageIndex: 0 });
        },
        { timeout: 10_000 },
      );
    }, 20_000);
  });

  // ---------------------------------------------------------------------------
  // The filter panel's inputs (like the name filter) - typing in any of
  // them should reset the table to page 1.
  // ---------------------------------------------------------------------------
  describe('filter input changes', () => {
    // If the user changes any filter (e.g. types in the name field), the
    // table should jump back to page 1 so they don't end up on a page
    // that no longer exists.
    test('typing in a filter field jumps the table back to page 1', async () => {
      await act(async () => {
        renderEventsPage();
      });

      await act(async () => {
        fireEvent.click(await screen.findByRole('button', { name: /Next/i }));
      });
      await waitFor(
        () => {
          expect(lastPagination()).toMatchObject({ pageIndex: 1 });
        },
        { timeout: 10_000 },
      );

      // We need to open the filter panel before we can interact with its
      // fields. Then we click our stand-in "Set name" button to pretend the
      // user typed something into the name filter.
      await act(async () => {
        fireEvent.click(screen.getByRole('button', { name: 'Filters' }));
      });
      await act(async () => {
        fireEvent.click(screen.getByTestId('filter-set-name'));
      });

      await waitFor(
        () => {
          expect(lastFilters()).toMatchObject({ name: 'foo' });
          expect(lastPagination()).toMatchObject({ pageIndex: 0 });
        },
        { timeout: 10_000 },
      );
    }, 20_000);
  });

  // ---------------------------------------------------------------------------
  // The Reset button - clicking it should clear all the filter fields
  // EXCEPT the date range (the user usually wants to keep their date
  // window when resetting other filters).
  // ---------------------------------------------------------------------------
  describe('reset', () => {
    // Set up a date range AND a name filter, then click Reset. The name
    // filter should be cleared, but the date range should remain.
    test('clicking Reset clears the other filters but keeps the date range', async () => {
      await act(async () => {
        renderEventsPage();
      });

      // First, pick a date range so it is recorded as part of the filters.
      await act(async () => {
        fireEvent.click(screen.getByTestId('set-date-range'));
      });
      // Then add a name filter (open the filter panel and click our
      // stand-in "Set name" button).
      await act(async () => {
        fireEvent.click(screen.getByRole('button', { name: 'Filters' }));
      });
      await act(async () => {
        fireEvent.click(screen.getByTestId('filter-set-name'));
      });

      await waitFor(() => {
        expect(lastFilters()).toMatchObject({
          fromDt: '2026-05-01',
          toDt: '2026-05-31',
          name: 'foo',
        });
      });

      // Now click Reset.
      await act(async () => {
        fireEvent.click(screen.getByTestId('filter-reset'));
      });

      await waitFor(() => {
        const f = lastFilters();
        expect(f.fromDt).toBe('2026-05-01');
        expect(f.toDt).toBe('2026-05-31');
        // The name filter should no longer be 'foo'. After reset it might
        // be null or undefined - we don't care which, as long as it is
        // cleared.
        expect(f.name).not.toBe('foo');
      });
    });
  });
});
