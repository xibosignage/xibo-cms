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
import { render, screen, within } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import { describe, test, expect, vi, beforeEach } from 'vitest';

import Media from '../Media';
import { useMediaFilterOptions } from '../hooks/useMediaFilterOptions';

import { mockEditMedia, mockMediaData, mockUser, type UseMediaReturn } from './mediaTestUtils';

import { UploadProvider } from '@/context/UploadContext';
import { UserProvider } from '@/context/UserContext';
import { testQueryClient } from '@/setupTests';

vi.mock('@/pages/Library/Media/hooks/useMediaFilterOptions', () => ({
  useMediaFilterOptions: vi.fn(),
}));
vi.mock('../hooks/useMediaData');
vi.mock('@/services/mediaApi', () => ({
  uploadMedia: vi.fn(),
  uploadMediaFromUrl: vi.fn(),
  updateMedia: vi.fn(),
  uploadThumbnail: vi.fn(),
  deleteMedia: vi.fn(),
  downloadMedia: vi.fn(),
  downloadMediaAsZip: vi.fn(),
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

// A media item expiring 30 minutes from the real current time.
const EXPIRES_IN_30_MIN = Math.floor(Date.now() / 1000) + 30 * 60;

const renderWithExpiresColumnVisible = () => {
  testQueryClient.setQueryData(['userPref', 'media_page'], {
    columnVisibility: { expires: true },
  });
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

describe('Media grid - Expires column', () => {
  beforeEach(() => {
    vi.mocked(useMediaFilterOptions).mockReturnValue({ filterOptions: [], isLoading: false });
    mockMediaData({
      data: {
        rows: [
          {
            ...mockEditMedia,
            mediaId: 7,
            name: 'expiring-banner.jpg',
            expires: EXPIRES_IN_30_MIN,
          },
        ],
        totalCount: 1,
      },
      isFetching: false,
      isError: false,
      error: null,
    } as UseMediaReturn);
  });

  // Regression: release44's equivalent Library grid column formatted this as
  // relative, human-readable time ("Expires in 30 minutes" /
  // mediaExpiresIn.replace('%s', momentDifference), views/library-page.twig
  // ~line 315-330), or "No expiry date" when unset. The React port's
  // MediaConfig.tsx "expires" column just renders the raw Unix timestamp
  // integer instead.
  test('shows a human-readable relative time, not a raw Unix timestamp', async () => {
    renderWithExpiresColumnVisible();

    const table = await screen.findByRole('table');
    const row = within(table).getByText('expiring-banner.jpg').closest('tr')!;

    expect(within(row).queryByText(String(EXPIRES_IN_30_MIN))).not.toBeInTheDocument();
    expect(within(row).getByText(/expires?.*30 minutes?/i)).toBeInTheDocument();
  });
});
