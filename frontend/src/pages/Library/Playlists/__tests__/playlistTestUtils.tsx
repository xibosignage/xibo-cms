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
import { MemoryRouter } from 'react-router-dom';
import { vi } from 'vitest';

import Playlists from '../Playlists';
import type { usePlaylistActions } from '../hooks/usePlaylistActions';
import { usePlaylistData } from '../hooks/usePlaylistData';

import { UserProvider } from '@/context/UserContext';
import { fetchPlaylist } from '@/services/playlistApi';
import type { FetchPlaylistResponse } from '@/services/playlistApi';
import { testQueryClient } from '@/setupTests';
import type { Playlist } from '@/types/playlist';
import type { User } from '@/types/user';

// -----------------------------------------------------------------------------
// One realistic playlist row.
// playlistId: 101 is asserted in delete tests.
// name: 'My Playlist' is asserted in render and delete modal tests.
// enableStat: 'On' — stats enabled by default so edit form tests can check it.
// -----------------------------------------------------------------------------
export const mockPlaylist: Playlist = {
  playlistId: 101,
  name: 'My Playlist',
  folderId: 1,
  ownerId: '1',
  createdDt: '2026-01-01 00:00:00',
  modifiedDt: '2026-03-01 10:00:00',
  valid: true,
  tags: [],
  duration: 30,
  enableStat: 'On',
  retired: false,
  expires: 0,
  updateInLayouts: false,
  isDynamic: false,
  filterMediaName: '',
  logicalOperatorName: 'OR',
  filterMediaTag: [],
  exactTags: false,
  logicalOperator: 'OR',
  filterFolderId: null,
  maxNumberOfItems: 0,
};

// -----------------------------------------------------------------------------
// The default logged-in user for most Playlists page tests.
// -----------------------------------------------------------------------------
export const mockUser: User = {
  userId: 1,
  userName: 'TestUser',
  userTypeId: 1,
  groupId: 1,
  features: { 'folder.view': true },
  settings: {
    defaultTimezone: 'UTC',
    defaultLanguage: 'en',
    DATE_FORMAT_JS: 'DD/MM/YYYY',
    TIME_FORMAT_JS: 'HH:mm',
  },
};

// -----------------------------------------------------------------------------
// usePlaylistData return shapes
// -----------------------------------------------------------------------------

// A table with one playlist row.
export const SINGLE_PLAYLIST: FetchPlaylistResponse = {
  rows: [mockPlaylist],
  totalCount: 1,
};

// An empty table — used for initial load and empty state tests.
export const EMPTY_PLAYLIST_TABLE: FetchPlaylistResponse = {
  rows: [],
  totalCount: 0,
};

// -----------------------------------------------------------------------------
// Typed mock helpers - centralise casts so they don't repeat across tests.
// -----------------------------------------------------------------------------
export type UsePlaylistReturn = ReturnType<typeof usePlaylistData>;
export type UsePlaylistActionsReturn = ReturnType<typeof usePlaylistActions>;

// Helps create fake data in the same format that usePlaylistData normally returns.
// Use this when your test directly mocks usePlaylistData (e.g. search or pagination tests).
export const mockPlaylistData = (rawData: FetchPlaylistResponse) => {
  vi.mocked(usePlaylistData).mockReturnValue({
    data: rawData,
    isFetching: false,
    isError: false,
    error: null,
  } as UsePlaylistReturn);
};

// Makes fetchPlaylist return the data you provide.
// Use this when you want the real usePlaylistData and React Query to run in your test.
export const mockFetchPlaylists = (rawData: FetchPlaylistResponse) => {
  vi.mocked(fetchPlaylist).mockResolvedValue(rawData);
};

// Returns a fresh usePlaylistActions mock value for every beforeEach call.
export const defaultPlaylistActions = (
  overrides: Partial<UsePlaylistActionsReturn> = {},
): UsePlaylistActionsReturn =>
  ({
    isDeleting: false,
    isCloning: false,
    deleteError: null,
    setDeleteError: vi.fn(),
    confirmDelete: vi.fn(),
    handleConfirmClone: vi.fn(),
    handleConfirmMove: vi.fn(),
    ...overrides,
  }) as UsePlaylistActionsReturn;

// -----------------------------------------------------------------------------
// Opens the Edit Playlist modal for mockPlaylist by clicking the Edit row action.
// Wait for the row text first - the table is behind an isHydrated guard and
// only renders after fetchUserPreference resolves.
// -----------------------------------------------------------------------------
export const openEditModal = async () => {
  await screen.findByText(mockPlaylist.name);
  fireEvent.click(screen.getByTitle('Edit'));
  return screen.findByRole('dialog', { name: 'Edit Playlist' });
};

// -----------------------------------------------------------------------------
// Render wrapper - provides all required context providers.
// -----------------------------------------------------------------------------
export const renderPlaylistsPage = () => {
  testQueryClient.setQueryData(['userPref', 'playlist_page'], null);
  return render(
    <QueryClientProvider client={testQueryClient}>
      <UserProvider initialUser={mockUser}>
        <MemoryRouter>
          <Playlists />
        </MemoryRouter>
      </UserProvider>
    </QueryClientProvider>,
  );
};
