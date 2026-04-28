/*
 * Copyright (C) 2026 Xibo Signage Ltd
 *
 * Xibo - Digital Signage - https://xibosignage.com
 *
 * This file is part of Xibo.
 *
 * Xibo is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * any later version.
 *
 * Xibo is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with Xibo.  If not, see <http://www.gnu.org/licenses/>.
 */

import { screen, fireEvent, waitFor, within, act } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type React from 'react';
import { vi, beforeEach, describe, test, expect } from 'vitest';

import { usePlaylistData } from '../hooks/usePlaylistData';

import { mockPlaylist, mockPlaylistData, renderPlaylistsPage } from './playlistTestUtils';

import { testQueryClient } from '@/setupTests';

// =============================================================================
// Module mocks
// =============================================================================

vi.mock('react-i18next', () => ({
  useTranslation: () => ({ t: (key: string) => key, i18n: { changeLanguage: vi.fn() } }),
  Trans: ({ children }: { children: React.ReactNode }) => children,
}));

vi.mock('@/services/folderApi', () => ({
  fetchFolderById: vi.fn().mockResolvedValue({ id: 1, text: 'Root' }),
  fetchFolderTree: vi.fn().mockResolvedValue([]),
  searchFolders: vi.fn().mockResolvedValue([]),
  fetchContextButtons: vi.fn().mockResolvedValue({ create: true }),
  selectFolder: vi.fn().mockResolvedValue({ success: true }),
}));
vi.mock('@/services/userApi', () => ({
  fetchUserPreference: vi.fn().mockResolvedValue(null),
  saveUserPreference: vi.fn().mockResolvedValue(undefined),
}));

// Bypass the 500ms debounce delay so tests don't wait on real setTimeout.
vi.mock('@/hooks/useDebounce');
vi.mock('../hooks/usePlaylistData', () => ({ usePlaylistData: vi.fn() }));
vi.mock('../hooks/usePlaylistFilterOptions', () => ({
  usePlaylistFilterOptions: vi.fn(() => ({
    filterOptions: [
      {
        label: 'Last Modified',
        name: 'lastModified',
        options: [
          { label: 'Any time', value: '' },
          { label: 'Today', value: 'today' },
          { label: 'Last 7 days', value: '7d' },
        ],
        shouldTranslateOptions: true,
        showAllOption: false,
      },
    ],
    isLoading: false,
  })),
}));

vi.mock('@/components/ui/FolderActionModals', () => ({ default: () => null }));
vi.mock('@/components/ui/FolderBreadCrumb', () => ({ default: () => null }));
vi.mock('@/components/ui/modals/Modal');

// =============================================================================
// Fixtures
// =============================================================================

// 10 rows with totalCount: 25 so pagination controls render (Next/Previous).
const PAGINATED_PLAYLISTS = {
  rows: Array.from({ length: 10 }).map((_, i) => ({
    ...mockPlaylist,
    playlistId: i + 1,
    name: `Playlist ${i + 1}`,
  })),
  totalCount: 25,
};

// =============================================================================
// Tests
// =============================================================================

describe('Playlists page - pagination', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    mockPlaylistData(PAGINATED_PLAYLISTS);
  });

  // ---------------------------------------------------------------------------
  // Typing in search while on page 2 resets pageIndex to 0.
  // ---------------------------------------------------------------------------
  test('typing in search while on page 2 resets pageIndex to 0', async () => {
    const user = userEvent.setup({ delay: null });
    await act(async () => {
      renderPlaylistsPage();
    });

    fireEvent.click(await screen.findByRole('button', { name: /Next/i }));

    await waitFor(() => {
      expect(usePlaylistData).toHaveBeenLastCalledWith(
        expect.objectContaining({
          pagination: expect.objectContaining({ pageIndex: 1 }),
        }),
      );
    });

    await user.type(screen.getByPlaceholderText('Search playlist...'), 'x');

    await waitFor(() => {
      expect(usePlaylistData).toHaveBeenLastCalledWith(
        expect.objectContaining({
          pagination: expect.objectContaining({ pageIndex: 0 }),
        }),
      );
    });
  });

  // ---------------------------------------------------------------------------
  // Applying an advanced filter while on page 2 resets pageIndex to 0.
  // ---------------------------------------------------------------------------
  test('applying an advanced filter while on page 2 resets pageIndex to 0', async () => {
    await act(async () => {
      renderPlaylistsPage();
    });

    fireEvent.click(await screen.findByRole('button', { name: /Next/i }));

    await waitFor(() => {
      expect(usePlaylistData).toHaveBeenLastCalledWith(
        expect.objectContaining({
          pagination: expect.objectContaining({ pageIndex: 1 }),
        }),
      );
    });

    await act(async () => {
      fireEvent.click(screen.getByRole('button', { name: 'Filters' }));
    });

    const lastModLabel = screen.getByText('Last Modified');
    const lastModContainer = lastModLabel.closest('div')!;
    await act(async () => {
      fireEvent.click(within(lastModContainer).getByRole('button'));
    });
    await act(async () => {
      fireEvent.click(within(lastModContainer).getByText('Today'));
    });

    await waitFor(() => {
      expect(usePlaylistData).toHaveBeenLastCalledWith(
        expect.objectContaining({
          pagination: expect.objectContaining({ pageIndex: 0 }),
        }),
      );
    });
  });
});
