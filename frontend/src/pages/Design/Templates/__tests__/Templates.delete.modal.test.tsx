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

import { screen, fireEvent, act, within } from '@testing-library/react';
import type React from 'react';
import { vi, beforeEach, describe, test, expect } from 'vitest';

import { useTemplateActions } from '../hooks/useTemplateActions';

import {
  SINGLE_TEMPLATE_ROWS,
  defaultTemplateActions,
  mockFetchTemplates,
  mockTemplate,
  renderTemplatesPage,
} from './templateTestUtils';

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

// Hooks
const confirmDelete = vi.fn();
vi.mock('../hooks/useTemplateActions', () => ({ useTemplateActions: vi.fn() }));
vi.mock('../hooks/useTemplateFilterOptions', () => ({
  useTemplateFilterOptions: vi.fn(() => ({ filterOptions: [], isLoading: false })),
}));

// UI
vi.mock('@/components/ui/FolderActionModals', () => ({ default: () => null }));
vi.mock('@/components/ui/modals/Modal');

// =============================================================================
// Helpers
// =============================================================================

// Delete action lives inside the
// "More actions" dropdown. The dropdown trigger uses aria-label (not title),
// so query by role+name. Open it, then pick Delete from the menu.
const openRowDeleteMenu = async () => {
  await screen.findByText(mockTemplate.layout);
  fireEvent.click(screen.getByRole('button', { name: 'More actions' }));
  fireEvent.click(await screen.findByText('Delete'));
};

// =============================================================================
// Tests
// =============================================================================

describe('Templates page - delete modal', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    vi.mocked(useTemplateActions).mockReturnValue(defaultTemplateActions({ confirmDelete }));
    mockFetchTemplates(SINGLE_TEMPLATE_ROWS);
  });

  // -------------------------------------------------------------------------
  // Clicking Delete on a row opens the confirmation modal.
  // -------------------------------------------------------------------------
  test('opens the Delete modal when the Delete action is clicked on a row', async () => {
    // Load the page with one template row visible.
    // Open More actions dropdown → click Delete.
    // A confirmation dialog should appear asking "Delete Template?" and showing
    // the name of the template the user is about to delete.
    await act(async () => {
      renderTemplatesPage();
    });

    await openRowDeleteMenu();

    const dialog = await screen.findByRole('dialog');
    expect(dialog).toBeInTheDocument();
    expect(within(dialog).getByText('Delete Template?')).toBeInTheDocument();
    expect(within(dialog).getByText(mockTemplate.layout)).toBeInTheDocument();
  });

  // -------------------------------------------------------------------------
  // Clicking Cancel closes the delete modal.
  // -------------------------------------------------------------------------
  test('closes the Delete modal when Cancel is clicked', async () => {
    // Open the delete confirmation modal, then click Cancel.
    // The modal should disappear and the user should be back on the main page
    // with no changes made - nothing was deleted.
    await act(async () => {
      renderTemplatesPage();
    });

    await openRowDeleteMenu();
    expect(screen.getByRole('dialog')).toBeInTheDocument();

    await act(async () => {
      fireEvent.click(screen.getByRole('button', { name: 'Cancel' }));
    });
    expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
  });

  // -------------------------------------------------------------------------
  // Clicking "Yes, Delete" calls confirmDelete with the template.
  // -------------------------------------------------------------------------
  test('calls confirmDelete when Yes, Delete is clicked', async () => {
    // Open the delete modal via More actions → Delete, then click confirm.
    // confirmDelete should be called with the template row.
    await act(async () => {
      renderTemplatesPage();
    });

    await openRowDeleteMenu();

    await act(async () => {
      fireEvent.click(screen.getByRole('button', { name: 'Yes, Delete' }));
    });

    expect(confirmDelete).toHaveBeenCalledTimes(1);
    expect(confirmDelete).toHaveBeenCalledWith([mockTemplate]);
  });

  // -------------------------------------------------------------------------
  // When delete fails the error is shown inside the open modal.
  // -------------------------------------------------------------------------
  test('shows a delete error inside the modal when deletion fails', async () => {
    // Simulate the server rejecting the delete request with an error message.
    // The modal must stay open and display the error so the user can read it.
    // It must NOT close automatically.
    vi.mocked(useTemplateActions).mockReturnValue(
      defaultTemplateActions({ deleteError: 'Template is in use by a campaign', confirmDelete }),
    );

    await act(async () => {
      renderTemplatesPage();
    });

    await openRowDeleteMenu();

    expect(screen.getByText('Template is in use by a campaign')).toBeInTheDocument();
    expect(screen.getByRole('dialog')).toBeInTheDocument();
  });

  // -------------------------------------------------------------------------
  // Selecting a row and clicking Delete Selected opens the bulk delete modal.
  // -------------------------------------------------------------------------
  test('opens the bulk Delete modal when a row is selected and Delete Selected is clicked', async () => {
    // Tick the checkbox on a row to select it.
    // A bulk action toolbar should appear at the top of the table.
    // Click "Delete Selected" in that toolbar.
    // The same delete confirmation dialog should appear, just like single-row delete.
    await act(async () => {
      renderTemplatesPage();
    });

    const checkboxes = await screen.findAllByRole('checkbox', { name: /Select row/i });
    await act(async () => {
      fireEvent.click(checkboxes[0]!);
    });

    await act(async () => {
      fireEvent.click(await screen.findByRole('button', { name: 'Delete Selected' }));
    });

    expect(await screen.findByRole('dialog')).toBeInTheDocument();
    expect(screen.getByText('Delete Template?')).toBeInTheDocument();
  });
});
