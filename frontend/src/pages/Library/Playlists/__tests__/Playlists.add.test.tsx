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
// Tests the Add Playlist flow: opening the modal via the "New Playlist" button,
// form validation, cancel, and successful creation.
//
// Uses the real AddAndEditPlaylistModal (SelectFolder and useMediaData stubbed).
// CreatePlaylist is auto-mocked via vi.mock('@/services/playlistApi').
//
// TDD contracts:
//   - Modal title:              'Add Playlist'
//   - Empty name error:         'Name is required'
//   - Cancel:                   closes modal, createPlaylist not called
//   - Successful add:           createPlaylist called, modal closes
//   - API error:                modal stays open
// =============================================================================

import { screen, fireEvent, waitFor } from '@testing-library/react';
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

import { createPlaylist } from '@/services/playlistApi';
import { testQueryClient } from '@/setupTests';

// ─── Mocks ────────────────────────────────────────────────────────────────────

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

// Bypass 500ms debounce so form changes are reflected immediately.
vi.mock('@/hooks/useDebounce');

// Stub SelectFolder to avoid folder-tree API calls inside the form.
vi.mock('@/components/ui/forms/SelectFolder', () => ({ default: () => null }));

// Stub useMediaData used in the dynamic preview section.
vi.mock('@/pages/Library/Media/hooks/useMediaData', () => ({
  useMediaData: vi.fn(() => ({ data: { rows: [], totalCount: 0 }, isFetching: false })),
}));

vi.mock('@/components/ui/FolderActionModals', () => ({ default: () => null }));
vi.mock('@/components/ui/modals/Modal');

vi.mock('../components/CopyPlaylistModal', () => ({ default: () => null }));
vi.mock('@/components/ui/modals/ShareModal', () => ({ default: () => null }));
vi.mock('@/components/ui/modals/MoveModal', () => ({ default: () => null }));

// ─── Helpers ──────────────────────────────────────────────────────────────────

// Waits for the New Playlist button to be enabled, clicks it, and returns the
// Add Playlist dialog. The button is disabled until fetchContextButtons resolves
// (folderApi mock returns { create: true } by default).
const openAddModal = async () => {
  const button = await screen.findByRole('button', { name: 'New Playlist' });
  await waitFor(() => expect(button).not.toBeDisabled());
  fireEvent.click(button);
  return screen.findByRole('dialog', { name: 'Add Playlist' });
};

// ─── Tests ────────────────────────────────────────────────────────────────────

describe('Playlists page - add playlist', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    vi.mocked(usePlaylistActions).mockReturnValue(defaultPlaylistActions());
    mockFetchPlaylists(SINGLE_PLAYLIST);
  });

  // -------------------------------------------------------------------------
  // Clicking New Playlist opens the Add Playlist modal.
  // -------------------------------------------------------------------------
  test('clicking New Playlist opens the Add Playlist modal', async () => {
    renderPlaylistsPage();

    const dialog = await openAddModal();

    expect(dialog).toBeInTheDocument();
  });

  // -------------------------------------------------------------------------
  // The Name field starts empty for a new playlist.
  // -------------------------------------------------------------------------
  test('Name field starts empty in the Add modal', async () => {
    renderPlaylistsPage();
    await openAddModal();

    expect(screen.getByLabelText('Name')).toHaveValue('');
  });

  // -------------------------------------------------------------------------
  // Submitting with an empty name shows a validation error and blocks the
  // API call entirely.
  // -------------------------------------------------------------------------
  test('Save with empty name shows validation error and does not call createPlaylist', async () => {
    renderPlaylistsPage();
    await openAddModal();

    fireEvent.click(screen.getByRole('button', { name: 'Save' }));

    expect(await screen.findByText('Name is required')).toBeInTheDocument();
    expect(createPlaylist).not.toHaveBeenCalled();
  });

  // -------------------------------------------------------------------------
  // Cancel closes the modal without touching the API.
  // -------------------------------------------------------------------------
  test('Cancel closes the Add modal without calling createPlaylist', async () => {
    renderPlaylistsPage();
    await openAddModal();

    fireEvent.click(screen.getByRole('button', { name: 'Cancel' }));

    await waitFor(() => {
      expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
    });
    expect(createPlaylist).not.toHaveBeenCalled();
  });

  // -------------------------------------------------------------------------
  // Happy path: entering a name and saving calls createPlaylist and closes
  // the modal.
  // -------------------------------------------------------------------------
  test('successful add calls createPlaylist and closes the modal', async () => {
    vi.mocked(createPlaylist).mockResolvedValueOnce(mockPlaylist);

    renderPlaylistsPage();
    await openAddModal();

    fireEvent.change(screen.getByLabelText('Name'), { target: { value: 'Brand New Playlist' } });
    fireEvent.click(screen.getByRole('button', { name: 'Save' }));

    await waitFor(() => {
      expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
    });

    expect(createPlaylist).toHaveBeenCalledTimes(1);
    expect(createPlaylist).toHaveBeenCalledWith(
      expect.objectContaining({ name: 'Brand New Playlist' }),
    );
  });

  // -------------------------------------------------------------------------
  // An API error keeps the modal open so the user can retry.
  // -------------------------------------------------------------------------
  test('API error on save keeps the Add modal open', async () => {
    vi.mocked(createPlaylist).mockRejectedValueOnce({
      isAxiosError: true,
      response: { data: { message: 'Name already exists' } },
    });

    renderPlaylistsPage();
    await openAddModal();

    fireEvent.change(screen.getByLabelText('Name'), { target: { value: 'Taken Name' } });
    fireEvent.click(screen.getByRole('button', { name: 'Save' }));

    await waitFor(() => {
      expect(screen.getByRole('dialog')).toBeInTheDocument();
    });
  });
});
