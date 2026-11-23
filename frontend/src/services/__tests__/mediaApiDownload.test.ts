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

import { beforeEach, describe, expect, test, vi } from 'vitest';

import http from '@/lib/api';
import { downloadMedia, fetchMediaBlob, getMediaStreamUrl } from '@/services/mediaApi';
import { navigateToDownload } from '@/utils/url';

vi.mock('@/config/publicPath', () => ({
  withPublicPath: (path: string) => `/cms/${path}`,
}));
vi.mock('@/lib/api', () => ({ default: { get: vi.fn() } }));
vi.mock('@/utils/url', () => ({ navigateToDownload: vi.fn() }));
vi.mock('@/workers/zipWorker?worker', () => ({ default: vi.fn() }));

beforeEach(() => {
  vi.clearAllMocks();
});

describe('media download URLs', () => {
  test('the stream URL is under the install root', () => {
    expect(getMediaStreamUrl(42)).toBe('/cms/json/library/download/42?stream=1');
  });

  test('a download navigates to the attachment URL with the file name encoded', () => {
    downloadMedia(42, 'my file & co.mp4');

    expect(navigateToDownload).toHaveBeenCalledWith(
      '/cms/json/library/download/42?attachment=my%20file%20%26%20co.mp4',
    );
  });

  test('a blob fetch follows the download redirect without credentials', async () => {
    const blob = new Blob(['bytes']);
    vi.mocked(http.get).mockResolvedValue({ data: blob });

    await expect(fetchMediaBlob(42)).resolves.toBe(blob);
    expect(http.get).toHaveBeenCalledWith('/library/download/42', {
      responseType: 'blob',
      withCredentials: false,
      withXSRFToken: false,
    });
  });
});
