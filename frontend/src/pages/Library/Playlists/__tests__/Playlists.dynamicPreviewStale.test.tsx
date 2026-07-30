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

import { screen, fireEvent, waitFor, within } from '@testing-library/react';
import { vi, beforeEach, describe, test, expect } from 'vitest';

import { usePlaylistActions } from '../hooks/usePlaylistActions';

import { defaultPlaylistActions, mockPlaylist, mockUser, renderPlaylistsPage } from './playlistTestUtils';

import { fetchMedia } from '@/services/mediaApi';
import { fetchPlaylist } from '@/services/playlistApi';
import { testQueryClient } from '@/setupTests';

// =============================================================================
// Module mocks
// =============================================================================

vi.mock('@/services/folderApi');
vi.mock('@/services/playlistApi');
vi.mock('@/services/userApi', () => ({
  fetchUserPreference: vi.fn().mockResolvedValue(null),
  saveUserPreference: vi.fn().mockResolvedValue(undefined),
}));

vi.mock('../hooks/usePlaylistActions', () => ({ usePlaylistActions: vi.fn() }));
vi.mock('../hooks/usePlaylistFilterOptions', () => ({
  usePlaylistFilterOptions: vi.fn(() => ({ filterOptions: [], isLoading: false })),
}));

// Deliberately NOT mocking useMediaData here - this test needs the real hook
// and the real shared QueryClient cache/invalidation behaviour to reproduce
// the reported bug.
vi.mock('@/services/mediaApi', () => ({
  fetchMedia: vi.fn(),
}));

vi.mock('@/hooks/useDebounce');
vi.mock('@/components/ui/FolderActionModals', () => ({ default: () => null }));
vi.mock('@/components/ui/forms/SelectFolder', () => ({ default: () => null }));
vi.mock('@/components/ui/modals/Modal');

// A dynamic playlist filtered by the "letters" tag.
const DYNAMIC_PLAYLIST = { ...mockPlaylist, isDynamic: true, filterMediaTags: 'letters' };

const FIVE_IMAGES = {
  rows: [
    { mediaId: 1, name: 'Image One', mediaType: 'image' },
    { mediaId: 2, name: 'Image Two', mediaType: 'image' },
    { mediaId: 3, name: 'Image Three', mediaType: 'image' },
    { mediaId: 4, name: 'Image Four', mediaType: 'image' },
    { mediaId: 5, name: 'Image Five', mediaType: 'image' },
  ],
  totalCount: 5,
};

const FOUR_IMAGES_TAG_REMOVED_FROM_TWO = {
  rows: FIVE_IMAGES.rows.filter((r) => r.mediaId !== 2),
  totalCount: 4,
};

const openEditModalFor = async () => {
  await screen.findByText(DYNAMIC_PLAYLIST.name);
  fireEvent.click(screen.getByTitle('Edit'));
  return screen.findByRole('dialog', { name: 'Edit Playlist' });
};

describe('Dynamic Playlist edit form - matching-media preview freshness', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    vi.mocked(usePlaylistActions).mockReturnValue(defaultPlaylistActions());
    vi.mocked(fetchPlaylist).mockResolvedValue({ rows: [DYNAMIC_PLAYLIST], totalCount: 1 });
  });

  // Reproduces the reported scenario:
  // 1. Open the Dynamic Playlist edit form - 5 images matching tag "letters" show up.
  // 2. Close the form, remove the tag from one image elsewhere in the app (simulated
  //    here the same way Media.tsx's handleRefresh does: invalidate the shared
  //    ['media'] query cache after a successful edit).
  // 3. Reopen the SAME edit form - the preview should now show only 4 images.
  test('preview reflects a tag removed elsewhere in the app after invalidation + reopen', async () => {
    vi.mocked(fetchMedia).mockResolvedValueOnce(FIVE_IMAGES);

    const { unmount } = renderPlaylistsPage();
    const dialog = await openEditModalFor();
    await within(dialog).findByText('Image Two');
    expect(within(dialog).getAllByText(/Image (One|Two|Three|Four|Five)/)).toHaveLength(5);

    // Close the form the same way a user would (Cancel), then unmount the page
    // to mimic navigating away entirely.
    fireEvent.click(within(dialog).getByRole('button', { name: 'Cancel' }));
    unmount();

    // Simulate: user went to Library > Media, removed the "letters" tag from
    // Image Two, and saved - which invalidates the shared ['media'] query
    // cache exactly like Media.tsx's handleRefresh() does.
    vi.mocked(fetchMedia).mockResolvedValue(FOUR_IMAGES_TAG_REMOVED_FROM_TWO);
    testQueryClient.invalidateQueries({ queryKey: ['media'] });

    // Reopen the same Dynamic Playlist edit form.
    renderPlaylistsPage();
    const reopenedDialog = await openEditModalFor();

    await waitFor(() => {
      expect(within(reopenedDialog).getAllByText(/Image (One|Two|Three|Four|Five)/)).toHaveLength(4);
    });
    expect(within(reopenedDialog).queryByText('Image Two')).not.toBeInTheDocument();
  });
});
