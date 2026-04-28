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

import { screen, fireEvent, act } from '@testing-library/react';
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

import { fetchPlaylist } from '@/services/playlistApi';
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

// AddAndEditPlaylistModal stub - replaces the real form with a minimal dialog.
// These tests only check that the page opens the modal and updates the table
// row on save. For form field tests see Playlists.edit.form.test.tsx.
//
// PlaylistModals renders AddAndEditPlaylistModal conditionally
// ({isModalOpen('edit') && ...}), so the stub only appears after Edit is clicked.
vi.mock('../components/AddAndEditPlaylistModal', () => ({
  default: ({ onSave }: { onSave?: () => void }) => (
    <div role="dialog" aria-label="Edit Playlist">
      <button onClick={() => onSave?.()}>Save Playlist</button>
    </div>
  ),
}));

// =============================================================================
// Fixtures
// =============================================================================

const updatedPlaylist = { ...mockPlaylist, name: 'My Playlist - Edited' };

// =============================================================================
// Tests
// =============================================================================

describe('Playlists page - edit', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    vi.mocked(usePlaylistActions).mockReturnValue(defaultPlaylistActions());
    mockFetchPlaylists(SINGLE_PLAYLIST);
  });

  // ---------------------------------------------------------------------------
  // Clicking Edit on a row opens the Edit Playlist modal.
  // ---------------------------------------------------------------------------
  test('opens the Edit modal when the Edit action is clicked on a row', async () => {
    await act(async () => {
      renderPlaylistsPage();
    });

    await act(async () => {
      fireEvent.click(await screen.findByTitle('Edit'));
    });

    expect(await screen.findByRole('dialog', { name: 'Edit Playlist' })).toBeInTheDocument();
  });

  // ---------------------------------------------------------------------------
  // Saving an edit triggers a refresh that replaces the row name in the table.
  // ---------------------------------------------------------------------------
  test('saving an edit updates the row name in the table', async () => {
    await act(async () => {
      renderPlaylistsPage();
    });

    expect(await screen.findByText(mockPlaylist.name)).toBeInTheDocument();

    await act(async () => {
      fireEvent.click(await screen.findByTitle('Edit'));
    });

    // When the data is refreshed, return the updated playlist.
    // This happens because onSave triggers handleRefresh which invalidates
    // the ['playlist'] query and causes a refetch.
    vi.mocked(fetchPlaylist).mockResolvedValueOnce({
      rows: [updatedPlaylist],
      totalCount: 1,
    });

    await act(async () => {
      fireEvent.click(screen.getByRole('button', { name: 'Save Playlist' }));
    });

    expect(await screen.findByText(updatedPlaylist.name)).toBeInTheDocument();
  });
});
