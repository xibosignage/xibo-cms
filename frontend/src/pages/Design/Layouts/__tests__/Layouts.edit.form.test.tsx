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

import { screen, fireEvent, waitFor } from '@testing-library/react';
import type React from 'react';
import { vi, beforeEach, describe, test, expect } from 'vitest';

import { useLayoutActions } from '../hooks/useLayoutActions';

import {
  SINGLE_LAYOUT,
  defaultLayoutActions,
  mockLayout,
  mockLayoutData,
  openEditModal,
  renderLayoutsPage,
} from './layoutTestUtils';

import { updateLayout } from '@/services/layoutsApi';
import { testQueryClient } from '@/setupTests';

// -----------------------------------------------------------------------------
// Module mocks
// -----------------------------------------------------------------------------

// 3rd-party
vi.mock('react-i18next', () => ({
  useTranslation: () => ({ t: (key: string) => key, i18n: { changeLanguage: vi.fn() } }),
  Trans: ({ children }: { children: React.ReactNode }) => children,
}));

// Services
vi.mock('@/services/folderApi');
vi.mock('@/services/layoutsApi');
vi.mock('@/services/userApi', () => ({
  fetchUserPreference: vi.fn().mockResolvedValue(null),
  saveUserPreference: vi.fn().mockResolvedValue(undefined),
}));

// Hooks
vi.mock('../hooks/useLayoutActions', () => ({ useLayoutActions: vi.fn() }));
vi.mock('../hooks/useLayoutData', () => ({ useLayoutData: vi.fn() }));
vi.mock('../hooks/useLayoutFilterOptions', () => ({
  useLayoutFilterOptions: vi.fn(() => ({ filterOptions: [], isLoading: false })),
}));
vi.mock('@/hooks/useOwner', () => ({
  useOwner: vi.fn().mockReturnValue({ owner: null, loading: false }),
}));

// UI
vi.mock('@/components/ui/FolderActionModals', () => ({ default: () => null }));
vi.mock('@/components/ui/forms/SelectFolder', () => ({ default: () => null }));
vi.mock('@/components/ui/modals/Modal');

// =============================================================================
// Tests
// =============================================================================

describe('Layouts page - edit form fields', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    vi.mocked(useLayoutActions).mockReturnValue(defaultLayoutActions());
    mockLayoutData(SINGLE_LAYOUT);
  });

  // ---------------------------------------------------------------------------
  // Name field - editable text input connected to the draft via onChange.
  // The label "Name" is linked to the input via htmlFor="name".
  // ---------------------------------------------------------------------------
  test('Name field is editable and reflects typed input', async () => {
    renderLayoutsPage();
    await openEditModal();

    const nameInput = screen.getByLabelText('Name');
    fireEvent.change(nameInput, { target: { value: 'Updated Layout' } });

    expect(nameInput).toHaveValue('Updated Layout');
  });

  // ---------------------------------------------------------------------------
  // Clearing the field and clicking Save should show "Name is required" and
  // block the API call entirely.
  // ---------------------------------------------------------------------------
  test('Save with empty name shows validation error and does not call updateLayout', async () => {
    renderLayoutsPage();
    await openEditModal();

    fireEvent.change(screen.getByLabelText('Name'), { target: { value: '' } });
    fireEvent.click(screen.getByRole('button', { name: 'Save' }));

    expect(await screen.findByText('Name is required')).toBeInTheDocument();
    expect(updateLayout).not.toHaveBeenCalled();
  });

  // ---------------------------------------------------------------------------
  // Description field - multiline text input (textarea), optional.
  // ---------------------------------------------------------------------------
  test('Description field accepts input', async () => {
    renderLayoutsPage();
    await openEditModal();

    const descriptionInput = screen.getByLabelText('Description');
    fireEvent.change(descriptionInput, { target: { value: 'A new description' } });

    expect(descriptionInput).toHaveValue('A new description');
  });

  // ---------------------------------------------------------------------------
  // Code field - optional identifier input.
  // The label "Code" is linked to the input via htmlFor="code".
  // ---------------------------------------------------------------------------
  test('Code field accepts input', async () => {
    renderLayoutsPage();
    await openEditModal();

    const codeInput = screen.getByLabelText('Code');
    fireEvent.change(codeInput, { target: { value: 'layout-001' } });

    expect(codeInput).toHaveValue('layout-001');
  });

  // ---------------------------------------------------------------------------
  // Retire Layout checkbox - toggling updates the draft.retired boolean.
  // ---------------------------------------------------------------------------
  test('Retire Layout checkbox toggles on and off', async () => {
    renderLayoutsPage();
    await openEditModal();

    const retireCheckbox = screen.getByLabelText('It will no longer be visible in the lists.');
    expect(retireCheckbox).not.toBeChecked();

    fireEvent.click(retireCheckbox);
    expect(retireCheckbox).toBeChecked();

    fireEvent.click(retireCheckbox);
    expect(retireCheckbox).not.toBeChecked();
  });

  // ---------------------------------------------------------------------------
  // Enable Stats checkbox - toggling updates the draft.enableStat boolean.
  // ---------------------------------------------------------------------------
  test('Enable Stats checkbox toggles on and off', async () => {
    renderLayoutsPage();
    await openEditModal();

    const statsCheckbox = screen.getByLabelText(/Collect Proof of Play/i);
    expect(statsCheckbox).toBeChecked();

    fireEvent.click(statsCheckbox);
    expect(statsCheckbox).not.toBeChecked();

    fireEvent.click(statsCheckbox);
    expect(statsCheckbox).toBeChecked();
  });

  // ---------------------------------------------------------------------------
  // Clicking Save sends the form data to the API and closes the modal.
  // ---------------------------------------------------------------------------
  test('Successful save calls updateLayout with correct payload and closes the modal', async () => {
    vi.mocked(updateLayout).mockResolvedValueOnce(mockLayout);

    renderLayoutsPage();
    await openEditModal();

    fireEvent.click(screen.getByRole('button', { name: 'Save' }));

    await waitFor(() => {
      expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
    });

    expect(updateLayout).toHaveBeenCalledWith(mockLayout.layoutId, {
      name: mockLayout.layout,
      description: null,
      tags: '',
      retired: 0,
      enableStat: 1,
      folderId: mockLayout.folderId,
    });
  });

  // ---------------------------------------------------------------------------
  // Failed save - API error keeps the modal open so the user can retry.
  // onClose is only called on success; an error sets apiError state instead.
  // ---------------------------------------------------------------------------
  test('Failed save keeps the modal open', async () => {
    vi.mocked(updateLayout).mockRejectedValueOnce({
      response: { data: { message: 'Layout name already exists' } },
    });

    renderLayoutsPage();
    await openEditModal();

    fireEvent.click(screen.getByRole('button', { name: 'Save' }));

    await waitFor(() => {
      expect(screen.getByRole('dialog')).toBeInTheDocument();
    });
  });
});
