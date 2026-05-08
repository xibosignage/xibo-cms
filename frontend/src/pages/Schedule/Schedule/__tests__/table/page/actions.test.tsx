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
import { SINGLE_EVENT, TWO_EVENTS, mockEvent } from '../../fixtures/event';
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

// EventModals decides which child modal (Schedule, Delete, or Copy) to show.
// We replace each child with a tiny placeholder <div> here so the tests can
// just check WHICH modal opened and what info it received - what those
// modals actually do is covered by their own dedicated test files.
vi.mock('@/components/ui/modals/ScheduleEventModal', () => ({
  default: vi.fn(({ mode, event }: { mode?: string; event?: unknown }) => (
    <div data-testid="schedule-modal" data-mode={mode ?? 'add'} data-has-event={!!event} />
  )),
}));
vi.mock('../../../components/DeleteEventModal', () => ({
  default: vi.fn(({ itemCount, eventName }: { itemCount: number; eventName?: string }) => (
    <div data-testid="delete-modal" data-item-count={itemCount} data-event-name={eventName ?? ''} />
  )),
}));
vi.mock('../../../components/CopyEventModal', () => ({
  default: vi.fn(({ scheduleEvent }: { scheduleEvent: { eventId: number } | null }) => (
    <div data-testid="copy-modal" data-event-id={scheduleEvent?.eventId ?? ''} />
  )),
}));

vi.mock('@/components/ui/modals/Modal');

// Replace the row-actions dropdown with simple plain buttons. The real
// dropdown uses a popup library that doesn't open properly in the test
// environment, so we render every action straight onto the page as a button
// the tests can click directly.
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

describe('Events page - actions', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    vi.mocked(useEventActions).mockReturnValue(defaultEventActions());
    vi.mocked(fetchUserPreference).mockResolvedValue(null);
  });

  // ---------------------------------------------------------------------------
  // Add Event button
  // ---------------------------------------------------------------------------
  describe('Add Event button', () => {
    // While the user's saved preferences are still loading, the Add Event
    // button stays disabled. We don't want the user creating new events
    // before their saved settings are applied, otherwise the event might
    // be created with the wrong defaults.
    test('the Add Event button is greyed out while saved preferences are still loading', () => {
      vi.mocked(fetchUserPreference).mockImplementation(() => new Promise(() => {}));
      mockEventData(SINGLE_EVENT);

      renderEventsPage(undefined, { hydrate: false });

      expect(screen.getByRole('button', { name: /Add Event/ })).toBeDisabled();
    });

    // Once the saved preferences have finished loading, the Add Event
    // button becomes clickable again.
    test('the Add Event button becomes clickable once preferences have loaded', async () => {
      mockEventData(SINGLE_EVENT);

      await act(async () => {
        renderEventsPage();
      });

      expect(screen.getByRole('button', { name: /Add Event/ })).toBeEnabled();
    });

    // Clicking "Add Event" should open the Schedule Event modal blank,
    // ready to add a brand-new event (no existing event passed in, no
    // "edit" flag).
    test('clicking Add Event opens the Schedule modal blank, ready to create a new event', async () => {
      mockEventData(SINGLE_EVENT);

      await act(async () => {
        renderEventsPage();
      });

      await act(async () => {
        fireEvent.click(screen.getByRole('button', { name: /Add Event/ }));
      });

      const modal = screen.getByTestId('schedule-modal');
      expect(modal).toHaveAttribute('data-mode', 'add');
      expect(modal).toHaveAttribute('data-has-event', 'false');
    });
  });

  // ---------------------------------------------------------------------------
  // Per-row actions (Edit / Make a Copy / Delete) - what should happen when
  // the user picks one from a row in the table.
  // ---------------------------------------------------------------------------
  describe('row actions', () => {
    // Clicking Edit on a row should open the Schedule modal in "edit" mode
    // and pass that row's event in so its fields can be pre-filled.
    test('clicking Edit on a row opens the Schedule modal pre-filled with that event', async () => {
      mockEventData(SINGLE_EVENT);

      await act(async () => {
        renderEventsPage();
      });

      await screen.findByText('Morning Promo');
      // The page actually shows two "Edit" buttons per row - one as a quick
      // shortcut and another inside the actions dropdown. We just click the
      // first one we find.
      await act(async () => {
        fireEvent.click(screen.getAllByRole('button', { name: 'Edit' })[0]!);
      });

      const modal = screen.getByTestId('schedule-modal');
      expect(modal).toHaveAttribute('data-mode', 'edit');
      expect(modal).toHaveAttribute('data-has-event', 'true');
    });

    // Clicking "Make a Copy" on a row should open the Copy modal with that
    // row's event passed in - the Copy modal needs it so it can suggest a
    // name like "Morning Promo (1)".
    test('clicking Make a Copy opens the Copy modal with the chosen event', async () => {
      mockEventData(SINGLE_EVENT);

      await act(async () => {
        renderEventsPage();
      });

      await screen.findByText('Morning Promo');
      await act(async () => {
        fireEvent.click(screen.getAllByRole('button', { name: 'Make a Copy' })[0]!);
      });

      expect(screen.getByTestId('copy-modal')).toHaveAttribute(
        'data-event-id',
        String(mockEvent.eventId),
      );
    });

    // Clicking Delete on a row should open the Delete modal with just that
    // one event listed for deletion. Because only one event is selected,
    // the modal should also show the event's name.
    test('clicking Delete on a row opens the Delete modal for that one event', async () => {
      mockEventData(SINGLE_EVENT);

      await act(async () => {
        renderEventsPage();
      });

      await screen.findByText('Morning Promo');
      await act(async () => {
        fireEvent.click(screen.getByRole('button', { name: 'Delete' }));
      });

      const modal = screen.getByTestId('delete-modal');
      expect(modal).toHaveAttribute('data-item-count', '1');
      expect(modal).toHaveAttribute('data-event-name', 'Morning Promo - Spring Campaign');
    });

    // Even with multiple events showing in the table, clicking Delete on
    // ONE row should only target that single row - not all of the visible
    // events. Bulk delete requires the user to tick checkboxes first.
    test('clicking Delete on one row only targets that row, not the whole table', async () => {
      mockEventData(TWO_EVENTS);

      await act(async () => {
        renderEventsPage();
      });

      await screen.findByText('Morning Promo');
      await screen.findByText('Lunch Promo');
      await act(async () => {
        fireEvent.click(screen.getAllByRole('button', { name: 'Delete' })[0]!);
      });

      expect(screen.getByTestId('delete-modal')).toHaveAttribute('data-item-count', '1');
    });
  });
});
