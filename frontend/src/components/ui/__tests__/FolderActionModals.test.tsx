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
// Test type: Component integration test
// Only the external boundaries are mocked (folderApi, notify, Modal)
// Tests for folder create, rename, delete, and move flows
// hook state → modal UI → API call → notification → modal closes
// =============================================================================

import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { vi, beforeEach, describe, test, expect } from 'vitest';

import FolderActionModals from '../FolderActionModals';

import { notify } from '@/components/ui/Notification';
import { useFolderActions } from '@/hooks/useFolderActions';
import { createFolder, editFolder, deleteFolder, moveFolder } from '@/services/folderApi';
import type { Folder } from '@/types/folder';

// -----------------------------------------------------------------------------
// Module mocks
// -----------------------------------------------------------------------------

vi.mock('react-i18next', () => ({
  useTranslation: () => ({ t: (key: string) => key }),
}));
vi.mock('@/components/ui/modals/Modal');
vi.mock('@/components/ui/modals/ShareModal', () => ({
  default: () => null,
}));
vi.mock('@/components/ui/forms/SelectFolder', () => ({
  default: ({ onSelect }: { onSelect: (folder: { id: number; text: string } | null) => void }) => (
    <button onClick={() => onSelect({ id: 10, text: 'Archive' })}>Select Destination</button>
  ),
}));
vi.mock('@/components/ui/Notification', () => ({
  notify: { info: vi.fn(), error: vi.fn(), success: vi.fn(), warning: vi.fn() },
}));
vi.mock('@/services/folderApi', () => ({
  createFolder: vi.fn(),
  editFolder: vi.fn(),
  deleteFolder: vi.fn(),
  moveFolder: vi.fn(),
}));

// -----------------------------------------------------------------------------
// Fixtures
// -----------------------------------------------------------------------------

const mockFolder: Folder = {
  id: 42,
  text: 'Design',
  isRoot: 0,
  type: null,
  parentId: 1,
  ownerId: 1,
  ownerName: 'MockUser',
  children: [],
};

// -----------------------------------------------------------------------------
// Test wrapper
//
// Renders the real useFolderActions hook.
// Three trigger buttons let each test open whichever modal it needs.
// -----------------------------------------------------------------------------

const Wrapper = ({ onSuccess }: { onSuccess?: () => void }) => {
  const folderActions = useFolderActions({ onSuccess });
  return (
    <>
      <button onClick={() => folderActions.openAction('create', mockFolder)}>Open Create</button>
      <button onClick={() => folderActions.openAction('rename', mockFolder)}>Open Rename</button>
      <button onClick={() => folderActions.openAction('delete', mockFolder)}>Open Delete</button>
      <button onClick={() => folderActions.openAction('move', mockFolder)}>Open Move</button>
      <FolderActionModals folderActions={folderActions} />
    </>
  );
};

// =============================================================================
// Tests
// =============================================================================

describe('FolderActionModals', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  // ===========================================================================
  // Create folder
  // ===========================================================================

  describe('create folder', () => {
    // -------------------------------------------------------------------------
    // Opening the create modal sets the input to the default "New Folder" name
    // so the user can immediately type over it.
    // -------------------------------------------------------------------------
    test('create modal opens with default folder name pre-filled', () => {
      render(<Wrapper />);
      fireEvent.click(screen.getByRole('button', { name: 'Open Create' }));

      expect(screen.getByRole('dialog', { name: 'Create New Folder' })).toBeInTheDocument();
      expect(screen.getByDisplayValue('New Folder')).toBeInTheDocument();
    });

    // -------------------------------------------------------------------------
    // The Create button is disabled when the name input is empty (whitespace
    // only counts as empty). The user must type a non-blank name first.
    // -------------------------------------------------------------------------
    test('Create button is disabled when folder name is cleared', async () => {
      const user = userEvent.setup({ delay: null });
      render(<Wrapper />);
      fireEvent.click(screen.getByRole('button', { name: 'Open Create' }));

      // Clear the pre-filled name
      await user.clear(screen.getByDisplayValue('New Folder'));

      expect(screen.getByRole('button', { name: 'Create' })).toBeDisabled();
    });

    // -------------------------------------------------------------------------
    // Clicking Create calls createFolder with the typed name and the parent
    // folder's ID (the folder that was right-clicked in the tree).
    // -------------------------------------------------------------------------
    test('clicking Create calls createFolder with correct name and parentId', async () => {
      const user = userEvent.setup({ delay: null });
      vi.mocked(createFolder).mockResolvedValueOnce({
        success: true,
        data: { id: 99, text: 'Marketing' } as Folder,
      });

      render(<Wrapper />);
      fireEvent.click(screen.getByRole('button', { name: 'Open Create' }));

      const input = screen.getByDisplayValue('New Folder');
      await user.clear(input);
      await user.type(input, 'Marketing');

      fireEvent.click(screen.getByRole('button', { name: 'Create' }));

      await waitFor(() => {
        expect(createFolder).toHaveBeenCalledWith({
          folderName: 'Marketing',
          parentId: mockFolder.id,
        });
      });
    });

    // -------------------------------------------------------------------------
    // A successful create closes the modal and shows a success notification.
    // -------------------------------------------------------------------------
    test('successful create closes the modal and shows a notification', async () => {
      vi.mocked(createFolder).mockResolvedValueOnce({
        success: true,
        data: { id: 99, text: 'New Folder' } as Folder,
      });

      render(<Wrapper />);
      fireEvent.click(screen.getByRole('button', { name: 'Open Create' }));
      fireEvent.click(screen.getByRole('button', { name: 'Create' }));

      await waitFor(() => {
        expect(screen.queryByRole('dialog', { name: 'Create New Folder' })).not.toBeInTheDocument();
      });
      expect(notify.info).toHaveBeenCalled();
    });

    // -------------------------------------------------------------------------
    // A failed create keeps the modal open and shows an error notification so
    // the user knows what went wrong and can try again.
    // -------------------------------------------------------------------------
    test('failed create shows error notification and keeps modal open', async () => {
      vi.mocked(createFolder).mockResolvedValueOnce({
        success: false,
        error: 'Name already taken',
      });

      render(<Wrapper />);
      fireEvent.click(screen.getByRole('button', { name: 'Open Create' }));
      fireEvent.click(screen.getByRole('button', { name: 'Create' }));

      await waitFor(() => {
        expect(notify.error).toHaveBeenCalledWith('Name already taken');
      });
      expect(screen.getByRole('dialog', { name: 'Create New Folder' })).toBeInTheDocument();
    });

    // -------------------------------------------------------------------------
    // Clicking Cancel closes the modal without calling the API.
    // -------------------------------------------------------------------------
    test('Cancel closes the create modal without calling createFolder', () => {
      render(<Wrapper />);
      fireEvent.click(screen.getByRole('button', { name: 'Open Create' }));
      fireEvent.click(screen.getByRole('button', { name: 'Cancel' }));

      expect(screen.queryByRole('dialog', { name: 'Create New Folder' })).not.toBeInTheDocument();
      expect(createFolder).not.toHaveBeenCalled();
    });
  });

  // ===========================================================================
  // Rename folder
  // ===========================================================================

  describe('rename folder', () => {
    // -------------------------------------------------------------------------
    // Opening rename pre-fills the input with the current folder name so the
    // user can edit it rather than typing from scratch.
    // -------------------------------------------------------------------------
    test('rename modal opens with the current folder name pre-filled', () => {
      render(<Wrapper />);
      fireEvent.click(screen.getByRole('button', { name: 'Open Rename' }));

      expect(screen.getByRole('dialog', { name: 'Rename Folder' })).toBeInTheDocument();
      expect(screen.getByDisplayValue(mockFolder.text)).toBeInTheDocument();
    });

    // -------------------------------------------------------------------------
    // The Rename button is disabled if the user clears the input — an empty
    // name is not a valid folder name.
    // -------------------------------------------------------------------------
    test('Rename button is disabled when folder name is cleared', async () => {
      const user = userEvent.setup({ delay: null });
      render(<Wrapper />);
      fireEvent.click(screen.getByRole('button', { name: 'Open Rename' }));

      await user.clear(screen.getByDisplayValue(mockFolder.text));

      expect(screen.getByRole('button', { name: 'Rename' })).toBeDisabled();
    });

    // -------------------------------------------------------------------------
    // Clicking Rename calls editFolder with the folder ID and the new name.
    // -------------------------------------------------------------------------
    test('clicking Rename calls editFolder with correct id and new name', async () => {
      const user = userEvent.setup({ delay: null });
      vi.mocked(editFolder).mockResolvedValueOnce({
        success: true,
        data: { ...mockFolder, text: 'Brand' },
      });

      render(<Wrapper />);
      fireEvent.click(screen.getByRole('button', { name: 'Open Rename' }));

      const input = screen.getByDisplayValue(mockFolder.text);
      await user.clear(input);
      await user.type(input, 'Brand');

      fireEvent.click(screen.getByRole('button', { name: 'Rename' }));

      await waitFor(() => {
        expect(editFolder).toHaveBeenCalledWith({ id: mockFolder.id, text: 'Brand' });
      });
    });

    // -------------------------------------------------------------------------
    // A successful rename closes the modal and shows a notification.
    // -------------------------------------------------------------------------
    test('successful rename closes the modal and shows a notification', async () => {
      vi.mocked(editFolder).mockResolvedValueOnce({ success: true, data: mockFolder });

      render(<Wrapper />);
      fireEvent.click(screen.getByRole('button', { name: 'Open Rename' }));
      fireEvent.click(screen.getByRole('button', { name: 'Rename' }));

      await waitFor(() => {
        expect(screen.queryByRole('dialog', { name: 'Rename Folder' })).not.toBeInTheDocument();
      });
      expect(notify.info).toHaveBeenCalled();
    });

    // -------------------------------------------------------------------------
    // A failed rename shows an error notification and keeps the modal open.
    // -------------------------------------------------------------------------
    test('failed rename shows error notification and keeps modal open', async () => {
      vi.mocked(editFolder).mockResolvedValueOnce({ success: false, error: 'Permission denied' });

      render(<Wrapper />);
      fireEvent.click(screen.getByRole('button', { name: 'Open Rename' }));
      fireEvent.click(screen.getByRole('button', { name: 'Rename' }));

      await waitFor(() => {
        expect(notify.error).toHaveBeenCalledWith('Permission denied');
      });
      expect(screen.getByRole('dialog', { name: 'Rename Folder' })).toBeInTheDocument();
    });

    // -------------------------------------------------------------------------
    // Cancel closes the modal without calling editFolder.
    // -------------------------------------------------------------------------
    test('Cancel closes the rename modal without calling editFolder', () => {
      render(<Wrapper />);
      fireEvent.click(screen.getByRole('button', { name: 'Open Rename' }));
      fireEvent.click(screen.getByRole('button', { name: 'Cancel' }));

      expect(screen.queryByRole('dialog', { name: 'Rename Folder' })).not.toBeInTheDocument();
      expect(editFolder).not.toHaveBeenCalled();
    });
  });

  // ===========================================================================
  // Delete folder
  // ===========================================================================

  describe('delete folder', () => {
    // -------------------------------------------------------------------------
    // The delete modal shows the folder name in the confirmation text so the
    // user knows exactly which folder they are about to delete.
    // -------------------------------------------------------------------------
    test('delete modal opens and shows the folder name', () => {
      render(<Wrapper />);
      fireEvent.click(screen.getByRole('button', { name: 'Open Delete' }));

      expect(screen.getByRole('dialog', { name: 'Delete Folder' })).toBeInTheDocument();
      expect(screen.getByText(`"${mockFolder.text}"`)).toBeInTheDocument();
    });

    // -------------------------------------------------------------------------
    // Clicking Delete calls deleteFolder with the correct folder ID.
    // -------------------------------------------------------------------------
    test('clicking Delete calls deleteFolder with the correct folder ID', async () => {
      vi.mocked(deleteFolder).mockResolvedValueOnce({ success: true, data: undefined });

      render(<Wrapper />);
      fireEvent.click(screen.getByRole('button', { name: 'Open Delete' }));
      fireEvent.click(screen.getByRole('button', { name: 'Delete' }));

      await waitFor(() => {
        expect(deleteFolder).toHaveBeenCalledWith(mockFolder.id);
      });
    });

    // -------------------------------------------------------------------------
    // A successful delete closes the modal and shows a notification.
    // -------------------------------------------------------------------------
    test('successful delete closes the modal and shows a notification', async () => {
      vi.mocked(deleteFolder).mockResolvedValueOnce({ success: true, data: undefined });

      render(<Wrapper />);
      fireEvent.click(screen.getByRole('button', { name: 'Open Delete' }));
      fireEvent.click(screen.getByRole('button', { name: 'Delete' }));

      await waitFor(() => {
        expect(screen.queryByRole('dialog', { name: 'Delete Folder' })).not.toBeInTheDocument();
      });
      expect(notify.info).toHaveBeenCalled();
    });

    // -------------------------------------------------------------------------
    // A failed delete shows an error notification and keeps the modal open so
    // the user can see what went wrong.
    // -------------------------------------------------------------------------
    test('failed delete shows error notification and keeps modal open', async () => {
      vi.mocked(deleteFolder).mockResolvedValueOnce({
        success: false,
        error: 'Folder is not empty',
      });

      render(<Wrapper />);
      fireEvent.click(screen.getByRole('button', { name: 'Open Delete' }));
      fireEvent.click(screen.getByRole('button', { name: 'Delete' }));

      await waitFor(() => {
        expect(notify.error).toHaveBeenCalledWith('Folder is not empty');
      });
      expect(screen.getByRole('dialog', { name: 'Delete Folder' })).toBeInTheDocument();
    });

    // -------------------------------------------------------------------------
    // Cancel closes the modal without calling deleteFolder.
    // -------------------------------------------------------------------------
    test('Cancel closes the delete modal without calling deleteFolder', () => {
      render(<Wrapper />);
      fireEvent.click(screen.getByRole('button', { name: 'Open Delete' }));
      fireEvent.click(screen.getByRole('button', { name: 'Cancel' }));

      expect(screen.queryByRole('dialog', { name: 'Delete Folder' })).not.toBeInTheDocument();
      expect(deleteFolder).not.toHaveBeenCalled();
    });
  });

  // ===========================================================================
  // Move folder
  // ===========================================================================

  describe('move folder', () => {
    // -------------------------------------------------------------------------
    // Opening the move modal shows the folder being moved and a destination
    // picker. The Move button starts disabled because no destination is selected.
    // -------------------------------------------------------------------------
    test('move modal opens with Move button disabled until a destination is selected', () => {
      render(<Wrapper />);
      fireEvent.click(screen.getByRole('button', { name: 'Open Move' }));

      expect(screen.getByRole('dialog', { name: 'Move Folder' })).toBeInTheDocument();
      expect(screen.getByRole('button', { name: 'Move' })).toBeDisabled();
    });

    // -------------------------------------------------------------------------
    // The modal shows which folder is being moved so the user knows what they
    // are about to relocate.
    // -------------------------------------------------------------------------
    test('move modal shows the name of the folder being moved', () => {
      render(<Wrapper />);
      fireEvent.click(screen.getByRole('button', { name: 'Open Move' }));

      expect(screen.getByText(mockFolder.text)).toBeInTheDocument();
    });

    // -------------------------------------------------------------------------
    // Selecting a destination folder via the picker enables the Move button.
    // The SelectFolder mock calls onSelect({ id: 10, text: 'Archive' }) when
    // "Select Destination" is clicked, simulating the user picking a folder.
    // -------------------------------------------------------------------------
    test('Move button becomes enabled after a destination folder is selected', () => {
      render(<Wrapper />);
      fireEvent.click(screen.getByRole('button', { name: 'Open Move' }));
      fireEvent.click(screen.getByRole('button', { name: 'Select Destination' }));

      expect(screen.getByRole('button', { name: 'Move' })).not.toBeDisabled();
    });

    // -------------------------------------------------------------------------
    // Clicking Move calls moveFolder with the correct source folder ID and the
    // selected destination folder ID.
    // -------------------------------------------------------------------------
    test('clicking Move calls moveFolder with the correct source id and targetId', async () => {
      vi.mocked(moveFolder).mockResolvedValueOnce({ success: true, data: undefined });

      render(<Wrapper />);
      fireEvent.click(screen.getByRole('button', { name: 'Open Move' }));
      fireEvent.click(screen.getByRole('button', { name: 'Select Destination' }));
      fireEvent.click(screen.getByRole('button', { name: 'Move' }));

      await waitFor(() => {
        expect(moveFolder).toHaveBeenCalledWith({
          id: mockFolder.id,
          targetId: 10,
          merge: false,
        });
      });
    });

    // -------------------------------------------------------------------------
    // When the merge checkbox is ticked, moveFolder is called with merge: true
    // so the server merges the contents rather than rejecting a name collision.
    // -------------------------------------------------------------------------
    test('ticking the merge checkbox sends merge: true to moveFolder', async () => {
      vi.mocked(moveFolder).mockResolvedValueOnce({ success: true, data: undefined });

      render(<Wrapper />);
      fireEvent.click(screen.getByRole('button', { name: 'Open Move' }));
      fireEvent.click(screen.getByRole('button', { name: 'Select Destination' }));
      fireEvent.click(screen.getByRole('checkbox', { name: /Merge contents/i }));
      fireEvent.click(screen.getByRole('button', { name: 'Move' }));

      await waitFor(() => {
        expect(moveFolder).toHaveBeenCalledWith(expect.objectContaining({ merge: true }));
      });
    });

    // -------------------------------------------------------------------------
    // A successful move closes the modal and shows a notification.
    // -------------------------------------------------------------------------
    test('successful move closes the modal and shows a notification', async () => {
      vi.mocked(moveFolder).mockResolvedValueOnce({ success: true, data: undefined });

      render(<Wrapper />);
      fireEvent.click(screen.getByRole('button', { name: 'Open Move' }));
      fireEvent.click(screen.getByRole('button', { name: 'Select Destination' }));
      fireEvent.click(screen.getByRole('button', { name: 'Move' }));

      await waitFor(() => {
        expect(screen.queryByRole('dialog', { name: 'Move Folder' })).not.toBeInTheDocument();
      });
      expect(notify.info).toHaveBeenCalled();
    });

    // -------------------------------------------------------------------------
    // A failed move shows an error notification and keeps the modal open so
    // the user can see what went wrong and choose a different destination.
    // -------------------------------------------------------------------------
    test('failed move shows error notification and keeps modal open', async () => {
      vi.mocked(moveFolder).mockResolvedValueOnce({ success: false, error: 'Target not found' });

      render(<Wrapper />);
      fireEvent.click(screen.getByRole('button', { name: 'Open Move' }));
      fireEvent.click(screen.getByRole('button', { name: 'Select Destination' }));
      fireEvent.click(screen.getByRole('button', { name: 'Move' }));

      await waitFor(() => {
        expect(notify.error).toHaveBeenCalledWith('Target not found');
      });
      expect(screen.getByRole('dialog', { name: 'Move Folder' })).toBeInTheDocument();
    });

    // -------------------------------------------------------------------------
    // Cancel closes the modal without calling moveFolder.
    // -------------------------------------------------------------------------
    test('Cancel closes the move modal without calling moveFolder', () => {
      render(<Wrapper />);
      fireEvent.click(screen.getByRole('button', { name: 'Open Move' }));
      fireEvent.click(screen.getByRole('button', { name: 'Cancel' }));

      expect(screen.queryByRole('dialog', { name: 'Move Folder' })).not.toBeInTheDocument();
      expect(moveFolder).not.toHaveBeenCalled();
    });
  });
});
