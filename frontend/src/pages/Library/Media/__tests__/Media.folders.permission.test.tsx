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
// Test type: Page integration test
// Mocks: folderApi, useMediaData, Modal, userApi
// Tests: folder sidebar visibility and bulk/row-action permissions by user role
// Bugs documented: TC-BUG-02 (selection not cleared), TC-BUG-05 (duplicate moves)
// =============================================================================

import { screen, fireEvent, waitFor, within } from '@testing-library/react';
import type React from 'react';
import { vi, describe, test, expect, beforeEach } from 'vitest';

import {
  MEDIA_ITEMS_IN_DIFFERENT_FOLDERS,
  ONE_MEDIA_ITEM,
  mockArchiveFolder,
  mockDesignFolder,
  mockMediaData,
  renderAs,
  userWithFolders,
  userWithoutFolders,
} from './mediaTestUtils';

import { fetchFolderTree, selectFolder } from '@/services/folderApi';

vi.mock('react-i18next', () => ({
  useTranslation: () => ({ t: (key: string) => key, i18n: { changeLanguage: vi.fn() } }),
  Trans: ({ children }: { children: React.ReactNode }) => children,
}));
vi.mock('@/services/userApi', () => ({
  fetchUserPreference: vi.fn().mockResolvedValue(null),
  saveUserPreference: vi.fn().mockResolvedValue(undefined),
}));
vi.mock('@/components/ui/modals/Modal');
vi.mock('@/services/folderApi', () => ({
  fetchFolderById: vi.fn().mockResolvedValue({ id: 1, text: 'Root' }),
  fetchFolderTree: vi.fn().mockResolvedValue([]),
  searchFolders: vi.fn().mockResolvedValue([]),
  fetchContextButtons: vi.fn().mockResolvedValue({ create: true }),
  selectFolder: vi.fn(),
}));
vi.mock('@/pages/Library/Media/hooks/useMediaFilterOptions', () => ({
  useMediaFilterOptions: vi.fn().mockReturnValue({ filterOptions: [], isLoading: false }),
}));
vi.mock('../hooks/useMediaData', () => ({
  useMediaData: vi.fn(),
}));

// =============================================================================
// Tests
// =============================================================================

describe('Media page – folder permissions', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    mockMediaData(ONE_MEDIA_ITEM);
  });

  // -------------------------------------------------------------------------
  // Folder sidebar visibility
  // -------------------------------------------------------------------------

  describe('folder sidebar', () => {
    // -------------------------------------------------------------------------
    // A user without folder permission should not see the folder panel at all.
    // -------------------------------------------------------------------------
    test('folder sidebar is not rendered for a user without folder.view permission', async () => {
      renderAs(userWithoutFolders);

      await screen.findByRole('table');

      expect(screen.queryByText('Select Folder')).not.toBeInTheDocument();
    });

    // -------------------------------------------------------------------------
    // A user with folder permission should see the folder panel toggle button.
    // -------------------------------------------------------------------------
    test('folder sidebar toggle is present for a user with folder.view permission', async () => {
      renderAs(userWithFolders);

      await screen.findByRole('table');

      expect(screen.queryByText('Select Folder')).toBeInTheDocument();
    });
  });

  // -------------------------------------------------------------------------
  // Bulk action bar — Move button
  // -------------------------------------------------------------------------

  describe('bulk action bar Move button', () => {
    // -------------------------------------------------------------------------
    // Selecting rows shows a bulk action bar. A user without folder permission
    // should not see a Move button there.
    // -------------------------------------------------------------------------
    test('Move button is absent from the bulk action bar for a user without folder.view', async () => {
      renderAs(userWithoutFolders);

      const checkboxes = await screen.findAllByRole('checkbox', { name: /Select row/i });
      fireEvent.click(checkboxes[0]!);

      await waitFor(() => {
        expect(screen.queryByRole('button', { name: /^Move$/i })).not.toBeInTheDocument();
      });
    });

    // -------------------------------------------------------------------------
    // A user with folder permission should see the Move button in the bulk bar.
    // -------------------------------------------------------------------------
    test('Move button appears in the bulk action bar for a user with folder.view', async () => {
      renderAs(userWithFolders);

      const checkboxes = await screen.findAllByRole('checkbox', { name: /Select row/i });
      fireEvent.click(checkboxes[0]!);

      expect(await screen.findByRole('button', { name: /^Move$/i })).toBeInTheDocument();
    });
  });

  // -------------------------------------------------------------------------
  // Row action menu — Move option
  // -------------------------------------------------------------------------

  describe('row action menu Move option', () => {
    // -------------------------------------------------------------------------
    // Each row has a three-dot menu of actions. A user without folder permission
    // should not see Move listed there.
    // -------------------------------------------------------------------------
    test('Move is absent from the row action menu for a user without folder.view', async () => {
      renderAs(userWithoutFolders);

      await screen.findByText('sample.jpg');

      // Open the row action dropdown (the last button in the actions cell)
      const actionButtons = screen.getAllByRole('button');
      const actionsDropdownBtn = actionButtons.find((btn) =>
        btn.closest('[data-column-id="tableActions"]'),
      );

      if (actionsDropdownBtn) {
        fireEvent.click(actionsDropdownBtn);
        await waitFor(() => {
          expect(screen.queryByRole('menuitem', { name: /^Move$/i })).not.toBeInTheDocument();
        });
      }
    });
  });

  // -------------------------------------------------------------------------
  // Moving items that come from different folders
  // -------------------------------------------------------------------------

  describe('inconsistent results when moving items from different folders', () => {
    // -------------------------------------------------------------------------
    // Both files should move successfully even if one is already in the target
    // folder. Known bug: the extra API call fails and the UI shows a partial error.
    // -------------------------------------------------------------------------
    test.fails('all selected items are moved when they come from different folders', async () => {
      vi.mocked(fetchFolderTree).mockResolvedValue([mockDesignFolder]);
      // Item 1 moves successfully. Item 2 is already in Design so the server rejects it.
      vi.mocked(selectFolder)
        .mockResolvedValueOnce({ success: true, data: undefined })
        .mockResolvedValueOnce({ success: false, error: 'Already in destination folder' });

      mockMediaData(MEDIA_ITEMS_IN_DIFFERENT_FOLDERS);

      renderAs(userWithFolders);

      // Select both rows
      const checkboxes = await screen.findAllByRole('checkbox', { name: /Select row/i });
      fireEvent.click(checkboxes[0]!);
      fireEvent.click(checkboxes[1]!);

      // Open the Move modal and pick Design as the destination
      fireEvent.click(await screen.findByRole('button', { name: /^Move$/i }));
      const dialog = await screen.findByRole('dialog');
      fireEvent.click(await within(dialog).findByText('Design'));

      const modalMoveBtn = within(dialog).getByRole('button', { name: /^Move$/i });
      await waitFor(() => expect(modalMoveBtn).not.toBeDisabled());
      fireEvent.click(modalMoveBtn);

      // The file already in the destination should be skipped so the move
      // completes cleanly with no partial-failure message.
      await waitFor(() => {
        expect(vi.mocked(selectFolder)).toHaveBeenCalledTimes(1);
      });
      expect(vi.mocked(selectFolder)).toHaveBeenCalledWith(
        expect.objectContaining({ targetId: 1, folderId: mockDesignFolder.id }),
      );
    });
  });

  // -------------------------------------------------------------------------
  // Checkbox state after a successful move
  // -------------------------------------------------------------------------

  describe('selection state after move', () => {
    // -------------------------------------------------------------------------
    // After moving files to another folder, the tick marks on those rows
    // should disappear.
    // -------------------------------------------------------------------------
    test.fails('row checkboxes are cleared after a successful move', async () => {
      vi.mocked(fetchFolderTree).mockResolvedValue([mockArchiveFolder]);
      vi.mocked(selectFolder).mockResolvedValue({ success: true, data: undefined });

      renderAs(userWithFolders);

      // Tick a row to show the bulk action bar
      const checkboxes = await screen.findAllByRole('checkbox', { name: /Select row/i });
      const checkbox = checkboxes[0]!;
      fireEvent.click(checkbox);
      expect(checkbox).toBeChecked();

      // Open the Move modal and pick Archive as the destination
      fireEvent.click(await screen.findByRole('button', { name: /^Move$/i }));
      const dialog = await screen.findByRole('dialog');
      fireEvent.click(await within(dialog).findByText('Archive'));

      // Wait for the Move button to become clickable — picking a folder
      // takes a moment to register before the button enables.
      const modalMoveBtn = within(dialog).getByRole('button', { name: /^Move$/i });
      await waitFor(() => expect(modalMoveBtn).not.toBeDisabled());
      fireEvent.click(modalMoveBtn);

      // The checkbox must be unticked once the move is done.
      await waitFor(() => {
        expect(checkbox).not.toBeChecked();
      });
    });
  });
});
