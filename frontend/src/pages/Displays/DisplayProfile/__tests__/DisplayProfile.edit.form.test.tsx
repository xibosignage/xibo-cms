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
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import type React from 'react';
import { vi, beforeEach, describe, test, expect } from 'vitest';

import DisplayProfileBaseForm from './DisplayProfileBaseForm.test-helper';
import { mockDisplayProfile } from './displayProfileTestUtils';

import { fetchDisplayProfileById, updateDisplayProfile } from '@/services/displayProfileApi';
import { testQueryClient } from '@/setupTests';

// =============================================================================
// Module mocks
// =============================================================================

vi.mock('react-i18next', () => ({
  useTranslation: () => ({ t: (key: string) => key, i18n: { changeLanguage: vi.fn() } }),
  Trans: ({ children }: { children: React.ReactNode }) => children,
}));

vi.mock('@/services/displayProfileApi');
vi.mock('@/components/ui/modals/Modal');

// =============================================================================
// Fixtures
// =============================================================================

const mockDisplayProfileFull = {
  ...mockDisplayProfile,
  config: [],
  configDefault: [],
};

// =============================================================================
// Helpers
// =============================================================================

const renderEditModal = (
  overrides: Partial<React.ComponentProps<typeof DisplayProfileBaseForm>> = {},
) => {
  const defaults = {
    isOpen: true,
    data: mockDisplayProfileFull,
    onClose: vi.fn(),
    onSave: vi.fn(),
  };
  return render(
    <QueryClientProvider client={testQueryClient}>
      <DisplayProfileBaseForm {...defaults} {...overrides} />
    </QueryClientProvider>,
  );
};

// Wait for the form to finish loading (past the "Loading settings…" spinner).
const openForm = async () => {
  await screen.findByPlaceholderText('Enter name');
};

// =============================================================================
// Tests
// =============================================================================

describe('DisplayProfile - edit form fields', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    vi.mocked(fetchDisplayProfileById).mockResolvedValue(mockDisplayProfileFull);
  });

  // ---------------------------------------------------------------------------
  // Name field - editable text input connected to the draft via onChange.
  // ---------------------------------------------------------------------------
  test('Name field is editable and reflects typed input', async () => {
    renderEditModal();
    await openForm();

    const nameInput = screen.getByPlaceholderText('Enter name');
    fireEvent.change(nameInput, { target: { value: 'Updated Profile' } });

    expect(nameInput).toHaveValue('Updated Profile');
  });

  // ---------------------------------------------------------------------------
  // Clearing the name and clicking Save shows "Name is required" and blocks
  // the API call entirely.
  // ---------------------------------------------------------------------------
  test('Save with empty name shows validation error and does not call updateDisplayProfile', async () => {
    renderEditModal();
    await openForm();

    fireEvent.change(screen.getByPlaceholderText('Enter name'), { target: { value: '' } });
    fireEvent.click(screen.getByRole('button', { name: 'Save' }));

    expect(await screen.findByText('Name is required')).toBeInTheDocument();
    expect(updateDisplayProfile).not.toHaveBeenCalled();
  });

  // ---------------------------------------------------------------------------
  // Default Profile checkbox - toggling updates the draft.isDefault value.
  // ---------------------------------------------------------------------------
  test('Default Profile checkbox toggles on and off', async () => {
    renderEditModal();
    await openForm();

    const checkbox = screen.getByRole('checkbox', { name: 'Default Profile?' });
    expect(checkbox).toBeChecked(); // mockDisplayProfile.isDefault === 1

    fireEvent.click(checkbox);
    expect(checkbox).not.toBeChecked();

    fireEvent.click(checkbox);
    expect(checkbox).toBeChecked();
  });

  // ---------------------------------------------------------------------------
  // Clicking Save sends the form data to the API and calls onClose.
  // ---------------------------------------------------------------------------
  test('Successful save calls updateDisplayProfile with correct payload and calls onClose', async () => {
    vi.mocked(updateDisplayProfile).mockResolvedValueOnce(mockDisplayProfile);
    const onClose = vi.fn();

    renderEditModal({ onClose });
    await openForm();

    fireEvent.click(screen.getByRole('button', { name: 'Save' }));

    await waitFor(() => {
      expect(onClose).toHaveBeenCalledTimes(1);
    });

    expect(updateDisplayProfile).toHaveBeenCalledWith(mockDisplayProfile.displayProfileId, {
      name: mockDisplayProfile.name,
      isDefault: mockDisplayProfile.isDefault,
      config: {},
    });
  });

  // ---------------------------------------------------------------------------
  // Failed save - API error keeps the modal open so the user can retry.
  // ---------------------------------------------------------------------------
  test('Failed save keeps the modal open and shows the error', async () => {
    vi.mocked(updateDisplayProfile).mockRejectedValueOnce({
      response: { data: { message: 'Display profile name already exists' } },
    });

    renderEditModal();
    await openForm();

    fireEvent.click(screen.getByRole('button', { name: 'Save' }));

    await waitFor(() => {
      expect(screen.getByRole('dialog')).toBeInTheDocument();
    });
    expect(await screen.findByText('Display profile name already exists')).toBeInTheDocument();
  });
});
