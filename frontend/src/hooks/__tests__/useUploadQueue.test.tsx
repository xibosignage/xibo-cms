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
import { AxiosError, AxiosHeaders, CanceledError } from 'axios';
import { vi, beforeEach, describe, test, expect } from 'vitest';

import { useUploadQueue } from '../useUploadQueue';

import { updateMedia, uploadMedia, uploadMediaFromUrl } from '@/services/mediaApi';
import type { UploadMediaRequest } from '@/services/mediaApi';
import { deferred } from '@/testUtils/deferred';
import { knownFailure } from '@/testUtils/knownFailure';
import { trackSequentialCalls } from '@/testUtils/sequentialMock';

// The upload queue behind Library > Media "Add Media" (and the global drop zone).
// Files are uploaded one at a time; name/tag edits made while or after a file
// uploads are written to the media item the upload created.

// ─── Mocks ────────────────────────────────────────────────────────────────────

vi.mock('@/services/mediaApi', () => ({
  uploadMedia: vi.fn(),
  uploadMediaFromUrl: vi.fn(),
  updateMedia: vi.fn(),
}));

// ─── Fixtures ─────────────────────────────────────────────────────────────────

const file = (name: string) => new File(['x'], name, { type: 'image/png' });

const uploaded = (mediaId: number, extra: Record<string, unknown> = {}) => ({
  data: { files: [{ mediaId, duration: 10, enableStat: 'Inherit', retired: 0, ...extra }] },
});

const apiError = (message: string) =>
  new AxiosError('Request failed', '422', undefined, undefined, {
    status: 422,
    statusText: 'Unprocessable',
    headers: {},
    config: { headers: new AxiosHeaders() },
    data: { message },
  });

// ─── Helpers ──────────────────────────────────────────────────────────────────

const renderQueue = (defaultFolderId = 7) => renderHook(() => useUploadQueue(defaultFolderId));

const uploadCall = (n: number) => vi.mocked(uploadMedia).mock.calls[n]![0] as UploadMediaRequest;

// ─── Tests ────────────────────────────────────────────────────────────────────

describe('useUploadQueue', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    vi.mocked(uploadMedia).mockResolvedValue(uploaded(100) as never);
    vi.mocked(updateMedia).mockResolvedValue({} as never);
  });

  describe('uploading files', () => {
    test('an added file uploads straight away under its own name, into the default folder', async () => {
      const { result } = renderQueue(7);

      act(() => result.current.addFiles([file('welcome.png')]));

      await waitFor(() => expect(uploadMedia).toHaveBeenCalledOnce());
      expect(uploadCall(0)).toEqual(
        expect.objectContaining({ name: 'welcome.png', folderId: 7, tags: [] }),
      );
      expect(uploadCall(0).file.name).toBe('welcome.png');
    });

    test('a folder chosen for the upload is used instead of the default', async () => {
      const { result } = renderQueue(7);

      act(() => result.current.addFiles([file('welcome.png')], 42));

      await waitFor(() => expect(uploadCall(0).folderId).toBe(42));
    });

    test('several files upload one at a time, in the order they were added', async () => {
      const tracker = trackSequentialCalls(vi.mocked(uploadMedia), uploaded(100), {
        key: ((req: UploadMediaRequest) => req.name!) as never,
      });
      const { result } = renderQueue();

      act(() => result.current.addFiles([file('a.png'), file('b.png'), file('c.png')]));

      await waitFor(() => expect(uploadMedia).toHaveBeenCalledTimes(3));
      expect(tracker.callOrder).toEqual(['a.png', 'b.png', 'c.png']);
      expect(tracker.maxInFlight).toBe(1);
    });

    test('progress is reported while uploading, and the item completes with what the server sent back', async () => {
      const upload = deferred<unknown>();
      vi.mocked(uploadMedia).mockReturnValue(upload.promise as never);
      const { result } = renderQueue();

      act(() => result.current.addFiles([file('welcome.png')]));
      await waitFor(() => expect(result.current.queue[0]!.status).toBe('uploading'));
      expect(result.current.hasPendingUploads).toBe(true);

      act(() => uploadCall(0).onProgress?.(55));
      expect(result.current.queue[0]!.progress).toBe(55);

      await act(async () =>
        upload.resolve(uploaded(321, { duration: 30, enableStat: 'On', retired: 1 })),
      );
      await waitFor(() => expect(result.current.queue[0]!.status).toBe('completed'));
      expect(result.current.queue[0]).toEqual(
        expect.objectContaining({
          progress: 100,
          mediaId: 321,
          duration: 30,
          enableStat: 'On',
          retired: 1,
        }),
      );
      expect(result.current.hasPendingUploads).toBe(false);
    });

    test('tags given before the upload starts are sent with it, one per entry', async () => {
      const upload = deferred<unknown>();
      vi.mocked(uploadMedia).mockReturnValueOnce(upload.promise as never);
      const { result } = renderQueue();

      // The first file holds the queue, so the second is still pending when tagged.
      act(() => result.current.addFiles([file('first.png'), file('second.png')]));
      await waitFor(() => expect(uploadMedia).toHaveBeenCalledOnce());
      act(() =>
        result.current.updateFileData(result.current.queue[1]!.id, { tags: 'lobby, promo|summer' }),
      );
      await act(async () => upload.resolve(uploaded(1)));

      await waitFor(() => expect(uploadMedia).toHaveBeenCalledTimes(2));
      expect(uploadCall(1).tags).toEqual(['lobby', 'promo|summer']);
    });
  });

  describe('failures', () => {
    test.each([
      ['the server rejects the file', uploaded(0, { error: 'File too large' }), 'File too large'],
      [
        'the server returns no media id',
        { data: { files: [{}] } },
        'Upload successful, but no Media ID was returned.',
      ],
    ])('when %s the item shows the reason', async (_label, response, message) => {
      vi.mocked(uploadMedia).mockResolvedValue(response as never);
      const { result } = renderQueue();

      act(() => result.current.addFiles([file('big.png')]));

      await waitFor(() => expect(result.current.queue[0]!.status).toBe('error'));
      expect(result.current.queue[0]!.error).toBe(message);
    });

    test('a refused request shows the message from the API response', async () => {
      vi.mocked(uploadMedia).mockRejectedValue(apiError('Storage quota exceeded'));
      const { result } = renderQueue();

      act(() => result.current.addFiles([file('big.png')]));

      await waitFor(() => expect(result.current.queue[0]!.error).toBe('Storage quota exceeded'));
    });

    test('one failed file does not stop the rest of the queue', async () => {
      vi.mocked(uploadMedia)
        .mockRejectedValueOnce(apiError('Bad file'))
        .mockResolvedValueOnce(uploaded(2) as never);
      const { result } = renderQueue();

      act(() => result.current.addFiles([file('bad.png'), file('good.png')]));

      await waitFor(() => expect(result.current.queue[1]!.status).toBe('completed'));
      expect(result.current.queue[0]!.status).toBe('error');
    });

    // The upload never created a media item, so renaming it is the user's way to retry.
    test('renaming a file that failed to upload uploads it again under the new name', async () => {
      vi.mocked(uploadMedia)
        .mockRejectedValueOnce(apiError('Name already in use'))
        .mockResolvedValueOnce(uploaded(9) as never);
      const { result } = renderQueue();

      act(() => result.current.addFiles([file('welcome.png')]));
      await waitFor(() => expect(result.current.queue[0]!.status).toBe('error'));
      act(() => result.current.updateFileData(result.current.queue[0]!.id, { name: 'welcome-2' }));

      await waitFor(() => expect(uploadMedia).toHaveBeenCalledTimes(2));
      expect(uploadCall(1).name).toBe('welcome-2');
      await waitFor(() => expect(result.current.queue[0]!.status).toBe('completed'));
    });
  });

  describe('editing after upload', () => {
    test('renaming an uploaded file saves the new name to the media item that was created', async () => {
      vi.mocked(uploadMedia).mockResolvedValue(
        uploaded(321, { duration: 30, enableStat: 'On', retired: 0 }) as never,
      );
      const { result } = renderQueue();

      act(() => result.current.addFiles([file('welcome.png')]));
      await waitFor(() => expect(result.current.queue[0]!.status).toBe('completed'));
      act(() =>
        result.current.updateFileData(result.current.queue[0]!.id, {
          name: 'Welcome',
          tags: 'lobby',
        }),
      );

      await waitFor(() => expect(updateMedia).toHaveBeenCalledOnce());
      // Duration, stats and retired are sent back as the upload reported them, so the
      // rename does not reset them.
      expect(updateMedia).toHaveBeenCalledWith(321, {
        name: 'Welcome',
        tags: 'lobby',
        duration: 30,
        enableStat: 'On',
        retired: 0,
      });
    });

    test('a rename made while the file is still uploading is saved once the upload finishes', async () => {
      const upload = deferred<unknown>();
      vi.mocked(uploadMedia).mockReturnValue(upload.promise as never);
      const { result } = renderQueue();

      act(() => result.current.addFiles([file('welcome.png')]));
      await waitFor(() => expect(result.current.queue[0]!.status).toBe('uploading'));
      act(() => result.current.updateFileData(result.current.queue[0]!.id, { name: 'Welcome' }));
      expect(updateMedia).not.toHaveBeenCalled();

      await act(async () => upload.resolve(uploaded(321)));

      await waitFor(() =>
        expect(updateMedia).toHaveBeenCalledWith(321, expect.objectContaining({ name: 'Welcome' })),
      );
    });

    test('a saved rename is sent once, not repeatedly', async () => {
      const { result } = renderQueue();

      act(() => result.current.addFiles([file('welcome.png')]));
      await waitFor(() => expect(result.current.queue[0]!.status).toBe('completed'));
      act(() => result.current.updateFileData(result.current.queue[0]!.id, { name: 'Welcome' }));
      await waitFor(() => expect(result.current.queue[0]!.isDirty).toBe(false));

      expect(updateMedia).toHaveBeenCalledOnce();
    });

    test('a rename the server refuses shows the reason on that file', async () => {
      vi.mocked(updateMedia).mockRejectedValue(apiError('Name already in use'));
      const { result } = renderQueue();

      act(() => result.current.addFiles([file('welcome.png')]));
      await waitFor(() => expect(result.current.queue[0]!.status).toBe('completed'));
      act(() => result.current.updateFileData(result.current.queue[0]!.id, { name: 'Menu' }));

      await waitFor(() => expect(result.current.queue[0]!.status).toBe('error'));
      expect(result.current.queue[0]!.error).toBe('Name already in use');
    });
  });

  describe('uploading from a URL', () => {
    test('is named after the last part of the URL, without its query string', async () => {
      vi.mocked(uploadMediaFromUrl).mockResolvedValue({ data: { mediaId: 5 } } as never);
      const { result } = renderQueue(7);

      act(() => result.current.addUrlToQueue('https://example.com/img/banner.jpg?v=2'));

      await waitFor(() => expect(uploadMediaFromUrl).toHaveBeenCalledOnce());
      expect(uploadMediaFromUrl).toHaveBeenCalledWith({
        url: 'https://example.com/img/banner.jpg?v=2',
        name: 'banner.jpg',
        folderId: 7,
        tags: [],
      });
      // A remote file with no reported duration gets the 10 s default.
      await waitFor(() => expect(result.current.queue[0]!.duration).toBe(10));
    });

    test('a URL the server cannot fetch shows the reason', async () => {
      vi.mocked(uploadMediaFromUrl).mockResolvedValue({
        data: { error: 'Unable to download' },
      } as never);
      const { result } = renderQueue();

      act(() => result.current.addUrlToQueue('https://example.com/missing.jpg'));

      await waitFor(() => expect(result.current.queue[0]!.error).toBe('Unable to download'));
    });
  });

  describe('removing and clearing', () => {
    test('a file removed before its turn is never uploaded', async () => {
      const first = deferred<unknown>();
      vi.mocked(uploadMedia).mockReturnValueOnce(first.promise as never);
      const { result } = renderQueue();

      act(() => result.current.addFiles([file('first.png'), file('second.png')]));
      await waitFor(() => expect(uploadMedia).toHaveBeenCalledOnce());
      act(() => result.current.removeFile(result.current.queue[1]!.id));
      await act(async () => first.resolve(uploaded(1)));

      await waitFor(() => expect(result.current.queue[0]!.status).toBe('completed'));
      expect(uploadMedia).toHaveBeenCalledOnce();
    });

    test('removing the file that is uploading cancels its request', async () => {
      const upload = deferred<unknown>();
      vi.mocked(uploadMedia).mockReturnValue(upload.promise as never);
      const { result } = renderQueue();

      act(() => result.current.addFiles([file('first.png')]));
      await waitFor(() => expect(uploadMedia).toHaveBeenCalledOnce());
      act(() => result.current.removeFile(result.current.queue[0]!.id));

      expect(uploadCall(0).signal!.aborted).toBe(true);
      expect(result.current.queue).toHaveLength(0);
      await act(async () => upload.reject(new CanceledError()));
    });

    test('clearing the queue cancels the upload in flight and empties the list', async () => {
      const upload = deferred<unknown>();
      vi.mocked(uploadMedia).mockReturnValue(upload.promise as never);
      const { result } = renderQueue();

      act(() => result.current.addFiles([file('first.png'), file('second.png')]));
      await waitFor(() => expect(uploadMedia).toHaveBeenCalledOnce());
      act(() => result.current.clearQueue());

      expect(uploadCall(0).signal!.aborted).toBe(true);
      expect(result.current.queue).toHaveLength(0);
      await act(async () => upload.reject(new CanceledError()));
      expect(uploadMedia).toHaveBeenCalledOnce();
    });

    // Open — xibosignage/xibo#4061.
    knownFailure(
      'removing the uploading file never leaves two uploads running at once',
      async () => {
        let inFlight = 0;
        let maxInFlight = 0;
        let settled = 0;
        const pending: Array<ReturnType<typeof deferred<unknown>>> = [];
        vi.mocked(uploadMedia).mockImplementation(((req: UploadMediaRequest) => {
          inFlight += 1;
          maxInFlight = Math.max(maxInFlight, inFlight);
          const d = deferred<unknown>();
          req.signal?.addEventListener('abort', () => d.reject(new CanceledError()));
          pending.push(d);
          return d.promise.finally(() => {
            inFlight -= 1;
            settled += 1;
          });
        }) as never);
        const { result } = renderQueue();

        act(() => result.current.addFiles([file('a.png'), file('b.png'), file('c.png')]));
        await waitFor(() => expect(uploadMedia).toHaveBeenCalledOnce());
        // Remove "a" while it uploads; "b" starts, then a's cancellation settles.
        await act(async () => result.current.removeFile(result.current.queue[0]!.id));
        await waitFor(() =>
          expect(vi.mocked(uploadMedia).mock.calls.length).toBeGreaterThanOrEqual(2),
        );
        // a's settling is what (wrongly) frees the queue; then drain microtasks and effects.
        await waitFor(() => expect(settled).toBe(1));
        await act(async () => new Promise((r) => setTimeout(r, 0)));
        return () => maxInFlight;
      },
      (getMax) => expect(getMax()).toBe(1),
    );
  });
});
