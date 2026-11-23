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

import { useTemplateActions } from '../hooks/useTemplateActions';

import {
  SINGLE_TEMPLATE_ROWS,
  defaultTemplateActions,
  mockFetchTemplates,
  mockTemplate,
  openEditModal,
  renderTemplatesPage,
} from './templateTestUtils';

import type * as TagInputModule from '@/components/ui/forms/TagInput';
import { updateTemplate } from '@/services/templatesApi';
import { testQueryClient } from '@/setupTests';

// =============================================================================
// Module mocks
// =============================================================================

// 3rd-party

// Services
vi.mock('@/services/folderApi');
vi.mock('@/services/templatesApi');
vi.mock('@/services/userApi', () => ({
  fetchUserPreference: vi.fn().mockResolvedValue(null),
  saveUserPreference: vi.fn().mockResolvedValue(undefined),
}));
vi.mock('@/services/resolutionApi', () => ({
  fetchResolution: vi.fn().mockResolvedValue({ rows: [] }),
}));

// Hooks
vi.mock('../hooks/useTemplateActions', () => ({ useTemplateActions: vi.fn() }));
vi.mock('../hooks/useTemplateFilterOptions', () => ({
  useTemplateFilterOptions: vi.fn(() => ({ filterOptions: [], isLoading: false })),
}));

// UI
vi.mock('@/components/ui/FolderActionModals', () => ({ default: () => null }));
vi.mock('@/components/ui/forms/SelectFolder', () => ({ default: () => null }));
vi.mock('@/components/ui/forms/TagInput', async (importOriginal) => {
  const actual = await importOriginal<typeof TagInputModule>();
  return { ...actual, default: () => null };
});
vi.mock('@/components/ui/modals/Modal');

// =============================================================================
// Tests
// =============================================================================

describe('Templates page - edit form fields', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    vi.mocked(useTemplateActions).mockReturnValue(defaultTemplateActions());
    mockFetchTemplates(SINGLE_TEMPLATE_ROWS);
  });

  // ---------------------------------------------------------------------------
  // Name field - editable text input connected to the draft via onChange.
  // The label "Name" is linked to the input via htmlFor="name".
  // ---------------------------------------------------------------------------
  test('Name field is editable and reflects typed input', async () => {
    renderTemplatesPage();
    await openEditModal();

    const nameInput = screen.getByRole('textbox', { name: 'Name' });
    fireEvent.change(nameInput, { target: { value: 'Updated Template' } });

    expect(nameInput).toHaveValue('Updated Template');
  });

  // ---------------------------------------------------------------------------
  // Clearing the Name field and clicking Save should show "Name is required"
  // and block the API call entirely.
  // ---------------------------------------------------------------------------
  test('Save with empty name shows validation error and does not call updateTemplate', async () => {
    renderTemplatesPage();
    await openEditModal();

    fireEvent.change(screen.getByRole('textbox', { name: 'Name' }), {
      target: { value: '' },
    });
    fireEvent.click(screen.getByRole('button', { name: 'Save' }));

    expect(await screen.findByText('Name is required')).toBeInTheDocument();
    expect(updateTemplate).not.toHaveBeenCalled();
  });

  // ---------------------------------------------------------------------------
  // Description field - multiline text input (textarea), optional.
  // ---------------------------------------------------------------------------
  test('Description field accepts input', async () => {
    renderTemplatesPage();
    await openEditModal();

    const descriptionInput = screen.getByRole('textbox', { name: 'Description' });
    fireEvent.change(descriptionInput, { target: { value: 'A new description' } });

    expect(descriptionInput).toHaveValue('A new description');
  });

  // ---------------------------------------------------------------------------
  // Retire checkbox - toggling updates the draft.retired boolean.
  // ---------------------------------------------------------------------------
  test('Retire checkbox toggles on and off', async () => {
    renderTemplatesPage();
    await openEditModal();

    const retireCheckbox = screen.getByLabelText(
      'Retired templates cannot be used for new assignments.',
    );
    expect(retireCheckbox).not.toBeChecked();

    fireEvent.click(retireCheckbox);
    expect(retireCheckbox).toBeChecked();

    fireEvent.click(retireCheckbox);
    expect(retireCheckbox).not.toBeChecked();
  });

  // ---------------------------------------------------------------------------
  // When the user saves changes, the app should save the right data
  // and close the popup.
  // ---------------------------------------------------------------------------
  test(
    'Successful save calls updateTemplate with correct payload and closes the modal',
    { tags: ['smoke'] },
    async () => {
      vi.mocked(updateTemplate).mockResolvedValueOnce(mockTemplate);

      renderTemplatesPage();
      await openEditModal();

      fireEvent.click(screen.getByRole('button', { name: 'Save' }));

      await waitFor(() => {
        expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
      });

      expect(updateTemplate).toHaveBeenCalledWith(mockTemplate.layoutId, {
        name: mockTemplate.layout,
        description: mockTemplate.description,
        tags: 'template',
        retired: 0,
        folderId: mockTemplate.folderId,
        enableStat: mockTemplate.enableStat,
        code: mockTemplate.code,
      });
    },
  );

  // ---------------------------------------------------------------------------
  // Failed save - API error keeps the modal open so the user can retry.
  // ---------------------------------------------------------------------------
  test('Failed save keeps the modal open', async () => {
    vi.mocked(updateTemplate).mockRejectedValueOnce({
      isAxiosError: true,
      response: { data: { message: 'Template name already exists' } },
    });

    renderTemplatesPage();
    await openEditModal();

    fireEvent.click(screen.getByRole('button', { name: 'Save' }));

    await waitFor(() => {
      expect(screen.getByRole('dialog')).toBeInTheDocument();
    });
  });
});
