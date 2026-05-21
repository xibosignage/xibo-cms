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

import { render, screen, fireEvent, within } from '@testing-library/react';
import type React from 'react';
import { vi, beforeEach, describe, test, expect } from 'vitest';

import DeleteEventModal from '../../../components/DeleteEventModal';

// ─── Mocks ────────────────────────────────────────────────────────────────────

vi.mock('react-i18next', () => ({
  useTranslation: () => ({ t: (key: string) => key, i18n: { changeLanguage: vi.fn() } }),
  Trans: ({ children }: { children: React.ReactNode }) => children,
}));

// Swap the real Modal wrapper for a simple stand-in. The stand-in just dumps
// the modal's contents and buttons into the page as plain HTML so the tests
// can find them with normal queries. The stand-in marks itself as a "dialog"
// so the tests can grab it with screen.getByRole('dialog').
vi.mock('@/components/ui/modals/Modal');

// =============================================================================
// Tests
// =============================================================================

describe('DeleteEventModal', () => {
  const onClose = vi.fn();
  const onDelete = vi.fn();
  const onDeleteOccurrence = vi.fn();

  beforeEach(() => {
    vi.clearAllMocks();
  });

  // ---------------------------------------------------------------------------
  // Deleting a single, one-off event (it doesn't repeat).
  // ---------------------------------------------------------------------------
  describe('single non-recurring event', () => {
    // The modal should show the event's name in the title, ask the user to
    // confirm, and NOT show any radio buttons (there's nothing to choose
    // between - it's just one event). Pressing confirm should run the
    // normal delete.
    test('shows the event name and a single confirmation message', () => {
      render(
        <DeleteEventModal
          onClose={onClose}
          onDelete={onDelete}
          itemCount={1}
          eventName="Morning Promo"
          isRecurring={false}
        />,
      );

      const dialog = screen.getByRole('dialog');
      // The heading should read something like: Delete "Morning Promo"? - and
      // the body should also mention the event name. We match the heading
      // loosely (just "Delete...Morning Promo") because the real title wraps
      // the name in fancy curly quotes that are awkward to type out exactly.
      expect(within(dialog).getByRole('heading', { level: 2 })).toHaveTextContent(
        /Delete.*Morning Promo/,
      );
      expect(within(dialog).getByText(/Are you sure you want to delete/)).toBeInTheDocument();
      // A one-off event has no recurrence options, so no radio buttons.
      expect(within(dialog).queryByRole('radio')).not.toBeInTheDocument();
    });

    // Clicking "Yes, delete" should call the delete function we passed in,
    // and ONLY that one - the per-occurrence delete should not be touched.
    test('clicking Yes, delete calls onDelete', () => {
      render(
        <DeleteEventModal
          onClose={onClose}
          onDelete={onDelete}
          itemCount={1}
          eventName="Morning Promo"
          isRecurring={false}
        />,
      );

      fireEvent.click(screen.getByRole('button', { name: 'Yes, delete' }));

      expect(onDelete).toHaveBeenCalledTimes(1);
      expect(onDeleteOccurrence).not.toHaveBeenCalled();
    });

    // Clicking "Cancel" should just close the modal - nothing should be
    // deleted.
    test('clicking Cancel calls onClose', () => {
      render(
        <DeleteEventModal
          onClose={onClose}
          onDelete={onDelete}
          itemCount={1}
          eventName="Morning Promo"
          isRecurring={false}
        />,
      );

      fireEvent.click(screen.getByRole('button', { name: 'Cancel' }));

      expect(onClose).toHaveBeenCalledTimes(1);
      expect(onDelete).not.toHaveBeenCalled();
    });

    // If no event name was given, the modal should fall back to the generic
    // phrase "this event" rather than showing a blank space.
    test('shows "this event" fallback when no eventName is provided', () => {
      render(
        <DeleteEventModal
          onClose={onClose}
          onDelete={onDelete}
          itemCount={1}
          isRecurring={false}
        />,
      );

      expect(screen.getAllByText('this event').length).toBeGreaterThan(0);
    });
  });

  // ---------------------------------------------------------------------------
  // Deleting a single event that repeats (e.g. weekly). Here the user gets
  // two radio buttons and the safer choice (delete just this one occurrence)
  // is picked by default.
  // ---------------------------------------------------------------------------
  describe('single recurring event', () => {
    // The user should see two options: "This instance only" (already
    // selected) and "The entire recurring event".
    test('shows both delete-this-occurrence and delete-series radios', () => {
      render(
        <DeleteEventModal
          onClose={onClose}
          onDelete={onDelete}
          onDeleteOccurrence={onDeleteOccurrence}
          itemCount={1}
          eventName="Weekly Special"
          isRecurring
        />,
      );

      const dialog = screen.getByRole('dialog');
      const radios = within(dialog).getAllByRole('radio');
      expect(radios).toHaveLength(2);
      expect(within(dialog).getByLabelText('This instance only')).toBeChecked();
      expect(within(dialog).getByLabelText('The entire recurring event')).not.toBeChecked();
    });

    // Because "This instance only" is selected by default, clicking confirm
    // without changing anything should delete just this single occurrence -
    // not the whole repeating series.
    test('confirming with default scope calls onDeleteOccurrence (not onDelete)', () => {
      render(
        <DeleteEventModal
          onClose={onClose}
          onDelete={onDelete}
          onDeleteOccurrence={onDeleteOccurrence}
          itemCount={1}
          eventName="Weekly Special"
          isRecurring
        />,
      );

      fireEvent.click(screen.getByRole('button', { name: 'Yes, delete' }));

      expect(onDeleteOccurrence).toHaveBeenCalledTimes(1);
      expect(onDelete).not.toHaveBeenCalled();
    });

    // If the user switches to "The entire recurring event" and then
    // confirms, the whole series should be deleted (the regular delete
    // handler runs - not the per-occurrence one).
    test('selecting the series radio and confirming calls onDelete', () => {
      render(
        <DeleteEventModal
          onClose={onClose}
          onDelete={onDelete}
          onDeleteOccurrence={onDeleteOccurrence}
          itemCount={1}
          eventName="Weekly Special"
          isRecurring
        />,
      );

      fireEvent.click(screen.getByLabelText('The entire recurring event'));
      fireEvent.click(screen.getByRole('button', { name: 'Yes, delete' }));

      expect(onDelete).toHaveBeenCalledTimes(1);
      expect(onDeleteOccurrence).not.toHaveBeenCalled();
    });

    // Safety net: if the parent page forgot to wire up an
    // "onDeleteOccurrence" handler, the radio buttons should NOT appear -
    // even though the event is recurring. We don't want to show the user a
    // choice that wouldn't actually do anything if picked.
    test('does not show radios when onDeleteOccurrence is not provided', () => {
      render(
        <DeleteEventModal
          onClose={onClose}
          onDelete={onDelete}
          itemCount={1}
          eventName="Weekly Special"
          isRecurring
        />,
      );

      expect(screen.queryByRole('radio')).not.toBeInTheDocument();
    });
  });

  // ---------------------------------------------------------------------------
  // Deleting multiple events at once (the user has selected several rows in
  // the schedule list). There's no single event name to show, and there are
  // no per-occurrence options.
  // ---------------------------------------------------------------------------
  describe('bulk delete', () => {
    // The wording should switch to plural ("Delete Events?") and the modal
    // should show how many events are about to be deleted.
    test('shows the bulk confirmation message with the item count', () => {
      render(
        <DeleteEventModal
          onClose={onClose}
          onDelete={onDelete}
          onDeleteOccurrence={onDeleteOccurrence}
          itemCount={3}
          isRecurring
        />,
      );

      const dialog = screen.getByRole('dialog');
      expect(within(dialog).getByText('Delete Events?')).toBeInTheDocument();
      expect(within(dialog).getByText('3')).toBeInTheDocument();
    });

    // Even if "isRecurring" happens to be true during a bulk delete, the
    // radio buttons should not appear. When many events are selected, there
    // isn't one specific occurrence to target, so the choice wouldn't make
    // sense.
    test('does not show the occurrence radio for a bulk delete', () => {
      render(
        <DeleteEventModal
          onClose={onClose}
          onDelete={onDelete}
          onDeleteOccurrence={onDeleteOccurrence}
          itemCount={3}
          isRecurring
        />,
      );

      expect(screen.queryByRole('radio')).not.toBeInTheDocument();
    });

    // For a bulk delete, clicking confirm should always trigger the regular
    // delete handler. The per-occurrence handler is never used in this mode.
    test('confirming calls onDelete', () => {
      render(
        <DeleteEventModal
          onClose={onClose}
          onDelete={onDelete}
          onDeleteOccurrence={onDeleteOccurrence}
          itemCount={3}
          isRecurring
        />,
      );

      fireEvent.click(screen.getByRole('button', { name: 'Yes, delete' }));

      expect(onDelete).toHaveBeenCalledTimes(1);
      expect(onDeleteOccurrence).not.toHaveBeenCalled();
    });
  });

  // ---------------------------------------------------------------------------
  // What the modal does while the delete is in flight, or when it fails.
  // ---------------------------------------------------------------------------
  describe('loading and error states', () => {
    // While the delete request is being processed, the confirm button should
    // change its label to "Deleting…" and become unclickable. This stops the
    // user from clicking it again and accidentally sending a second request.
    test('confirm button is disabled and shows "Deleting…" while isLoading', () => {
      render(
        <DeleteEventModal
          onClose={onClose}
          onDelete={onDelete}
          itemCount={1}
          eventName="Morning Promo"
          isLoading
        />,
      );

      const btn = screen.getByRole('button', { name: 'Deleting…' });
      expect(btn).toBeDisabled();
    });

    // If the delete fails and the parent passes an error message in, that
    // message should appear inside the modal so the user can see what went
    // wrong (rather than the modal just closing silently).
    test('renders the error message inside the dialog', () => {
      render(
        <DeleteEventModal
          onClose={onClose}
          onDelete={onDelete}
          itemCount={1}
          eventName="Morning Promo"
          error="Event is in use"
        />,
      );

      expect(within(screen.getByRole('dialog')).getByText('Event is in use')).toBeInTheDocument();
    });
  });
});
