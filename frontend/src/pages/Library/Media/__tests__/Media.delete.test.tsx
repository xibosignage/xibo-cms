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
import { test, vi, beforeEach } from 'vitest';

import { mockEditMedia, mockMediaData, renderMediaPage } from './mediaTestUtils';

import { deleteMedia } from '@/services/mediaApi';
import { testQueryClient } from '@/setupTests';

// -----------------------------------------------------------------------------
// Module mocks
// -----------------------------------------------------------------------------

vi.mock('@/pages/Library/Media/hooks/useMediaFilterOptions', () => ({
  useMediaFilterOptions: vi.fn().mockReturnValue({ filterOptions: [], isLoading: false }),
}));
vi.mock('../hooks/useMediaData');
vi.mock('@/services/mediaApi', () => ({
  uploadMedia: vi.fn(),
  uploadMediaFromUrl: vi.fn(),
  updateMedia: vi.fn(),
  uploadThumbnail: vi.fn(),
  deleteMedia: vi.fn(),
}));
vi.mock('@/services/folderApi', () => ({
  fetchFolderById: vi.fn().mockResolvedValue({ id: 1, text: 'Root' }),
  fetchFolderTree: vi.fn().mockResolvedValue([]),
  searchFolders: vi.fn().mockResolvedValue([]),
  fetchContextButtons: vi.fn().mockResolvedValue({ create: true }),
  selectFolder: vi.fn(),
}));
vi.mock('@/services/userApi', () => ({
  fetchUserPreference: vi.fn().mockResolvedValue(null),
  saveUserPreference: vi.fn().mockResolvedValue(undefined),
}));

// =============================================================================
// Helpers
// =============================================================================

// Opens the delete confirmation modal for mockEditMedia by selecting its row
// and clicking the Delete toolbar button.
const openDeleteModal = async () => {
  const checkboxes = await screen.findAllByRole('checkbox', { name: /Select row/i });
  fireEvent.click(checkboxes[0]!);

  const deleteBtn = await screen.findByRole('button', { name: /Delete Selected/i });
  fireEvent.click(deleteBtn);

  return screen.findByRole('dialog');
};

// =============================================================================
// Tests
// =============================================================================

describe('Delete Media', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    mockMediaData({
      data: { rows: [mockEditMedia], totalCount: 1 },
      isFetching: false,
      isError: false,
      error: null,
    });
  });

  // ---------------------------------------------------------------------------
  // Selecting a row and clicking Delete opens the confirmation modal.
  // ---------------------------------------------------------------------------
  test('Delete button opens the confirmation modal', async () => {
    renderMediaPage();

    const dialog = await openDeleteModal();

    expect(dialog).toBeInTheDocument();
  });

  // ---------------------------------------------------------------------------
  // Clicking Cancel closes the modal without calling the API.
  // ---------------------------------------------------------------------------
  test('Cancel closes the delete modal without calling deleteMedia', async () => {
    renderMediaPage();
    await openDeleteModal();

    fireEvent.click(screen.getByRole('button', { name: 'Cancel' }));

    await waitFor(() => {
      expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
    });
    expect(deleteMedia).not.toHaveBeenCalled();
  });

  // ---------------------------------------------------------------------------
  // Clicking "Yes, Delete" and getting a success response closes the modal.
  // ---------------------------------------------------------------------------
  test('Successful delete closes the modal', async () => {
    vi.mocked(deleteMedia).mockResolvedValueOnce(undefined);

    renderMediaPage();
    await openDeleteModal();

    fireEvent.click(screen.getByRole('button', { name: 'Yes, Delete' }));

    await waitFor(() => {
      expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
    });
  });

  // ---------------------------------------------------------------------------
  // Delete modal shows error when an item cannot be deleted
  // ---------------------------------------------------------------------------
  test('delete modal stays open and shows error when an item cannot be deleted', async () => {
    vi.mocked(deleteMedia).mockRejectedValueOnce(new Error('Media is in use'));

    renderMediaPage();
    await openDeleteModal();

    fireEvent.click(screen.getByRole('button', { name: 'Yes, Delete' }));

    // Modal must stay open — user needs to see the error
    await waitFor(() => {
      expect(screen.getByRole('dialog')).toBeInTheDocument();
    });

    // Error message must appear inside the modal
    expect(screen.getByText('1 item(s) could not be deleted.')).toBeInTheDocument();
  });
});
