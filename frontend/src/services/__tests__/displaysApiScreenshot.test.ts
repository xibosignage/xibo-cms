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
import { fetchDisplayScreenshotBlob } from '@/services/displaysApi';

vi.mock('@/lib/api', () => ({ default: { get: vi.fn() } }));

beforeEach(() => {
  vi.clearAllMocks();
});

describe('display screenshot fetch', () => {
  test('the screenshot follows the download redirect without credentials', async () => {
    const blob = new Blob(['jpeg']);
    const signal = new AbortController().signal;
    vi.mocked(http.get).mockResolvedValue({ data: blob });

    await expect(fetchDisplayScreenshotBlob(3, signal)).resolves.toBe(blob);
    expect(http.get).toHaveBeenCalledWith('/display/screenshot/3', {
      responseType: 'blob',
      withCredentials: false,
      withXSRFToken: false,
      signal,
    });
  });
});
