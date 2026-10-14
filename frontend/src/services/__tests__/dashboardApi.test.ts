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

// Boundary tests for src/services/dashboardApi.ts (rule 1.6): pure function in, HTTP call
// out, no DOM. Page tests mock this module, so these are the only checks that the right
// endpoint and parameters are actually sent.
//
// Sections are owned by the dashboard plans that use them:
//   Status Dashboard   — fetchStatusDashboard
//   Media Manager      — fetchMediaDashboard / fetchUnusedMedia / fetchUnreleasedMedia
//   Playlist Dashboard — fetchPlaylistsForDashboard / fetchPlaylistSpots /
//                        uploadSpotMedia / deleteSpotWidget

import { vi, beforeEach, describe, test, expect } from 'vitest';

import http from '@/lib/api';
import {
  deleteSpotWidget,
  fetchMediaDashboard,
  fetchPlaylistSpots,
  fetchPlaylistsForDashboard,
  fetchStatusDashboard,
  fetchUnreleasedMedia,
  fetchUnusedMedia,
  uploadSpotMedia,
} from '@/services/dashboardApi';

vi.mock('@/lib/api', () => ({
  default: { get: vi.fn(), post: vi.fn(), delete: vi.fn() },
}));

describe('dashboardApi - Status Dashboard', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  test('loading the Status Dashboard calls its endpoint, passes the cancel signal and returns the data', async () => {
    const data = { countUsers: 3 };
    vi.mocked(http.get).mockResolvedValue({ data });
    const controller = new AbortController();

    const result = await fetchStatusDashboard(controller.signal);

    expect(http.get).toHaveBeenCalledTimes(1);
    expect(http.get).toHaveBeenCalledWith('/statusdashboard', { signal: controller.signal });
    expect(result).toBe(data);
  });
});

describe('dashboardApi - Media Manager Dashboard', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  test('loading the Media Manager Dashboard calls its endpoint, passes the cancel signal and returns the data', async () => {
    const data = { library: { countOf: 3 } };
    vi.mocked(http.get).mockResolvedValue({ data });
    const controller = new AbortController();

    const result = await fetchMediaDashboard(controller.signal);

    expect(http.get).toHaveBeenCalledWith('/mediamanager', { signal: controller.signal });
    expect(result).toBe(data);
  });

  test('loading unused media asks the library for unused items only and reads the total from the header', async () => {
    const rows = [{ mediaId: 1 }, { mediaId: 2 }];
    vi.mocked(http.get).mockResolvedValue({ data: rows, headers: { 'x-total-count': '11' } });
    const controller = new AbortController();

    const result = await fetchUnusedMedia(
      { start: 5, length: 5, sortBy: 'name', sortDir: 'desc' },
      controller.signal,
    );

    expect(http.get).toHaveBeenCalledWith('/library', {
      params: { start: 5, length: 5, sortBy: 'name', sortDir: 'desc', unusedOnly: 1 },
      signal: controller.signal,
    });
    expect(result).toEqual({ rows, totalCount: 11 });
  });

  // The opposite branch to the test above: same endpoint, the other flag, never both.
  test('loading unreleased media asks the library for unreleased items only', async () => {
    vi.mocked(http.get).mockResolvedValue({ data: [], headers: { 'x-total-count': '11' } });

    const result = await fetchUnreleasedMedia({ start: 0, length: 5 });

    expect(http.get).toHaveBeenCalledWith('/library', {
      params: { start: 0, length: 5, unreleasedOnly: 1 },
      signal: undefined,
    });
    expect(result.totalCount).toBe(11);
  });

  test('a response without a total header is treated as a total of 0', async () => {
    vi.mocked(http.get).mockResolvedValue({ data: [{ mediaId: 1 }], headers: {} });

    const result = await fetchUnusedMedia({ start: 0, length: 5 });

    expect(result.totalCount).toBe(0);
  });
});

describe('dashboardApi - Playlist Dashboard', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  // The form data and request options of the most recent upload.
  const lastUpload = () => {
    const calls = vi.mocked(http.post).mock.calls;
    const [url, body, config] = calls[calls.length - 1]!;
    return {
      url,
      form: body as FormData,
      config: config as { onUploadProgress: (e: { loaded: number; total?: number }) => void },
    };
  };

  test('loading playlists sends the paging and name filter, and reads the total from the header', async () => {
    const rows = [{ playlistId: 1 }, { playlistId: 2 }];
    vi.mocked(http.get).mockResolvedValue({ data: rows, headers: { 'x-total-count': '25' } });

    const result = await fetchPlaylistsForDashboard({ start: 10, length: 10, name: 'lob' });

    expect(http.get).toHaveBeenCalledWith('/playlistdashboard/data', {
      params: { start: 10, length: 10, name: 'lob' },
      signal: undefined,
    });
    expect(result).toEqual({ rows, totalCount: 25 });
  });

  test('loading spots asks for the chosen playlist', async () => {
    const data = { spotsFound: 3 };
    vi.mocked(http.get).mockResolvedValue({ data });

    const result = await fetchPlaylistSpots(7);

    expect(http.get).toHaveBeenCalledWith('/playlistdashboard/7', { signal: undefined });
    expect(result).toBe(data);
  });

  test('an upload sends the file, its name and the playlist, and keeps layouts up to date', async () => {
    vi.mocked(http.post).mockResolvedValue({ data: { files: [] } });
    const file = new File(['x'], 'photo.png', { type: 'image/png' });

    await uploadSpotMedia({ file, playlistId: 3 });

    const { url, form } = lastUpload();
    expect(url).toBe('/library');
    expect(form.get('files[]')).toBe(file);
    expect(form.get('name[]')).toBe('photo.png');
    expect(form.get('playlistId')).toBe('3');
    expect(form.get('updateInLayouts')).toBe('1');
    expect(form.get('deleteOldRevisions')).toBe('1');
    expect(form.has('widgetId')).toBe(false);
    expect(form.has('oldMediaId')).toBe(false);
  });

  // Edge value 0 (rule 1.3): `!= null` must send it; a truthiness check would drop it.
  test('a replacement names the widget and old media, even when an id is 0', async () => {
    vi.mocked(http.post).mockResolvedValue({ data: { files: [] } });

    await uploadSpotMedia({
      file: new File(['x'], 'a.png'),
      playlistId: 3,
      widgetId: 5,
      oldMediaId: 0,
    });

    const { form } = lastUpload();
    expect(form.get('widgetId')).toBe('5');
    expect(form.get('oldMediaId')).toBe('0');
  });

  test('upload progress is reported in whole percent, and not at all when the size is unknown', async () => {
    vi.mocked(http.post).mockResolvedValue({ data: { files: [] } });
    const onProgress = vi.fn();

    await uploadSpotMedia({ file: new File(['x'], 'a.png'), playlistId: 3, onProgress });
    const { config } = lastUpload();
    config.onUploadProgress({ loaded: 333, total: 1000 });
    config.onUploadProgress({ loaded: 500 });

    expect(onProgress.mock.calls).toEqual([[33]]);
  });

  test('removing a widget says whether to delete its library media', async () => {
    vi.mocked(http.delete).mockResolvedValue({});

    await deleteSpotWidget(9, true);
    await deleteSpotWidget(9, false);

    const calls = vi.mocked(http.delete).mock.calls;
    expect(calls[0]![0]).toBe('/playlist/widget/9');
    expect(calls[0]![1]).toEqual(expect.objectContaining({ params: { deleteMedia: 1 } }));
    expect(calls[1]![1]).toEqual(expect.objectContaining({ params: { deleteMedia: 0 } }));
  });
});
