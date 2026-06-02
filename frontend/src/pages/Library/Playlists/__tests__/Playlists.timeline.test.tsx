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

import type { FetchPlaylistResponse } from '@/services/playlistApi';
import { testQueryClient } from '@/setupTests';
import type { Playlist } from '@/types/playlist';

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

vi.mock('@/components/ui/modals/ShareModal', () => ({
  default: ({ onClose }: { onClose: () => void }) => (
    <div role="dialog" aria-label="Share Playlist">
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

vi.mock('@/components/ui/modals/ScheduleEventModal', () => ({ default: () => null }));

// =============================================================================
// Multi-playlist fixture (used in URL construction tests)
// =============================================================================

const mockPlaylist2: Playlist = { ...mockPlaylist, playlistId: 202, name: 'Second Playlist' };

const TWO_PLAYLISTS: FetchPlaylistResponse = {
  rows: [mockPlaylist, mockPlaylist2],
  totalCount: 2,
};

// =============================================================================
// Helpers
// =============================================================================

/**
 * Opens the More actions dropdown and clicks the Timeline item inside it.
 * Waits for "Make a Copy" to confirm the dropdown is open before clicking,
 * then targets the last Timeline button (the dropdown item) since both the
 * quick action and dropdown item share the same accessible name.
 */
const openDropdownTimeline = async () => {
  await screen.findByText(mockPlaylist.name);
  fireEvent.click(screen.getByRole('button', { name: 'More actions' }));
  await screen.findByRole('button', { name: 'Make a Copy' });
  const timelineButtons = screen.getAllByRole('button', { name: 'Timeline' });
  fireEvent.click(timelineButtons[timelineButtons.length - 1]!);
};

// =============================================================================
// Tests
// =============================================================================

describe('Playlists page - Timeline action', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    vi.mocked(usePlaylistActions).mockReturnValue(defaultPlaylistActions());
    mockFetchPlaylists(SINGLE_PLAYLIST);
    vi.spyOn(window, 'open').mockImplementation(() => null);
  });

  // ---------------------------------------------------------------------------
  // Section 1 — Quick Action Button
  // ---------------------------------------------------------------------------

  test('Timeline quick action button is visible for a playlist row', async () => {
    renderPlaylistsPage();

    await screen.findByText(mockPlaylist.name);

    expect(screen.getByTitle('Timeline')).toBeInTheDocument();
  });

  test('Clicking the quick action Timeline button calls window.open with the designer URL and _blank target', async () => {
    renderPlaylistsPage();

    await screen.findByText(mockPlaylist.name);
    fireEvent.click(screen.getByTitle('Timeline'));

    expect(window.open).toHaveBeenCalledOnce();
    expect(window.open).toHaveBeenCalledWith('/playlist/designer/101', '_blank');
  });

  test('Clicking the quick action Timeline button does not open any modal dialog', async () => {
    renderPlaylistsPage();

    await screen.findByText(mockPlaylist.name);
    fireEvent.click(screen.getByTitle('Timeline'));

    expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
  });

  // ---------------------------------------------------------------------------
  // Section 2 — Dropdown Action
  // ---------------------------------------------------------------------------

  test('Timeline item is visible in the More actions dropdown', async () => {
    renderPlaylistsPage();

    await screen.findByText(mockPlaylist.name);
    fireEvent.click(screen.getByRole('button', { name: 'More actions' }));
    await screen.findByRole('button', { name: 'Make a Copy' });

    const timelineButtons = screen.getAllByRole('button', { name: 'Timeline' });
    expect(timelineButtons.length).toBeGreaterThanOrEqual(2);
  });

  test('Clicking the dropdown Timeline item calls window.open with the designer URL', async () => {
    renderPlaylistsPage();

    await openDropdownTimeline();

    expect(window.open).toHaveBeenCalledOnce();
    expect(window.open).toHaveBeenCalledWith('/playlist/designer/101', '_blank');
  });

  test('Clicking the dropdown Timeline item does not open any modal dialog', async () => {
    renderPlaylistsPage();

    await openDropdownTimeline();

    expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
  });

  // ---------------------------------------------------------------------------
  // Section 3 — URL Construction
  // ---------------------------------------------------------------------------

  test('Timeline URL includes the correct playlistId from the row', async () => {
    renderPlaylistsPage();

    await screen.findByText(mockPlaylist.name);
    fireEvent.click(screen.getByTitle('Timeline'));

    expect(window.open).toHaveBeenCalledWith(
      `/playlist/designer/${mockPlaylist.playlistId}`,
      '_blank',
    );
  });

  test('Different playlists produce different timeline URLs', async () => {
    mockFetchPlaylists(TWO_PLAYLISTS);
    renderPlaylistsPage();

    await screen.findByText('Second Playlist');
    const secondRow = screen.getByText('Second Playlist').closest('tr') as HTMLElement;
    fireEvent.click(within(secondRow).getByTitle('Timeline'));

    expect(window.open).toHaveBeenCalledWith('/playlist/designer/202', '_blank');
  });

  // ---------------------------------------------------------------------------
  // Section 4 — Entry Points and Permissions
  // ---------------------------------------------------------------------------

  test('Both the quick action and the dropdown Timeline buttons are rendered', async () => {
    renderPlaylistsPage();

    await screen.findByText(mockPlaylist.name);
    fireEvent.click(screen.getByRole('button', { name: 'More actions' }));
    await screen.findByRole('button', { name: 'Make a Copy' });

    const timelineButtons = screen.getAllByRole('button', { name: 'Timeline' });
    expect(timelineButtons.length).toBeGreaterThanOrEqual(2);
  });

  test('Timeline action is available without any special permissions', async () => {
    // renderPlaylistsPage uses mockUser which has no schedule.add or other elevated permissions.
    renderPlaylistsPage();

    await screen.findByText(mockPlaylist.name);

    expect(screen.getByTitle('Timeline')).toBeInTheDocument();
  });
});
