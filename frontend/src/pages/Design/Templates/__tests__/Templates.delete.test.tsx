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

import { screen, fireEvent, waitFor, within } from '@testing-library/react';
import { vi, beforeEach, describe, test, expect } from 'vitest';

import {
  mockFetchTemplates,
  mockTemplate,
  renderTemplatesPage,
  SINGLE_TEMPLATE_ROWS,
} from './templateTestUtils';

import { deleteLayout } from '@/services/layoutsApi';
import { testQueryClient } from '@/setupTests';

// =============================================================================
// Module mocks
// =============================================================================

// 3rd-party

// Services
vi.mock('@/services/folderApi');
vi.mock('@/services/layoutsApi');
vi.mock('@/services/templatesApi');
vi.mock('@/services/userApi', () => ({
  fetchUserPreference: vi.fn().mockResolvedValue(null),
  saveUserPreference: vi.fn().mockResolvedValue(undefined),
}));

// UI
vi.mock('@/components/ui/FolderActionModals', () => ({ default: () => null }));
vi.mock('@/components/ui/modals/Modal');

// =============================================================================
// Helpers
// =============================================================================
const openDeleteModal = async () => {
  await screen.findByText(mockTemplate.layout);

  const checkboxes = screen.getAllByRole('checkbox', { name: /Select row/i });
  fireEvent.click(checkboxes[0]!);

  const deleteBtn = await screen.findByRole('button', { name: /Delete Selected/i });
  fireEvent.click(deleteBtn);

  return screen.findByRole('dialog');
};

// =============================================================================
// Tests
// =============================================================================

describe('Delete Template', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    mockFetchTemplates(SINGLE_TEMPLATE_ROWS);
  });

  // ---------------------------------------------------------------------------
  // Selecting a row and clicking Delete Selected opens the confirmation modal.
  // ---------------------------------------------------------------------------
  test('Delete Selected opens the confirmation modal', async () => {
    renderTemplatesPage();

    const dialog = await openDeleteModal();

    expect(dialog).toBeInTheDocument();
    expect(within(dialog).getByText('Delete Template?')).toBeInTheDocument();
    expect(within(dialog).getByText(mockTemplate.layout)).toBeInTheDocument();
  });

  // ---------------------------------------------------------------------------
  // Clicking Cancel closes the modal without calling the API.
  // ---------------------------------------------------------------------------
  test('Cancel closes the modal without calling deleteLayout', async () => {
    renderTemplatesPage();
    await openDeleteModal();

    fireEvent.click(screen.getByRole('button', { name: 'Cancel' }));

    await waitFor(() => {
      expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
    });
    expect(deleteLayout).not.toHaveBeenCalled();
  });

  // ---------------------------------------------------------------------------
  // Clicking "Yes, Delete" and getting a success response closes the modal.
  // ---------------------------------------------------------------------------
  test('successful delete calls deleteLayout and closes the modal', async () => {
    vi.mocked(deleteLayout).mockResolvedValueOnce(undefined);

    renderTemplatesPage();
    await openDeleteModal();

    fireEvent.click(screen.getByRole('button', { name: 'Yes, Delete' }));

    await waitFor(() => {
      expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
    });
    expect(deleteLayout).toHaveBeenCalledTimes(1);
    expect(deleteLayout).toHaveBeenCalledWith(mockTemplate.layoutId);
  });

  // ---------------------------------------------------------------------------
  // Delete modal shows error when an item cannot be deleted.
  // ---------------------------------------------------------------------------
  test('failed delete keeps the modal open and shows an error', async () => {
    vi.mocked(deleteLayout).mockRejectedValueOnce(new Error('Template is in use'));

    renderTemplatesPage();
    await openDeleteModal();

    fireEvent.click(screen.getByRole('button', { name: 'Yes, Delete' }));

    await waitFor(() => {
      expect(screen.getByRole('dialog')).toBeInTheDocument();
    });
    expect(
      screen.getByText('1 item(s) could not be deleted because they are in use.'),
    ).toBeInTheDocument();
  });
});
