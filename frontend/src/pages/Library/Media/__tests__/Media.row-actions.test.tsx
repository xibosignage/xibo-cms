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

import { screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { vi, beforeEach, describe, test, expect } from 'vitest';

import { mockEditMedia, mockMediaData, renderMediaPage } from './mediaTestUtils';

import type * as CmsVisibilityModule from '@/hooks/useCmsVisibility';
import { cloneMedia, downloadMedia, setMediaEnableStat } from '@/services/mediaApi';
import { testQueryClient } from '@/setupTests';
import { mockNotifySuccess } from '@/testUtils/notifyMock';
import type { Media } from '@/types/media';

// Page-level wiring for the single-item row actions that had no test: Make a Copy,
// Replace File, Enable Stats Collection, Details and Download. Each test proves the
// action opens for the row it was clicked on, using two rows so the wrong one would
// show. The modals' own behaviour is in modals/.

// ─── Mocks ────────────────────────────────────────────────────────────────────

vi.mock('@/pages/Library/Media/hooks/useMediaFilterOptions', () => ({
  useMediaFilterOptions: vi.fn().mockReturnValue({ filterOptions: [], isLoading: false }),
}));
vi.mock('../hooks/useMediaData');
// The real hook can settle after the first render and re-render the page and its rows.
vi.mock('@/hooks/useCmsVisibility', async (importOriginal) => ({
  ...(await importOriginal<typeof CmsVisibilityModule>()),
  useServiceVisibility: () => () => 'shown',
}));
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

// Render every row-menu action as a plain button; the floating-ui menu does not
// open under JSDOM. (Same stub as the Layouts page tests.)
vi.mock('@/components/ui/table/DataTableRowActions', () => ({
  default: ({
    row,
    actions,
  }: {
    row: unknown;
    actions: Array<{ label?: string; onClick?: (r: unknown) => void; isSeparator?: boolean }>;
  }) => (
    <div>
      {actions
        .filter((a) => !a.isSeparator && a.label)
        .map((action, i) => (
          <button key={i} onClick={() => action.onClick?.(row)}>
            {action.label}
          </button>
        ))}
    </div>
  ),
}));

// ─── Fixtures ─────────────────────────────────────────────────────────────────

const row = (mediaId: number, name: string, overrides: Partial<Media> = {}): Media => ({
  ...mockEditMedia,
  mediaId,
  name,
  fileName: `${name.toLowerCase()}.png`,
  userPermissions: { view: 1, edit: 1, delete: 1, modifyPermissions: 1 },
  ...overrides,
});

const WELCOME = row(1, 'Welcome', { enableStat: 'On' });
const MENU = row(2, 'Menu', { enableStat: 'Off' });

// ─── Helpers ──────────────────────────────────────────────────────────────────

// The <tr> holding a media item, found by its name cell.
const rowFor = async (name: string) => (await screen.findByText(name)).closest('tr') as HTMLElement;

// ─── Tests ────────────────────────────────────────────────────────────────────

describe('Media row actions', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    mockMediaData({
      data: { rows: [WELCOME, MENU], totalCount: 2 },
      isFetching: false,
      isError: false,
      error: null,
    });
  });

  describe('Make a Copy', () => {
    test('opens the Copy modal for the row clicked, and copying sends that media id', async () => {
      const user = userEvent.setup();
      vi.mocked(cloneMedia).mockResolvedValue(MENU);
      renderMediaPage();

      await user.click(within(await rowFor('Menu')).getByRole('button', { name: 'Make a Copy' }));
      const dialog = await screen.findByRole('dialog', { name: 'Copy Media' });
      expect(within(dialog).getByRole('textbox', { name: 'New name' })).toHaveValue('Menu (1)');
      await user.click(within(dialog).getByRole('button', { name: 'Save' }));

      await waitFor(() => expect(cloneMedia).toHaveBeenCalledOnce());
      expect(cloneMedia).toHaveBeenCalledWith(
        expect.objectContaining({ mediaId: 2, name: 'Menu (1)' }),
      );
      await waitFor(() =>
        expect(screen.queryByRole('dialog', { name: 'Copy Media' })).not.toBeInTheDocument(),
      );
    });

    // The page passes the names on screen as "existing", so a copy can't be given the
    // name of another row.
    test('a copy cannot take the name of another media item on the page', async () => {
      const user = userEvent.setup();
      renderMediaPage();

      await user.click(within(await rowFor('Menu')).getByRole('button', { name: 'Make a Copy' }));
      const dialog = await screen.findByRole('dialog', { name: 'Copy Media' });
      const name = within(dialog).getByRole('textbox', { name: 'New name' });
      await user.clear(name);
      await user.type(name, 'Welcome');
      await user.click(within(dialog).getByRole('button', { name: 'Save' }));

      expect(
        within(dialog).getByText('A media item with this name already exists'),
      ).toBeInTheDocument();
      expect(cloneMedia).not.toHaveBeenCalled();
    });
  });

  test('Replace File opens the Replace File modal for the row clicked', async () => {
    const user = userEvent.setup();
    renderMediaPage();

    await user.click(within(await rowFor('Menu')).getByRole('button', { name: 'Replace File' }));

    const dialog = await screen.findByRole('dialog', { name: 'Replace File' });
    expect(within(dialog).getByRole('textbox', { name: 'Name' })).toHaveValue('Menu');
  });

  test('Enable Stats Collection opens on the row’s current setting and saves it for that row', async () => {
    const user = userEvent.setup();
    vi.mocked(setMediaEnableStat).mockResolvedValue(undefined);
    renderMediaPage();

    await user.click(
      within(await rowFor('Menu')).getByRole('button', { name: 'Enable Stats Collection' }),
    );
    const dialog = await screen.findByRole('dialog', { name: 'Enable Stats Collection' });
    expect(
      within(dialog).getByRole('combobox', { name: 'Enable Media Stats Collection?' }),
    ).toHaveTextContent('Off');
    await user.click(within(dialog).getByRole('button', { name: 'Save' }));

    await waitFor(() => expect(setMediaEnableStat).toHaveBeenCalledWith(2, 'Off'));
  });

  test('Details opens the side panel with the file details of the row clicked', async () => {
    const user = userEvent.setup();
    renderMediaPage();

    await user.click(within(await rowFor('Menu')).getByRole('button', { name: 'Details' }));

    expect(await screen.findByText('File Details')).toBeInTheDocument();
    expect(screen.getByText('menu.png')).toBeInTheDocument();
    expect(screen.queryByText('welcome.png')).not.toBeInTheDocument();
  });

  describe('Download', () => {
    test('downloads the row’s file under its original file name and says so', async () => {
      const user = userEvent.setup();
      vi.mocked(downloadMedia).mockResolvedValue(undefined);
      renderMediaPage();

      await user.click(
        within(await rowFor('Menu')).getAllByRole('button', { name: 'Download' })[0]!,
      );

      await waitFor(() => expect(downloadMedia).toHaveBeenCalledWith(2, 'menu.png'));
      expect(mockNotifySuccess).toHaveBeenCalledWith('Download started!');
    });

    // xibosignage/xibo#4064: the download is a navigation now, so the browser's own download list
    // shows a failed download; the page never sees the response.
  });
});
