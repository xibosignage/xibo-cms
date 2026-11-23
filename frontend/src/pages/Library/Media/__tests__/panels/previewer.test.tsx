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

import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type { ComponentProps } from 'react';
import { vi, beforeEach, afterEach, describe, test, expect } from 'vitest';

import MediaPreviewer from '../../components/MediaPreviewer';
import { mockEditMedia, mockUser } from '../mediaTestUtils';

import { fetchMediaBlob, getMediaStreamUrl } from '@/services/mediaApi';
import { fetchUsers } from '@/services/userApi';
import type { FetchUsersResponse } from '@/services/userApi';
import { knownFailure } from '@/testUtils/knownFailure';
import type { Media } from '@/types/media';
import type { User } from '@/types/user';

// Full-screen preview opened from a media thumbnail. useOwner and MediaInfoPanel are
// real; only the media and user services are mocked.

// ─── Mocks ────────────────────────────────────────────────────────────────────

vi.mock('@/services/mediaApi', () => ({
  fetchMediaBlob: vi.fn(),
  getMediaStreamUrl: vi.fn(),
}));
vi.mock('@/services/userApi', () => ({ fetchUsers: vi.fn() }));

// ─── Fixtures ─────────────────────────────────────────────────────────────────

const MEDIA: Media = { ...mockEditMedia, mediaId: 42, name: 'Welcome', ownerId: '3' };

const buildUser = (userId: number): User => ({
  ...mockUser,
  userId,
  userName: `user${userId}`,
  email: `user${userId}@example.com`,
});

// GET /user, first page of a CMS with 25 users.
const FIRST_PAGE_OF_25_USERS = {
  rows: Array.from({ length: 10 }, (_, i) => buildUser(i + 1)),
  totalCount: 25,
} as unknown as FetchUsersResponse;

// ─── Helpers ──────────────────────────────────────────────────────────────────

const createObjectURL = vi.fn();
const revokeObjectURL = vi.fn();

const renderPreviewer = (props: Partial<ComponentProps<typeof MediaPreviewer>> = {}) => {
  const onClose = vi.fn();
  const utils = render(
    <MediaPreviewer
      mediaId={42}
      mediaType="image"
      fileName="Welcome"
      mediaData={MEDIA}
      folderName="Marketing"
      onClose={onClose}
      {...props}
    />,
  );
  return { onClose, ...utils };
};

const blobPassedToObjectUrl = () => createObjectURL.mock.calls[0]![0] as Blob;

// ─── Tests ────────────────────────────────────────────────────────────────────

describe('MediaPreviewer', () => {
  const originalCreate = window.URL.createObjectURL;
  const originalRevoke = window.URL.revokeObjectURL;

  beforeEach(() => {
    vi.clearAllMocks();
    createObjectURL.mockReturnValue('blob:media-42');
    Object.assign(window.URL, { createObjectURL, revokeObjectURL });
    vi.mocked(fetchMediaBlob).mockResolvedValue(new Blob(['png'], { type: 'image/png' }));
    vi.mocked(getMediaStreamUrl).mockImplementation(
      (id) => `/json/library/download/${id}?stream=1`,
    );
    vi.mocked(fetchUsers).mockResolvedValue(FIRST_PAGE_OF_25_USERS);
  });

  afterEach(() => {
    Object.assign(window.URL, { createObjectURL: originalCreate, revokeObjectURL: originalRevoke });
  });

  describe('what is shown', () => {
    test('nothing is shown when no media has been chosen', () => {
      const { container } = renderPreviewer({ mediaId: null });

      expect(container).toBeEmptyDOMElement();
      expect(fetchMediaBlob).not.toHaveBeenCalled();
    });

    test('an image is downloaded and shown under its name', async () => {
      renderPreviewer();

      expect(screen.getByRole('heading', { name: 'Welcome' })).toBeInTheDocument();
      expect(await screen.findByRole('img', { name: 'Welcome' })).toHaveAttribute(
        'src',
        'blob:media-42',
      );
      expect(fetchMediaBlob).toHaveBeenCalledWith(42);
    });

    test('says it is loading until the file has arrived', async () => {
      let respond!: (b: Blob) => void;
      vi.mocked(fetchMediaBlob).mockReturnValue(new Promise((res) => (respond = res)));
      renderPreviewer();

      expect(await screen.findByText('Loading Media...')).toBeInTheDocument();
      respond(new Blob(['png'], { type: 'image/png' }));
      expect(await screen.findByRole('img', { name: 'Welcome' })).toBeInTheDocument();
    });

    // Video and audio are streamed so playback can start before the whole file has
    // downloaded; buffering them into a blob first is the regression to guard.
    test.each(['video', 'audio'])(
      '%s files are streamed from the CMS, not downloaded first',
      async (type) => {
        const { container } = renderPreviewer({ mediaType: type });

        const player = await waitFor(() => {
          const el = container.ownerDocument.querySelector(type);
          expect(el).not.toBeNull();
          return el!;
        });
        expect(player).toHaveAttribute('src', '/json/library/download/42?stream=1');
        expect(fetchMediaBlob).not.toHaveBeenCalled();
      },
    );

    test('a PDF is shown in an embedded viewer', async () => {
      vi.mocked(fetchMediaBlob).mockResolvedValue(new Blob(['%PDF'], { type: 'application/pdf' }));
      renderPreviewer({ mediaType: 'pdf', fileName: 'Menu' });

      expect(await screen.findByTitle('Menu')).toHaveAttribute('src', 'blob:media-42');
      // The browser only renders the PDF inline if the blob is typed as one.
      expect(blobPassedToObjectUrl().type).toBe('application/pdf');
    });

    test('when the type is not known, it is worked out from the downloaded file', async () => {
      vi.mocked(fetchMediaBlob).mockResolvedValue(new Blob(['%PDF'], { type: 'application/pdf' }));
      renderPreviewer({ mediaType: null, fileName: 'Unknown' });

      expect(await screen.findByTitle('Unknown')).toBeInTheDocument();
    });

    test('a file that cannot be previewed says so', async () => {
      vi.mocked(fetchMediaBlob).mockResolvedValue(new Blob(['x'], { type: 'application/zip' }));
      renderPreviewer({ mediaType: 'genericfile' });

      expect(
        await screen.findByText('Preview not available for this file type!'),
      ).toBeInTheDocument();
    });

    test('a failed download says the preview could not be loaded', async () => {
      vi.spyOn(console, 'error').mockImplementation(() => {});
      vi.mocked(fetchMediaBlob).mockRejectedValue(new Error('404'));
      renderPreviewer();

      expect(await screen.findByText('Failed to load media preview')).toBeInTheDocument();
    });

    test('closing the preview releases the downloaded file from memory', async () => {
      const { unmount } = renderPreviewer();
      await screen.findByRole('img', { name: 'Welcome' });

      unmount();

      expect(revokeObjectURL).toHaveBeenCalledWith('blob:media-42');
    });
  });

  describe('header actions', () => {
    test('the Close button and the Escape key both close the preview', async () => {
      const user = userEvent.setup();
      const { onClose } = renderPreviewer();
      await screen.findByRole('img', { name: 'Welcome' });

      await user.click(screen.getByRole('button', { name: 'Close' }));
      await user.keyboard('{Escape}');

      expect(onClose).toHaveBeenCalledTimes(2);
    });

    test('Move, Share and Download appear only when the page offers them', async () => {
      renderPreviewer();
      await screen.findByRole('img', { name: 'Welcome' });

      for (const name of ['Move', 'Share', 'Download']) {
        expect(screen.queryByRole('button', { name })).not.toBeInTheDocument();
      }
    });

    test('each offered action calls the page, and Share passes the media id', async () => {
      const user = userEvent.setup();
      const onMove = vi.fn();
      const onShare = vi.fn();
      const onDownload = vi.fn();
      renderPreviewer({ onMove, onShare, onDownload });
      await screen.findByRole('img', { name: 'Welcome' });

      await user.click(screen.getByRole('button', { name: 'Move' }));
      await user.click(screen.getByRole('button', { name: 'Share' }));
      await user.click(screen.getByRole('button', { name: 'Download' }));

      expect(onMove).toHaveBeenCalledOnce();
      expect(onShare).toHaveBeenCalledWith(42);
      expect(onDownload).toHaveBeenCalledOnce();
    });

    test('Download is locked while a download is already running', async () => {
      renderPreviewer({ onDownload: vi.fn(), isDownloading: true });

      expect(await screen.findByRole('button', { name: 'Download' })).toBeDisabled();
    });

    test('Details is only offered when there is media data to show', async () => {
      renderPreviewer({ mediaData: null });
      await screen.findByRole('img', { name: 'Welcome' });

      expect(screen.queryByRole('button', { name: 'Details' })).not.toBeInTheDocument();
    });
  });

  describe('details panel', () => {
    test('shows the owner and folder of the media', async () => {
      renderPreviewer();

      expect(await screen.findByText('user3')).toBeInTheDocument();
      expect(screen.getByText('Marketing')).toBeInTheDocument();
    });

    // Open — xibosignage/xibo#4060.
    knownFailure(
      'the details panel finds the owner even when they are not among the first ten users',
      async () => {
        renderPreviewer({ mediaData: { ...MEDIA, ownerId: '21' } });
        await waitFor(() => expect(fetchUsers).toHaveBeenCalled());
        await screen.findByRole('img', { name: 'Welcome' });
      },
      async () => expect(await screen.findByText('user21')).toBeInTheDocument(),
    );

    // Open — xibosignage/xibo#4060.
    knownFailure(
      'a normal user sees the owner of media someone else shared with them',
      async () => {
        vi.mocked(fetchUsers).mockResolvedValue({
          rows: [buildUser(5)],
          totalCount: 1,
        } as unknown as FetchUsersResponse);
        renderPreviewer({ mediaData: { ...MEDIA, ownerId: '2' } });
        await waitFor(() => expect(fetchUsers).toHaveBeenCalled());
        await screen.findByRole('img', { name: 'Welcome' });
      },
      async () => expect(await screen.findByText('user2')).toBeInTheDocument(),
    );

    // Open — xibosignage/xibo#4067.
    knownFailure(
      'the details panel stays out of the accessibility tree until Details is clicked',
      async () => {
        renderPreviewer();
        await screen.findByRole('img', { name: 'Welcome' });
      },
      () => expect(screen.queryByRole('complementary')).not.toBeInTheDocument(),
    );
  });
});
