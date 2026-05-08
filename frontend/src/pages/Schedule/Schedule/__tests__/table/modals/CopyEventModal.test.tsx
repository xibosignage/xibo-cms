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

import CopyEventModal from '../../../components/CopyEventModal';
import { mockEvent } from '../../fixtures/event';

// ─── Mocks ────────────────────────────────────────────────────────────────────

vi.mock('react-i18next', () => ({
  useTranslation: () => ({ t: (key: string) => key, i18n: { changeLanguage: vi.fn() } }),
  Trans: ({ children }: { children: React.ReactNode }) => children,
}));

// Replace the real Modal with a fake one for testing. The fake uses the
// modal's title ("Copy Event") as its accessibility label, which is how the
// tests below find the dialog on screen.
vi.mock('@/components/ui/modals/Modal');

// =============================================================================
// Tests
// =============================================================================

describe('CopyEventModal', () => {
  const onClose = vi.fn();
  const onConfirm = vi.fn();

  beforeEach(() => {
    vi.clearAllMocks();
  });

  // ---------------------------------------------------------------------------
  // What the modal looks like when it first opens.
  // ---------------------------------------------------------------------------

  // When you copy an event called "Morning Promo", the name field should
  // already be filled in with "Morning Promo (1)" so you don't have to type
  // a new name from scratch.
  test('pre-fills the name field with the event name plus " (1)"', () => {
    render(
      <CopyEventModal
        onClose={onClose}
        onConfirm={onConfirm}
        scheduleEvent={mockEvent}
        existingNames={[]}
      />,
    );

    const dialog = screen.getByRole('dialog', { name: 'Copy Event' });
    expect(within(dialog).getByLabelText('New name')).toHaveValue('Morning Promo (1)');
  });

  // ---------------------------------------------------------------------------
  // What happens if the user leaves the name blank.
  // ---------------------------------------------------------------------------

  // If the user clears the name (or only types spaces) and clicks Save, the
  // modal should show an error and refuse to save.
  test('shows "Name is required" when the name is empty', () => {
    render(
      <CopyEventModal
        onClose={onClose}
        onConfirm={onConfirm}
        scheduleEvent={mockEvent}
        existingNames={[]}
      />,
    );

    const dialog = screen.getByRole('dialog', { name: 'Copy Event' });
    fireEvent.change(within(dialog).getByLabelText('New name'), { target: { value: '   ' } });
    fireEvent.click(within(dialog).getByRole('button', { name: 'Save' }));

    expect(within(dialog).getByText('Name is required')).toBeInTheDocument();
    expect(onConfirm).not.toHaveBeenCalled();
  });

  // ---------------------------------------------------------------------------
  // What happens if the user picks a name that's already taken.
  // ---------------------------------------------------------------------------

  // Capital letters don't matter when checking for duplicates. If "Morning
  // Promo" already exists, typing "morning promo" should still be treated
  // as a duplicate, show an error, and refuse to save.
  test('will not save a name that already exists, even with different capital letters', () => {
    render(
      <CopyEventModal
        onClose={onClose}
        onConfirm={onConfirm}
        scheduleEvent={null}
        existingNames={['Morning Promo']}
      />,
    );

    const dialog = screen.getByRole('dialog', { name: 'Copy Event' });
    fireEvent.change(within(dialog).getByLabelText('New name'), {
      target: { value: 'morning promo' },
    });
    fireEvent.click(within(dialog).getByRole('button', { name: 'Save' }));

    expect(
      within(dialog).getByText('An event item with this name already exists'),
    ).toBeInTheDocument();
    expect(onConfirm).not.toHaveBeenCalled();
  });

  // After the user sees the "name already exists" error, they can fix it
  // just by editing the name. The error message should disappear as soon
  // as they start typing again.
  test('the duplicate-name error goes away as soon as the user starts typing again', () => {
    render(
      <CopyEventModal
        onClose={onClose}
        onConfirm={onConfirm}
        scheduleEvent={null}
        existingNames={['Morning Promo']}
      />,
    );

    const input = screen.getByLabelText('New name');
    fireEvent.change(input, { target: { value: 'Morning Promo' } });
    fireEvent.click(screen.getByRole('button', { name: 'Save' }));

    expect(screen.getByText('An event item with this name already exists')).toBeInTheDocument();

    fireEvent.change(input, { target: { value: 'Morning Promo (1)' } });

    expect(
      screen.queryByText('An event item with this name already exists'),
    ).not.toBeInTheDocument();
  });

  // ---------------------------------------------------------------------------
  // The normal "everything works" case.
  // ---------------------------------------------------------------------------

  // When the user enters a valid name and clicks Save, any extra spaces at
  // the start or end of the name are removed before the copy is created.
  test('Save trims any extra spaces from the name before saving', () => {
    render(
      <CopyEventModal
        onClose={onClose}
        onConfirm={onConfirm}
        scheduleEvent={null}
        existingNames={['Morning Promo']}
      />,
    );

    fireEvent.change(screen.getByLabelText('New name'), {
      target: { value: '  Morning Promo Copy  ' },
    });
    fireEvent.click(screen.getByRole('button', { name: 'Save' }));

    expect(onConfirm).toHaveBeenCalledWith('Morning Promo Copy');
  });

  // ---------------------------------------------------------------------------
  // What happens when the user cancels, and what the modal looks like
  // while it's saving.
  // ---------------------------------------------------------------------------

  // Clicking Cancel should just close the modal without copying anything.
  test('clicking Cancel just closes the modal without copying anything', () => {
    render(
      <CopyEventModal
        onClose={onClose}
        onConfirm={onConfirm}
        scheduleEvent={mockEvent}
        existingNames={[]}
      />,
    );

    fireEvent.click(screen.getByRole('button', { name: 'Cancel' }));

    expect(onClose).toHaveBeenCalledTimes(1);
    expect(onConfirm).not.toHaveBeenCalled();
  });

  // While the copy is being saved, both buttons are greyed out and the
  // Save button changes to "Saving…" so the user can see something is
  // happening and can't accidentally click Save twice.
  test('while the copy is being saved, the buttons are greyed out and Save shows "Saving…"', () => {
    render(
      <CopyEventModal
        onClose={onClose}
        onConfirm={onConfirm}
        scheduleEvent={mockEvent}
        existingNames={[]}
        isLoading
      />,
    );

    expect(screen.getByRole('button', { name: 'Saving…' })).toBeDisabled();
    expect(screen.getByRole('button', { name: 'Cancel' })).toBeDisabled();
  });

  // If the modal is told it's closed, it should show nothing on screen.
  test('shows nothing on screen when the modal is told it is closed', () => {
    render(
      <CopyEventModal
        isOpen={false}
        onClose={onClose}
        onConfirm={onConfirm}
        scheduleEvent={mockEvent}
        existingNames={[]}
      />,
    );

    expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
  });
});
