/*
 * Xibo Digital Signage
 * Copyright (C) 2006-2026 Xibo Signage Ltd
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published
 * by the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 *
 * This program is also available under a commercial licence from
 * Xibo Signage Ltd. See https://xibosignage.com/terms
 */

import { screen, fireEvent, waitFor } from '@testing-library/react';
import { vi, beforeEach, describe, test, expect } from 'vitest';

import { useLayoutActions } from '../hooks/useLayoutActions';

import {
  SINGLE_LAYOUT,
  defaultLayoutActions,
  mockFetchLayouts,
  mockLayout,
  openEditModal,
  renderLayoutsPage,
} from './layoutTestUtils';

import { updateLayout } from '@/services/layoutsApi';
import { testQueryClient } from '@/setupTests';

// -----------------------------------------------------------------------------
// Module mocks
// -----------------------------------------------------------------------------

// 3rd-party

// Services
vi.mock('@/services/folderApi');
vi.mock('@/services/layoutsApi');
vi.mock('@/services/userApi', () => ({
  fetchUserPreference: vi.fn().mockResolvedValue(null),
  saveUserPreference: vi.fn().mockResolvedValue(undefined),
}));

// Hooks
vi.mock('../hooks/useLayoutActions', () => ({ useLayoutActions: vi.fn() }));
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
    mockFetchLayouts(SINGLE_LAYOUT);
  });

  // ---------------------------------------------------------------------------
  // Name field - editable text input connected to the draft via onChange.
  // The label "Name" is linked to the input via htmlFor="name".
  // ---------------------------------------------------------------------------
  test('Name field is editable and reflects typed input', async () => {
    renderLayoutsPage();
    await openEditModal();

    const nameInput = screen.getByRole('textbox', { name: 'Name' });
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

    fireEvent.change(screen.getByRole('textbox', { name: 'Name' }), {
      target: { value: '' },
    });
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

    const descriptionInput = screen.getByRole('textbox', { name: 'Description' });
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
  test(
    'Successful save calls updateLayout with correct payload and closes the modal',
    { tags: ['smoke'] },
    async () => {
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
        code: '',
        retired: 0,
        enableStat: 1,
        folderId: mockLayout.folderId,
      });
    },
  );

  // ---------------------------------------------------------------------------
  // Failed save - API error keeps the modal open so the user can retry.
  // onClose is only called on success; an error sets apiError state instead.
  // ---------------------------------------------------------------------------
  test('Failed save keeps the modal open', async () => {
    vi.mocked(updateLayout).mockRejectedValueOnce({
      isAxiosError: true,
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
