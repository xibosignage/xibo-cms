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

import { QueryClientProvider } from '@tanstack/react-query';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import type React from 'react';
import { MemoryRouter } from 'react-router-dom';
import { vi, beforeEach, describe, test, expect } from 'vitest';

import Playlists from '../Playlists';
import { usePlaylistActions } from '../hooks/usePlaylistActions';

import {
  SINGLE_PLAYLIST,
  defaultPlaylistActions,
  mockFetchPlaylists,
  mockPlaylist,
  mockUser,
  renderPlaylistsPage,
} from './playlistTestUtils';

import { UserProvider } from '@/context/UserContext';
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
vi.mock('../components/CopyPlaylistModal', () => ({ default: () => null }));

vi.mock('@/components/ui/modals/MoveModal', () => ({
  default: ({ onClose }: { onClose: () => void }) => (
    <div role="dialog" aria-label="Move">
      <button onClick={onClose}>Cancel</button>
    </div>
  ),
}));

vi.mock('@/components/ui/modals/ShareModal', () => ({
  default: ({ onClose }: { onClose: () => void }) => (
    <div role="dialog" aria-label="Share Playlist">
      <button onClick={onClose}>Cancel</button>
    </div>
  ),
}));

vi.mock('@/components/ui/modals/ScheduleEventModal', () => ({ default: () => null }));

// =============================================================================
// Helpers
// =============================================================================

const selectFirstRow = async () => {
  await screen.findByText(mockPlaylist.name);
  const checkboxes = screen.getAllByRole('checkbox', { name: /Select row/i });
  fireEvent.click(checkboxes[0]!);
};

const renderWithNoFolderView = () => {
  testQueryClient.setQueryData(['userPref', 'playlist_page'], null);
  return render(
    <QueryClientProvider client={testQueryClient}>
      <UserProvider initialUser={{ ...mockUser, features: {} }}>
        <MemoryRouter>
          <Playlists />
        </MemoryRouter>
      </UserProvider>
    </QueryClientProvider>,
  );
};

// =============================================================================
// Tests
// =============================================================================

describe('Playlists page - bulk actions', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    vi.mocked(usePlaylistActions).mockReturnValue(defaultPlaylistActions());
    mockFetchPlaylists(SINGLE_PLAYLIST);
  });

  // ---------------------------------------------------------------------------
  // Selecting a row reveals the Delete Selected bulk action.
  // ---------------------------------------------------------------------------
  test('selecting a row reveals the Delete Selected bulk action', async () => {
    renderPlaylistsPage();

    await selectFirstRow();

    expect(await screen.findByRole('button', { name: /Delete Selected/i })).toBeInTheDocument();
  });

  // ---------------------------------------------------------------------------
  // Selecting a row reveals the Share bulk action.
  // ---------------------------------------------------------------------------
  test('selecting a row reveals the Share bulk action', async () => {
    renderPlaylistsPage();

    await selectFirstRow();

    expect(await screen.findByRole('button', { name: /Share/i })).toBeInTheDocument();
  });

  // ---------------------------------------------------------------------------
  // Move bulk action is visible when the user has folder.view feature.
  // mockUser has 'folder.view': true by default.
  // ---------------------------------------------------------------------------
  test('Move bulk action is visible when the user has folder.view feature', async () => {
    renderPlaylistsPage();

    await selectFirstRow();

    expect(await screen.findByRole('button', { name: /^Move$/i })).toBeInTheDocument();
  });

  // ---------------------------------------------------------------------------
  // Move bulk action is hidden when the user lacks folder.view feature.
  // ---------------------------------------------------------------------------
  test('Move bulk action is hidden when the user lacks folder.view feature', async () => {
    renderWithNoFolderView();

    await screen.findByText(mockPlaylist.name);
    const checkboxes = screen.getAllByRole('checkbox', { name: /Select row/i });
    fireEvent.click(checkboxes[0]!);

    // Wait for at least one bulk action to appear to confirm the toolbar is rendered.
    await screen.findByRole('button', { name: /Delete Selected/i });
    expect(screen.queryByRole('button', { name: /^Move$/i })).not.toBeInTheDocument();
  });

  // ---------------------------------------------------------------------------
  // Deselecting a row hides the bulk action toolbar.
  // ---------------------------------------------------------------------------
  test('deselecting a row hides the bulk action toolbar', async () => {
    renderPlaylistsPage();

    await selectFirstRow();
    expect(await screen.findByRole('button', { name: /Delete Selected/i })).toBeInTheDocument();

    // Click the checkbox again to deselect the row.
    const checkboxes = screen.getAllByRole('checkbox', { name: /Select row/i });
    fireEvent.click(checkboxes[0]!);

    await waitFor(() => {
      expect(screen.queryByRole('button', { name: /Delete Selected/i })).not.toBeInTheDocument();
    });
  });

  // ---------------------------------------------------------------------------
  // Clicking the bulk Move button opens the Move modal.
  // ---------------------------------------------------------------------------
  test('clicking the bulk Move button opens the Move modal', async () => {
    renderPlaylistsPage();

    await selectFirstRow();
    fireEvent.click(await screen.findByRole('button', { name: /^Move$/i }));

    expect(await screen.findByRole('dialog', { name: 'Move' })).toBeInTheDocument();
  });

  // ---------------------------------------------------------------------------
  // Clicking the bulk Share button opens the Share modal.
  // ---------------------------------------------------------------------------
  test('clicking the bulk Share button opens the Share modal', async () => {
    renderPlaylistsPage();

    await selectFirstRow();
    fireEvent.click(await screen.findByRole('button', { name: /Share/i }));

    expect(await screen.findByRole('dialog', { name: 'Share Playlist' })).toBeInTheDocument();
  });
});
