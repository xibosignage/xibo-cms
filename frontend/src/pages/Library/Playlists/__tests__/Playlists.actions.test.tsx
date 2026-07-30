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
import { render, screen, fireEvent } from '@testing-library/react';
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

vi.mock('../components/CopyPlaylistModal', () => ({
  default: ({ onClose }: { onClose: () => void }) => (
    <div role="dialog" aria-label="Copy Playlist">
      <button onClick={onClose}>Cancel</button>
    </div>
  ),
}));

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

const openDropdownAction = async (label: string) => {
  await screen.findByText(mockPlaylist.name);
  fireEvent.click(screen.getByRole('button', { name: 'More actions' }));
  fireEvent.click(await screen.findByRole('button', { name: label }));
};

const renderWithScheduleFeature = () => {
  testQueryClient.setQueryData(['userPref', 'playlist_page'], null);
  return render(
    <QueryClientProvider client={testQueryClient}>
      <UserProvider
        initialUser={{ ...mockUser, features: { 'folder.view': true, 'schedule.add': true } }}
      >
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

describe('Playlists page - row actions', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    vi.mocked(usePlaylistActions).mockReturnValue(defaultPlaylistActions());
    mockFetchPlaylists(SINGLE_PLAYLIST);
  });

  // ---------------------------------------------------------------------------
  // Dropdown menu contains the expected actions.
  // Edit appears twice (quick action icon + dropdown item), so use getAllByRole.
  // ---------------------------------------------------------------------------
  test('More actions dropdown shows Edit, Make a Copy, Move, Share, Timeline, and Delete', async () => {
    renderPlaylistsPage();

    await screen.findByText(mockPlaylist.name);
    fireEvent.click(screen.getByRole('button', { name: 'More actions' }));

    // Edit appears as both a quick-action icon button and a dropdown item.
    expect((await screen.findAllByRole('button', { name: 'Edit' })).length).toBeGreaterThan(0);
    expect(screen.getByRole('button', { name: 'Make a Copy' })).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Move' })).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Share' })).toBeInTheDocument();
    // Timeline also appears as both a quick-action icon button and a dropdown item.
    expect(screen.getAllByRole('button', { name: 'Timeline' }).length).toBeGreaterThan(0);
    expect(screen.getByRole('button', { name: 'Delete' })).toBeInTheDocument();
  });

  // ---------------------------------------------------------------------------
  // Schedule is absent by default (mockUser has no schedule.add feature).
  // ---------------------------------------------------------------------------
  test('Schedule action is absent from the dropdown when the user does not have schedule.add feature', async () => {
    renderPlaylistsPage();

    await screen.findByText(mockPlaylist.name);
    fireEvent.click(screen.getByRole('button', { name: 'More actions' }));

    // Wait for a known always-present action to confirm the dropdown is open.
    await screen.findByRole('button', { name: 'Make a Copy' });
    expect(screen.queryByRole('button', { name: 'Schedule' })).not.toBeInTheDocument();
  });

  // ---------------------------------------------------------------------------
  // Schedule appears when the user has schedule.add feature.
  // ---------------------------------------------------------------------------
  test('Schedule action is present in the dropdown when user has schedule.add feature', async () => {
    renderWithScheduleFeature();

    await screen.findByText(mockPlaylist.name);
    fireEvent.click(screen.getByRole('button', { name: 'More actions' }));

    expect(await screen.findByRole('button', { name: 'Schedule' })).toBeInTheDocument();
  });

  // ---------------------------------------------------------------------------
  // Clicking "Make a Copy" opens the Copy Playlist modal.
  // ---------------------------------------------------------------------------
  test('clicking Make a Copy opens the Copy Playlist modal', async () => {
    renderPlaylistsPage();

    await openDropdownAction('Make a Copy');

    expect(await screen.findByRole('dialog', { name: 'Copy Playlist' })).toBeInTheDocument();
  });

  // ---------------------------------------------------------------------------
  // Clicking "Move" opens the Move modal.
  // ---------------------------------------------------------------------------
  test('clicking Move opens the Move modal', async () => {
    renderPlaylistsPage();

    await openDropdownAction('Move');

    expect(await screen.findByRole('dialog', { name: 'Move' })).toBeInTheDocument();
  });

  // ---------------------------------------------------------------------------
  // Clicking "Share" opens the Share modal.
  // ---------------------------------------------------------------------------
  test('clicking Share opens the Share modal', async () => {
    renderPlaylistsPage();

    await openDropdownAction('Share');

    expect(await screen.findByRole('dialog', { name: 'Share Playlist' })).toBeInTheDocument();
  });

  // ---------------------------------------------------------------------------
  // Gating parity checks against release44 (see bugs-found/playlist-bugs/).
  // Each of these pins one specific gate so a future regression on that gate
  // alone fails here, rather than only being caught by a broad manual audit.
  // ---------------------------------------------------------------------------

  test('Edit, Make a Copy, Move, Timeline, and Enable Stats Collection are all absent when the user lacks edit permission on the row', async () => {
    mockFetchPlaylists({
      rows: [{ ...mockPlaylist, userPermissions: { ...mockPlaylist.userPermissions, edit: 0 } }],
      totalCount: 1,
    });
    renderPlaylistsPage();

    await screen.findByText(mockPlaylist.name);
    fireEvent.click(screen.getByRole('button', { name: 'More actions' }));

    // Anchor on an action that doesn't depend on edit permission, to confirm
    // the dropdown is open before asserting the others are missing.
    await screen.findByRole('button', { name: 'Delete' });
    expect(screen.queryByRole('button', { name: 'Edit' })).not.toBeInTheDocument();
    expect(screen.queryByRole('button', { name: 'Make a Copy' })).not.toBeInTheDocument();
    expect(screen.queryByRole('button', { name: 'Move' })).not.toBeInTheDocument();
    expect(screen.queryByRole('button', { name: 'Timeline' })).not.toBeInTheDocument();
    expect(
      screen.queryByRole('button', { name: 'Enable Stats Collection' }),
    ).not.toBeInTheDocument();
  });

  test('Timeline is absent for a Dynamic playlist even when the user has edit permission', async () => {
    mockFetchPlaylists({
      rows: [{ ...mockPlaylist, isDynamic: true }],
      totalCount: 1,
    });
    renderPlaylistsPage();

    await screen.findByText(mockPlaylist.name);
    fireEvent.click(screen.getByRole('button', { name: 'More actions' }));

    await screen.findAllByRole('button', { name: 'Edit' });
    expect(screen.queryByRole('button', { name: 'Timeline' })).not.toBeInTheDocument();
  });

  // Known bug — the bulk Move button is correctly gated on the Folders
  // feature, but this per-row one isn't. See
  // bugs-found/playlist-bugs/permission-gating/playlist-move-row-action-not-folder-view-gated.md.
  // Kept as test.fails (not test.skip) so a real fix surfaces here immediately.
  test.fails(
    'Move is absent from the row dropdown for a user lacking the Folders feature',
    async () => {
      testQueryClient.setQueryData(['userPref', 'playlist_page'], null);
      render(
        <QueryClientProvider client={testQueryClient}>
          <UserProvider
            initialUser={{ ...mockUser, features: { ...mockUser.features, 'folder.view': false } }}
          >
            <MemoryRouter>
              <Playlists />
            </MemoryRouter>
          </UserProvider>
        </QueryClientProvider>,
      );

      await screen.findByText(mockPlaylist.name);
      fireEvent.click(screen.getByRole('button', { name: 'More actions' }));

      await screen.findAllByRole('button', { name: 'Edit' });
      expect(screen.queryByRole('button', { name: 'Move' })).not.toBeInTheDocument();
    },
  );

  test('Delete is absent when the user lacks delete permission on the row', async () => {
    mockFetchPlaylists({
      rows: [{ ...mockPlaylist, userPermissions: { ...mockPlaylist.userPermissions, delete: 0 } }],
      totalCount: 1,
    });
    renderPlaylistsPage();

    await screen.findByText(mockPlaylist.name);
    fireEvent.click(screen.getByRole('button', { name: 'More actions' }));

    await screen.findAllByRole('button', { name: 'Edit' });
    expect(screen.queryByRole('button', { name: 'Delete' })).not.toBeInTheDocument();
  });

  test('Share is absent when the user lacks modifyPermissions on the row', async () => {
    mockFetchPlaylists({
      rows: [
        {
          ...mockPlaylist,
          userPermissions: { ...mockPlaylist.userPermissions, modifyPermissions: 0 },
        },
      ],
      totalCount: 1,
    });
    renderPlaylistsPage();

    await screen.findByText(mockPlaylist.name);
    fireEvent.click(screen.getByRole('button', { name: 'More actions' }));

    await screen.findAllByRole('button', { name: 'Edit' });
    expect(screen.queryByRole('button', { name: 'Share' })).not.toBeInTheDocument();
  });

  test('Share is absent when the user lacks the user.sharing feature, even with modifyPermissions on the row', async () => {
    testQueryClient.setQueryData(['userPref', 'playlist_page'], null);
    render(
      <QueryClientProvider client={testQueryClient}>
        <UserProvider
          initialUser={{ ...mockUser, features: { ...mockUser.features, 'user.sharing': false } }}
        >
          <MemoryRouter>
            <Playlists />
          </MemoryRouter>
        </UserProvider>
      </QueryClientProvider>,
    );

    await screen.findByText(mockPlaylist.name);
    fireEvent.click(screen.getByRole('button', { name: 'More actions' }));

    await screen.findAllByRole('button', { name: 'Edit' });
    expect(screen.queryByRole('button', { name: 'Share' })).not.toBeInTheDocument();
  });

  test('Schedule appears when the user lacks edit permission but SCHEDULE_WITH_VIEW_PERMISSION is on', async () => {
    mockFetchPlaylists({
      rows: [{ ...mockPlaylist, userPermissions: { ...mockPlaylist.userPermissions, edit: 0 } }],
      totalCount: 1,
    });
    testQueryClient.setQueryData(['userPref', 'playlist_page'], null);
    render(
      <QueryClientProvider client={testQueryClient}>
        <UserProvider
          initialUser={{
            ...mockUser,
            features: { ...mockUser.features, 'schedule.add': true },
            settings: { ...mockUser.settings, SCHEDULE_WITH_VIEW_PERMISSION: '1' },
          }}
        >
          <MemoryRouter>
            <Playlists />
          </MemoryRouter>
        </UserProvider>
      </QueryClientProvider>,
    );

    await screen.findByText(mockPlaylist.name);
    fireEvent.click(screen.getByRole('button', { name: 'More actions' }));

    expect(await screen.findByRole('button', { name: 'Schedule' })).toBeInTheDocument();
  });

  test('Usage Report is absent when the user lacks both schedule.view and layout.view features', async () => {
    testQueryClient.setQueryData(['userPref', 'playlist_page'], null);
    render(
      <QueryClientProvider client={testQueryClient}>
        <UserProvider
          initialUser={{
            ...mockUser,
            features: { ...mockUser.features, 'schedule.view': false, 'layout.view': false },
          }}
        >
          <MemoryRouter>
            <Playlists />
          </MemoryRouter>
        </UserProvider>
      </QueryClientProvider>,
    );

    await screen.findByText(mockPlaylist.name);
    fireEvent.click(screen.getByRole('button', { name: 'More actions' }));

    await screen.findAllByRole('button', { name: 'Edit' });
    expect(screen.queryByRole('button', { name: 'Usage Report' })).not.toBeInTheDocument();
  });

  test('Usage Report appears when the user has layout.view even without schedule.view', async () => {
    testQueryClient.setQueryData(['userPref', 'playlist_page'], null);
    render(
      <QueryClientProvider client={testQueryClient}>
        <UserProvider
          initialUser={{
            ...mockUser,
            features: { ...mockUser.features, 'schedule.view': false, 'layout.view': true },
          }}
        >
          <MemoryRouter>
            <Playlists />
          </MemoryRouter>
        </UserProvider>
      </QueryClientProvider>,
    );

    await screen.findByText(mockPlaylist.name);
    fireEvent.click(screen.getByRole('button', { name: 'More actions' }));

    expect(await screen.findByRole('button', { name: 'Usage Report' })).toBeInTheDocument();
  });
});
