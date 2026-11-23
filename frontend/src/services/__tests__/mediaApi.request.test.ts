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

// What the library service puts on the wire. The saved download name is
// covered in mediaApi.download.test.ts.

import { afterEach, beforeEach, describe, expect, test } from 'vitest';

import {
  fetchMedia,
  getMediaStreamUrl,
  setMediaEnableStat,
  uploadMedia,
} from '@/services/mediaApi';
import {
  installHttpStub,
  lastRequest,
  respondWith,
  restoreHttpStub,
} from '@/testUtils/httpAdapter';

beforeEach(installHttpStub);
afterEach(restoreHttpStub);

const file = new File(['x'], 'holiday.png', { type: 'image/png' });

describe('fetchMedia', () => {
  test('returns the rows and the total from x-total-count', async () => {
    const rows = [{ mediaId: 1 }];
    respondWith({ data: rows, headers: { 'x-total-count': '9' } });

    const result = await fetchMedia({ start: 0, length: 10 });

    expect(lastRequest().path).toBe('/json/library');
    expect(lastRequest().query.get('start')).toBe('0');
    expect(result).toEqual({ rows, totalCount: 9 });
  });
});

describe('uploadMedia', () => {
  test('posts the file as multipart form data, not JSON', async () => {
    respondWith({ data: { files: [] } });

    await uploadMedia({ file });

    const req = lastRequest();
    expect(req.method).toBe('POST');
    expect(req.path).toBe('/json/library');
    expect(req.formData.get('files[]')).toBeInstanceOf(File);
  });

  test('names the media after the file, and puts it in the root folder, by default', async () => {
    respondWith({ data: { files: [] } });

    await uploadMedia({ file });

    const { formData } = lastRequest();
    expect(formData.get('name[]')).toBe('holiday.png');
    expect(formData.get('folderId')).toBe('1');
  });

  test('sends the name and folder it is given', async () => {
    respondWith({ data: { files: [] } });

    await uploadMedia({ file, name: 'Summer', folderId: 7 });

    const { formData } = lastRequest();
    expect(formData.get('name[]')).toBe('Summer');
    expect(formData.get('folderId')).toBe('7');
  });

  test('sends each tag as a repeated tags[] key', async () => {
    respondWith({ data: { files: [] } });

    await uploadMedia({ file, tags: ['summer', 'beach'] });

    expect(lastRequest().formData.getAll('tags[]')).toEqual(['summer', 'beach']);
  });

  test('sends one empty tags[] when there are no tags', async () => {
    respondWith({ data: { files: [] } });

    await uploadMedia({ file });

    expect(lastRequest().formData.getAll('tags[]')).toEqual(['']);
  });

  test('returns the upload result', async () => {
    const data = { files: [{ name: 'holiday.png', mediaId: 3 }] };
    respondWith({ data });

    expect(await uploadMedia({ file })).toEqual({ data });
  });
});

describe('setMediaEnableStat', () => {
  test('puts only the stats setting to the media item', async () => {
    await setMediaEnableStat(3, 'On');

    const req = lastRequest();
    expect(req.method).toBe('PUT');
    expect(req.path).toBe('/json/library/setenablestat/3');
    expect([...req.body.entries()]).toEqual([['enableStat', 'On']]);
  });
});

describe('getMediaStreamUrl', () => {
  // At a root install. The /cms/ sub-folder case is in installRoot.test.ts.
  test('points at the streamed download route', () => {
    expect(getMediaStreamUrl(3)).toBe('/json/library/download/3?stream=1');
  });
});
