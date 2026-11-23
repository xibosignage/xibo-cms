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

// =============================================================================
// Test type: Component test
// SelectFolder — the folder picker used by most add/edit forms. Covers what
// the picker shows, what it reports through onSelect, Clear (which resets to
// the user's home folder rather than to nothing), and the New Folder entry.
//
// Mocked boundaries: folderApi, the user/permission hooks, and the folder
// action hook (its onSuccess is captured so a test can play "folder created").
// FolderTreeList is a stub with one "Design" folder and a "Rename Design"
// action, and it prints the search text it receives — FolderTreeList has its
// own suite.
//
// Accessibility fallbacks (xibosignage/xibo#4071):
// - the label is not tied to the trigger, so the trigger is found by the name
//   it shows (placeholder or folder name)
// - the folder search box has no accessible name; it is the only textbox
//   while the panel is open
// =============================================================================

import { screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { type ComponentProps } from 'react';
import { vi, beforeEach, afterEach, describe, test, expect } from 'vitest';

import { mockFolder, mockHomeFolder, mockNoFolderUser, mockUser } from './fixtures/folders';
import { renderWithQueryClient } from './helpers/renderWithQueryClient';

import SelectFolder from '@/components/ui/forms/SelectFolder';
import { useUserContext } from '@/context/UserContext';
import { usePermissions } from '@/hooks/usePermissions';
import { fetchContextButtons, fetchFolderById } from '@/services/folderApi';
import { testQueryClient } from '@/setupTests';
import type { Folder } from '@/types/folder';

const folderActions = vi.hoisted(() => ({
  openAction: vi.fn(),
}));

vi.mock('@/context/UserContext', () => ({ useUserContext: vi.fn() }));
vi.mock('@/hooks/usePermissions', () => ({ usePermissions: vi.fn() }));
vi.mock('@/services/folderApi', () => ({
  fetchContextButtons: vi.fn(),
  fetchFolderById: vi.fn(),
}));
vi.mock('@/hooks/useFolderActions', () => ({
  useFolderActions: ({
    onSuccess,
  }: {
    onSuccess?: (target?: { id: number; text: string }) => void;
  }) => {
    return { openAction: folderActions.openAction, onSuccess };
  },
}));
// Stands in for the create-folder modal: its one button plays "the user saved
// a new folder called Fresh", which SelectFolder hears through onSuccess.
vi.mock('@/components/ui/FolderActionModals', () => ({
  default: ({
    folderActions: actions,
  }: {
    folderActions: { onSuccess?: (target?: { id: number; text: string }) => void };
  }) => (
    <button type="button" onClick={() => actions.onSuccess?.({ id: 99, text: 'Fresh' })}>
      Finish creating folder
    </button>
  ),
}));
vi.mock('@/components/ui/FolderTreeList', () => ({
  default: ({
    onSelect,
    onAction,
    searchQuery,
    customSlot,
  }: {
    onSelect: (folder: Folder) => void;
    onAction?: (action: string, folder: Folder) => void;
    searchQuery: string;
    customSlot?: React.ReactNode;
  }) => {
    const design = mockFolder;
    return (
      <div>
        {customSlot}
        <p>Tree search: {searchQuery || '(none)'}</p>
        <button onClick={() => onSelect(design)}>Design</button>
        <button onClick={() => onAction?.('rename', design)}>Rename Design</button>
      </div>
    );
  },
}));

const renderSelectFolder = (props: Partial<ComponentProps<typeof SelectFolder>> = {}) => {
  const onSelect = vi.fn();
  const result = renderWithQueryClient(<SelectFolder onSelect={onSelect} {...props} />);
  return { ...result, onSelect };
};

const picker = (name: string | RegExp) => screen.getByRole('button', { name });
const panelHeading = () => screen.queryByText('Select Folder');

beforeEach(() => {
  testQueryClient.clear();
  vi.clearAllMocks();
  vi.mocked(useUserContext).mockReturnValue({ user: mockUser } as unknown as ReturnType<
    typeof useUserContext
  >);
  vi.mocked(usePermissions).mockReturnValue({ canViewFolders: true } as unknown as ReturnType<
    typeof usePermissions
  >);
  vi.mocked(fetchContextButtons).mockResolvedValue({ create: false });
  vi.mocked(fetchFolderById).mockImplementation(async (id: number) =>
    id === mockHomeFolder.id ? mockHomeFolder : mockFolder,
  );
});

// Undo the console.error spies some tests install.
afterEach(() => {
  vi.restoreAllMocks();
});

describe('SelectFolder', () => {
  describe('what the picker shows', () => {
    test('is hidden from users who cannot view folders, unless the check is turned off', () => {
      vi.mocked(usePermissions).mockReturnValue({ canViewFolders: false } as unknown as ReturnType<
        typeof usePermissions
      >);
      const { unmount } = renderSelectFolder();
      expect(screen.queryByText('Select folder location')).not.toBeInTheDocument();
      unmount();

      renderSelectFolder({ enforceViewPermission: false });
      expect(screen.getByText('Select folder location')).toBeInTheDocument();
    });

    test('shows the default label, a custom label and the Optional marker', () => {
      const { unmount } = renderSelectFolder();
      expect(screen.getByText('Select folder location')).toBeInTheDocument();
      unmount();

      renderSelectFolder({ label: 'Destination', optional: true });
      expect(screen.getByText('Destination')).toBeInTheDocument();
      expect(screen.getByText('Optional')).toBeInTheDocument();
    });

    test('shows a placeholder and no clear button when nothing is chosen', () => {
      const { unmount } = renderSelectFolder();
      expect(picker('Select a folder')).toBeInTheDocument();
      expect(screen.queryByRole('button', { name: 'Clear selection' })).not.toBeInTheDocument();
      unmount();

      renderSelectFolder({ placeholder: 'Choose…' });
      expect(picker('Choose…')).toBeInTheDocument();
    });

    test('shows a folder name given by the caller without looking it up', () => {
      renderSelectFolder({ selectedId: 42, selectedText: 'Design' });
      expect(picker('Design')).toBeInTheDocument();
      expect(fetchFolderById).not.toHaveBeenCalled();
    });

    test('looks up the name when only an id is given, showing Loading... meanwhile', async () => {
      let resolve!: (folder: Folder) => void;
      vi.mocked(fetchFolderById).mockReturnValue(
        new Promise<Folder>((res) => {
          resolve = res;
        }),
      );
      renderSelectFolder({ selectedId: 42 });

      expect(picker('Loading...')).toBeInTheDocument();
      expect(fetchFolderById).toHaveBeenCalledWith(42);

      resolve(mockFolder);
      await screen.findByRole('button', { name: 'Design' });
    });

    test('falls back to "Folder #id" when the name lookup fails', async () => {
      vi.spyOn(console, 'error').mockImplementation(() => {});
      vi.mocked(fetchFolderById).mockRejectedValue(new Error('not found'));
      renderSelectFolder({ selectedId: 77 });

      await screen.findByRole('button', { name: 'Folder #77' });
      expect(fetchFolderById).toHaveBeenCalledWith(77);
    });

    test('shows help text', () => {
      renderSelectFolder({ helpText: 'Where the media is stored' });
      expect(screen.getByText('Where the media is stored')).toBeInTheDocument();
    });
  });

  describe('choosing a folder', () => {
    test('clicking the picker opens the folder panel with a search box', async () => {
      const user = userEvent.setup();
      renderSelectFolder();

      await user.click(picker('Select a folder'));

      await screen.findByText('Select Folder');
      expect(screen.getByRole('textbox')).toBeInTheDocument();
    });

    test('choosing a folder reports it, closes the panel and shows its name', async () => {
      const user = userEvent.setup();
      const { onSelect } = renderSelectFolder();

      await user.click(picker('Select a folder'));
      await user.click(await screen.findByRole('button', { name: 'Design' }));

      expect(onSelect).toHaveBeenCalledTimes(1);
      expect(onSelect).toHaveBeenCalledWith({ id: 42, text: 'Design' });
      await waitFor(() => expect(panelHeading()).not.toBeInTheDocument());
      expect(picker('Design')).toBeInTheDocument();
    });

    test('search text reaches the folder tree and is cleared when the panel closes', async () => {
      const user = userEvent.setup();
      renderSelectFolder();

      await user.click(picker('Select a folder'));
      await user.type(await screen.findByRole('textbox'), 'Des');
      expect(screen.getByText('Tree search: Des')).toBeInTheDocument();

      await user.keyboard('{Escape}');
      await waitFor(() => expect(panelHeading()).not.toBeInTheDocument());

      await user.click(picker('Select a folder'));
      expect(await screen.findByRole('textbox')).toHaveValue('');
      expect(screen.getByText('Tree search: (none)')).toBeInTheDocument();
    });

    test('folder actions from the tree are passed on to the caller', async () => {
      const user = userEvent.setup();
      const onAction = vi.fn();
      renderSelectFolder({ onAction });

      await user.click(picker('Select a folder'));
      await user.click(await screen.findByRole('button', { name: 'Rename Design' }));

      expect(onAction).toHaveBeenCalledWith('rename', expect.objectContaining({ id: 42 }));
    });
  });

  describe('clearing', () => {
    test('Clear resets to the home folder, looking its name up only the first time', async () => {
      const user = userEvent.setup();
      const { onSelect } = renderSelectFolder({ selectedId: 42, selectedText: 'Design' });

      await user.click(screen.getByRole('button', { name: 'Clear selection' }));
      await waitFor(() => expect(onSelect).toHaveBeenCalledWith({ id: 5, text: 'My Home' }));
      await screen.findByRole('button', { name: 'My Home' });
      expect(panelHeading()).not.toBeInTheDocument();

      await user.click(screen.getByRole('button', { name: 'Clear selection' }));
      expect(onSelect).toHaveBeenCalledTimes(2);
      expect(onSelect).toHaveBeenLastCalledWith({ id: 5, text: 'My Home' });
      expect(fetchFolderById).toHaveBeenCalledTimes(1);
      expect(fetchFolderById).toHaveBeenCalledWith(5);
    });

    test('if the home folder lookup fails, the home folder is still chosen by id', async () => {
      const user = userEvent.setup();
      vi.spyOn(console, 'error').mockImplementation(() => {});
      vi.mocked(fetchFolderById).mockRejectedValue(new Error('not found'));
      const { onSelect } = renderSelectFolder({ selectedId: 42, selectedText: 'Design' });

      await user.click(screen.getByRole('button', { name: 'Clear selection' }));

      await waitFor(() => expect(onSelect).toHaveBeenCalledWith({ id: 5, text: '5' }));
      expect(fetchFolderById).toHaveBeenCalledWith(5);
    });

    test('folder 1 is used when the user has no home folder', async () => {
      const user = userEvent.setup();
      vi.mocked(useUserContext).mockReturnValue({ user: mockNoFolderUser } as unknown as ReturnType<
        typeof useUserContext
      >);
      renderSelectFolder({ selectedId: 42, selectedText: 'Design' });

      await user.click(screen.getByRole('button', { name: 'Clear selection' }));

      await waitFor(() => expect(fetchFolderById).toHaveBeenCalledWith(1));
    });
  });

  describe('disabled', () => {
    test('a disabled picker cannot be opened or cleared', async () => {
      // The wrapper sets pointer-events: none; let userEvent click anyway so
      // the test proves the click does nothing, rather than never happening.
      const user = userEvent.setup({ pointerEventsCheck: 0 });
      renderSelectFolder({ selectedId: 42, selectedText: 'Design', disabled: true });

      await user.click(picker('Design'));

      expect(panelHeading()).not.toBeInTheDocument();
      expect(screen.queryByRole('button', { name: 'Clear selection' })).not.toBeInTheDocument();
    });
  });

  describe('new folder', () => {
    test('"New Folder" appears only when the user may create folders in their home folder', async () => {
      const user = userEvent.setup();
      const { unmount } = renderSelectFolder();

      await user.click(picker('Select a folder'));
      await waitFor(() => expect(fetchContextButtons).toHaveBeenCalled());
      expect(screen.queryByRole('button', { name: 'New Folder' })).not.toBeInTheDocument();
      unmount();

      testQueryClient.clear();
      vi.mocked(fetchContextButtons).mockResolvedValue({ create: true });
      renderSelectFolder();
      await user.click(picker('Select a folder'));

      expect(await screen.findByRole('button', { name: 'New Folder' })).toBeEnabled();
      expect(fetchContextButtons).toHaveBeenLastCalledWith(5, expect.anything());
    });

    test('"New Folder" closes the panel and starts creating a folder in the home folder', async () => {
      const user = userEvent.setup();
      vi.mocked(fetchContextButtons).mockResolvedValue({ create: true });
      renderSelectFolder();

      await user.click(picker('Select a folder'));
      await user.click(await screen.findByRole('button', { name: 'New Folder' }));

      expect(folderActions.openAction).toHaveBeenCalledTimes(1);
      expect(folderActions.openAction).toHaveBeenCalledWith(
        'create',
        expect.objectContaining({ id: 5 }),
      );
      await waitFor(() => expect(panelHeading()).not.toBeInTheDocument());
    });

    test('a newly created folder becomes the chosen folder', async () => {
      const user = userEvent.setup();
      const { onSelect } = renderSelectFolder();

      await user.click(screen.getByRole('button', { name: 'Finish creating folder' }));

      expect(onSelect).toHaveBeenCalledWith({ id: 99, text: 'Fresh' });
      await screen.findByRole('button', { name: 'Fresh' });
    });
  });
});
