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

import { vi } from 'vitest';

import type {
  FetchPlaylistsForDashboardResponse,
  UploadSpotMediaResponse,
} from '@/services/dashboardApi';
import {
  deleteSpotWidget,
  fetchPlaylistSpots,
  fetchPlaylistsForDashboard,
  uploadSpotMedia,
} from '@/services/dashboardApi';
import { fetchUserPreference } from '@/services/userApi';
import type { PlaylistSpotsResponse } from '@/types/dashboard';

// Thin wrappers over the services the Playlist Dashboard calls. They only set return values;
// no per-id branching lives here. Sequences (e.g. "2nd delete fails") are chained with
// mockResolvedValueOnce / mockRejectedValueOnce in the test itself.
//
// Requires at the test file level:
//   vi.mock('@/services/dashboardApi', () => ({ fetchPlaylistsForDashboard, fetchPlaylistSpots,
//     uploadSpotMedia, deleteSpotWidget }))
//   vi.mock('@/services/userApi', () => ({ fetchUserPreference, saveUserPreference }))

// Controlled promise (§4.4): returns the promise and its resolve/reject handles.
export const controlled = <T>() => {
  let resolve!: (value: T) => void;
  let reject!: (reason: unknown) => void;
  const promise = new Promise<T>((res, rej) => {
    resolve = res;
    reject = rej;
  });
  return { promise, resolve, reject };
};

// GET /playlistdashboard/data (the playlist picker)
export const mockPlaylists = (response: FetchPlaylistsForDashboardResponse) => {
  vi.mocked(fetchPlaylistsForDashboard).mockResolvedValue(response);
};

// GET /playlistdashboard/{id}
export const mockSpots = (response: PlaylistSpotsResponse) => {
  vi.mocked(fetchPlaylistSpots).mockResolvedValue(response);
};
export const mockSpotsError = (message: string) => {
  vi.mocked(fetchPlaylistSpots).mockRejectedValue(new Error(message));
};
export const mockSpotsPending = () => {
  const { promise, resolve } = controlled<PlaylistSpotsResponse>();
  vi.mocked(fetchPlaylistSpots).mockReturnValue(promise);
  return resolve;
};

// GET /user/pref?preference=playlist_dashboard
export const mockSavedPref = (selectedPlaylistId: number | null) => {
  vi.mocked(fetchUserPreference).mockResolvedValue({ selectedPlaylistId });
};
export const mockNoPref = () => {
  vi.mocked(fetchUserPreference).mockResolvedValue(undefined);
};
export const mockPrefPending = () => {
  const { promise, resolve } = controlled<{ selectedPlaylistId: number | null } | undefined>();
  vi.mocked(fetchUserPreference).mockReturnValue(promise);
  return resolve;
};

// POST /library (spot upload)
export const uploadOk = (mediaId = 77): UploadSpotMediaResponse => ({
  files: [{ mediaId, widgetId: 1, name: '', size: 0, mediaType: 'image' }],
});
export const mockUploadOk = (mediaId = 77) => {
  vi.mocked(uploadSpotMedia).mockResolvedValue(uploadOk(mediaId));
};
export const mockUploadServerError = (error: string) => {
  vi.mocked(uploadSpotMedia).mockResolvedValue({
    files: [{ mediaId: 0, widgetId: 0, name: '', size: 0, mediaType: '', error }],
  });
};
export const mockUploadThrows = () => {
  vi.mocked(uploadSpotMedia).mockRejectedValue(new Error('Network Error'));
};
export const mockUploadPending = () => {
  const { promise, resolve } = controlled<UploadSpotMediaResponse>();
  vi.mocked(uploadSpotMedia).mockReturnValue(promise);
  return resolve;
};

// DELETE /playlist/widget/{id}
export const mockDeleteOk = () => {
  vi.mocked(deleteSpotWidget).mockResolvedValue(undefined);
};
export const mockDeletePending = () => {
  const { promise, resolve } = controlled<void>();
  vi.mocked(deleteSpotWidget).mockReturnValue(promise);
  return resolve;
};
