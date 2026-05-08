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

import { render, screen } from '@testing-library/react';
import { vi, beforeEach, describe, test, expect } from 'vitest';

import CopyEventModal from '../../../components/CopyEventModal';
import DeleteEventModal from '../../../components/DeleteEventModal';
import { EventModals } from '../../../components/EventModals';
import { mockEvent, mockEvent2, mockRecurringEvent } from '../../fixtures/event';

import ScheduleEventModal from '@/components/ui/modals/ScheduleEventModal';
import type { Event } from '@/types/event';

// ─── Mocks ────────────────────────────────────────────────────────────────────

// Replace each child modal with a tiny placeholder <div> that just records
// what props were passed to it. The tests then only need to check WHICH
// child EventModals decides to show and what info it forwarded - we're not
// testing what those child modals actually do inside.
vi.mock('../../../components/DeleteEventModal', () => ({
  default: vi.fn(() => <div data-testid="delete-modal" />),
}));
vi.mock('../../../components/CopyEventModal', () => ({
  default: vi.fn(() => <div data-testid="copy-modal" />),
}));
vi.mock('@/components/ui/modals/ScheduleEventModal', () => ({
  default: vi.fn(({ mode }: { mode?: string }) => (
    <div data-testid="schedule-modal" data-mode={mode ?? 'add'} />
  )),
}));

// A small helper that builds a complete set of props for EventModals using
// safe defaults. Each test can pass in just the bits it cares about and
// everything else stays at the defaults.
type EventModalsProps = Parameters<typeof EventModals>[0];

const buildProps = (overrides: Partial<EventModalsProps> = {}): EventModalsProps => ({
  actions: {
    activeModal: null,
    closeModal: vi.fn(),
    handleRefresh: vi.fn(),
    deleteError: null,
    isDeleting: false,
    isCloning: false,
    agendaDate: null,
    displayGroups: [],
    ...overrides.actions,
  },
  selection: {
    selectedEvent: null,
    selectedEventId: null,
    itemsToDelete: [],
    existingNames: [],
    shareEntityIds: null,
    setShareEntityIds: vi.fn(),
    ...overrides.selection,
  },
  handlers: {
    confirmDelete: vi.fn(),
    confirmDeleteOccurrence: vi.fn(),
    handleConfirmClone: vi.fn(),
    ...overrides.handlers,
  },
});

// =============================================================================
// Tests
// =============================================================================

describe('EventModals - routing', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  // ---------------------------------------------------------------------------
  // When there is no active modal, EventModals should display nothing at all.
  // ---------------------------------------------------------------------------
  test('renders nothing when activeModal is null', () => {
    render(<EventModals {...buildProps()} />);
    expect(screen.queryByTestId('delete-modal')).not.toBeInTheDocument();
    expect(screen.queryByTestId('copy-modal')).not.toBeInTheDocument();
    expect(screen.queryByTestId('schedule-modal')).not.toBeInTheDocument();
  });

  // ---------------------------------------------------------------------------
  // Routing rules for the delete modal: when does EventModals open it, and
  // what info does it hand over?
  // ---------------------------------------------------------------------------
  describe('delete modal', () => {
    // Deleting one regular event: the modal should be told the event's
    // display name (in "name - campaign" format) and that it is not a
    // recurring event.
    test('opens DeleteEventModal with the single event details', () => {
      render(
        <EventModals
          {...buildProps({
            actions: { activeModal: 'delete' } as EventModalsProps['actions'],
            selection: { itemsToDelete: [mockEvent] } as EventModalsProps['selection'],
          })}
        />,
      );

      expect(screen.getByTestId('delete-modal')).toBeInTheDocument();
      expect(DeleteEventModal).toHaveBeenCalledWith(
        expect.objectContaining({
          itemCount: 1,
          eventName: 'Morning Promo - Spring Campaign',
          isRecurring: false,
        }),
        undefined,
      );
    });

    // Deleting one recurring event: EventModals should pass isRecurring=true
    // so the delete modal knows to show the "this instance only / entire
    // series" radio buttons.
    test('opens DeleteEventModal with isRecurring=true for a recurring event', () => {
      render(
        <EventModals
          {...buildProps({
            actions: { activeModal: 'delete' } as EventModalsProps['actions'],
            selection: { itemsToDelete: [mockRecurringEvent] } as EventModalsProps['selection'],
          })}
        />,
      );

      expect(DeleteEventModal).toHaveBeenCalledWith(
        expect.objectContaining({ itemCount: 1, isRecurring: true }),
        undefined,
      );
      // EventModals should also hand over an onDeleteOccurrence function.
      // Without it, the delete modal hides the radio buttons entirely - so
      // we double-check that the function actually got passed through.
      const firstCall = (DeleteEventModal as unknown as ReturnType<typeof vi.fn>).mock.calls[0];
      const props = firstCall![0] as Parameters<typeof DeleteEventModal>[0];
      expect(typeof props.onDeleteOccurrence).toBe('function');
    });

    // Bulk delete (more than one event selected): there's no single event
    // name to show, recurrence options don't apply, and there's no
    // per-occurrence handler to forward.
    test('opens DeleteEventModal in bulk mode when more than one item is selected', () => {
      render(
        <EventModals
          {...buildProps({
            actions: { activeModal: 'delete' } as EventModalsProps['actions'],
            selection: {
              itemsToDelete: [mockEvent, mockEvent2],
            } as EventModalsProps['selection'],
          })}
        />,
      );

      expect(DeleteEventModal).toHaveBeenCalledWith(
        expect.objectContaining({
          itemCount: 2,
          eventName: undefined,
          isRecurring: false,
          onDeleteOccurrence: undefined,
        }),
        undefined,
      );
    });

    // If the server reports a problem with the delete, EventModals should
    // pass that error message and the "in progress" flag through to the
    // modal so the user can see what's happening.
    test('forwards deleteError and isDeleting to the modal', () => {
      render(
        <EventModals
          {...buildProps({
            actions: {
              activeModal: 'delete',
              deleteError: 'Cannot delete',
              isDeleting: true,
            } as EventModalsProps['actions'],
            selection: { itemsToDelete: [mockEvent] } as EventModalsProps['selection'],
          })}
        />,
      );

      expect(DeleteEventModal).toHaveBeenCalledWith(
        expect.objectContaining({ error: 'Cannot delete', isLoading: true }),
        undefined,
      );
    });
  });

  // ---------------------------------------------------------------------------
  // Routing rules for the copy/duplicate modal.
  // ---------------------------------------------------------------------------
  describe('copy modal', () => {
    // EventModals should hand over the event being copied AND the list of
    // names already in use. The copy modal uses these to pre-fill the name
    // field and warn the user if they pick a name that's already taken.
    test('opens CopyEventModal with the selected event and existingNames', () => {
      render(
        <EventModals
          {...buildProps({
            actions: { activeModal: 'copy' } as EventModalsProps['actions'],
            selection: {
              selectedEvent: mockEvent,
              existingNames: ['Morning Promo'],
            } as EventModalsProps['selection'],
          })}
        />,
      );

      expect(screen.getByTestId('copy-modal')).toBeInTheDocument();
      expect(CopyEventModal).toHaveBeenCalledWith(
        expect.objectContaining({
          scheduleEvent: mockEvent,
          existingNames: ['Morning Promo'],
        }),
        undefined,
      );
    });

    // When the user confirms the copy with a chosen name, EventModals
    // should pass that name straight through to the page-level "clone"
    // handler - no extra logic in between.
    test('forwards onConfirm to the page-level handleConfirmClone handler', () => {
      const handleConfirmClone = vi.fn();
      render(
        <EventModals
          {...buildProps({
            actions: { activeModal: 'copy' } as EventModalsProps['actions'],
            selection: { selectedEvent: mockEvent } as EventModalsProps['selection'],
            handlers: { handleConfirmClone } as unknown as EventModalsProps['handlers'],
          })}
        />,
      );

      const firstCall = (CopyEventModal as unknown as ReturnType<typeof vi.fn>).mock.calls[0];
      const props = firstCall![0] as Parameters<typeof CopyEventModal>[0];
      props.onConfirm('My Copy');

      expect(handleConfirmClone).toHaveBeenCalledWith('My Copy');
    });
  });

  // ---------------------------------------------------------------------------
  // Routing rules for the schedule event modal. "schedule" means open it
  // blank to add a new event; "edit" means open it filled in to edit an
  // existing one.
  // ---------------------------------------------------------------------------
  describe('schedule and edit modal', () => {
    // When activeModal is "schedule", the modal should open in add mode -
    // no specific event passed in, no "edit" flag.
    test('opens ScheduleEventModal in add mode for activeModal="schedule"', () => {
      render(
        <EventModals
          {...buildProps({
            actions: { activeModal: 'schedule' } as EventModalsProps['actions'],
          })}
        />,
      );

      expect(screen.getByTestId('schedule-modal')).toHaveAttribute('data-mode', 'add');
      expect(ScheduleEventModal).toHaveBeenCalledWith(
        expect.objectContaining({ isOpen: true }),
        undefined,
      );
      const firstCall = (ScheduleEventModal as unknown as ReturnType<typeof vi.fn>).mock.calls[0];
      const props = firstCall![0] as Parameters<typeof ScheduleEventModal>[0];
      expect((props as { mode?: string }).mode).toBeUndefined();
      expect((props as { event?: Event }).event).toBeUndefined();
    });

    // When activeModal is "edit", the modal should open in edit mode with
    // the currently selected event passed in so its fields can be
    // pre-filled.
    test('opens ScheduleEventModal in edit mode with the selectedEvent', () => {
      render(
        <EventModals
          {...buildProps({
            actions: { activeModal: 'edit' } as EventModalsProps['actions'],
            selection: { selectedEvent: mockEvent } as EventModalsProps['selection'],
          })}
        />,
      );

      expect(screen.getByTestId('schedule-modal')).toHaveAttribute('data-mode', 'edit');
      expect(ScheduleEventModal).toHaveBeenCalledWith(
        expect.objectContaining({ isOpen: true, mode: 'edit', event: mockEvent }),
        undefined,
      );
    });

    // Safety check: if activeModal is "edit" but no event is actually
    // selected, the modal should NOT open. This protects against a timing
    // glitch - for example, the event row disappearing (someone else
    // deleted it) just as the user is trying to open the edit modal.
    test('does not render in edit mode when selectedEvent is null', () => {
      render(
        <EventModals
          {...buildProps({
            actions: { activeModal: 'edit' } as EventModalsProps['actions'],
          })}
        />,
      );

      expect(screen.queryByTestId('schedule-modal')).not.toBeInTheDocument();
    });
  });
});
