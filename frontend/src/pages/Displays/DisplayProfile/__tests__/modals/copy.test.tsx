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

import { screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { vi, beforeEach, describe, test, expect } from 'vitest';

import { renderCopyDisplayProfileModal } from './helpers/renderCopyDisplayProfileModal';

import { testQueryClient } from '@/setupTests';

// =============================================================================
// Module mocks
// =============================================================================

vi.mock('@/components/ui/modals/Modal');

// =============================================================================
// Tests — CopyDisplayProfileModal
// =============================================================================

describe('CopyDisplayProfileModal', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
  });

  test('the modal opens with the title "Copy Display Profile"', () => {
    renderCopyDisplayProfileModal();

    expect(screen.getByRole('dialog', { name: /copy display profile/i })).toBeInTheDocument();
  });

  test('the New name is pre-filled with an incremented copy of the source name', () => {
    renderCopyDisplayProfileModal();

    // incrementName('Android Profile') => 'Android Profile (1)'
    expect(screen.getByRole('textbox', { name: /new name/i })).toHaveValue('Android Profile (1)');
  });

  test('saving with an empty name shows "Name is required"', async () => {
    const user = userEvent.setup();
    const { onConfirm } = renderCopyDisplayProfileModal();

    await user.clear(screen.getByRole('textbox', { name: /new name/i }));
    await user.click(screen.getByRole('button', { name: /^save$/i }));

    expect(await screen.findByText('Name is required')).toBeInTheDocument();
    expect(onConfirm).not.toHaveBeenCalled();
  });

  test('saving a whitespace-only name shows "Name is required"', async () => {
    const user = userEvent.setup();
    const { onConfirm } = renderCopyDisplayProfileModal();

    const input = screen.getByRole('textbox', { name: /new name/i });
    await user.clear(input);
    await user.type(input, '   ');
    await user.click(screen.getByRole('button', { name: /^save$/i }));

    // The modal trims before validating, so whitespace-only is treated as empty.
    expect(await screen.findByText('Name is required')).toBeInTheDocument();
    expect(onConfirm).not.toHaveBeenCalled();
  });

  test('saving a duplicate name shows the already-exists error', async () => {
    const user = userEvent.setup();
    // The pre-filled name itself is already taken (case-insensitive match).
    const { onConfirm } = renderCopyDisplayProfileModal({
      existingNames: ['android profile (1)'],
    });

    await user.click(screen.getByRole('button', { name: /^save$/i }));

    expect(
      await screen.findByText('A display profile with this name already exists'),
    ).toBeInTheDocument();
    expect(onConfirm).not.toHaveBeenCalled();
  });

  test('a successful save confirms with the trimmed new name', async () => {
    const user = userEvent.setup();
    const { onConfirm } = renderCopyDisplayProfileModal();

    const input = screen.getByRole('textbox', { name: /new name/i });
    await user.clear(input);
    await user.type(input, 'Fresh Copy');
    await user.click(screen.getByRole('button', { name: /^save$/i }));

    expect(onConfirm).toHaveBeenCalledWith('Fresh Copy');
  });

  test('clicking Cancel closes the modal without confirming', async () => {
    const user = userEvent.setup();
    const { onClose, onConfirm } = renderCopyDisplayProfileModal();

    await user.click(screen.getByRole('button', { name: /^cancel$/i }));

    expect(onClose).toHaveBeenCalled();
    expect(onConfirm).not.toHaveBeenCalled();
  });

  test('the Save button shows "Saving…" while the copy is in progress', () => {
    renderCopyDisplayProfileModal({ isLoading: true });

    expect(screen.getByRole('button', { name: /saving/i })).toBeDisabled();
  });
});
