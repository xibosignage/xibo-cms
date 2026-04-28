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

import { usePlaylistActions } from '../hooks/usePlaylistActions';

import {
  SINGLE_PLAYLIST,
  defaultPlaylistActions,
  mockFetchPlaylists,
  mockPlaylist,
  openEditModal,
  renderPlaylistsPage,
} from './playlistTestUtils';

import { updatePlaylist } from '@/services/playlistApi';
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

// Stub the media preview hook used inside AddAndEditPlaylistModal when isDynamic is on.
vi.mock('@/pages/Library/Media/hooks/useMediaData', () => ({
  useMediaData: vi.fn(() => ({ data: { rows: [], totalCount: 0 }, isFetching: false })),
}));

// Bypass the 500 ms debounce on filterMediaName inside AddAndEditPlaylistModal.
vi.mock('@/hooks/useDebounce');

vi.mock('@/components/ui/FolderActionModals', () => ({ default: () => null }));
// Stub SelectFolder to avoid folder-tree API calls inside the form.
vi.mock('@/components/ui/forms/SelectFolder', () => ({ default: () => null }));
vi.mock('@/components/ui/modals/Modal');

// =============================================================================
// Tests
// =============================================================================

describe('Playlists page - edit form fields', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    vi.mocked(usePlaylistActions).mockReturnValue(defaultPlaylistActions());
    mockFetchPlaylists(SINGLE_PLAYLIST);
  });

  // ---------------------------------------------------------------------------
  // Name field - editable text input connected to the draft via onChange.
  // The label "Name" is linked to the input via htmlFor="name".
  // ---------------------------------------------------------------------------
  test('Name field is editable and reflects typed input', async () => {
    renderPlaylistsPage();
    await openEditModal();

    const nameInput = screen.getByLabelText('Name');
    fireEvent.change(nameInput, { target: { value: 'Updated Playlist' } });

    expect(nameInput).toHaveValue('Updated Playlist');
  });

  // ---------------------------------------------------------------------------
  // Clearing the name and clicking Save shows "Name is required" and blocks
  // the API call entirely.
  // ---------------------------------------------------------------------------
  test('Save with empty name shows validation error and does not call updatePlaylist', async () => {
    renderPlaylistsPage();
    await openEditModal();

    fireEvent.change(screen.getByLabelText('Name'), { target: { value: '' } });
    fireEvent.click(screen.getByRole('button', { name: 'Save' }));

    expect(await screen.findByText('Name is required')).toBeInTheDocument();
    expect(updatePlaylist).not.toHaveBeenCalled();
  });

  // ---------------------------------------------------------------------------
  // Dynamic Playlist checkbox - toggling updates the draft.isDynamic boolean.
  // ---------------------------------------------------------------------------
  test('Dynamic Playlist checkbox toggles on and off', async () => {
    renderPlaylistsPage();
    await openEditModal();

    const dynamicCheckbox = screen.getByRole('checkbox', { name: /Dynamic Playlist/i });
    expect(dynamicCheckbox).not.toBeChecked();

    fireEvent.click(dynamicCheckbox);
    expect(dynamicCheckbox).toBeChecked();

    fireEvent.click(dynamicCheckbox);
    expect(dynamicCheckbox).not.toBeChecked();
  });

  // ---------------------------------------------------------------------------
  // Clicking Save sends the form data to the API and closes the modal.
  // ---------------------------------------------------------------------------
  test('Successful save calls updatePlaylist with correct payload and closes the modal', async () => {
    vi.mocked(updatePlaylist).mockResolvedValueOnce(mockPlaylist);

    renderPlaylistsPage();
    await openEditModal();

    fireEvent.click(screen.getByRole('button', { name: 'Save' }));

    await waitFor(() => {
      expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
    });

    expect(updatePlaylist).toHaveBeenCalledWith(mockPlaylist.playlistId, {
      name: mockPlaylist.name,
      isDynamic: false,
      tags: '',
      enableStat: mockPlaylist.enableStat,
      folderId: mockPlaylist.folderId,
    });
  });

  // ---------------------------------------------------------------------------
  // Failed save - API error keeps the modal open so the user can retry.
  // onClose is only called on success; an error sets apiError state instead.
  // ---------------------------------------------------------------------------
  test('Failed save keeps the modal open', async () => {
    vi.mocked(updatePlaylist).mockRejectedValueOnce({
      response: { data: { message: 'Playlist name already exists' } },
    });

    renderPlaylistsPage();
    await openEditModal();

    fireEvent.click(screen.getByRole('button', { name: 'Save' }));

    await waitFor(() => {
      expect(screen.getByRole('dialog')).toBeInTheDocument();
    });
  });
});
