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

import { vi } from 'vitest';

import { mockFont } from '../fixtures/font';

import {
  deleteFont,
  downloadFont,
  fetchFontBlob,
  fetchFontDetails,
  fetchFonts,
  uploadFont,
} from '@/services/fontApi';
import type { FetchFontsResponse } from '@/services/fontApi';
import type { Font, FontDetails } from '@/types/font';

// All helpers require vi.mock('@/services/fontApi') at the test file level.

// Makes fetchFonts return the provided data.
export const mockFetchFonts = (rawData: FetchFontsResponse) => {
  vi.mocked(fetchFonts).mockResolvedValue(rawData);
};

// Makes fetchFontDetails return the provided details.
export const mockFetchFontDetails = (details: FontDetails) => {
  vi.mocked(fetchFontDetails).mockResolvedValue(details);
};

// Makes fetchFontBlob return a small blob for the preview.
export const mockFetchFontBlob = () => {
  vi.mocked(fetchFontBlob).mockResolvedValue(new Blob(['font']));
};

// Makes uploadFont report 100% progress and resolve with the given font.
export const mockUploadFont = (font: Font = mockFont) => {
  vi.mocked(uploadFont).mockImplementation(async (_file, onProgress) => {
    onProgress?.(100);
    return font;
  });
};

// Makes deleteFont resolve.
export const mockDeleteFont = () => {
  vi.mocked(deleteFont).mockResolvedValue(undefined);
};

// Makes downloadFont resolve.
export const mockDownloadFont = () => {
  vi.mocked(downloadFont).mockResolvedValue(undefined);
};
