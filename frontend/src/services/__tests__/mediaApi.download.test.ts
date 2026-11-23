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

import { downloadMedia } from '@/services/mediaApi';

/**
 * Run the real downloadMedia and return the link the browser follows. The
 * server names the saved file from its `attachment` parameter.
 */
const followedLink = (fileName: string) => {
  let href: string | null = null;
  vi.spyOn(HTMLAnchorElement.prototype, 'click').mockImplementation(function (
    this: HTMLAnchorElement,
  ) {
    href = this.getAttribute('href');
  });

  downloadMedia(42, fileName);
  return new URL(href!, window.location.origin);
};

describe('downloadMedia - the saved file name', () => {
  afterEach(() => {
    vi.restoreAllMocks();
  });

  test('asks the server to save the file under the name it is given', () => {
    const link = followedLink('holiday photo.png');

    expect(link.pathname).toMatch(/json\/library\/download\/42$/);
    expect(link.searchParams.get('attachment')).toBe('holiday photo.png');
  });
});
