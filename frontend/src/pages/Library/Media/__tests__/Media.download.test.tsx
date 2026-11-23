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

import { fireEvent, screen, waitFor, within } from '@testing-library/react';
import { beforeEach, describe, expect, test, vi } from 'vitest';

import { mockEditMedia, mockMediaData, renderMediaPage } from './mediaTestUtils';

import { downloadMedia } from '@/services/mediaApi';
import { testQueryClient } from '@/setupTests';

vi.mock('@/pages/Library/Media/hooks/useMediaFilterOptions', () => ({
  useMediaFilterOptions: vi.fn().mockReturnValue({ filterOptions: [], isLoading: false }),
}));
vi.mock('../hooks/useMediaData');
vi.mock('@/services/mediaApi', () => ({
  uploadMedia: vi.fn(),
  uploadMediaFromUrl: vi.fn(),
  updateMedia: vi.fn(),
  uploadThumbnail: vi.fn(),
  deleteMedia: vi.fn(),
  downloadMedia: vi.fn(),
  fetchMediaBlob: vi.fn(),
}));
vi.mock('@/services/folderApi', () => ({
  fetchFolderById: vi.fn().mockResolvedValue({ id: 1, text: 'Root' }),
  fetchFolderTree: vi.fn().mockResolvedValue([]),
  searchFolders: vi.fn().mockResolvedValue([]),
  fetchContextButtons: vi.fn().mockResolvedValue({ create: true }),
  selectFolder: vi.fn(),
}));
vi.mock('@/services/userApi', () => ({
  fetchUserPreference: vi.fn().mockResolvedValue(null),
  saveUserPreference: vi.fn().mockResolvedValue(undefined),
}));

// The library stores each upload under its own name (42.png) and keeps the
// name it was uploaded with. In mockEditMedia the two are the same, which
// hides which one a download uses.
const holidayPhoto = { ...mockEditMedia, storedAs: '42.png', fileName: 'holiday.png' };

const clickDownload = async () => {
  await screen.findAllByText(holidayPhoto.name);
  fireEvent.click(screen.getByRole('button', { name: 'Download' }));
  await waitFor(() => {
    expect(downloadMedia).toHaveBeenCalledTimes(1);
  });
};

// Regression cover for xibosignage/xibo#3944: downloading one file saved it
// under the library's internal name (42.png) instead of the name it was
// uploaded with.
describe('Media - downloading one file', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    vi.mocked(downloadMedia).mockResolvedValue(undefined);
    mockMediaData({
      data: { rows: [holidayPhoto], totalCount: 1 },
      isFetching: false,
      isError: false,
      error: null,
    });
  });

  test('the file is saved under the name it was uploaded with', async () => {
    renderMediaPage();
    await clickDownload();

    expect(downloadMedia).toHaveBeenCalledWith(42, 'holiday.png');
  });
});

// =============================================================================
// The other two ways to download one file
// =============================================================================

// The row's Download button is not the only caller. Selecting one row and
// using the toolbar's Download, and the Download button in the preview, both
// download a single file too, each through its own code.

/** Select the only row and click Download in the selection toolbar. */
const downloadSelected = async () => {
  fireEvent.click((await screen.findAllByRole('checkbox', { name: /Select row/i }))[0]!);
  const toolbar = (await screen.findByTitle('Clear selection')).parentElement!.parentElement!;
  fireEvent.click(within(toolbar).getByRole('button', { name: 'Download' }));
};

/** Open the row's preview and click Download in it. */
const downloadFromPreview = async () => {
  fireEvent.click((await screen.findAllByRole('button', { name: 'Preview media' }))[0]!);
  const downloads = await screen.findAllByRole('button', { name: 'Download' });
  // The preview's button is the last one rendered (after the row's own).
  fireEvent.click(downloads[downloads.length - 1]!);
};

const PATHS = [
  ['the selection toolbar', downloadSelected],
  ['the preview', downloadFromPreview],
] as const;

/** The file name the page handed to downloadMedia. */
const downloadedName = async () => {
  await waitFor(() => {
    expect(downloadMedia).toHaveBeenCalledTimes(1);
  });
  return vi.mocked(downloadMedia).mock.calls[0]![1];
};

describe('Media - downloading one file from the toolbar or the preview', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    vi.mocked(downloadMedia).mockResolvedValue(undefined);
  });

  const showRow = (row: object) =>
    mockMediaData({
      data: { rows: [row], totalCount: 1 },
      isFetching: false,
      isError: false,
      error: null,
    });

  test.each(PATHS)(
    'downloading from %s uses the name the file was uploaded with',
    async (_, download) => {
      showRow(holidayPhoto);
      renderMediaPage();
      await download();

      expect(downloadMedia).toHaveBeenCalledWith(42, 'holiday.png');
    },
  );

  // Report 218. Media with no original file name comes back from the API with
  // `fileName: null`; these paths fall back to the name it is stored under.
  // https://github.com/xibosignage/xibo/issues/4049
  test.each(PATHS)(
    'downloading from %s gives a file with no original name a real name',
    async (_, download) => {
      showRow({ ...holidayPhoto, fileName: null });
      renderMediaPage();
      await download();

      expect(await downloadedName()).toBe('42.png');
    },
  );
});
