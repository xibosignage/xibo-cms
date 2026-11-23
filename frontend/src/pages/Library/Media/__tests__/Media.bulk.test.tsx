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

import '@/testUtils/notifyMock';

import { screen, waitFor } from '@testing-library/react';
import userEvent, { type UserEvent } from '@testing-library/user-event';
import { vi, beforeEach, describe, test, expect } from 'vitest';

import { mockEditMedia, mockMediaData, renderMediaPage } from './mediaTestUtils';

import { downloadMedia, downloadMediaAsZip } from '@/services/mediaApi';
import { testQueryClient } from '@/setupTests';
import {
  mockNotifyError,
  mockNotifyInfo,
  mockNotifySuccess,
  mockNotifyWarning,
} from '@/testUtils/notifyMock';
import type { Media } from '@/types/media';

// Bulk actions on selected rows. Each one must act only on the items the user is
// allowed to change, tell them about any it skipped, and refuse outright when
// none qualify. The modals they open are stubbed to show what they were handed.

// ─── Mocks ────────────────────────────────────────────────────────────────────

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
  cloneMedia: vi.fn(),
  replaceMedia: vi.fn(),
  setMediaEnableStat: vi.fn(),
  downloadMedia: vi.fn(),
  downloadMediaAsZip: vi.fn(),
  fetchMediaBlob: vi.fn(),
  getMediaStreamUrl: vi.fn(),
  tidyLibrary: vi.fn(),
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
  fetchUsers: vi.fn().mockResolvedValue({ rows: [], totalCount: 0 }),
}));
vi.mock('@/services/tagApi', () => ({
  fetchTags: vi.fn().mockResolvedValue({ rows: [], totalCount: 0 }),
  editMultipleTags: vi.fn(),
}));
vi.mock('@/components/ui/modals/Modal');

vi.mock('@/components/ui/modals/ShareModal', () => ({
  default: ({ entityId }: { entityId: number | number[] | null }) => (
    <div role="dialog" aria-label="Share">{`share ids: ${JSON.stringify(entityId)}`}</div>
  ),
}));
vi.mock('@/components/ui/modals/EditTagsMultipleModal', () => ({
  default: ({ ids }: { ids: number[] }) => (
    <div role="dialog" aria-label="Edit Tags">{`tag ids: ${JSON.stringify(ids)}`}</div>
  ),
}));
vi.mock('@/components/ui/modals/EnableStatsMultipleModal', () => ({
  default: ({ items, ids }: { items?: { mediaId: number }[]; ids?: number[] }) => (
    <div role="dialog" aria-label="Enable Stats Multiple">
      {`stats ids: ${JSON.stringify(ids ?? items?.map((i) => i.mediaId))}`}
    </div>
  ),
}));
vi.mock('@/components/ui/modals/MoveModal', () => ({
  default: ({ items }: { items?: { mediaId: number }[] }) => (
    <div
      role="dialog"
      aria-label="Move"
    >{`move ids: ${JSON.stringify(items?.map((i) => i.mediaId))}`}</div>
  ),
}));

// ─── Fixtures ─────────────────────────────────────────────────────────────────

const row = (mediaId: number, name: string, perms: Partial<Media['userPermissions']>): Media => ({
  ...mockEditMedia,
  mediaId,
  name,
  fileName: `${name.toLowerCase()}.png`,
  userPermissions: { view: 1, edit: 0, delete: 0, modifyPermissions: 0, ...perms },
});

const FULL = row(1, 'Welcome', { edit: 1, delete: 1, modifyPermissions: 1 });
const VIEW_ONLY = row(2, 'Menu', {});
const VIEW_ONLY_2 = row(3, 'Promo', {});

// ─── Helpers ──────────────────────────────────────────────────────────────────

const showRows = (rows: Media[]) =>
  mockMediaData({
    data: { rows, totalCount: rows.length },
    isFetching: false,
    isError: false,
    error: null,
  });

const selectAll = async (user: UserEvent, rowCount: number) => {
  expect(await screen.findAllByRole('checkbox', { name: /Select row/i })).toHaveLength(rowCount);
  // Re-query each time: selecting a row re-renders the table, so earlier nodes go stale.
  for (let i = 0; i < rowCount; i++) {
    await user.click(screen.getAllByRole('checkbox', { name: /Select row/i })[i]!);
  }
  await waitFor(() =>
    screen
      .getAllByRole('checkbox', { name: /Select row/i })
      .forEach((box) => expect(box).toBeChecked()),
  );
};

// Rows have quick-action buttons with the same names (Download, Edit…); the bulk
// bar's button is the one that is not inside a table row.
const bulk = (user: UserEvent, label: string) => {
  const matches = screen.getAllByRole('button', { name: label }).filter((b) => !b.closest('tr'));
  expect(matches).toHaveLength(1);
  return user.click(matches[0]!);
};

// ─── Tests ────────────────────────────────────────────────────────────────────

describe('Media bulk actions', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
  });

  describe('permission filtering', () => {
    test.each([
      ['Share', 'share ids: [1]'],
      ['Move', 'move ids: [1]'],
    ])(
      '%s acts only on the items the user may change, and says how many were skipped',
      async (label, expected) => {
        const user = userEvent.setup();
        showRows([FULL, VIEW_ONLY]);
        renderMediaPage();

        await selectAll(user, 2);
        await bulk(user, label);

        expect(await screen.findByText(expected)).toBeInTheDocument();
        expect(mockNotifyInfo).toHaveBeenCalledOnce();
      },
    );

    // Unlike Share and Move, these two drop the items the user can't edit without
    // saying so (Media.tsx onEditTags / onEnableStats have no "skipped" notice).
    test.each([
      ['Edit Tags', 'tag ids: [1]'],
      ['Enable Stats Collection', 'stats ids: [1]'],
    ])(
      '%s acts only on the items the user may change, without a skipped notice',
      async (label, expected) => {
        const user = userEvent.setup();
        showRows([FULL, VIEW_ONLY]);
        renderMediaPage();

        await selectAll(user, 2);
        await bulk(user, label);

        expect(await screen.findByText(expected)).toBeInTheDocument();
        expect(mockNotifyInfo).not.toHaveBeenCalled();
      },
    );

    test.each(['Share', 'Edit Tags', 'Enable Stats Collection', 'Move', 'Delete Selected'])(
      '%s with only items the user cannot change is refused with a warning',
      async (label) => {
        const user = userEvent.setup();
        showRows([VIEW_ONLY, VIEW_ONLY_2]);
        renderMediaPage();

        await selectAll(user, 2);
        await bulk(user, label);

        await waitFor(() => expect(mockNotifyWarning).toHaveBeenCalledOnce());
        expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
      },
    );

    test('Delete Selected offers only the items the user may delete', async () => {
      const user = userEvent.setup();
      showRows([FULL, VIEW_ONLY]);
      renderMediaPage();

      await selectAll(user, 2);
      await bulk(user, 'Delete Selected');

      const dialog = await screen.findByRole('dialog');
      expect(dialog).toHaveTextContent('Welcome');
      expect(dialog).not.toHaveTextContent('Menu');
      expect(mockNotifyInfo).toHaveBeenCalledOnce();
    });
  });

  describe('Download', () => {
    test('one selected item is downloaded as itself, not zipped', async () => {
      const user = userEvent.setup();
      vi.mocked(downloadMedia).mockResolvedValue(undefined);
      showRows([FULL, VIEW_ONLY]);
      renderMediaPage();

      const boxes = await screen.findAllByRole('checkbox', { name: /Select row/i });
      await user.click(boxes[1]!);
      await bulk(user, 'Download');

      await waitFor(() => expect(downloadMedia).toHaveBeenCalledWith(2, 'menu.png'));
      expect(downloadMediaAsZip).not.toHaveBeenCalled();
      expect(mockNotifySuccess).toHaveBeenCalledWith('Download started!');
    });

    // Download needs only view permission, so view-only items are included.
    test('several selected items are zipped together under their file names', async () => {
      const user = userEvent.setup();
      vi.mocked(downloadMediaAsZip).mockResolvedValue(undefined);
      showRows([FULL, VIEW_ONLY]);
      renderMediaPage();

      await selectAll(user, 2);
      await bulk(user, 'Download');

      await waitFor(() => expect(downloadMediaAsZip).toHaveBeenCalledOnce());
      expect(downloadMediaAsZip).toHaveBeenCalledWith(
        [
          { mediaId: 1, fileName: 'welcome.png' },
          { mediaId: 2, fileName: 'menu.png' },
        ],
        'media_page_export.zip',
      );
      await waitFor(() =>
        expect(mockNotifySuccess).toHaveBeenCalledWith('ZIP file generated and download started!'),
      );
    });

    test('an item with no file name is zipped under its media name', async () => {
      const user = userEvent.setup();
      vi.mocked(downloadMediaAsZip).mockResolvedValue(undefined);
      showRows([FULL, { ...VIEW_ONLY, fileName: '' }]);
      renderMediaPage();

      await selectAll(user, 2);
      await bulk(user, 'Download');

      await waitFor(() => expect(downloadMediaAsZip).toHaveBeenCalledOnce());
      const items = vi.mocked(downloadMediaAsZip).mock.calls[0]![0];
      expect(items[1]).toEqual({ mediaId: 2, fileName: 'Menu' });
    });

    test('a failed zip tells the user', async () => {
      vi.spyOn(console, 'error').mockImplementation(() => {});
      const user = userEvent.setup();
      vi.mocked(downloadMediaAsZip).mockRejectedValue(new Error('boom'));
      showRows([FULL, VIEW_ONLY]);
      renderMediaPage();

      await selectAll(user, 2);
      await bulk(user, 'Download');

      await waitFor(() =>
        expect(mockNotifyError).toHaveBeenCalledWith('An error occurred while zipping the files.'),
      );
    });
  });
});
