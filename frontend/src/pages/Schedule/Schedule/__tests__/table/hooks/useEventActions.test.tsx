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

import { renderHook, act } from '@testing-library/react';
import type { TFunction } from 'i18next';
import { describe, test, expect, vi, beforeEach } from 'vitest';

import { useEventActions } from '../../../hooks/useEventActions';
import { mockEvent } from '../../fixtures/event';

// ─── Mocks ────────────────────────────────────────────────────────────────────

// Replace the real API calls with fakes we can spy on.
const mockCloneEvent = vi.fn();
const mockDeleteEvent = vi.fn();
const mockDeleteEventOccurrence = vi.fn();
vi.mock('@/services/eventApi', () => ({
  cloneEvent: (...args: unknown[]) => mockCloneEvent(...args),
  deleteEvent: (...args: unknown[]) => mockDeleteEvent(...args),
  deleteEventOccurrence: (...args: unknown[]) => mockDeleteEventOccurrence(...args),
}));

// Replace the toast notification system with fakes we can assert against.
const mockNotifySuccess = vi.fn();
const mockNotifyError = vi.fn();
vi.mock('@/components/ui/Notification', () => ({
  notify: {
    success: (...args: unknown[]) => mockNotifySuccess(...args),
    error: (...args: unknown[]) => mockNotifyError(...args),
    info: vi.fn(),
    warning: vi.fn(),
  },
}));

// =============================================================================
// Tests
// =============================================================================

describe('useEventActions', () => {
  // Bypass i18next's strict typing so a plain echo function is accepted as t().
  const mockT = ((str: string) => str) as unknown as TFunction;
  const mockHandleRefresh = vi.fn();
  const mockCloseModal = vi.fn();
  const mockSetRowSelection = vi.fn();

  // Helper so each test doesn't have to repeat the same renderHook boilerplate.
  const setupHook = () =>
    renderHook(() =>
      useEventActions({
        t: mockT,
        handleRefresh: mockHandleRefresh,
        closeModal: mockCloseModal,
        setRowSelection: mockSetRowSelection,
      }),
    );

  beforeEach(() => {
    vi.clearAllMocks();
  });

  // ---------------------------------------------------------------------------
  // What the hook looks like the moment it's first set up.
  // ---------------------------------------------------------------------------

  // Before the user does anything, no actions are in flight and no errors
  // have happened yet.
  test('starts off in a clean state - nothing in progress and no errors', () => {
    const { result } = setupHook();

    expect(result.current.isDeleting).toBe(false);
    expect(result.current.isCloning).toBe(false);
    expect(result.current.deleteError).toBeNull();
  });

  // ---------------------------------------------------------------------------
  // The "make a copy" flow - this is the scenario that was missing.
  // ---------------------------------------------------------------------------

  describe('handleConfirmClone', () => {
    // Safety guard: if the page somehow calls clone with no event selected,
    // the hook should do nothing rather than blow up.
    test('does nothing when the user somehow tries to copy without an event selected', async () => {
      const { result } = setupHook();

      await act(async () => {
        await result.current.handleConfirmClone(null, 'Anything');
      });

      expect(mockCloneEvent).not.toHaveBeenCalled();
      expect(mockHandleRefresh).not.toHaveBeenCalled();
      expect(mockCloseModal).not.toHaveBeenCalled();
    });

    // The happy path: API succeeds, table refreshes, modal closes,
    // and the user sees a green success toast.
    test('when the copy succeeds: the event is copied, a green success toast appears, the table refreshes, and the modal closes', async () => {
      mockCloneEvent.mockResolvedValue({});
      const { result } = setupHook();

      await act(async () => {
        await result.current.handleConfirmClone(mockEvent, 'Morning Promo (1)');
      });

      expect(mockCloneEvent).toHaveBeenCalledWith({
        eventId: mockEvent.eventId,
        name: 'Morning Promo (1)',
      });
      expect(mockNotifySuccess).toHaveBeenCalledWith('Event copied successfully');
      expect(mockHandleRefresh).toHaveBeenCalledTimes(1);
      expect(mockCloseModal).toHaveBeenCalledTimes(1);
      expect(mockNotifyError).not.toHaveBeenCalled();
    });

    // The unhappy path: if the API fails, the user sees a red error toast
    // and the modal stays open so they can try again. The table is NOT
    // refreshed because nothing actually changed on the server.
    test('when the copy fails: a red error toast appears, the table does not refresh, and the modal stays open so the user can retry', async () => {
      mockCloneEvent.mockRejectedValue(new Error('Network Error'));
      const { result } = setupHook();

      await act(async () => {
        await result.current.handleConfirmClone(mockEvent, 'Morning Promo (1)');
      });

      expect(mockNotifyError).toHaveBeenCalledWith('Failed to copy event');
      expect(mockHandleRefresh).not.toHaveBeenCalled();
      expect(mockCloseModal).not.toHaveBeenCalled();
      expect(mockNotifySuccess).not.toHaveBeenCalled();
    });

    // After either a success or a failure, the "is currently copying" flag
    // must end up back at false so the Save button isn't stuck in its
    // "Saving…" state.
    test('after a successful copy, the "is currently copying" flag is reset to false', async () => {
      mockCloneEvent.mockResolvedValue({});
      const { result } = setupHook();

      await act(async () => {
        await result.current.handleConfirmClone(mockEvent, 'Morning Promo (1)');
      });

      expect(result.current.isCloning).toBe(false);
    });

    test('after a failed copy, the "is currently copying" flag is also reset to false (so Save is not stuck)', async () => {
      mockCloneEvent.mockRejectedValue(new Error('Network Error'));
      const { result } = setupHook();

      await act(async () => {
        await result.current.handleConfirmClone(mockEvent, 'Morning Promo (1)');
      });

      expect(result.current.isCloning).toBe(false);
    });
  });

  // ---------------------------------------------------------------------------
  // The "delete one or more events" flow. This is the path used by both the
  // per-row Delete and the bulk Delete Selected actions - the page just
  // hands over a list of one (single delete) or many (bulk delete) events.
  // ---------------------------------------------------------------------------

  describe('confirmDelete', () => {
    // Safety guard: if the page somehow asks to delete an empty list, the
    // hook should quietly do nothing rather than fire a useless API call.
    test('does nothing when the list of events to delete is empty', async () => {
      const { result } = setupHook();

      await act(async () => {
        await result.current.confirmDelete([]);
      });

      expect(mockDeleteEvent).not.toHaveBeenCalled();
      expect(mockHandleRefresh).not.toHaveBeenCalled();
      expect(mockCloseModal).not.toHaveBeenCalled();
    });

    // The happy path for a single event: the API is called once, the row
    // selection gets cleared, the table refreshes so the user sees the
    // deletion, and the modal closes.
    test('when deleting a single event succeeds: calls the delete API, clears the row selection, refreshes the table, and closes the modal', async () => {
      mockDeleteEvent.mockResolvedValue({});
      const { result } = setupHook();

      await act(async () => {
        await result.current.confirmDelete([mockEvent]);
      });

      expect(mockDeleteEvent).toHaveBeenCalledTimes(1);
      expect(mockDeleteEvent).toHaveBeenCalledWith(mockEvent.eventId);
      expect(mockSetRowSelection).toHaveBeenCalledWith({});
      expect(mockHandleRefresh).toHaveBeenCalledTimes(1);
      expect(mockCloseModal).toHaveBeenCalledTimes(1);
      expect(result.current.deleteError).toBeNull();
    });

    // The happy path for a bulk delete: one API call is fired for every
    // selected event, then the selection clears, the table refreshes, and
    // the modal closes.
    test('when deleting multiple events succeeds: fires one API call per event, clears the row selection, refreshes the table, and closes the modal', async () => {
      mockDeleteEvent.mockResolvedValue({});
      const secondEvent = { ...mockEvent, eventId: 1002, name: 'Lunch Promo' };
      const { result } = setupHook();

      await act(async () => {
        await result.current.confirmDelete([mockEvent, secondEvent]);
      });

      expect(mockDeleteEvent).toHaveBeenCalledTimes(2);
      expect(mockDeleteEvent).toHaveBeenNthCalledWith(1, mockEvent.eventId);
      expect(mockDeleteEvent).toHaveBeenNthCalledWith(2, secondEvent.eventId);
      expect(mockSetRowSelection).toHaveBeenCalledWith({});
      expect(mockHandleRefresh).toHaveBeenCalledTimes(1);
      expect(mockCloseModal).toHaveBeenCalledTimes(1);
    });

    // The unhappy path: if the API rejects, the user should see an error
    // message and the modal should stay open so they can read it. We DO
    // still clear the selection and refresh the table though, because in
    // a bulk delete some of the deletes may have actually succeeded -
    // refreshing keeps the table in sync with reality.
    test('when a delete fails: shows an error message, refreshes the table to reflect any partial success, but keeps the modal open so the user sees the error', async () => {
      mockDeleteEvent.mockRejectedValue(new Error('Network Error'));
      const { result } = setupHook();

      await act(async () => {
        await result.current.confirmDelete([mockEvent]);
      });

      expect(result.current.deleteError).toBeTruthy();
      expect(mockSetRowSelection).toHaveBeenCalledWith({});
      expect(mockHandleRefresh).toHaveBeenCalledTimes(1);
      expect(mockCloseModal).not.toHaveBeenCalled();
    });

    // After a successful delete the "is currently deleting" flag must
    // reset to false so the confirm button isn't stuck in its loading
    // state.
    test('after a successful delete, the "is currently deleting" flag is reset to false', async () => {
      mockDeleteEvent.mockResolvedValue({});
      const { result } = setupHook();

      await act(async () => {
        await result.current.confirmDelete([mockEvent]);
      });

      expect(result.current.isDeleting).toBe(false);
    });

    // After a failed delete the flag must also reset, otherwise the user
    // would be stuck looking at "Deleting…" with no way to retry.
    test('after a failed delete, the "is currently deleting" flag is also reset to false (so the confirm button is not stuck)', async () => {
      mockDeleteEvent.mockRejectedValue(new Error('Network Error'));
      const { result } = setupHook();

      await act(async () => {
        await result.current.confirmDelete([mockEvent]);
      });

      expect(result.current.isDeleting).toBe(false);
    });
  });

  // ---------------------------------------------------------------------------
  // The "delete just this one occurrence of a recurring event" flow. Used
  // when the user picks "This instance only" in the recurring delete modal.
  // ---------------------------------------------------------------------------

  describe('confirmDeleteOccurrence', () => {
    // The happy path: the API is called with the event id and the
    // occurrence's date range (so the server knows which instance to
    // remove), then the table refreshes and the modal closes.
    test('on success: calls the API with the event id and the date range, refreshes the table, and closes the modal', async () => {
      mockDeleteEventOccurrence.mockResolvedValue({});
      const { result } = setupHook();

      await act(async () => {
        await result.current.confirmDeleteOccurrence(mockEvent);
      });

      expect(mockDeleteEventOccurrence).toHaveBeenCalledWith(
        mockEvent.eventId,
        mockEvent.fromDt,
        mockEvent.toDt,
      );
      expect(mockHandleRefresh).toHaveBeenCalledTimes(1);
      expect(mockCloseModal).toHaveBeenCalledTimes(1);
      expect(result.current.deleteError).toBeNull();
    });

    // The unhappy path: if the API fails, the user gets an error message,
    // the table is NOT refreshed (nothing actually changed on the server),
    // and the modal stays open so the user can retry or read the error.
    test('on failure: shows an error message, does not refresh the table, and keeps the modal open', async () => {
      mockDeleteEventOccurrence.mockRejectedValue(new Error('Network Error'));
      const { result } = setupHook();

      await act(async () => {
        await result.current.confirmDeleteOccurrence(mockEvent);
      });

      expect(result.current.deleteError).toBeTruthy();
      expect(mockHandleRefresh).not.toHaveBeenCalled();
      expect(mockCloseModal).not.toHaveBeenCalled();
    });

    // After either a success or a failure the "is currently deleting"
    // flag must reset to false so the confirm button isn't stuck.
    test('after a successful occurrence delete, the "is currently deleting" flag is reset to false', async () => {
      mockDeleteEventOccurrence.mockResolvedValue({});
      const { result } = setupHook();

      await act(async () => {
        await result.current.confirmDeleteOccurrence(mockEvent);
      });

      expect(result.current.isDeleting).toBe(false);
    });

    test('after a failed occurrence delete, the "is currently deleting" flag is also reset to false', async () => {
      mockDeleteEventOccurrence.mockRejectedValue(new Error('Network Error'));
      const { result } = setupHook();

      await act(async () => {
        await result.current.confirmDeleteOccurrence(mockEvent);
      });

      expect(result.current.isDeleting).toBe(false);
    });
  });
});
