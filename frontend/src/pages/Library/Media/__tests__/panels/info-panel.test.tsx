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

import { render, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type { ComponentProps } from 'react';
import { vi, beforeEach, describe, test, expect } from 'vitest';

import { MediaInfoPanel } from '../../components/MediaInfoPanel';
import { mockEditMedia, mockUser } from '../mediaTestUtils';

import { knownFailure } from '@/testUtils/knownFailure';
import type { Media } from '@/types/media';

// MediaInfoPanel is presentational. It is shared by the full-screen MediaPreviewer
// (dark) and the Media grid's Details side panel (light, applyVersionTwo); the two
// themes differ only in CSS classes, which are not asserted.

const renderPanel = (props: Partial<ComponentProps<typeof MediaInfoPanel>> = {}) => {
  const onClose = vi.fn();
  render(
    <MediaInfoPanel
      onClose={onClose}
      mediaData={mockEditMedia}
      owner={mockUser}
      folderName="Marketing"
      loading={false}
      {...props}
    />,
  );
  return { onClose };
};

const withMedia = (overrides: Partial<Media>): Media => ({ ...mockEditMedia, ...overrides });

describe('MediaInfoPanel', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  describe('owner', () => {
    test('shows the owner name and email once they have loaded', () => {
      renderPanel();

      expect(screen.getByText('MockUser')).toBeInTheDocument();
      expect(screen.getByText('mockemail@email.com')).toBeInTheDocument();
    });

    test('hides the owner while it is still loading', () => {
      renderPanel({ loading: true });

      expect(screen.queryByText('MockUser')).not.toBeInTheDocument();
    });

    test('shows a dash for the name and email when the owner could not be found', () => {
      renderPanel({ owner: null });

      // Look only inside the Owner block; the panel also has an unfinished, CSS-hidden
      // "Sharing" block that renders a dash of its own.
      const ownerBlock = screen.getByText('OWNER').parentElement!;
      expect(within(ownerBlock).getAllByText('-')).toHaveLength(2);
      expect(screen.queryByText('MockUser')).not.toBeInTheDocument();
    });
  });

  describe('file details', () => {
    test('shows the folder, type, file name and dates', () => {
      renderPanel({
        mediaData: withMedia({
          mediaType: 'video',
          fileName: 'promo.mp4',
          createdDt: '2026-01-01 09:00:00',
          modifiedDt: '2026-02-01 09:00:00',
          expires: '2026-12-31 23:59:00',
        }),
      });

      for (const text of [
        'Marketing',
        'video',
        'promo.mp4',
        '2026-01-01 09:00:00',
        '2026-02-01 09:00:00',
        '2026-12-31 23:59:00',
      ]) {
        expect(screen.getByText(text)).toBeInTheDocument();
      }
    });

    test('lists every tag, or says there are none', () => {
      renderPanel({
        mediaData: withMedia({
          tags: [
            { tag: 'nature', value: 'forest', tagId: 1 },
            { tag: 'lobby', value: '', tagId: 2 },
          ],
        }),
      });
      expect(screen.getByText('nature')).toBeInTheDocument();
      expect(screen.getByText('lobby')).toBeInTheDocument();
      expect(screen.queryByText('NO TAGS')).not.toBeInTheDocument();
    });

    test('says there are no tags when the media has none', () => {
      renderPanel({ mediaData: withMedia({ tags: [] }) });

      expect(screen.getByText('NO TAGS')).toBeInTheDocument();
    });

    test('shows the duration as hours, minutes and seconds', () => {
      renderPanel({ mediaData: withMedia({ duration: 3725 }) });

      expect(screen.getByText('01:02:05')).toBeInTheDocument();
    });

    test('shows the size both formatted and in bytes', () => {
      renderPanel({ mediaData: withMedia({ fileSizeFormatted: '500 KB', fileSize: 512000 }) });

      expect(screen.getByText('500 KB')).toBeInTheDocument();
      expect(screen.getByText('512000 Bytes')).toBeInTheDocument();
    });

    test('shows the resolution, with a dash for a missing width or height', () => {
      renderPanel({ mediaData: withMedia({ width: 1920, height: 0 } as Partial<Media>) });

      expect(screen.getByText('1920x-')).toBeInTheDocument();
    });

    // The API sends expires as 0 (not '') for media that never expires.
    test('media that never expires shows a dash for the expiry date', () => {
      renderPanel({ mediaData: withMedia({ expires: 0 as unknown as string }) });

      const expiry = screen.getByText('EXPIRY DATE').parentElement!;
      expect(within(expiry).getByText('-')).toBeInTheDocument();
      expect(within(expiry).queryByText('0')).not.toBeInTheDocument();
    });

    test('with no media selected none of the file details are shown', () => {
      renderPanel({ mediaData: null });

      expect(screen.getByText('File Details')).toBeInTheDocument();
      expect(screen.queryByText('OWNER')).not.toBeInTheDocument();
    });
  });

  describe('closing', () => {
    test('the header button closes the panel', async () => {
      const user = userEvent.setup();
      const { onClose } = renderPanel();

      // The panel's only button; it has no accessible name (knownFailure below).
      const buttons = screen.getAllByRole('button');
      expect(buttons).toHaveLength(1);
      await user.click(buttons[0]!);

      expect(onClose).toHaveBeenCalledOnce();
    });

    // Open — xibosignage/xibo#4067.
    knownFailure(
      'the panel close button is announced as "Close" to screen readers',
      () => {
        renderPanel();
      },
      () => expect(screen.getByRole('button', { name: 'Close' })).toBeInTheDocument(),
    );
  });
});
