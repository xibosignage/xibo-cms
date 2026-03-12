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

import { QueryClientProvider } from '@tanstack/react-query';
import { render, screen, fireEvent } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import { vi } from 'vitest';

import Media from '../Media';
import { useMediaData } from '../hooks/useMediaData';

import { UploadProvider } from '@/context/UploadContext';
import { UserProvider } from '@/context/UserContext';
import { testQueryClient } from '@/setupTests';
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
  retired: false,
  updateInLayouts: false,
  userPermissions: { view: 1, edit: 1, delete: 1 },
  deleteOldRevisions: false,
};

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
// ---------------------------------------------------------------------------

export const renderMediaPage = () => {
  return render(
    <QueryClientProvider client={testQueryClient}>
      <UploadProvider>
        <UserProvider initialUser={mockUser}>
          <MemoryRouter>
            <Media />
          </MemoryRouter>
        </UserProvider>
      </UploadProvider>
    </QueryClientProvider>,
  );
};
