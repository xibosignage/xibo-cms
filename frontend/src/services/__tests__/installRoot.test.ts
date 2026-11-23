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

// A CMS installed in a sub-folder (/cms/) instead of at the web root. The
// install root comes from <meta name="public-path">, which publicPath.ts and
// lib/api.ts read once, when they are first imported. So the meta is set in
// vi.hoisted, before any import below runs. Every other service test runs at
// the root (/), where a hard-coded path would pass too.

import { afterEach, beforeEach, describe, expect, test, vi } from 'vitest';

import { fetchDisplays, fetchDisplaysMap } from '@/services/displaysApi';
import { getMediaStreamUrl } from '@/services/mediaApi';
import { fetchUserPreference } from '@/services/userApi';
import {
  installHttpStub,
  lastRequest,
  respondWith,
  restoreHttpStub,
} from '@/testUtils/httpAdapter';

vi.hoisted(() => {
  const meta = document.createElement('meta');
  meta.name = 'public-path';
  meta.content = '/cms/';
  document.head.appendChild(meta);
});

beforeEach(installHttpStub);
afterEach(restoreHttpStub);

describe('a CMS installed under /cms/', () => {
  test('JSON API requests go under the install root', async () => {
    respondWith({ data: [] }, { data: {} });

    await fetchDisplays();
    expect(lastRequest().path).toBe('/cms/json/display');

    await fetchUserPreference('layout_page');
    expect(lastRequest().path).toBe('/cms/json/user/pref');
  });

  test('the display map, a web route outside /json, goes under the install root', async () => {
    respondWith({ data: { type: 'FeatureCollection', features: [] } });

    await fetchDisplaysMap();

    expect(lastRequest().path).toBe('/cms/display/map');
  });

  test('the media stream URL points under the install root', () => {
    expect(getMediaStreamUrl(3)).toBe('/cms/json/library/download/3?stream=1');
  });
});
