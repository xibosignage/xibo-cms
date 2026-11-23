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

import { render, screen, fireEvent } from '@testing-library/react';
import { vi, beforeEach, test, expect } from 'vitest';

import { UploadProgressDock } from '../UploadProgressDock';

import { useUploadContext } from '@/context/UploadContext';
import type { UploadItem } from '@/hooks/useUploadQueue';

vi.mock('@/context/UploadContext', () => ({
  useUploadContext: vi.fn(),
}));

const clearQueue = vi.fn();
const clearFinished = vi.fn();

function renderWith(queue: Partial<UploadItem>[]) {
  vi.mocked(useUploadContext).mockReturnValue({
    queue: queue.map((item, i) => ({ id: String(i), type: 'file', progress: 100, ...item })),
    clearQueue,
    clearFinished,
    uploadSession: 0,
  } as unknown as ReturnType<typeof useUploadContext>);

  return render(<UploadProgressDock />);
}

beforeEach(() => {
  vi.clearAllMocks();
});

test('a failed thumbnail counts as an error', () => {
  renderWith([
    { displayName: 'clip.mp4', status: 'completed', thumbnailFailed: true },
    { displayName: 'photo.jpg', status: 'completed' },
  ]);

  expect(screen.getByText('Uploads completed with errors')).toBeInTheDocument();
});

test('finished uploads without failures are shown as completed', () => {
  renderWith([{ displayName: 'photo.jpg', status: 'completed' }]);

  expect(screen.getByText('Uploads completed')).toBeInTheDocument();
});

test('dismissing keeps items that can be retried', () => {
  renderWith([{ displayName: 'clip.mp4', status: 'completed', thumbnailFailed: true }]);

  fireEvent.click(screen.getByRole('button', { name: 'Dismiss' }));

  expect(clearFinished).toHaveBeenCalledTimes(1);
  expect(clearQueue).not.toHaveBeenCalled();
});

test('the dock cannot be dismissed while uploads are in progress', () => {
  renderWith([{ displayName: 'clip.mp4', status: 'uploading', progress: 40 }]);

  expect(screen.queryByRole('button', { name: 'Dismiss' })).not.toBeInTheDocument();
});

test('the collapse button hides and shows the item list', () => {
  renderWith([{ displayName: 'photo.jpg', status: 'completed' }]);

  fireEvent.click(screen.getByRole('button', { name: 'Collapse' }));
  expect(screen.queryByText('photo.jpg')).not.toBeInTheDocument();

  fireEvent.click(screen.getByRole('button', { name: 'Expand' }));
  expect(screen.getByText('photo.jpg')).toBeInTheDocument();
});

test('items left over from an earlier session are not counted in a new one', () => {
  const stale: Partial<UploadItem> = {
    id: 'old',
    type: 'file',
    progress: 100,
    displayName: 'old.mp4',
    status: 'error',
  };
  // Opening the Add Media dialog starts upload session 1.
  const withQueue = (queue: Partial<UploadItem>[]) =>
    vi.mocked(useUploadContext).mockReturnValue({
      queue,
      clearQueue,
      clearFinished,
      uploadSession: 1,
    } as unknown as ReturnType<typeof useUploadContext>);
  const { rerender } = renderWith([stale]);

  withQueue([
    stale,
    { id: 'a', type: 'file', progress: 0, displayName: 'a.jpg', status: 'pending' },
    { id: 'b', type: 'file', progress: 0, displayName: 'b.jpg', status: 'pending' },
  ]);
  rerender(<UploadProgressDock />);
  withQueue([
    stale,
    { id: 'a', type: 'file', progress: 100, displayName: 'a.jpg', status: 'completed' },
    { id: 'b', type: 'file', progress: 10, displayName: 'b.jpg', status: 'uploading' },
  ]);
  rerender(<UploadProgressDock />);

  expect(screen.getByText('1/2 items are still uploading')).toBeInTheDocument();
  expect(screen.queryByText('old.mp4')).not.toBeInTheDocument();
});

test('a retried item from an earlier session rejoins the new one', () => {
  const old: Partial<UploadItem> = {
    id: 'old',
    type: 'file',
    progress: 100,
    displayName: 'old.mp4',
  };
  const { rerender } = renderWith([{ ...old, status: 'error' }]);

  vi.mocked(useUploadContext).mockReturnValue({
    queue: [{ ...old, status: 'error' }],
    clearQueue,
    clearFinished,
    uploadSession: 1,
  } as unknown as ReturnType<typeof useUploadContext>);
  rerender(<UploadProgressDock />);
  expect(screen.queryByText('old.mp4')).not.toBeInTheDocument();

  vi.mocked(useUploadContext).mockReturnValue({
    queue: [{ ...old, progress: 0, status: 'pending' }],
    clearQueue,
    clearFinished,
    uploadSession: 1,
  } as unknown as ReturnType<typeof useUploadContext>);
  rerender(<UploadProgressDock />);

  expect(screen.getByText('0/1 items are still uploading')).toBeInTheDocument();
  expect(screen.getByText('old.mp4')).toBeInTheDocument();
});
