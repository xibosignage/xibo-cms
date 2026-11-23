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

import { vi, beforeEach, describe, test, expect } from 'vitest';

import http from '@/lib/api';
import {
  deleteFont,
  downloadFont,
  fetchFontBlob,
  fetchFontDetails,
  fetchFonts,
  uploadFont,
} from '@/services/fontApi';
import { navigateToDownload } from '@/utils/url';

// =============================================================================
// Module mocks
// =============================================================================

vi.mock('@/lib/api', () => ({
  default: { get: vi.fn(), post: vi.fn(), delete: vi.fn() },
}));
vi.mock('@/config/publicPath', () => ({
  withPublicPath: (path: string) => `/cms/${path}`,
}));
vi.mock('@/utils/url', () => ({ navigateToDownload: vi.fn() }));

// =============================================================================
// Tests
// =============================================================================

describe('fontApi', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  // ---------------------------------------------------------------------------
  // fetchFonts
  // ---------------------------------------------------------------------------

  describe('fetchFonts', () => {
    test('GETs /fonts with the query params and passes the signal separately', async () => {
      vi.mocked(http.get).mockResolvedValue({ data: [], headers: { 'x-total-count': '0' } });
      const signal = new AbortController().signal;

      await fetchFonts({
        start: 10,
        length: 10,
        name: 'Rob',
        sortBy: 'name',
        sortDir: 'asc',
        signal,
      });

      expect(http.get).toHaveBeenCalledWith('/fonts', {
        params: { start: 10, length: 10, name: 'Rob', sortBy: 'name', sortDir: 'asc' },
        signal,
      });
    });

    test('defaults to the first page of 10 when called with no options', async () => {
      vi.mocked(http.get).mockResolvedValue({ data: [], headers: {} });

      await fetchFonts();

      expect(http.get).toHaveBeenCalledWith('/fonts', {
        params: { start: 0, length: 10 },
        signal: undefined,
      });
    });

    test('returns the rows and parses totalCount from the X-Total-Count header', async () => {
      const rows = [{ id: 1, name: 'Roboto' }];
      vi.mocked(http.get).mockResolvedValue({ data: rows, headers: { 'x-total-count': '42' } });

      await expect(fetchFonts()).resolves.toEqual({ rows, totalCount: 42 });
    });

    test('totalCount is 0 when the X-Total-Count header is missing', async () => {
      vi.mocked(http.get).mockResolvedValue({ data: [], headers: {} });

      await expect(fetchFonts()).resolves.toEqual({ rows: [], totalCount: 0 });
    });
  });

  // ---------------------------------------------------------------------------
  // uploadFont
  // ---------------------------------------------------------------------------

  describe('uploadFont', () => {
    const file = new File(['x'], 'Roboto.ttf', { type: 'font/ttf' });

    test('POSTs the file as multipart form data under "files"', async () => {
      vi.mocked(http.post).mockResolvedValue({ data: { files: [{ id: 1 }] } });

      await uploadFont(file);

      const [url, body, config] = vi.mocked(http.post).mock.calls[0]!;
      expect(url).toBe('/fonts');
      expect(body).toBeInstanceOf(FormData);
      expect((body as FormData).get('files')).toBe(file);
      expect(config?.headers).toEqual({ 'Content-Type': 'multipart/form-data' });
    });

    test('returns the first uploaded file from the response', async () => {
      vi.mocked(http.post).mockResolvedValue({
        data: { files: [{ id: 1, name: 'Roboto' }] },
      });

      await expect(uploadFont(file)).resolves.toEqual({ id: 1, name: 'Roboto' });
    });

    test('falls back to the response body when there is no files array', async () => {
      vi.mocked(http.post).mockResolvedValue({ data: { id: 1, name: 'Roboto' } });

      await expect(uploadFont(file)).resolves.toEqual({ id: 1, name: 'Roboto' });
    });

    // The upload handler returns HTTP 200 even on errors — the error is in the body.
    test('throws the error from the response body', async () => {
      vi.mocked(http.post).mockResolvedValue({
        data: { files: [{ name: 'Roboto.ttf', error: 'Invalid font file' }] },
      });

      await expect(uploadFont(file)).rejects.toThrow('Invalid font file');
    });

    test('reports upload progress as a rounded percentage', async () => {
      vi.mocked(http.post).mockResolvedValue({ data: { files: [{ id: 1 }] } });
      const onProgress = vi.fn();

      await uploadFont(file, onProgress);
      const { onUploadProgress } = vi.mocked(http.post).mock.calls[0]![2]!;
      onUploadProgress!({ loaded: 1, total: 3 } as never);

      expect(onProgress).toHaveBeenCalledWith(33);
    });

    test('does not report progress when the total size is unknown', async () => {
      vi.mocked(http.post).mockResolvedValue({ data: { files: [{ id: 1 }] } });
      const onProgress = vi.fn();

      await uploadFont(file, onProgress);
      const { onUploadProgress } = vi.mocked(http.post).mock.calls[0]![2]!;
      onUploadProgress!({ loaded: 1, total: undefined } as never);

      expect(onProgress).not.toHaveBeenCalled();
    });
  });

  // ---------------------------------------------------------------------------
  // fetchFontBlob / fetchFontDetails
  // ---------------------------------------------------------------------------

  test('fetchFontBlob GETs the download URL as a blob and returns a Blob', async () => {
    vi.mocked(http.get).mockResolvedValue({ data: 'font-bytes' });

    const blob = await fetchFontBlob(7);

    expect(http.get).toHaveBeenCalledWith('/fonts/download/7', {
      responseType: 'blob',
      withCredentials: false,
      withXSRFToken: false,
    });
    expect(blob).toBeInstanceOf(Blob);
    expect(await blob.text()).toBe('font-bytes');
  });

  test('fetchFontDetails GETs /fonts/details/:id and returns the details object', async () => {
    const details = { Name: 'Roboto', Version: '2.137' };
    vi.mocked(http.get).mockResolvedValue({ data: { details } });

    await expect(fetchFontDetails(7)).resolves.toEqual(details);
    expect(http.get).toHaveBeenCalledWith('/fonts/details/7');
  });

  // ---------------------------------------------------------------------------
  // downloadFont
  // ---------------------------------------------------------------------------

  test('downloadFont navigates to the font download URL under the install root', () => {
    downloadFont(7);

    expect(navigateToDownload).toHaveBeenCalledWith('/cms/json/fonts/download/7');
  });

  // ---------------------------------------------------------------------------
  // deleteFont
  // ---------------------------------------------------------------------------

  test('deleteFont sends DELETE /fonts/:id/delete as an XHR request', async () => {
    vi.mocked(http.delete).mockResolvedValue({});

    await deleteFont(7);

    expect(http.delete).toHaveBeenCalledWith('/fonts/7/delete', {
      headers: { 'X-Requested-With': 'XMLHttpRequest' },
    });
  });

  test('deleteFont rejects when the request fails', async () => {
    vi.mocked(http.delete).mockRejectedValue(new Error('Network Error'));

    await expect(deleteFont(7)).rejects.toThrow('Network Error');
  });
});
