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

import { screen, fireEvent, waitFor, within } from '@testing-library/react';
import type React from 'react';
import { vi, beforeEach, describe, test, expect } from 'vitest';

import {
  mockFetchPlaylists,
  mockPlaylist,
  renderPlaylistsPage,
  SINGLE_PLAYLIST,
} from './playlistTestUtils';

import { deletePlaylist } from '@/services/playlistApi';
import { testQueryClient } from '@/setupTests';

// =============================================================================
// Module mocks
// =============================================================================

vi.mock('react-i18next', () => ({
  useTranslation: () => ({ t: (key: string) => key, i18n: { changeLanguage: vi.fn() } }),
  Trans: ({ children }: { children: React.ReactNode }) => children,
}));

vi.mock('@/services/folderApi');
vi.mock('@/services/playlistApi');
vi.mock('@/services/userApi', () => ({
  fetchUserPreference: vi.fn().mockResolvedValue(null),
  saveUserPreference: vi.fn().mockResolvedValue(undefined),
}));

vi.mock('@/components/ui/modals/Modal');
vi.mock('../components/AddAndEditPlaylistModal', () => ({ default: () => null }));
vi.mock('../components/CopyPlaylistModal', () => ({ default: () => null }));
vi.mock('@/components/ui/modals/ShareModal', () => ({ default: () => null }));
vi.mock('@/components/ui/modals/MoveModal', () => ({ default: () => null }));
vi.mock('@/components/ui/FolderActionModals', () => ({ default: () => null }));

// =============================================================================
// Helpers
// =============================================================================

const openDeleteModal = async () => {
  await screen.findByText(mockPlaylist.name);

  const checkboxes = screen.getAllByRole('checkbox', { name: /Select row/i });
  fireEvent.click(checkboxes[0]!);

  const deleteBtn = await screen.findByRole('button', { name: /Delete Selected/i });
  fireEvent.click(deleteBtn);

  return screen.findByRole('dialog');
};

// =============================================================================
// Tests
// =============================================================================

describe('Delete Playlist', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    mockFetchPlaylists(SINGLE_PLAYLIST);
  });

  // ---------------------------------------------------------------------------
  // Selecting a row and clicking Delete Selected opens the confirmation modal.
  // ---------------------------------------------------------------------------
  test('Delete Selected opens the confirmation modal', async () => {
    renderPlaylistsPage();

    const dialog = await openDeleteModal();

    expect(dialog).toBeInTheDocument();
    expect(within(dialog).getByText('Delete Playlist?')).toBeInTheDocument();
    expect(within(dialog).getByText(mockPlaylist.name)).toBeInTheDocument();
  });

  // ---------------------------------------------------------------------------
  // Clicking Cancel closes the modal without calling the API.
  // ---------------------------------------------------------------------------
  test('Cancel closes the modal without calling deletePlaylist', async () => {
    renderPlaylistsPage();
    await openDeleteModal();

    fireEvent.click(screen.getByRole('button', { name: 'Cancel' }));

    await waitFor(() => {
      expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
    });
    expect(deletePlaylist).not.toHaveBeenCalled();
  });

  // ---------------------------------------------------------------------------
  // Clicking "Yes, Delete" and getting a success response closes the modal.
  // ---------------------------------------------------------------------------
  test('successful delete calls deletePlaylist and closes the modal', async () => {
    vi.mocked(deletePlaylist).mockResolvedValueOnce(undefined);

    renderPlaylistsPage();
    await openDeleteModal();

    fireEvent.click(screen.getByRole('button', { name: 'Yes, Delete' }));

    await waitFor(() => {
      expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
    });
    expect(deletePlaylist).toHaveBeenCalledTimes(1);
    expect(deletePlaylist).toHaveBeenCalledWith(mockPlaylist.playlistId);
  });

  // ---------------------------------------------------------------------------
  // Delete modal shows error when an item cannot be deleted.
  // ---------------------------------------------------------------------------
  test('failed delete keeps the modal open and shows an error', async () => {
    vi.mocked(deletePlaylist).mockRejectedValueOnce(new Error('Playlist is in use'));

    renderPlaylistsPage();
    await openDeleteModal();

    fireEvent.click(screen.getByRole('button', { name: 'Yes, Delete' }));

    await waitFor(() => {
      expect(screen.getByRole('dialog')).toBeInTheDocument();
    });
    expect(screen.getByText('{{count}} item(s) could not be deleted.')).toBeInTheDocument();
  });
});
