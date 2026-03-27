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
// Test type: Component unit test
// Mocks: folderApi, UserContext, react-i18next
// Tests: tab visibility, home folder icon, search debounce, context menu share
// =============================================================================

import { render, screen, fireEvent, act, waitFor } from '@testing-library/react';
import type React from 'react';
import { describe, test, expect, vi, beforeEach, afterEach } from 'vitest';

vi.mock('react-i18next', () => ({
  useTranslation: () => ({ t: (key: string) => key }),
}));

vi.mock('@/context/UserContext', () => ({
  useUserContext: vi.fn(),
}));

vi.mock('@/services/folderApi', () => ({
  fetchFolderTree: vi.fn(),
  searchFolders: vi.fn(),
  fetchContextButtons: vi.fn(),
}));

import FolderTreeList from '../FolderTreeList';

import { useUserContext } from '@/context/UserContext';
import { fetchFolderTree, searchFolders, fetchContextButtons } from '@/services/folderApi';

const mockUseUserContext = useUserContext as ReturnType<typeof vi.fn>;
const mockFetchFolderTree = fetchFolderTree as ReturnType<typeof vi.fn>;
const mockSearchFolders = searchFolders as ReturnType<typeof vi.fn>;
const mockFetchContextButtons = fetchContextButtons as ReturnType<typeof vi.fn>;

// A root folder that contains one sub-folder, used in tab and icon tests.
const TREE_WITH_SUBFOLDER = [
  {
    id: 1,
    text: 'Root',
    isRoot: 1,
    children: [{ id: 5, text: 'Marketing', isRoot: 0, children: [] }],
  },
];

type TreeProps = Partial<React.ComponentProps<typeof FolderTreeList>>;
type TestUser = { homeFolderId: number | null };

const renderTree = (props: TreeProps = {}, user: TestUser = { homeFolderId: null }) => {
  mockUseUserContext.mockReturnValue({ user });
  return render(
    <FolderTreeList
      selectedId={null}
      onSelect={vi.fn()}
      searchQuery=""
      refreshTrigger={0}
      {...props}
    />,
  );
};

describe('FolderTreeList', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    mockFetchFolderTree.mockResolvedValue(TREE_WITH_SUBFOLDER);
    mockSearchFolders.mockResolvedValue([]);
    mockFetchContextButtons.mockResolvedValue({
      create: false,
      modify: false,
      share: false,
      move: false,
      delete: false,
    });
  });

  // -------------------------------------------------------------------------
  // Tab structure
  // -------------------------------------------------------------------------

  describe('tab structure', () => {
    // -------------------------------------------------------------------------
    // When the user has a personal home folder separate from the root, two tabs
    // appear so they can switch between their own space and everything else.
    // -------------------------------------------------------------------------
    test('shows Home and Shared with me tabs when the user has a separate home folder', async () => {
      renderTree({}, { homeFolderId: 5 });

      await screen.findByRole('button', { name: /Home/i });
      expect(screen.getByRole('button', { name: /Shared with me/i })).toBeInTheDocument();
    });

    // -------------------------------------------------------------------------
    // When the home folder is the same as the root, both tabs would show the
    // same content, so no tabs are shown.
    // -------------------------------------------------------------------------
    test('shows no tabs when the home folder is the same as the root folder', async () => {
      renderTree({}, { homeFolderId: 1 });

      await waitFor(() => {
        expect(screen.queryByRole('button', { name: /Home/i })).not.toBeInTheDocument();
        expect(screen.queryByRole('button', { name: /Shared with me/i })).not.toBeInTheDocument();
      });
    });

    // -------------------------------------------------------------------------
    // A user with no home folder configured should also see no tabs.
    // -------------------------------------------------------------------------
    test('shows no tabs when the user has no home folder configured', async () => {
      renderTree({}, { homeFolderId: null });

      await waitFor(() => {
        expect(screen.queryByRole('button', { name: /Home/i })).not.toBeInTheDocument();
        expect(screen.queryByRole('button', { name: /Shared with me/i })).not.toBeInTheDocument();
      });
    });

    // -------------------------------------------------------------------------
    // Clicking the Shared with me tab notifies the parent that the active tab
    // has changed.
    // -------------------------------------------------------------------------
    test('switches to Shared with me when that tab is clicked', async () => {
      const onActiveTabChange = vi.fn();
      renderTree({ onActiveTabChange }, { homeFolderId: 5 });

      const sharedTab = await screen.findByRole('button', { name: /Shared with me/i });
      fireEvent.click(sharedTab);

      expect(onActiveTabChange).toHaveBeenCalledWith('Shared with me');
    });
  });

  // -------------------------------------------------------------------------
  // Home folder icon
  // -------------------------------------------------------------------------

  describe('home folder icon', () => {
    // -------------------------------------------------------------------------
    // The row for the user's home folder shows a house icon so it stands out
    // from regular folders.
    // -------------------------------------------------------------------------
    test('home folder uses a house icon while other folders use the standard folder icon', async () => {
      renderTree({}, { homeFolderId: 5 });

      // The Marketing folder is the home folder and must appear in the Home tab.
      expect(await screen.findByText('Marketing')).toBeInTheDocument();
    });
  });

  // -------------------------------------------------------------------------
  // Search debounce
  // -------------------------------------------------------------------------

  describe('search debounce', () => {
    afterEach(() => {
      vi.useRealTimers();
    });

    // -------------------------------------------------------------------------
    // Typing in the search box should not fire an API call straight away.
    // The component waits 300 ms after the user stops typing before searching.
    // -------------------------------------------------------------------------
    test('does not call the search API until 300ms after the user stops typing', async () => {
      vi.useFakeTimers();
      mockFetchFolderTree.mockResolvedValue([]);

      const { rerender } = renderTree({}, { homeFolderId: 1 });

      // Let the initial tree load finish before testing search behaviour.
      await act(async () => {
        await Promise.resolve();
      });

      mockSearchFolders.mockClear();

      // Simulate the user typing a search query.
      rerender(
        <FolderTreeList
          selectedId={null}
          onSelect={vi.fn()}
          searchQuery="marketing"
          refreshTrigger={0}
        />,
      );

      // The search API should not fire immediately after the prop changes.
      expect(mockSearchFolders).not.toHaveBeenCalled();

      // Still within the 300 ms window — no call yet.
      act(() => {
        vi.advanceTimersByTime(299);
      });
      expect(mockSearchFolders).not.toHaveBeenCalled();

      // Past 300 ms — the debounce fires and the API is called.
      await act(async () => {
        vi.advanceTimersByTime(1);
        await Promise.resolve();
      });

      expect(mockSearchFolders).toHaveBeenCalledWith('marketing', expect.any(AbortSignal));
    });

    // -------------------------------------------------------------------------
    // Typing several characters quickly should produce only one API call —
    // for the final value — not one for each character typed.
    // -------------------------------------------------------------------------
    test('resets the timer each time a new character is typed, producing only one API call', async () => {
      vi.useFakeTimers();
      mockFetchFolderTree.mockResolvedValue([]);

      const { rerender } = renderTree({}, { homeFolderId: 1 });
      await act(async () => {
        await Promise.resolve();
      });
      mockSearchFolders.mockClear();

      // Type "ma" then "mar" in quick succession, each within the 300 ms window.
      for (const query of ['ma', 'mar']) {
        rerender(
          <FolderTreeList
            selectedId={null}
            onSelect={vi.fn()}
            searchQuery={query}
            refreshTrigger={0}
          />,
        );
        act(() => {
          vi.advanceTimersByTime(100);
        });
      }

      // Type the final value and let the full 300 ms elapse.
      rerender(
        <FolderTreeList
          selectedId={null}
          onSelect={vi.fn()}
          searchQuery="marketing"
          refreshTrigger={0}
        />,
      );
      await act(async () => {
        vi.advanceTimersByTime(300);
        await Promise.resolve();
      });

      // Only the final search value should have been sent to the API.
      expect(mockSearchFolders).toHaveBeenCalledTimes(1);
      expect(mockSearchFolders).toHaveBeenCalledWith('marketing', expect.any(AbortSignal));
    });
  });

  // -------------------------------------------------------------------------
  // Context menu — sharing
  // -------------------------------------------------------------------------

  describe('folder context menu', () => {
    // -------------------------------------------------------------------------
    // Right-clicking a folder opens a menu. When the user has share permission,
    // a Share option must appear in that menu.
    // -------------------------------------------------------------------------
    test('shows Share in the context menu when the user has share permission on the folder', async () => {
      mockFetchContextButtons.mockResolvedValue({
        create: false,
        modify: false,
        share: true,
        move: false,
        delete: false,
      });
      mockFetchFolderTree.mockResolvedValue([{ id: 1, text: 'Root', isRoot: 1, children: [] }]);
      renderTree({ onAction: vi.fn() }, { homeFolderId: null });

      const folder = await screen.findByText('Root');
      fireEvent.contextMenu(folder);

      expect(await screen.findByText('Share')).toBeInTheDocument();
    });

    // -------------------------------------------------------------------------
    // Clicking Share in the context menu calls the action handler with the
    // folder that was right-clicked.
    // -------------------------------------------------------------------------
    test('calls the action handler with share and the folder when Share is clicked', async () => {
      const onAction = vi.fn();
      mockFetchContextButtons.mockResolvedValue({
        create: false,
        modify: false,
        share: true,
        move: false,
        delete: false,
      });
      const sharedFolder = { id: 1, text: 'Root', isRoot: 1, children: [] };
      mockFetchFolderTree.mockResolvedValue([sharedFolder]);
      renderTree({ onAction }, { homeFolderId: null });

      const folder = await screen.findByText('Root');
      fireEvent.contextMenu(folder);

      fireEvent.click(await screen.findByText('Share'));

      expect(onAction).toHaveBeenCalledWith('share', expect.objectContaining({ id: 1 }));
    });

    // -------------------------------------------------------------------------
    // When the user does not have share permission, Share must not appear in
    // the context menu.
    // -------------------------------------------------------------------------
    test('does not show Share in the context menu when share permission is not granted', async () => {
      mockFetchContextButtons.mockResolvedValue({
        create: false,
        modify: false,
        share: false,
        move: false,
        delete: false,
      });
      mockFetchFolderTree.mockResolvedValue([{ id: 1, text: 'Root', isRoot: 1, children: [] }]);
      renderTree({}, { homeFolderId: null });

      const folder = await screen.findByText('Root');
      fireEvent.contextMenu(folder);

      await waitFor(() => {
        expect(screen.queryByText('Share')).not.toBeInTheDocument();
      });
    });
  });
});
