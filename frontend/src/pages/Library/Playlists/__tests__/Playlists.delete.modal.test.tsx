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

import { screen, fireEvent, within } from '@testing-library/react';
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

// Hooks
const confirmDelete = vi.fn();
vi.mock('../hooks/usePlaylistActions', () => ({ usePlaylistActions: vi.fn() }));
vi.mock('../hooks/usePlaylistFilterOptions', () => ({
  usePlaylistFilterOptions: vi.fn(() => ({ filterOptions: [], isLoading: false })),
}));

// UI
vi.mock('@/components/ui/FolderActionModals', () => ({ default: () => null }));
vi.mock('@/components/ui/modals/Modal');

// =============================================================================
// Tests
// =============================================================================

describe('Playlists page - delete modal', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    vi.mocked(usePlaylistActions).mockReturnValue(defaultPlaylistActions({ confirmDelete }));
    mockFetchPlaylists(SINGLE_PLAYLIST);
  });

  // -------------------------------------------------------------------------
  // Clicking Delete on a row opens the confirmation modal.
  // Delete is a dropdown action — open "More actions" first, then click Delete.
  // -------------------------------------------------------------------------
  test('opens the Delete modal when the Delete action is clicked on a row', async () => {
    renderPlaylistsPage();

    // Wait for the row to render, then open the three-dot menu.
    await screen.findByTitle('Edit');
    fireEvent.click(screen.getByRole('button', { name: 'More actions' }));
    fireEvent.click(await screen.findByRole('button', { name: 'Delete' }));

    const dialog = await screen.findByRole('dialog');
    expect(dialog).toBeInTheDocument();
    expect(within(dialog).getByText('Delete Playlist?')).toBeInTheDocument();
    expect(within(dialog).getByText(mockPlaylist.name)).toBeInTheDocument();
  });

  // -------------------------------------------------------------------------
  // Clicking Cancel closes the delete modal.
  // -------------------------------------------------------------------------
  test('closes the Delete modal when Cancel is clicked', async () => {
    renderPlaylistsPage();

    await screen.findByTitle('Edit');
    fireEvent.click(screen.getByRole('button', { name: 'More actions' }));
    fireEvent.click(await screen.findByRole('button', { name: 'Delete' }));
    expect(await screen.findByRole('dialog')).toBeInTheDocument();

    fireEvent.click(screen.getByRole('button', { name: 'Cancel' }));
    expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
  });

  // -------------------------------------------------------------------------
  // Clicking "Yes, Delete" calls confirmDelete with the playlist.
  // -------------------------------------------------------------------------
  test('calls confirmDelete when Yes, Delete is clicked', async () => {
    renderPlaylistsPage();

    await screen.findByTitle('Edit');
    fireEvent.click(screen.getByRole('button', { name: 'More actions' }));
    fireEvent.click(await screen.findByRole('button', { name: 'Delete' }));
    await screen.findByRole('dialog');

    fireEvent.click(screen.getByRole('button', { name: 'Yes, Delete' }));

    expect(confirmDelete).toHaveBeenCalledTimes(1);
    expect(confirmDelete).toHaveBeenCalledWith([mockPlaylist]);
  });

  // -------------------------------------------------------------------------
  // When delete fails the error is shown inside the open modal.
  // -------------------------------------------------------------------------
  test('shows a delete error inside the modal when deletion fails', async () => {
    vi.mocked(usePlaylistActions).mockReturnValue(
      defaultPlaylistActions({ deleteError: 'Playlist is in use by a layout', confirmDelete }),
    );

    renderPlaylistsPage();

    await screen.findByTitle('Edit');
    fireEvent.click(screen.getByRole('button', { name: 'More actions' }));
    fireEvent.click(await screen.findByRole('button', { name: 'Delete' }));

    expect(await screen.findByText('Playlist is in use by a layout')).toBeInTheDocument();
    expect(screen.getByRole('dialog')).toBeInTheDocument();
  });

  // -------------------------------------------------------------------------
  // Selecting a row and clicking Delete Selected opens the bulk delete modal.
  // -------------------------------------------------------------------------
  test('opens the bulk Delete modal when a row is selected and Delete Selected is clicked', async () => {
    renderPlaylistsPage();

    // Wait for data to load before interacting with row checkboxes.
    await screen.findByTitle('Edit');

    const checkboxes = screen.getAllByRole('checkbox', { name: /Select row/i });
    fireEvent.click(checkboxes[0]!);

    fireEvent.click(await screen.findByRole('button', { name: 'Delete Selected' }));

    expect(await screen.findByRole('dialog')).toBeInTheDocument();
    expect(screen.getByText('Delete Playlist?')).toBeInTheDocument();
  });
});
