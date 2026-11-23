/*
 * Xibo Digital Signage
 * Copyright (C) 2006-2026 Xibo Signage Ltd
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published
 * by the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 *
 * This program is also available under a commercial licence from
 * Xibo Signage Ltd. See https://xibosignage.com/terms
 */

// =============================================================================
// Test type: Page integration test
// Tests that the Playlists grid page renders the expected elements and displays
// row data correctly.
//
// TDD contracts:
//   - Search placeholder:        'Search playlist...'
//   - Filters button label:      'Filters'
//   - Add Playlist button label: 'Add Playlist'
//   - Duration format:           formatDuration(30) === '00:00:30'
//   - Error alert role:          role="alert" containing the error message
// =============================================================================

import { screen } from '@testing-library/react';
import { vi, beforeEach, describe, test, expect } from 'vitest';

import { usePlaylistActions } from '../hooks/usePlaylistActions';
import { usePlaylistData } from '../hooks/usePlaylistData';

import {
  EMPTY_PLAYLIST_TABLE,
  SINGLE_PLAYLIST,
  defaultPlaylistActions,
  mockPlaylist,
  mockPlaylistData,
  renderPlaylistsPage,
} from './playlistTestUtils';

import { testQueryClient } from '@/setupTests';

// ─── Mocks ────────────────────────────────────────────────────────────────────

vi.mock('@/services/folderApi');
vi.mock('@/services/playlistApi');
vi.mock('@/services/userApi', () => ({
  fetchUserPreference: vi.fn().mockResolvedValue(null),
  saveUserPreference: vi.fn().mockResolvedValue(undefined),
}));

vi.mock('../hooks/usePlaylistData', () => ({ usePlaylistData: vi.fn() }));
vi.mock('../hooks/usePlaylistActions', () => ({ usePlaylistActions: vi.fn() }));
vi.mock('../hooks/usePlaylistFilterOptions', () => ({
  usePlaylistFilterOptions: vi.fn(() => ({ filterOptions: [], isLoading: false })),
}));

vi.mock('@/components/ui/FolderActionModals', () => ({ default: () => null }));
vi.mock('@/components/ui/FolderBreadCrumb', () => ({ default: () => null }));
vi.mock('@/components/ui/modals/Modal');

vi.mock('../components/AddAndEditPlaylistModal', () => ({ default: () => null }));
vi.mock('../components/CopyPlaylistModal', () => ({ default: () => null }));
vi.mock('@/components/ui/modals/ShareModal', () => ({ default: () => null }));
vi.mock('@/components/ui/modals/MoveModal', () => ({ default: () => null }));

// ─── Tests ────────────────────────────────────────────────────────────────────

describe('Playlists page - rendering', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    vi.mocked(usePlaylistActions).mockReturnValue(defaultPlaylistActions());
    mockPlaylistData(EMPTY_PLAYLIST_TABLE);
  });

  // -------------------------------------------------------------------------
  // Search input is always present.
  // -------------------------------------------------------------------------
  test('renders the search input', async () => {
    renderPlaylistsPage();

    expect(await screen.findByPlaceholderText('Search playlist...')).toBeInTheDocument();
  });

  // -------------------------------------------------------------------------
  // Filters button is always present.
  // -------------------------------------------------------------------------
  test('renders the Filters button', async () => {
    renderPlaylistsPage();

    expect(await screen.findByRole('button', { name: 'Filters' })).toBeInTheDocument();
  });

  // -------------------------------------------------------------------------
  // Add Playlist button is present (may be disabled until folderPerms resolve).
  // -------------------------------------------------------------------------
  test('renders the Add Playlist button', async () => {
    renderPlaylistsPage();

    expect(await screen.findByRole('button', { name: 'Add Playlist' })).toBeInTheDocument();
  });

  // -------------------------------------------------------------------------
  // Playlist name cell is rendered when there is a row.
  // -------------------------------------------------------------------------
  test(
    'shows the playlist name in the table when data is loaded',
    { tags: ['smoke'] },
    async () => {
      mockPlaylistData(SINGLE_PLAYLIST);
      renderPlaylistsPage();

      expect(await screen.findByText(mockPlaylist.name)).toBeInTheDocument();
    },
  );

  // -------------------------------------------------------------------------
  // Duration column renders the formatted HH:MM:SS string.
  // formatDuration(30) → '00:00:30'
  // -------------------------------------------------------------------------
  test('shows the formatted duration in the table', async () => {
    mockPlaylistData(SINGLE_PLAYLIST);
    renderPlaylistsPage();

    expect(await screen.findByText('00:00:30')).toBeInTheDocument();
  });

  // -------------------------------------------------------------------------
  // Playlist name is absent when the table has no rows.
  // -------------------------------------------------------------------------
  test('does not show the playlist name when the table is empty', async () => {
    renderPlaylistsPage();

    await screen.findByPlaceholderText('Search playlist...');

    expect(screen.queryByText(mockPlaylist.name)).not.toBeInTheDocument();
  });

  // -------------------------------------------------------------------------
  // Error alert is shown when usePlaylistData returns isError.
  // -------------------------------------------------------------------------
  test('shows an error alert when data fetch fails', async () => {
    vi.mocked(usePlaylistData).mockReturnValue({
      data: { rows: [], totalCount: 0 },
      isFetching: false,
      isError: true,
      error: new Error('Server Error'),
    } as unknown as ReturnType<typeof usePlaylistData>);

    renderPlaylistsPage();

    expect(await screen.findByRole('alert')).toBeInTheDocument();
    expect(screen.getByRole('alert')).toHaveTextContent('Server Error');
  });
});
