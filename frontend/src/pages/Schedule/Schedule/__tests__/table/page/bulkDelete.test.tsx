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

import { screen, fireEvent, act } from '@testing-library/react';
import type React from 'react';
import { vi, beforeEach, describe, test, expect } from 'vitest';

import { useEventActions } from '../../../hooks/useEventActions';
import { TWO_EVENTS, mockEvent, mockEvent2 } from '../../fixtures/event';
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

vi.mock('../../../components/DateRangeController', () => ({
  DateRangeController: () => <div data-testid="date-range" />,
}));
vi.mock('../../../components/DisplayGroupMultiSelect', () => ({
  DisplayGroupMultiSelect: () => <div data-testid="display-group-select" />,
}));
vi.mock('../../../components/EventCalendar', () => ({
  EventCalendar: () => <div data-testid="event-calendar" />,
}));

// Replace the real Delete modal with a tiny placeholder. The placeholder
// records how many events were passed in (so the tests can check the
// count) and provides a single "Confirm" button to fire the delete. The
// real modal's full UI is tested in DeleteEventModal.test.tsx - we don't
// repeat that here.
vi.mock('../../../components/DeleteEventModal', () => ({
  default: ({ itemCount, onDelete }: { itemCount: number; onDelete: () => void }) => (
    <div data-testid="delete-modal" data-item-count={itemCount}>
      <button data-testid="confirm-delete" onClick={onDelete}>
        Confirm
      </button>
    </div>
  ),
}));
vi.mock('../../../components/CopyEventModal', () => ({ default: () => null }));
vi.mock('@/components/ui/modals/ScheduleEventModal', () => ({ default: () => null }));
vi.mock('@/components/ui/modals/Modal');
vi.mock('@/components/ui/table/DataTableRowActions', () => ({ default: () => null }));

// =============================================================================
// Tests
// =============================================================================

describe('Events page - bulk delete', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    vi.mocked(useEventActions).mockReturnValue(defaultEventActions());
    vi.mocked(fetchUserPreference).mockResolvedValue(null);
    mockEventData(TWO_EVENTS);
  });

  // ---------------------------------------------------------------------------
  // The bulk-delete button only appears once the user ticks one or more
  // checkboxes in the table.
  // ---------------------------------------------------------------------------

  // If no rows are ticked, the bulk-action toolbar (and its "Delete
  // Selected" button) should not be on screen.
  test('the "Delete Selected" button is hidden until the user ticks at least one row', async () => {
    await act(async () => {
      renderEventsPage();
    });

    expect(screen.queryByRole('button', { name: 'Delete Selected' })).not.toBeInTheDocument();
  });

  // Once the user ticks at least one row, the bulk-action toolbar should
  // appear with the "Delete Selected" button on it. (The button is an
  // icon-only button - we find it by its tooltip/title.)
  test('the "Delete Selected" button appears once a row is ticked', async () => {
    await act(async () => {
      renderEventsPage();
    });

    // Wait for the table row to actually appear before we look for its
    // checkbox - otherwise the checkboxes won't exist yet.
    await screen.findByText('Morning Promo');
    const checkboxes = screen.getAllByRole('checkbox', { name: /Select row/i });
    await act(async () => {
      // The first checkbox in the list is the "select all" one in the
      // table header. The second one is the first event row.
      fireEvent.click(checkboxes[1]!);
    });

    // The toolbar's "Delete Selected" button is an icon button - we look
    // for it by the tooltip text the user would see on hover.
    expect(await screen.findByTitle('Delete Selected')).toBeInTheDocument();
  });

  // ---------------------------------------------------------------------------
  // The full bulk-delete flow: tick rows, click "Delete Selected", confirm.
  // ---------------------------------------------------------------------------

  // Tick all rows on the page (in this test, two events) and click "Delete
  // Selected". The Delete modal should open and show that 2 events are
  // about to be deleted.
  test('selecting two rows and clicking "Delete Selected" opens the modal showing 2 events', async () => {
    await act(async () => {
      renderEventsPage();
    });

    await screen.findByText('Morning Promo');
    const checkboxes = screen.getAllByRole('checkbox', { name: /Select row/i });
    // The first checkbox is the "select all" one in the table header -
    // clicking it ticks every row on the current page in one go (both
    // events in this test).
    await act(async () => {
      fireEvent.click(checkboxes[0]!);
    });

    await act(async () => {
      fireEvent.click(await screen.findByTitle('Delete Selected'));
    });

    const modal = await screen.findByTestId('delete-modal');
    expect(modal).toHaveAttribute('data-item-count', '2');
  });

  // After confirming the bulk delete, all of the ticked events should be
  // sent off for deletion in a single call.
  test('confirming the bulk delete sends all ticked events for deletion at once', async () => {
    const confirmDelete = vi.fn();
    vi.mocked(useEventActions).mockReturnValue(defaultEventActions({ confirmDelete }));

    await act(async () => {
      renderEventsPage();
    });

    await screen.findByText('Morning Promo');
    const checkboxes = screen.getAllByRole('checkbox', { name: /Select row/i });
    // Click the "select all" header checkbox so both events get ticked
    // in one go.
    await act(async () => {
      fireEvent.click(checkboxes[0]!);
    });
    await act(async () => {
      fireEvent.click(await screen.findByTitle('Delete Selected'));
    });
    await act(async () => {
      fireEvent.click(screen.getByTestId('confirm-delete'));
    });

    expect(confirmDelete).toHaveBeenCalledTimes(1);
    // We compare event IDs rather than the full event objects because the
    // table can stash the row data in slightly different shapes - the IDs
    // are the part we actually care about.
    const passedIds = (confirmDelete.mock.calls[0]![0] as Array<{ eventId: number }>).map(
      (e) => e.eventId,
    );
    expect(passedIds).toEqual(expect.arrayContaining([mockEvent.eventId, mockEvent2.eventId]));
    expect(passedIds).toHaveLength(2);
  });
});
