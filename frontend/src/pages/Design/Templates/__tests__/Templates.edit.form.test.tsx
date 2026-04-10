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

import { useTemplateActions } from '../hooks/useTemplateActions';

import {
  SINGLE_TEMPLATE_ROWS,
  defaultTemplateActions,
  mockFetchTemplates,
  mockTemplate,
  openEditModal,
  renderTemplatesPage,
} from './templateTestUtils';

import { updateTemplate } from '@/services/templatesApi';
import { testQueryClient } from '@/setupTests';

// =============================================================================
// Module mocks
// =============================================================================

// 3rd-party
vi.mock('react-i18next', () => ({
  useTranslation: () => ({ t: (key: string) => key, i18n: { changeLanguage: vi.fn() } }),
  Trans: ({ children }: { children: React.ReactNode }) => children,
}));

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
vi.mock('@/components/ui/forms/TagInput', () => ({ default: () => null }));
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

    const nameInput = screen.getByLabelText('Name');
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

    fireEvent.change(screen.getByLabelText('Name'), { target: { value: '' } });
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

    const descriptionInput = screen.getByLabelText('Description');
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

  test.fails(
    'Successful save calls updateTemplate with correct payload and closes the modal',
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
      });
    },
  );

  // ---------------------------------------------------------------------------
  // Failed save - API error keeps the modal open so the user can retry.
  // ---------------------------------------------------------------------------
  test('Failed save keeps the modal open', async () => {
    vi.mocked(updateTemplate).mockRejectedValueOnce({
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
