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

import { act, renderHook, waitFor } from '@testing-library/react';
import { beforeEach, describe, expect, test, vi } from 'vitest';

import { hasFailed, isStoring, useUploadQueue, type UploadItem } from '@/hooks/useUploadQueue';
import { uploadMedia, uploadThumbnail } from '@/services/mediaApi';

vi.mock('@/services/mediaApi', () => ({
  uploadMedia: vi.fn(),
  updateMedia: vi.fn(),
  uploadMediaFromUrl: vi.fn(),
  uploadThumbnail: vi.fn(),
}));

const first = (queue: UploadItem[]): UploadItem => queue[0]!;

const video = () => new File(['video'], 'clip.mp4', { type: 'video/mp4' });

// An upload that stays in flight until the test resolves it.
const holdUpload = () => {
  let resolve: () => void = () => {};
  vi.mocked(uploadMedia).mockImplementation(
    () =>
      new Promise((done) => {
        resolve = () => done({ data: { files: [{ mediaId: 42, duration: 10 }] } } as never);
      }),
  );
  return () => resolve();
};

describe('isStoring', () => {
  const item = (overrides: Partial<UploadItem>): UploadItem => ({
    id: '1',
    type: 'file',
    progress: 0,
    status: 'uploading',
    ...overrides,
  });

  test('is false while the browser is still sending the file', () => {
    expect(isStoring(item({ progress: 60 }))).toBe(false);
  });

  test('is true once the file is sent and the CMS has not replied', () => {
    expect(isStoring(item({ progress: 100 }))).toBe(true);
  });

  test('is true for a URL upload in progress', () => {
    expect(isStoring(item({ type: 'url', progress: 0 }))).toBe(true);
  });

  test('is false once the upload has finished', () => {
    expect(isStoring(item({ progress: 100, status: 'completed' }))).toBe(false);
  });
});

describe('hasFailed', () => {
  const item = (overrides: Partial<UploadItem>): UploadItem => ({
    id: '1',
    type: 'file',
    progress: 100,
    status: 'completed',
    ...overrides,
  });

  test('is true when the upload failed', () => {
    expect(hasFailed(item({ status: 'error' }))).toBe(true);
  });

  test('is true when the upload finished but its thumbnail failed', () => {
    expect(hasFailed(item({ thumbnailFailed: true }))).toBe(true);
  });

  test('is false for a finished upload', () => {
    expect(hasFailed(item({}))).toBe(false);
  });

  test('is false while uploading', () => {
    expect(hasFailed(item({ status: 'uploading', progress: 50 }))).toBe(false);
  });
});

describe('useUploadQueue video thumbnails', () => {
  beforeEach(() => {
    vi.mocked(uploadThumbnail).mockReset().mockResolvedValue(undefined);
  });

  // The thumbnail is captured while the upload is still running; it must be sent once the media exists,
  // without waiting for the upload dialog to be confirmed.
  test('sends a thumbnail captured during the upload once the media exists', async () => {
    const finishUpload = holdUpload();
    const { result } = renderHook(() => useUploadQueue(1));
    const blob = new Blob(['frame'], { type: 'image/png' });

    act(() => result.current.addFiles([video()]));
    await waitFor(() => expect(first(result.current.queue).status).toBe('uploading'));

    act(() =>
      result.current.updateFileData(first(result.current.queue).id, { thumbnailBlob: blob }),
    );
    expect(uploadThumbnail).not.toHaveBeenCalled();

    await act(async () => finishUpload());

    await waitFor(() => expect(uploadThumbnail).toHaveBeenCalledWith({ mediaId: 42, image: blob }));
    expect(uploadThumbnail).toHaveBeenCalledTimes(1);
  });

  test('flags a failed thumbnail and sends it again when retaken', async () => {
    const finishUpload = holdUpload();
    vi.mocked(uploadThumbnail).mockRejectedValueOnce(new Error('500'));
    const { result } = renderHook(() => useUploadQueue(1));

    act(() => result.current.addFiles([video()]));
    await waitFor(() => expect(first(result.current.queue).status).toBe('uploading'));
    act(() =>
      result.current.updateFileData(first(result.current.queue).id, {
        thumbnailBlob: new Blob(['a']),
      }),
    );
    await act(async () => finishUpload());

    await waitFor(() => expect(first(result.current.queue).thumbnailFailed).toBe(true));
    expect(uploadThumbnail).toHaveBeenCalledTimes(1);

    const retaken = new Blob(['b']);
    act(() =>
      result.current.updateFileData(first(result.current.queue).id, { thumbnailBlob: retaken }),
    );

    await waitFor(() => expect(first(result.current.queue).thumbnailFailed).toBe(false));
    expect(uploadThumbnail).toHaveBeenLastCalledWith({ mediaId: 42, image: retaken });
  });

  test('clears the failed flag as soon as a retake starts', async () => {
    const finishUpload = holdUpload();
    vi.mocked(uploadThumbnail).mockRejectedValueOnce(new Error('500'));
    const { result } = renderHook(() => useUploadQueue(1));

    act(() => result.current.addFiles([video()]));
    await waitFor(() => expect(first(result.current.queue).status).toBe('uploading'));
    act(() =>
      result.current.updateFileData(first(result.current.queue).id, {
        thumbnailBlob: new Blob(['a']),
      }),
    );
    await act(async () => finishUpload());
    await waitFor(() => expect(first(result.current.queue).thumbnailFailed).toBe(true));

    // The retaken thumbnail stays in flight, so only the retake itself can clear the flag.
    vi.mocked(uploadThumbnail).mockReturnValue(new Promise(() => {}));
    act(() =>
      result.current.updateFileData(first(result.current.queue).id, {
        thumbnailBlob: new Blob(['b']),
      }),
    );

    expect(first(result.current.queue).thumbnailFailed).toBe(false);
  });
});

describe('useUploadQueue clearFinished', () => {
  beforeEach(() => {
    vi.mocked(uploadThumbnail).mockReset().mockResolvedValue(undefined);
  });

  test('removes finished uploads and keeps those still in flight', async () => {
    const finish = holdUpload();
    const { result } = renderHook(() => useUploadQueue());

    act(() => result.current.addFiles([video()]));
    await waitFor(() => expect(first(result.current.queue).status).toBe('uploading'));

    act(() => result.current.clearFinished());
    expect(result.current.queue).toHaveLength(1);

    await act(async () => finish());
    await waitFor(() => expect(first(result.current.queue).status).toBe('completed'));

    act(() => result.current.clearFinished());
    expect(result.current.queue).toHaveLength(0);
  });

  test('keeps a finished upload whose thumbnail is still to be sent', async () => {
    vi.mocked(uploadThumbnail).mockReturnValue(new Promise(() => {}));
    const finish = holdUpload();
    const { result } = renderHook(() => useUploadQueue());

    act(() => result.current.addFiles([video()]));
    await waitFor(() => expect(first(result.current.queue).status).toBe('uploading'));
    act(() =>
      result.current.updateFileData(first(result.current.queue).id, {
        thumbnailBlob: new Blob(['thumb']),
      }),
    );

    await act(async () => finish());
    await waitFor(() => expect(first(result.current.queue).status).toBe('completed'));

    act(() => result.current.clearFinished());
    expect(result.current.queue).toHaveLength(1);
  });

  test('keeps a finished upload whose thumbnail failed, so it can be retaken', async () => {
    vi.mocked(uploadThumbnail).mockRejectedValue(new Error('rejected'));
    const finish = holdUpload();
    const { result } = renderHook(() => useUploadQueue());

    act(() => result.current.addFiles([video()]));
    await waitFor(() => expect(first(result.current.queue).status).toBe('uploading'));
    act(() =>
      result.current.updateFileData(first(result.current.queue).id, {
        thumbnailBlob: new Blob(['thumb']),
      }),
    );

    await act(async () => finish());
    await waitFor(() => expect(first(result.current.queue).thumbnailFailed).toBe(true));

    act(() => result.current.clearFinished());
    expect(result.current.queue).toHaveLength(1);
  });
});
