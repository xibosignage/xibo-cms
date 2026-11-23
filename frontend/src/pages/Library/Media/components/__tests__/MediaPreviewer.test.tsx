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

import { fireEvent, render, screen } from '@testing-library/react';
import { beforeEach, expect, test, vi } from 'vitest';

import MediaPreviewer from '../MediaPreviewer';

import { fetchMediaBlob } from '@/services/mediaApi';
import { recoverPlayback } from '@/utils/recoverPlayback';

vi.mock('@/services/mediaApi', () => ({
  fetchMediaBlob: vi.fn(),
  getMediaStreamUrl: (id: number) => `/json/library/download/${id}?stream=1`,
}));
vi.mock('@/hooks/useOwner', () => ({ useOwner: () => ({ owner: null, loading: false }) }));
vi.mock('@/utils/recoverPlayback', () => ({ recoverPlayback: vi.fn() }));

beforeEach(() => {
  vi.clearAllMocks();
  vi.spyOn(console, 'error').mockImplementation(() => {});
});

test('a preview that cannot be fetched shows an error', async () => {
  vi.mocked(fetchMediaBlob).mockRejectedValue(new Error('CORS'));

  render(<MediaPreviewer mediaId={42} mediaType="image" fileName="photo.jpg" onClose={vi.fn()} />);

  expect(await screen.findByText('Failed to load media preview')).toBeInTheDocument();
  expect(screen.queryByRole('img')).not.toBeInTheDocument();
});

test('playback that cannot be recovered shows an error', () => {
  vi.mocked(recoverPlayback).mockReturnValue(false);

  const { container } = render(
    <MediaPreviewer mediaId={42} mediaType="video" fileName="clip.mp4" onClose={vi.fn()} />,
  );
  fireEvent.error(container.querySelector('video')!);

  expect(recoverPlayback).toHaveBeenCalledWith(
    expect.any(HTMLVideoElement),
    '/json/library/download/42?stream=1',
  );
  expect(screen.getByText('Failed to load media preview')).toBeInTheDocument();
});

test('playback that recovers keeps the video', () => {
  vi.mocked(recoverPlayback).mockReturnValue(true);

  const { container } = render(
    <MediaPreviewer mediaId={42} mediaType="video" fileName="clip.mp4" onClose={vi.fn()} />,
  );
  fireEvent.error(container.querySelector('video')!);

  expect(screen.queryByText('Failed to load media preview')).not.toBeInTheDocument();
  expect(container.querySelector('video')).toBeInTheDocument();
});
