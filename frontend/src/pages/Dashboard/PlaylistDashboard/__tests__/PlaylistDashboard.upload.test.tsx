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

import { act, fireEvent, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type { UserEvent } from '@testing-library/user-event';
import { vi, afterEach, beforeEach, describe, test, expect } from 'vitest';

import {
  buildDashboardPlaylist,
  buildSpotsResponse,
  buildSpotWidget,
  buildSpotWidgets,
  LOCKED_WIDGET,
  makeFile,
  SUBPLAYLIST_WIDGET,
} from './fixtures/playlistDashboard';
import {
  getFileInput,
  getSpot,
  renderPlaylistDashboardPage,
  selectPlaylist,
} from './helpers/renderPlaylistDashboardPage';
import {
  mockPlaylists,
  mockSpots,
  mockUploadOk,
  mockUploadPending,
  mockUploadServerError,
  mockUploadThrows,
  uploadOk,
} from './mocks/playlistDashboardApi';

import type { UploadSpotMediaParams } from '@/services/dashboardApi';
import { fetchPlaylistSpots, uploadSpotMedia } from '@/services/dashboardApi';
import { testQueryClient } from '@/setupTests';
import type { PlaylistSpotsResponse, SpotWidget } from '@/types/dashboard';

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
vi.mock('@/pages/Library/Media/components/MediaPreviewer', () => ({
  default: () => null,
}));
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
// Helpers local to this file
// =============================================================================

const LOBBY = buildDashboardPlaylist({ playlistId: 1, name: 'Lobby' });

const spots = (widgets: SpotWidget[], spotsFound = widgets.length) =>
  buildSpotsResponse({ playlist: { playlistId: 1, name: 'Lobby', widgets }, spotsFound });

const openLobby = async (user: UserEvent, response: PlaylistSpotsResponse) => {
  mockSpots(response);
  renderPlaylistDashboardPage();
  await selectPlaylist(user, 'Lobby');
  await screen.findByText(/Spots Available/);
};

// The params object of the n-th upload call.
const uploadParams = (n = 0): UploadSpotMediaParams => vi.mocked(uploadSpotMedia).mock.calls[n]![0];

// Reports upload progress the way axios does, through the onProgress callback the page gave.
const reportProgress = (percent: number, n = 0) =>
  act(() => {
    uploadParams(n).onProgress?.(percent);
  });

// =============================================================================
// Tests
// =============================================================================

describe('Playlist Dashboard - uploading', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    mockPlaylists({ rows: [LOBBY], totalCount: 1 });
    // JSDOM has no object URLs; images get a temporary preview through these.
    URL.createObjectURL = vi.fn(() => 'blob:mock-preview');
    URL.revokeObjectURL = vi.fn();
  });

  afterEach(() => {
    vi.restoreAllMocks();
  });

  // Payload (rule 1.1): an empty spot is a new upload into this playlist, not a replacement.
  test('uploading to an empty spot sends the file for this playlist and names no widget', async () => {
    const user = userEvent.setup();
    mockUploadOk();
    await openLobby(user, spots([], 2));
    const file = makeFile('photo.png');

    await user.upload(getFileInput(getSpot(1)), file);

    await waitFor(() => expect(uploadSpotMedia).toHaveBeenCalledTimes(1));
    expect(uploadParams()).toEqual(
      expect.objectContaining({ file, playlistId: 1, widgetId: undefined, oldMediaId: undefined }),
    );
  });

  // Three widgets, upload to spot 2: proves the spot index picks the right widget.
  test('uploading to a filled spot replaces that spot’s widget and media, not another’s', async () => {
    const user = userEvent.setup();
    mockUploadOk();
    const widgets = buildSpotWidgets(3);
    await openLobby(user, spots(widgets));

    await user.upload(getFileInput(getSpot(2)), makeFile('new.png'));

    await waitFor(() => expect(uploadSpotMedia).toHaveBeenCalledTimes(1));
    expect(uploadParams()).toEqual(
      expect.objectContaining({
        playlistId: 1,
        widgetId: widgets[1]!.widgetId,
        oldMediaId: widgets[1]!.mediaIds[0],
      }),
    );
  });

  // userEvent can't drag files, so the drop is fired directly (documented §5.2 exception).
  test('dropping a file on an empty spot uploads it', async () => {
    const user = userEvent.setup();
    mockUploadOk();
    await openLobby(user, spots([], 1));
    const file = makeFile('dropped.png');

    fireEvent.drop(getSpot(1), { dataTransfer: { files: [file] } });

    await waitFor(() => expect(uploadSpotMedia).toHaveBeenCalledTimes(1));
    expect(uploadParams().file).toBe(file);
  });

  test('while uploading, the spot shows the file, its size and progress, and disables Select File', async () => {
    const user = userEvent.setup();
    mockUploadPending();
    await openLobby(user, spots([buildSpotWidget({ type: 'video' })]));

    await user.upload(getFileInput(getSpot(1)), makeFile('big.mp4', 'video/mp4', 5 * 1024 * 1024));

    const spot = getSpot(1);
    await within(spot).findByText('big.mp4');
    expect(within(spot).getByText('5 MiB')).toBeInTheDocument();
    expect(within(spot).getByText('0%')).toBeInTheDocument();
    expect(within(spot).getByRole('button', { name: /select file/i })).toBeDisabled();
  });

  test('the percentage updates as the upload progresses', async () => {
    const user = userEvent.setup();
    mockUploadPending();
    await openLobby(user, spots([], 1));
    await user.upload(getFileInput(getSpot(1)), makeFile('a.png'));
    await within(getSpot(1)).findByText('0%');

    reportProgress(40);

    expect(within(getSpot(1)).getByText('40%')).toBeInTheDocument();
  });

  test('when the upload finishes, the spots reload and the spot shows its new content', async () => {
    const user = userEvent.setup();
    const resolve = mockUploadPending();
    await openLobby(user, spots([], 1));
    await user.upload(getFileInput(getSpot(1)), makeFile('photo.png'));
    await within(getSpot(1)).findByText('0%');
    const loadsBefore = vi.mocked(fetchPlaylistSpots).mock.calls.length;

    mockSpots(
      spots([
        buildSpotWidget({
          mediaFiles: [
            { mediaId: 77, fileName: 'photo-saved.png', mediaType: 'image', fileSize: '2 KiB' },
          ],
        }),
      ]),
    );
    await act(async () => resolve(uploadOk()));

    await within(getSpot(1)).findByText('photo-saved.png');
    expect(vi.mocked(fetchPlaylistSpots).mock.calls.length).toBeGreaterThan(loadsBefore);
    expect(within(getSpot(1)).queryByText(/%$/)).not.toBeInTheDocument();
  });

  // A server-side rejection resolves successfully with an error field: not an exception.
  test('if the server rejects the file, the spot shows the reason and nothing reloads', async () => {
    const user = userEvent.setup();
    mockUploadServerError('File too large');
    await openLobby(user, spots([], 1));
    const loadsBefore = vi.mocked(fetchPlaylistSpots).mock.calls.length;

    await user.upload(getFileInput(getSpot(1)), makeFile('huge.png'));

    await within(getSpot(1)).findByText('File too large');
    expect(vi.mocked(fetchPlaylistSpots).mock.calls.length).toBe(loadsBefore);
    expect(uploadSpotMedia).toHaveBeenCalledTimes(1);
  });

  test('if the upload fails outright, the spot shows "Upload failed"', async () => {
    const user = userEvent.setup();
    mockUploadThrows();
    await openLobby(user, spots([], 1));

    await user.upload(getFileInput(getSpot(1)), makeFile('a.png'));

    await within(getSpot(1)).findByText('Upload failed');
    expect(uploadSpotMedia).toHaveBeenCalledTimes(1);
  });

  test('two spots upload at the same time without affecting each other', async () => {
    const user = userEvent.setup();
    mockUploadPending();
    await openLobby(user, spots([], 2));

    await user.upload(getFileInput(getSpot(1)), makeFile('a.png'));
    mockUploadPending();
    await user.upload(getFileInput(getSpot(2)), makeFile('b.png'));
    await within(getSpot(2)).findByText('b.png');

    reportProgress(30, 0);

    expect(within(getSpot(1)).getByText('a.png')).toBeInTheDocument();
    expect(within(getSpot(1)).getByText('30%')).toBeInTheDocument();
    expect(within(getSpot(2)).getByText('0%')).toBeInTheDocument();
  });

  test('dropping a file on a sub-playlist or a locked spot uploads nothing', async () => {
    const user = userEvent.setup();
    await openLobby(user, spots([SUBPLAYLIST_WIDGET, { ...LOCKED_WIDGET, widgetId: 2 }]));

    fireEvent.drop(getSpot(1), { dataTransfer: { files: [makeFile()] } });
    fireEvent.drop(getSpot(2), { dataTransfer: { files: [makeFile()] } });

    expect(uploadSpotMedia).not.toHaveBeenCalled();
  });
});
