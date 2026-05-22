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

import { usePlaylistActions } from '../hooks/usePlaylistActions';

import {
  SINGLE_PLAYLIST,
  defaultPlaylistActions,
  mockFetchPlaylists,
  mockPlaylist,
  renderPlaylistsPage,
} from './playlistTestUtils';

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

vi.mock('../hooks/usePlaylistActions', () => ({ usePlaylistActions: vi.fn() }));
vi.mock('../hooks/usePlaylistFilterOptions', () => ({
  usePlaylistFilterOptions: vi.fn(() => ({ filterOptions: [], isLoading: false })),
}));

vi.mock('@/components/ui/FolderActionModals', () => ({ default: () => null }));
vi.mock('@/components/ui/modals/Modal');

vi.mock('../components/AddAndEditPlaylistModal', () => ({ default: () => null }));
vi.mock('@/components/ui/modals/ShareModal', () => ({ default: () => null }));
vi.mock('@/components/ui/modals/MoveModal', () => ({ default: () => null }));

// =============================================================================
// Helpers
// =============================================================================

const openCopyModal = async () => {
  await screen.findByText(mockPlaylist.name);
  fireEvent.click(screen.getByRole('button', { name: 'More actions' }));
  fireEvent.click(await screen.findByRole('button', { name: 'Make a Copy' }));
  return screen.findByRole('dialog', { name: 'Copy Playlist' });
};

// =============================================================================
// Tests
// =============================================================================

describe('Playlists page - copy playlist', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    vi.mocked(usePlaylistActions).mockReturnValue(defaultPlaylistActions());
    mockFetchPlaylists(SINGLE_PLAYLIST);
  });

  // ---------------------------------------------------------------------------
  // Clicking "Make a Copy" opens the Copy Playlist modal.
  // ---------------------------------------------------------------------------
  test('Make a Copy action opens the Copy Playlist modal', async () => {
    renderPlaylistsPage();

    const dialog = await openCopyModal();

    expect(dialog).toBeInTheDocument();
  });

  // ---------------------------------------------------------------------------
  // The name field is pre-populated with an incremented version of the playlist name.
  // incrementName('My Playlist') → 'My Playlist (1)'
  // ---------------------------------------------------------------------------
  test('name field is pre-populated with an incremented playlist name', async () => {
    renderPlaylistsPage();

    const dialog = await openCopyModal();

    expect(within(dialog).getByLabelText('New name')).toHaveValue('My Playlist (1)');
  });

  // ---------------------------------------------------------------------------
  // An empty name must be rejected — the error appears and the action is not called.
  // ---------------------------------------------------------------------------
  test('clearing the name and saving shows "Name is required"', async () => {
    const handleConfirmClone = vi.fn();
    vi.mocked(usePlaylistActions).mockReturnValue(defaultPlaylistActions({ handleConfirmClone }));

    renderPlaylistsPage();

    const dialog = await openCopyModal();
    fireEvent.change(within(dialog).getByLabelText('New name'), { target: { value: '' } });
    fireEvent.click(within(dialog).getByRole('button', { name: 'Save' }));

    expect(await screen.findByText('Name is required')).toBeInTheDocument();
    expect(handleConfirmClone).not.toHaveBeenCalled();
  });

  // ---------------------------------------------------------------------------
  // Using a name that already exists in the table must show a duplicate error.
  // mockPlaylist.name ('My Playlist') is present in the existingNames list.
  // ---------------------------------------------------------------------------
  test('entering a duplicate name shows a duplicate name error', async () => {
    const handleConfirmClone = vi.fn();
    vi.mocked(usePlaylistActions).mockReturnValue(defaultPlaylistActions({ handleConfirmClone }));

    renderPlaylistsPage();

    const dialog = await openCopyModal();
    fireEvent.change(within(dialog).getByLabelText('New name'), {
      target: { value: mockPlaylist.name },
    });
    fireEvent.click(within(dialog).getByRole('button', { name: 'Save' }));

    expect(
      await screen.findByText('A playlist item with this name already exists'),
    ).toBeInTheDocument();
    expect(handleConfirmClone).not.toHaveBeenCalled();
  });

  // ---------------------------------------------------------------------------
  // Happy path: saving with the pre-filled incremented name calls handleConfirmClone
  // with (selectedPlaylist, name, copyMediaFiles=false).
  // ---------------------------------------------------------------------------
  test('Save calls handleConfirmClone with the correct name and copyMediaFiles=false', async () => {
    const handleConfirmClone = vi.fn();
    vi.mocked(usePlaylistActions).mockReturnValue(defaultPlaylistActions({ handleConfirmClone }));

    renderPlaylistsPage();

    const dialog = await openCopyModal();
    fireEvent.click(within(dialog).getByRole('button', { name: 'Save' }));

    expect(handleConfirmClone).toHaveBeenCalledTimes(1);
    expect(handleConfirmClone).toHaveBeenCalledWith(mockPlaylist, 'My Playlist (1)', false);
  });

  // ---------------------------------------------------------------------------
  // Ticking the "Make new copies of all media" checkbox passes copyMediaFiles=true.
  // ---------------------------------------------------------------------------
  test('ticking the media copies checkbox calls handleConfirmClone with copyMediaFiles=true', async () => {
    const handleConfirmClone = vi.fn();
    vi.mocked(usePlaylistActions).mockReturnValue(defaultPlaylistActions({ handleConfirmClone }));

    renderPlaylistsPage();

    const dialog = await openCopyModal();
    fireEvent.click(
      within(dialog).getByRole('checkbox', {
        name: 'Make new copies of all media on this playlist?',
      }),
    );
    fireEvent.click(within(dialog).getByRole('button', { name: 'Save' }));

    expect(handleConfirmClone).toHaveBeenCalledTimes(1);
    expect(handleConfirmClone).toHaveBeenCalledWith(mockPlaylist, 'My Playlist (1)', true);
  });

  // ---------------------------------------------------------------------------
  // Clicking Cancel closes the modal without triggering any action.
  // ---------------------------------------------------------------------------
  test('Cancel closes the Copy Playlist modal', async () => {
    renderPlaylistsPage();

    const dialog = await openCopyModal();
    expect(dialog).toBeInTheDocument();

    fireEvent.click(within(dialog).getByRole('button', { name: 'Cancel' }));

    await waitFor(() => expect(screen.queryByRole('dialog')).not.toBeInTheDocument());
  });
});
