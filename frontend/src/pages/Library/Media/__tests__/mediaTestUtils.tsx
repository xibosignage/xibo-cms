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

// =============================================================================
// Shared test helpers for the Media page.
// =============================================================================

import { QueryClientProvider } from '@tanstack/react-query';
import { render, screen, fireEvent } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import { vi } from 'vitest';

import Media from '../Media';
import { useMediaData } from '../hooks/useMediaData';

import { UploadProvider } from '@/context/UploadContext';
import { UserProvider } from '@/context/UserContext';
import { testQueryClient } from '@/setupTests';
import type { Folder } from '@/types/folder';
import type { Media as MediaItem } from '@/types/media';
import type { User, UserFeatures } from '@/types/user';

// ---------------------------------------------------------------------------
// mockEditMedia.
// userPermissions.edit: 1  so the Edit quick-action button is visible.
// expires: ''             so expiry resolves to { type: 'never' } = "Never Expire".
// tags: [{ tag: 'nature', value: 'forest' }] for serialisation tests.
// ---------------------------------------------------------------------------
export const mockEditMedia: MediaItem = {
  mediaId: 42,
  name: 'test-image.png',
  folderId: 1,
  storedAs: 'test-image.png',
  thumbnail: '',
  mediaType: 'image',
  createdDt: '2026-01-01 00:00:00',
  modifiedDt: '2026-01-02 00:00:00',
  ownerId: '1',
  valid: true,
  fileName: 'test-image.png',
  fileSizeFormatted: '500 KB',
  fileSize: 512000,
  orientation: 'landscape',
  tags: [{ tag: 'nature', value: 'forest', tagId: 1 }],
  duration: 10,
  mediaNoExpiryDate: '1',
  enableStat: 'Inherit',
  expires: '',
  released: 1,
  retired: false,
  updateInLayouts: false,
  userPermissions: { view: 1, edit: 1, delete: 1 },
  deleteOldRevisions: false,
};

// -----------------------------------------------------------------------------
// Fake users
//
// Two pre-built users with different permission levels.
// -----------------------------------------------------------------------------

// This user can see and use the folder panel.
export const userWithFolders: User = {
  userId: 1,
  userName: 'FolderUser',
  userTypeId: 1,
  homeFolderId: 1,
  features: {
    'folder.view': true,
  } as UserFeatures,
} as User;

// This user has no folder permission — no folder panel, no Move actions.
export const userWithoutFolders: User = {
  userId: 2,
  userName: 'NoFolderUser',
  userTypeId: 1,
  homeFolderId: 1,
  features: {} as UserFeatures,
} as User;

// -----------------------------------------------------------------------------
// Fake folders
//
// Two simple folders used as move destinations in folder-related tests.
// -----------------------------------------------------------------------------

export const mockArchiveFolder: Folder = {
  id: 99,
  text: 'Archive',
  isRoot: 0,
  type: null,
  parentId: 0,
  ownerId: 0,
  ownerName: '',
  children: [],
};

export const mockDesignFolder: Folder = {
  id: 10,
  text: 'Design',
  isRoot: 0,
  type: null,
  parentId: 0,
  ownerId: 0,
  ownerName: '',
  children: [],
};

// -----------------------------------------------------------------------------
// Fake table data
// -----------------------------------------------------------------------------

// A table with a single image row that has full permissions.
// Triggers the bulk action bar and all per-row action buttons.
export const ONE_MEDIA_ITEM = {
  data: {
    rows: [
      {
        mediaId: 1,
        name: 'sample.jpg',
        mediaType: 'image',
        userPermissions: { view: 1, edit: 1, delete: 1 },
      },
    ],
    totalCount: 1,
  },
  isFetching: false,
  isError: false,
  error: null,
};

// A table with two rows that sit in different folders.
// Used to test what happens when a move spans more than one source folder.
export const MEDIA_ITEMS_IN_DIFFERENT_FOLDERS = {
  data: {
    rows: [
      {
        mediaId: 1,
        name: 'photo.jpg',
        folderId: 5,
        mediaType: 'image',
        userPermissions: { view: 1, edit: 1, delete: 1 },
      },
      {
        mediaId: 2,
        name: 'doc.pdf',
        folderId: mockDesignFolder.id,
        mediaType: 'generic',
        userPermissions: { view: 1, edit: 1, delete: 1 },
      },
    ],
    totalCount: 2,
  },
  isFetching: false,
  isError: false,
  error: null,
};

// The default logged-in user for most Media page tests.
export const mockUser: User = {
  userId: 1,
  userName: 'MockUser',
  userTypeId: 1,
  email: 'mockemail@email.com',
  firstName: 'Mock',
  lastName: 'User',
  phone: '123456789',
  features: {
    'folder.view': true,
  } as UserFeatures,
} as User;

// ---------------------------------------------------------------------------
// Typed mock helper
// Centralises the cast so it doesn't repeat across every test.
// ---------------------------------------------------------------------------

export type UseMediaReturn = ReturnType<typeof useMediaData>;

export const mockMediaData = (overrides: unknown) => {
  vi.mocked(useMediaData).mockReturnValue(overrides as UseMediaReturn);
};

// openEditModal: waits for the media row to appear (DataTable hydrated), then
// clicks the Edit quick-action button and waits for the dialog.
export const openEditModal = async () => {
  await screen.findAllByText(mockEditMedia.name);
  const editBtn = screen.getByRole('button', { name: 'Edit' });
  fireEvent.click(editBtn);
  return screen.findByRole('dialog', { name: 'Edit Media' });
};

// ---------------------------------------------------------------------------
// Render wrapper — provides all required context providers
// renderAs(user)    — use when the test cares which user is logged in
// renderMediaPage() — when the test doesn't care about permissions
// ---------------------------------------------------------------------------

export const renderAs = (user: User) => {
  testQueryClient.setQueryData(['userPref', 'media_page'], null);
  return render(
    <QueryClientProvider client={testQueryClient}>
      <UploadProvider>
        <UserProvider initialUser={user}>
          <MemoryRouter>
            <Media />
          </MemoryRouter>
        </UserProvider>
      </UploadProvider>
    </QueryClientProvider>,
  );
};

export const renderMediaPage = () => renderAs(mockUser);
