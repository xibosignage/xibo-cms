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

import { act, renderHook } from '@testing-library/react';
import { afterEach, beforeEach, expect, test, vi } from 'vitest';

import { REFRESH_DELAY_MS, useMediaUpload } from '../useMediaUpload';

import { useUploadContext } from '@/context/UploadContext';
import type { UploadItem } from '@/hooks/useUploadQueue';

vi.mock('@/context/UploadContext', () => ({
  useUploadContext: vi.fn(),
}));

const clearQueue = vi.fn();
const clearFinished = vi.fn();
const saveMetadata = vi.fn().mockResolvedValue(undefined);
const handleRefresh = vi.fn();

let queue: UploadItem[] = [];

const item = (overrides: Partial<UploadItem>): UploadItem => ({
  id: '1',
  type: 'file',
  progress: 100,
  status: 'completed',
  mediaId: 42,
  ...overrides,
});

const renderUpload = () => {
  vi.mocked(useUploadContext).mockImplementation(
    () =>
      ({
        queue,
        addFiles: vi.fn(),
        removeFile: vi.fn(),
        clearQueue,
        clearFinished,
        updateFileData: vi.fn(),
        saveMetadata,
        addUrlToQueue: vi.fn(),
        startUploadSession: vi.fn(),
      }) as unknown as ReturnType<typeof useUploadContext>,
  );

  return renderHook(() =>
    useMediaUpload({ targetUploadFolderId: 1, canAddToFolder: true, handleRefresh }),
  );
};

beforeEach(() => {
  vi.clearAllMocks();
  queue = [];
});

afterEach(() => {
  vi.useRealTimers();
});

// Background saves refresh the list after a short pause, so a batch refreshes once.
const settle = () => act(() => vi.advanceTimersByTime(REFRESH_DELAY_MS));

test('opening the dialog clears uploads finished in an earlier session', () => {
  const { result } = renderUpload();

  act(() => result.current.setAddModalOpen(true));

  expect(result.current.isAddModalOpen).toBe(true);
  expect(clearFinished).toHaveBeenCalledTimes(1);
});

test('Done clears finished uploads, closes the dialog and refreshes the list', async () => {
  queue = [item({})];
  const { result } = renderUpload();
  act(() => result.current.setAddModalOpen(true));
  clearFinished.mockClear();

  await act(() => result.current.handleStartUpload());

  expect(saveMetadata).toHaveBeenCalledTimes(1);
  expect(clearFinished).toHaveBeenCalledTimes(1);
  expect(result.current.isAddModalOpen).toBe(false);
  expect(handleRefresh).toHaveBeenCalledTimes(1);
});

test('Done keeps the queue while uploads are still in progress', async () => {
  queue = [item({ status: 'uploading', progress: 50, mediaId: undefined })];
  const { result } = renderUpload();
  act(() => result.current.setAddModalOpen(true));
  clearFinished.mockClear();

  await act(() => result.current.handleStartUpload());

  expect(clearFinished).not.toHaveBeenCalled();
  expect(result.current.isAddModalOpen).toBe(false);
  expect(handleRefresh).toHaveBeenCalledTimes(1);
});

test('Cancel clears the queue, closes the dialog and refreshes the list', () => {
  queue = [item({ status: 'uploading', progress: 50, mediaId: undefined })];
  const { result } = renderUpload();
  act(() => result.current.setAddModalOpen(true));

  act(() => result.current.handleCancelUpload());

  expect(clearQueue).toHaveBeenCalledTimes(1);
  expect(result.current.isAddModalOpen).toBe(false);
  expect(handleRefresh).toHaveBeenCalledTimes(1);
});

test('refreshes the list when a thumbnail is saved after Done', () => {
  vi.useFakeTimers();
  const blob = new Blob(['thumb']);
  queue = [item({ thumbnailBlob: blob })];
  const { rerender } = renderUpload();

  queue = [item({ thumbnailBlob: blob, savedThumbnailBlob: blob })];
  rerender();
  settle();

  expect(handleRefresh).toHaveBeenCalledTimes(1);
});

test('refreshes the list when an upload finishes after Done', () => {
  vi.useFakeTimers();
  queue = [item({ status: 'uploading', progress: 100, mediaId: undefined })];
  const { rerender } = renderUpload();

  queue = [item({})];
  rerender();
  settle();

  expect(handleRefresh).toHaveBeenCalledTimes(1);
});

test('does not refresh the list when a thumbnail fails to save', () => {
  vi.useFakeTimers();
  const blob = new Blob(['thumb']);
  queue = [item({ thumbnailBlob: blob })];
  const { rerender } = renderUpload();

  queue = [item({ thumbnailBlob: blob, savedThumbnailBlob: blob, thumbnailFailed: true })];
  rerender();
  settle();

  expect(handleRefresh).not.toHaveBeenCalled();
});

test('refreshes the list when edited details are saved after Done', () => {
  vi.useFakeTimers();
  queue = [item({ isDirty: true })];
  const { rerender } = renderUpload();

  queue = [item({ isDirty: false })];
  rerender();
  settle();

  expect(handleRefresh).toHaveBeenCalledTimes(1);
});

test('a batch of uploads finishing together refreshes the list once, after the last', () => {
  vi.useFakeTimers();
  const pending = (id: string) => item({ id, status: 'uploading', mediaId: undefined });
  queue = [pending('1'), pending('2'), pending('3')];
  const { rerender } = renderUpload();

  queue = [item({ id: '1' }), pending('2'), pending('3')];
  rerender();
  act(() => vi.advanceTimersByTime(REFRESH_DELAY_MS - 1));
  queue = [item({ id: '1' }), item({ id: '2' }), pending('3')];
  rerender();
  act(() => vi.advanceTimersByTime(REFRESH_DELAY_MS - 1));
  queue = [item({ id: '1' }), item({ id: '2' }), item({ id: '3' })];
  rerender();

  expect(handleRefresh).not.toHaveBeenCalled();

  settle();

  expect(handleRefresh).toHaveBeenCalledTimes(1);
});
