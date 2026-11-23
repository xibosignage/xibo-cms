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

import { afterEach, describe, expect, test, vi } from 'vitest';

import { isSafeHttpUrl, navigateToDownload } from '@/utils/url';

describe('navigateToDownload', () => {
  afterEach(() => {
    vi.restoreAllMocks();
  });

  test('clicks a temporary download link to the URL and removes it', () => {
    const click = vi.spyOn(HTMLAnchorElement.prototype, 'click').mockImplementation(() => {});

    navigateToDownload('/cms/json/library/download/42?attachment=a.mp4');

    const clicked = click.mock.instances[0] as HTMLAnchorElement | undefined;

    expect(clicked?.getAttribute('href')).toBe(
      '/cms/json/library/download/42?attachment=a.mp4&downloadLink=1',
    );
    expect(clicked?.hasAttribute('download')).toBe(true);
    expect(clicked?.isConnected).toBe(false);
  });
});

describe('isSafeHttpUrl', () => {
  test('allows http, https and relative URLs', () => {
    expect(isSafeHttpUrl('https://example.com/a')).toBe(true);
    expect(isSafeHttpUrl('http://example.com/a')).toBe(true);
    expect(isSafeHttpUrl('/json/library/download/1')).toBe(true);
  });

  test('rejects empty and script URLs', () => {
    expect(isSafeHttpUrl('')).toBe(false);
    expect(isSafeHttpUrl(null)).toBe(false);
    expect(isSafeHttpUrl('javascript:alert(1)')).toBe(false);
  });
});
