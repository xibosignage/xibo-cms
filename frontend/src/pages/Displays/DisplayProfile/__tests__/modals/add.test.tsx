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
import { render, screen, waitFor, fireEvent } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { useState } from 'react';
import { vi, beforeEach, describe, test, expect } from 'vitest';

import { buildDisplayProfile, mockUser } from '../fixtures/displayProfile';

import { renderAddDisplayProfileModal } from './helpers/renderAddDisplayProfileModal';

import { UserProvider } from '@/context/UserContext';
import AddDisplayProfileModal from '@/pages/Displays/DisplayProfile/components/AddDisplayProfileModal';
import { createDisplayProfile } from '@/services/displayProfileApi';
import { testQueryClient } from '@/setupTests';
import { waitForClose } from '@/testUtils/rtl';

// =============================================================================
// Module mocks
// =============================================================================

vi.mock('@/components/ui/modals/Modal');

vi.mock('@/services/displayProfileApi', () => ({
  createDisplayProfile: vi.fn(),
}));

vi.mock('@/components/ui/forms/SelectDropdown', () => ({
  default: ({
    label,
    value,
    options,
    onSelect,
    error,
  }: {
    label?: string;
    value?: string;
    options?: Array<{ value: string; label: string }>;
    onSelect?: (value: string) => void;
    error?: string;
  }) => (
    <>
      <select aria-label={label} value={value ?? ''} onChange={(e) => onSelect?.(e.target.value)}>
        <option value="" />
        {options?.map((o) => (
          <option key={o.value} value={o.value}>
            {o.label}
          </option>
        ))}
      </select>
      {error && <span>{error}</span>}
    </>
  ),
}));

// =============================================================================
// Tests — AddDisplayProfileModal
// =============================================================================

describe('AddDisplayProfileModal', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
  });

  test('the modal opens with the title "Add Display Profile"', () => {
    renderAddDisplayProfileModal();

    expect(screen.getByRole('dialog', { name: /add display profile/i })).toBeInTheDocument();
  });

  test('the Name, Display Type, and Default Profile fields are present', () => {
    renderAddDisplayProfileModal();

    expect(screen.getByRole('textbox', { name: /^name$/i })).toBeInTheDocument();
    expect(screen.getByRole('combobox', { name: /display type/i })).toBeInTheDocument();
    expect(screen.getByRole('checkbox', { name: /default profile/i })).toBeInTheDocument();
  });

  test('the default values are empty Display Type and unchecked Default Profile', () => {
    renderAddDisplayProfileModal();

    expect(screen.getByRole('combobox', { name: /display type/i })).toHaveValue('');
    expect(screen.getByRole('checkbox', { name: /default profile/i })).not.toBeChecked();
  });

  test('saving with an empty Name shows a validation error', async () => {
    const user = userEvent.setup();
    renderAddDisplayProfileModal();

    // Pick a valid type so only the Name branch fails.
    fireEvent.change(screen.getByRole('combobox', { name: /display type/i }), {
      target: { value: 'android' },
    });
    await user.click(screen.getByRole('button', { name: /^save$/i }));

    expect(await screen.findByText('Name is required')).toBeInTheDocument();
    expect(createDisplayProfile).not.toHaveBeenCalled();
  });

  test('saving with no Display Type shows a validation error', async () => {
    const user = userEvent.setup();
    renderAddDisplayProfileModal();

    await user.type(screen.getByRole('textbox', { name: /^name$/i }), 'My Profile');
    await user.click(screen.getByRole('button', { name: /^save$/i }));

    expect(await screen.findByText('Display type is required')).toBeInTheDocument();
    expect(createDisplayProfile).not.toHaveBeenCalled();
  });

  test('a successful save sends the entered values to the API', async () => {
    const user = userEvent.setup();
    vi.mocked(createDisplayProfile).mockResolvedValue(buildDisplayProfile());
    const { onSave, onClose } = renderAddDisplayProfileModal();

    await user.type(screen.getByRole('textbox', { name: /^name$/i }), 'My Profile');
    fireEvent.change(screen.getByRole('combobox', { name: /display type/i }), {
      target: { value: 'android' },
    });
    await user.click(screen.getByRole('button', { name: /^save$/i }));

    await waitFor(() => {
      expect(createDisplayProfile).toHaveBeenCalledWith({
        name: 'My Profile',
        type: 'android',
        isDefault: 0,
      });
    });
    expect(onSave).toHaveBeenCalled();
    expect(onClose).toHaveBeenCalled();
  });

  test('clicking Cancel closes the modal without saving', async () => {
    const user = userEvent.setup();
    const { onClose } = renderAddDisplayProfileModal();

    await user.click(screen.getByRole('button', { name: /^cancel$/i }));

    expect(onClose).toHaveBeenCalled();
    expect(createDisplayProfile).not.toHaveBeenCalled();
  });

  test('the Save button shows "Saving…" while the request is in progress', async () => {
    const user = userEvent.setup();
    let resolveCreate: (value: ReturnType<typeof buildDisplayProfile>) => void = () => {};
    vi.mocked(createDisplayProfile).mockReturnValueOnce(
      new Promise((resolve) => {
        resolveCreate = resolve;
      }),
    );
    const { onClose } = renderAddDisplayProfileModal();

    await user.type(screen.getByRole('textbox', { name: /^name$/i }), 'My Profile');
    fireEvent.change(screen.getByRole('combobox', { name: /display type/i }), {
      target: { value: 'android' },
    });
    await user.click(screen.getByRole('button', { name: /^save$/i }));

    expect(await screen.findByRole('button', { name: /saving/i })).toBeDisabled();

    // Wait for the resulting close so the update isn't left outside act().
    resolveCreate(buildDisplayProfile());
    await waitForClose(onClose);
  });

  test('an API error is displayed in the modal', async () => {
    const user = userEvent.setup();
    vi.mocked(createDisplayProfile).mockRejectedValueOnce(
      new Error('Display profile name already exists'),
    );
    renderAddDisplayProfileModal();

    await user.type(screen.getByRole('textbox', { name: /^name$/i }), 'My Profile');
    fireEvent.change(screen.getByRole('combobox', { name: /display type/i }), {
      target: { value: 'android' },
    });
    await user.click(screen.getByRole('button', { name: /^save$/i }));

    expect(await screen.findByRole('alert')).toHaveTextContent(
      'Display profile name already exists',
    );
  });

  test('re-opening the modal resets the fields to their defaults', async () => {
    const user = userEvent.setup();

    // A tiny harness lets us toggle isOpen so the modal can be closed and re-opened.
    function Harness() {
      const [open, setOpen] = useState(true);
      return (
        <>
          <button onClick={() => setOpen((o) => !o)}>toggle</button>
          <AddDisplayProfileModal isOpen={open} onClose={vi.fn()} onSave={vi.fn()} />
        </>
      );
    }

    render(
      <QueryClientProvider client={testQueryClient}>
        <UserProvider initialUser={mockUser}>
          <Harness />
        </UserProvider>
      </QueryClientProvider>,
    );

    // Type a value, then close and re-open the modal.
    await user.type(screen.getByRole('textbox', { name: /^name$/i }), 'Temporary');
    await user.click(screen.getByRole('button', { name: /^toggle$/i })); // close
    await user.click(screen.getByRole('button', { name: /^toggle$/i })); // re-open

    // The Name field is blank again (the draft is reset on open).
    expect(screen.getByRole('textbox', { name: /^name$/i })).toHaveValue('');
  });
});
