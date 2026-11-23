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

import { fireEvent, screen, waitFor } from '@testing-library/react';
import { vi, beforeEach, describe, test, expect } from 'vitest';

import { mockEditMedia, mockMediaData, renderAs } from './mediaTestUtils';

import { testQueryClient } from '@/setupTests';
import { PERSONAS, buildCurrentUser } from '@/testUtils/personas';

// Adding media needs the Add media feature. These tests run as a content
// manager, not a super admin: hasFeature() lets a super admin through without
// reading any features, so only a restricted user can show the gate.
// (mediaTestUtils' userWithoutFolders is a super admin, so it can't be used here.)

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
  fetchFolderById: vi.fn().mockResolvedValue({ id: 5, text: 'Marketing' }),
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

// The home folder is not the root folder. A non-admin may only save in the
// root when a CMS setting allows it, so a root home folder would disable the
// button for a reason that has nothing to do with the feature.
const withAddMedia = buildCurrentUser(PERSONAS.contentManager, { homeFolderId: 5 });
const withoutAddMedia = buildCurrentUser(PERSONAS.contentManager, {
  homeFolderId: 5,
  features: { 'library.add': false },
});

// Drags a file over the page, which shows whether this user may upload here.
const dragFileOverPage = () => {
  const file = new File(['x'], 'photo.png', { type: 'image/png' });
  fireEvent.dragEnter(screen.getByRole('button', { name: /add media/i }), {
    dataTransfer: {
      files: [file],
      items: [{ kind: 'file', type: file.type, getAsFile: () => file }],
      types: ['Files'],
    },
  });
};

describe('Media: adding media needs the Add media feature', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    mockMediaData({
      data: { rows: [mockEditMedia], totalCount: 1 },
      isFetching: false,
      isError: false,
      error: null,
    });
  });

  test('a user without Add media has the Add Media button disabled', async () => {
    renderAs(withoutAddMedia);
    await screen.findAllByText(mockEditMedia.name);

    // The button is also disabled while the page loads, so first show the
    // reason: only the upload check produces this message.
    dragFileOverPage();
    expect(await screen.findByText('You cannot upload files here')).toBeInTheDocument();

    expect(screen.getByRole('button', { name: /add media/i })).toBeDisabled();
  });

  test('a user with Add media has the Add Media button enabled', async () => {
    renderAs(withAddMedia);
    await screen.findAllByText(mockEditMedia.name);

    dragFileOverPage();
    expect(await screen.findByText(/upload files to/i)).toBeInTheDocument();

    await waitFor(() => expect(screen.getByRole('button', { name: /add media/i })).toBeEnabled());
  });
});
