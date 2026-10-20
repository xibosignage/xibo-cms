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

import { screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { vi, beforeEach, describe, test, expect } from 'vitest';

import {
  buildDashboardPlaylist,
  buildSpotsResponse,
  buildSpotWidget,
  buildSpotWidgets,
} from './fixtures/playlistDashboard';
import {
  getSpot,
  renderPlaylistDashboardPage,
  selectPlaylist,
} from './helpers/renderPlaylistDashboardPage';
import {
  mockPlaylists,
  mockSpots,
  mockSpotsError,
  mockSpotsPending,
} from './mocks/playlistDashboardApi';

import { fetchPlaylistSpots } from '@/services/dashboardApi';
import { testQueryClient } from '@/setupTests';

// =============================================================================
// Module mocks
// =============================================================================

vi.mock('@/services/dashboardApi', () => ({
  fetchPlaylistsForDashboard: vi.fn().mockResolvedValue({ rows: [], totalCount: 0 }),
  fetchPlaylistSpots: vi.fn(),
  uploadSpotMedia: vi.fn(),
  deleteSpotWidget: vi.fn().mockResolvedValue(undefined),
}));
vi.mock('@/services/userApi', () => ({
  fetchUserPreference: vi.fn().mockResolvedValue(undefined),
  saveUserPreference: vi.fn().mockResolvedValue(undefined),
}));
vi.mock('@/components/ui/Notification', () => ({
  notify: { success: vi.fn(), error: vi.fn(), info: vi.fn(), warning: vi.fn() },
}));
vi.mock('@/components/ui/modals/Modal');
// MediaPreviewer fetches blobs and streams; this stub reports which media it was given.
vi.mock('@/pages/Library/Media/components/MediaPreviewer', () => ({
  default: ({
    mediaId,
    fileName,
    onClose,
  }: {
    mediaId: number | null;
    fileName?: string;
    onClose: () => void;
  }) =>
    mediaId ? (
      <div role="dialog" aria-label="Media preview">
        <p>{`media ${mediaId} ${fileName ?? ''}`}</p>
        <button onClick={onClose}>Close preview</button>
      </div>
    ) : null,
}));
// Native <select> for page-level tests (selection only). Paging and search are tested against
// the real SelectDropdown in components/PlaylistDropdown.test.tsx.
vi.mock('@/components/ui/forms/SelectDropdown', () => ({
  default: ({
    label,
    value,
    options,
    onSelect,
  }: {
    label?: string;
    value: string;
    options: { value: string; label: string }[];
    onSelect: (v: string) => void;
  }) => (
    <select aria-label={label} value={value} onChange={(e) => onSelect(e.target.value)}>
      <option value="">—</option>
      {options.map((o) => (
        <option key={o.value} value={o.value}>
          {o.label}
        </option>
      ))}
    </select>
  ),
}));

// =============================================================================
// Shared data
// =============================================================================

const LOBBY = buildDashboardPlaylist({ playlistId: 1, name: 'Lobby' });
const RECEPTION = buildDashboardPlaylist({ playlistId: 2, name: 'Reception' });

// Two filled spots out of five: the counter reads 3/5, so a sign or off-by-one error in
// "spotsFound - widgets.length" is visible (with 0 widgets it would read 5/5 either way).
const TWO_OF_FIVE = buildSpotsResponse({
  playlist: { playlistId: 1, name: 'Lobby', widgets: buildSpotWidgets(2) },
  spotsFound: 5,
});

// =============================================================================
// Tests
// =============================================================================

describe('Playlist Dashboard - before a playlist is chosen', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    mockPlaylists({ rows: [LOBBY, RECEPTION], totalCount: 2 });
  });

  test('only the picker, the heading and the help text are shown', async () => {
    renderPlaylistDashboardPage();
    await screen.findByRole('option', { name: 'Lobby' });

    expect(screen.getByRole('combobox', { name: 'Playlist' })).toBeInTheDocument();
    expect(screen.getByRole('heading', { name: 'Playlist Content' })).toBeInTheDocument();
    expect(screen.queryByText(/Spots Available/)).not.toBeInTheDocument();
    expect(screen.queryByRole('button', { name: 'Remove All' })).not.toBeInTheDocument();
    expect(screen.queryByRole('button', { name: /select file/i })).not.toBeInTheDocument();
  });

  test('no spots are requested until a playlist is chosen', async () => {
    renderPlaylistDashboardPage();
    await screen.findByRole('option', { name: 'Lobby' });

    expect(fetchPlaylistSpots).not.toHaveBeenCalled();
  });
});

describe('Playlist Dashboard - after a playlist is chosen', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    mockPlaylists({ rows: [LOBBY, RECEPTION], totalCount: 2 });
  });

  test('choosing a playlist loads that playlist’s spots', async () => {
    const user = userEvent.setup();
    mockSpots(TWO_OF_FIVE);
    renderPlaylistDashboardPage();

    await selectPlaylist(user, 'Lobby');

    await waitFor(() => expect(fetchPlaylistSpots).toHaveBeenCalled());
    expect(vi.mocked(fetchPlaylistSpots).mock.calls[0]![0]).toBe(1);
  });

  test('while the spots load, "Loading..." is shown', async () => {
    const user = userEvent.setup();
    mockSpotsPending();
    renderPlaylistDashboardPage();

    await selectPlaylist(user, 'Lobby');

    await screen.findByText('Loading...');
  });

  test('the counter shows free spots out of the total', async () => {
    const user = userEvent.setup();
    mockSpots(TWO_OF_FIVE);
    renderPlaylistDashboardPage();

    await selectPlaylist(user, 'Lobby');

    await screen.findByText('3/5 Spots Available');
  });

  test('one numbered row per spot, filled spots first', async () => {
    const user = userEvent.setup();
    mockSpots(TWO_OF_FIVE);
    renderPlaylistDashboardPage();

    await selectPlaylist(user, 'Lobby');
    await screen.findByText('3/5 Spots Available');

    expect(within(getSpot(1)).getByText('test-widget-1.png')).toBeInTheDocument();
    expect(within(getSpot(2)).getByText('test-widget-2.png')).toBeInTheDocument();
    for (const n of [3, 4, 5]) {
      expect(within(getSpot(n)).getByText(/drag and drop files or/i)).toBeInTheDocument();
    }
    expect(() => getSpot(6)).toThrow();
  });

  test('a playlist with no spots shows a warning and no rows', async () => {
    const user = userEvent.setup();
    mockSpots(buildSpotsResponse({ spotsFound: 0 }));
    renderPlaylistDashboardPage();

    await selectPlaylist(user, 'Lobby');

    await screen.findByText(/does not have any Spots for you to manage/);
    expect(screen.queryByRole('button', { name: /select file/i })).not.toBeInTheDocument();
  });

  test('Remove All is shown when the playlist has widgets', async () => {
    const user = userEvent.setup();
    mockSpots(buildSpotsResponse({ playlist: { widgets: [buildSpotWidget()] }, spotsFound: 3 }));
    renderPlaylistDashboardPage();

    await selectPlaylist(user, 'Lobby');

    await screen.findByRole('button', { name: 'Remove All' });
  });

  test('Remove All is not shown when the playlist has no widgets', async () => {
    const user = userEvent.setup();
    mockSpots(buildSpotsResponse({ spotsFound: 3 }));
    renderPlaylistDashboardPage();

    await selectPlaylist(user, 'Lobby');
    await screen.findByText('3/3 Spots Available');

    expect(screen.queryByRole('button', { name: 'Remove All' })).not.toBeInTheDocument();
  });

  test('a failed load shows the error message', async () => {
    const user = userEvent.setup();
    mockSpotsError('Playlist not found');
    renderPlaylistDashboardPage();

    await selectPlaylist(user, 'Lobby');

    expect(await screen.findByRole('alert')).toHaveTextContent('Playlist not found');
    expect(fetchPlaylistSpots).toHaveBeenCalled();
  });

  test('switching playlist shows the other playlist’s spots', async () => {
    const user = userEvent.setup();
    vi.mocked(fetchPlaylistSpots).mockImplementation(async (id: number) =>
      buildSpotsResponse({
        playlist: {
          playlistId: id,
          widgets: [
            buildSpotWidget({
              widgetId: id,
              mediaFiles: [
                {
                  mediaId: 100 + id,
                  fileName: id === 1 ? 'lobby.png' : 'reception.png',
                  mediaType: 'image',
                  fileSize: '1 KiB',
                },
              ],
            }),
          ],
        },
        spotsFound: 1,
      }),
    );
    renderPlaylistDashboardPage();

    await selectPlaylist(user, 'Lobby');
    await screen.findByText('lobby.png');
    await selectPlaylist(user, 'Reception');

    await screen.findByText('reception.png');
    expect(screen.queryByText('lobby.png')).not.toBeInTheDocument();
  });
});

describe('Playlist Dashboard - preview', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    mockPlaylists({ rows: [LOBBY], totalCount: 1 });
    mockSpots(buildSpotsResponse({ playlist: { widgets: [buildSpotWidget()] }, spotsFound: 1 }));
  });

  // The thumbnail button is named by its image's alt text, which is the file name.
  test('clicking a thumbnail opens the preview for that media', async () => {
    const user = userEvent.setup();
    renderPlaylistDashboardPage();
    await selectPlaylist(user, 'Lobby');

    await user.click(await screen.findByRole('button', { name: 'test-widget.png' }));

    const preview = await screen.findByRole('dialog', { name: 'Media preview' });
    expect(preview).toHaveTextContent('media 101 test-widget.png');
  });

  test('closing the preview closes it', async () => {
    const user = userEvent.setup();
    renderPlaylistDashboardPage();
    await selectPlaylist(user, 'Lobby');
    await user.click(await screen.findByRole('button', { name: 'test-widget.png' }));
    await screen.findByRole('dialog', { name: 'Media preview' });

    await user.click(screen.getByRole('button', { name: 'Close preview' }));

    await waitFor(() =>
      expect(screen.queryByRole('dialog', { name: 'Media preview' })).not.toBeInTheDocument(),
    );
  });
});
