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

// =============================================================================
// Test type: Component isolation — CopyDisplayModal
// Renders the modal directly with props. No full page or API calls needed.
// =============================================================================

import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type React from 'react';
import { vi, beforeEach, describe, test, expect } from 'vitest';

import { mockDisplay } from '../fixtures/display';
import CopyDisplayModal from '../../components/CopyDisplayModal';

import { testQueryClient } from '@/setupTests';

// =============================================================================
// Module mocks
// =============================================================================

vi.mock('react-i18next', () => {
  const t = (key: string) => key;
  return {
    useTranslation: () => ({ t, i18n: { changeLanguage: vi.fn() } }),
    Trans: ({ children }: { children: React.ReactNode }) => children,
  };
});

vi.mock('@/components/ui/modals/Modal');

// =============================================================================
// Tests
// =============================================================================

describe('CopyDisplayModal', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
  });

  // ---------------------------------------------------------------------------
  // The name field is pre-populated with an incremented version of the
  // display's current name so the user has a sensible starting point.
  // mockDisplay.display = 'Test Display' → incrementName → 'Test Display (1)'
  // ---------------------------------------------------------------------------
  test('pre-populates the name field with an incremented display name', () => {
    render(
      <CopyDisplayModal
        display={mockDisplay}
        onClose={vi.fn()}
        onConfirm={vi.fn()}
        existingNames={[]}
      />,
    );

    expect(screen.getByRole('textbox', { name: /new name/i })).toHaveValue('Test Display (1)');
  });

  // ---------------------------------------------------------------------------
  // Clearing the name field and clicking Save must show a validation error
  // rather than calling onConfirm — an empty name is not valid.
  // ---------------------------------------------------------------------------
  test('shows "Name is required" error when name is cleared and Save clicked', async () => {
    const onConfirm = vi.fn();
    const user = userEvent.setup();

    render(
      <CopyDisplayModal
        display={mockDisplay}
        onClose={vi.fn()}
        onConfirm={onConfirm}
        existingNames={[]}
      />,
    );

    await user.clear(screen.getByRole('textbox', { name: /new name/i }));
    await user.click(screen.getByRole('button', { name: /^save$/i }));

    expect(screen.getByText('Name is required')).toBeVisible();
    expect(onConfirm).not.toHaveBeenCalled();
  });

  // ---------------------------------------------------------------------------
  // Typing a name that already exists in existingNames must show a validation
  // error — duplicate display names are not allowed.
  // ---------------------------------------------------------------------------
  test('shows duplicate name error when name already exists', async () => {
    const onConfirm = vi.fn();
    const user = userEvent.setup();

    render(
      <CopyDisplayModal
        display={mockDisplay}
        onClose={vi.fn()}
        onConfirm={onConfirm}
        existingNames={['Lobby Screen']}
      />,
    );

    const input = screen.getByRole('textbox', { name: /new name/i });
    await user.clear(input);
    await user.type(input, 'Lobby Screen');
    await user.click(screen.getByRole('button', { name: /^save$/i }));

    expect(screen.getByText('A display with this name already exists')).toBeVisible();
    expect(onConfirm).not.toHaveBeenCalled();
  });

  // ---------------------------------------------------------------------------
  // Duplicate name check is case-insensitive — "lobby screen" matches
  // "Lobby Screen" in existingNames.
  // ---------------------------------------------------------------------------
  test('duplicate name check is case-insensitive', async () => {
    const onConfirm = vi.fn();
    const user = userEvent.setup();

    render(
      <CopyDisplayModal
        display={mockDisplay}
        onClose={vi.fn()}
        onConfirm={onConfirm}
        existingNames={['Lobby Screen']}
      />,
    );

    const input = screen.getByRole('textbox', { name: /new name/i });
    await user.clear(input);
    await user.type(input, 'lobby screen');
    await user.click(screen.getByRole('button', { name: /^save$/i }));

    expect(screen.getByText('A display with this name already exists')).toBeVisible();
    expect(onConfirm).not.toHaveBeenCalled();
  });

  // ---------------------------------------------------------------------------
  // A valid, unique name must call onConfirm with the trimmed value so the
  // caller can dispatch the copy API call.
  // ---------------------------------------------------------------------------
  test('clicking Save with a valid name calls onConfirm with the name', async () => {
    const onConfirm = vi.fn();
    const user = userEvent.setup();

    render(
      <CopyDisplayModal
        display={mockDisplay}
        onClose={vi.fn()}
        onConfirm={onConfirm}
        existingNames={[]}
      />,
    );

    await user.click(screen.getByRole('button', { name: /^save$/i }));

    expect(onConfirm).toHaveBeenCalledWith('Test Display (1)');
  });

  // ---------------------------------------------------------------------------
  // Cancel must call onClose so the modal can be dismissed without saving.
  // ---------------------------------------------------------------------------
  test('clicking Cancel calls onClose', async () => {
    const onClose = vi.fn();
    const user = userEvent.setup();

    render(
      <CopyDisplayModal
        display={mockDisplay}
        onClose={onClose}
        onConfirm={vi.fn()}
        existingNames={[]}
      />,
    );

    await user.click(screen.getByRole('button', { name: /^cancel$/i }));

    expect(onClose).toHaveBeenCalledOnce();
  });

  // ---------------------------------------------------------------------------
  // While the copy is in-flight the Save button must be disabled and show
  // "Saving…" so users cannot submit twice.
  // ---------------------------------------------------------------------------
  test('Save button is disabled and shows "Saving…" when isLoading is true', () => {
    render(
      <CopyDisplayModal
        display={mockDisplay}
        onClose={vi.fn()}
        onConfirm={vi.fn()}
        existingNames={[]}
        isLoading
      />,
    );

    expect(screen.getByRole('button', { name: /saving/i })).toBeDisabled();
  });
});
