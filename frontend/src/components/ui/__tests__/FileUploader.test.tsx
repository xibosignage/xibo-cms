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

import { render, screen, fireEvent, act } from '@testing-library/react';
import { useDropzone } from 'react-dropzone';
import { vi, afterEach, beforeEach, test } from 'vitest';

import { FileUploader } from '../FileUploader';

import type { UploadItem } from '@/hooks/useUploadQueue';

// -----------------------------------------------------------------------------
// Mocks
// -----------------------------------------------------------------------------

vi.mock('react-dropzone', async (importOriginal) => {
  const actual = await importOriginal();
  return {
    ...(actual as object),
    useDropzone: vi.fn(),
  };
});
vi.mock('@/hooks/usePreline', () => ({ usePreline: vi.fn() }));
vi.mock('@/components/ui/forms/TagInput', () => ({
  default: () => <div data-testid="tag-input" />,
}));
vi.mock('@/utils/captureVideoFrame', () => ({
  captureVideoFrame: vi.fn(),
}));
vi.mock('../Notification', () => ({
  notify: { info: vi.fn(), error: vi.fn(), success: vi.fn(), warning: vi.fn() },
}));

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

// Gives the drop zone a working default shape before each test.
const defaultDropzoneMock = () => {
  vi.mocked(useDropzone).mockImplementation(
    () =>
      ({
        getRootProps: () => ({}),
        getInputProps: () => ({}),
        isDragActive: false,
        open: vi.fn(),
      }) as unknown as ReturnType<typeof useDropzone>,
  );
};

// Fake callback functions — let us check whether the component called them.
const makeSpies = () => ({
  addFiles: vi.fn(),
  removeFile: vi.fn(),
  clearQueue: vi.fn(),
  updateFileData: vi.fn(),
  onUrlUpload: vi.fn(),
});

// Fake file in the upload queue.
const makeItem = (overrides: Partial<UploadItem> = {}): UploadItem => ({
  id: 'item-1',
  type: 'file',
  file: new File(['content'], 'test.png', { type: 'image/png' }),
  progress: 0,
  status: 'pending',
  displayName: 'test.png',
  tags: '',
  isDirty: false,
  ...overrides,
});

// Opens the upload screen with a given queue and optional prop overrides,
// then returns the fake callbacks so tests can assert on them.
const renderUploader = (
  queueItems: UploadItem[] = [],
  propOverrides: Partial<React.ComponentProps<typeof FileUploader>> = {},
) => {
  const spies = makeSpies();
  render(
    <FileUploader
      queue={queueItems}
      addFiles={spies.addFiles}
      removeFile={spies.removeFile}
      clearQueue={spies.clearQueue}
      updateFileData={spies.updateFileData}
      isUploading={false}
      onUrlUpload={spies.onUrlUpload}
      disabled={false}
      {...propOverrides}
    />,
  );
  return spies;
};

// =============================================================================
// Tests
// =============================================================================

describe('FileUploader component', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    defaultDropzoneMock();
  });

  // Restore real timers even when a fake-timer test fails mid-way
  afterEach(() => {
    vi.useRealTimers();
  });

  // ---------------------------------------------------------------------------
  // Disabled state
  // ---------------------------------------------------------------------------

  // If the folder does not allow uploads, show the lock message and hide Select Files.
  test('shows lock message and hides Select Files when upload is disabled', () => {
    renderUploader([], { disabled: true });

    expect(screen.getByText('Upload disabled for this folder')).toBeInTheDocument();
    expect(screen.queryByText('Select Files')).not.toBeInTheDocument();
  });

  // Even if a user types a URL, the Upload button must stay disabled when the folder is locked.
  test('Upload button stays disabled when folder is locked even if a URL is typed', () => {
    renderUploader([], { disabled: true });

    const urlInput = screen.getByPlaceholderText('https://www.example.com/example');
    fireEvent.change(urlInput, { target: { value: 'https://example.com/file.mp4' } });

    expect(screen.getByRole('button', { name: 'Upload' })).toBeDisabled();
  });

  // ---------------------------------------------------------------------------
  // Queue actions
  // ---------------------------------------------------------------------------

  // Clicking Remove All clears every file from the queue.
  test('clicking Remove All clears the entire queue', () => {
    const spies = renderUploader([makeItem()]);

    fireEvent.click(screen.getByText('Remove All'));

    expect(spies.clearQueue).toHaveBeenCalledTimes(1);
  });

  // Clicking the Remove button on a file row removes only that file.
  test('clicking the Remove button calls removeFile with the correct item id', () => {
    const spies = renderUploader([makeItem({ id: 'item-abc' })]);

    fireEvent.click(screen.getByTitle('Remove File'));

    expect(spies.removeFile).toHaveBeenCalledWith('item-abc');
  });

  // ---------------------------------------------------------------------------
  // Queue summary text
  // ---------------------------------------------------------------------------

  // When all uploads finish, show "All N items completed".
  test('queue summary shows "All N items completed" when every upload finishes', () => {
    const items = [
      makeItem({ id: '1', status: 'completed', displayName: 'a.png' }),
      makeItem({ id: '2', status: 'completed', displayName: 'b.png' }),
    ];

    renderUploader(items);

    expect(screen.getByText('All 2 items completed')).toBeInTheDocument();
  });

  // When some files are still uploading, show how many are in progress.
  test('queue summary shows how many items are still uploading', () => {
    const items = [
      makeItem({ id: '1', status: 'uploading', displayName: 'a.png' }),
      makeItem({ id: '2', status: 'completed', displayName: 'b.png' }),
    ];

    renderUploader(items);

    expect(screen.getByText('1 of 2 items are still uploading')).toBeInTheDocument();
  });

  // ---------------------------------------------------------------------------
  // Name field debounce
  //
  // The name input waits 500ms after the last keystroke before saving — to
  // avoid a save on every character typed. vi.useFakeTimers() replaces the
  // real clock so the test can skip ahead 600ms instantly.
  // ---------------------------------------------------------------------------

  // Typing a new name does not save immediately — it waits for the 500ms delay.
  test('renaming a file saves after the debounce delay, not on every keystroke', async () => {
    vi.useFakeTimers();

    const spies = renderUploader([makeItem({ id: 'x', displayName: 'original.png' })]);

    fireEvent.change(screen.getByDisplayValue('original.png'), {
      target: { value: 'renamed.png' },
    });

    expect(spies.updateFileData).not.toHaveBeenCalled();

    await act(async () => {
      vi.advanceTimersByTime(600);
    });

    expect(spies.updateFileData).toHaveBeenCalledWith('x', { name: 'renamed.png' });
  });

  // While a file is uploading, the name field is read-only to prevent mid-transfer edits.
  test('name field is locked while an item is uploading', () => {
    renderUploader([makeItem({ id: '1', status: 'uploading', displayName: 'active.mp4' })]);

    expect(screen.getByDisplayValue('active.mp4')).toBeDisabled();
  });

  // ---------------------------------------------------------------------------
  // Queue item rendering
  // ---------------------------------------------------------------------------

  // Files added via URL show a globe icon instead of a file preview.
  test('URL items show a globe icon instead of a file preview', () => {
    const urlItem = makeItem({
      id: 'u1',
      type: 'url',
      file: undefined,
      url: 'https://example.com/video.mp4',
      displayName: 'video.mp4',
    });

    renderUploader([urlItem]);

    expect(document.querySelector('svg.lucide-globe')).toBeInTheDocument();
  });

  // When the server rejects a file, the error message appears under that file row.
  test('server error message appears under a failed upload item', () => {
    const errorItem = makeItem({
      id: 'err-1',
      status: 'error',
      progress: 0,
      displayName: 'duplicate.png',
      error: 'You already own media with this name. Please choose another.',
    });

    renderUploader([errorItem]);

    expect(
      screen.getByText('You already own media with this name. Please choose another.'),
    ).toBeInTheDocument();
  });

  // ---------------------------------------------------------------------------
  // Progress bar
  // ---------------------------------------------------------------------------

  // Files that have not started uploading yet show no progress bar.
  test('progress bar is hidden for items that have not started uploading', () => {
    renderUploader([makeItem({ id: '1', status: 'pending', progress: 0 })]);

    expect(screen.queryByText('0%')).not.toBeInTheDocument();
  });

  // The progress bar width matches the upload percentage.
  test('progress bar width reflects the upload percentage', () => {
    renderUploader([makeItem({ id: '1', status: 'uploading', progress: 75 })]);

    const bar = document.querySelector('.bg-xibo-blue-600[style]') as HTMLElement | null;
    expect(bar).toBeInTheDocument();
    expect(bar?.style.width).toBe('75%');
  });

  // ---------------------------------------------------------------------------
  // URL upload
  // ---------------------------------------------------------------------------

  // The Upload button is disabled when nothing is typed in the URL field.
  test('Upload button is disabled when the URL field is empty', () => {
    renderUploader();

    expect(screen.getByRole('button', { name: 'Upload' })).toBeDisabled();
  });

  // Clicking Upload sends the URL and then clears the input for the next entry.
  test('clicking Upload sends the URL to onUrlUpload and clears the input', () => {
    const spies = renderUploader();

    const urlInput = screen.getByPlaceholderText('https://www.example.com/example');
    fireEvent.change(urlInput, { target: { value: 'https://example.com/file.mp4' } });
    fireEvent.click(screen.getByRole('button', { name: 'Upload' }));

    expect(spies.onUrlUpload).toHaveBeenCalledWith('https://example.com/file.mp4');
    expect(urlInput).toHaveValue('');
  });

  // ---------------------------------------------------------------------------
  // Drop zone
  // ---------------------------------------------------------------------------

  // The drop zone shows the maximum file size so users know before they drag a file in.
  test('drop zone shows the maximum allowed file size', () => {
    renderUploader();

    expect(screen.getByText(/Maximum file size: 2 GB/)).toBeInTheDocument();
  });

  // ---------------------------------------------------------------------------
  // File size label
  // ---------------------------------------------------------------------------

  // URL items have no local file so the file size column shows "Remote"
  // instead of a byte count.
  test('file size shows "Remote" for URL items', () => {
    renderUploader([makeItem({ type: 'url', file: undefined, status: 'uploading', progress: 10 })]);

    expect(screen.getByText('Remote')).toBeInTheDocument();
  });

  // While an item is uploading the percentage is shown next to the progress bar.
  test('progress percentage text is shown while uploading', () => {
    renderUploader([makeItem({ status: 'uploading', progress: 75 })]);

    expect(screen.getByText('75%')).toBeInTheDocument();
  });

  // Once every byte has reached the CMS, it is still storing the file, so 100% would be misleading.
  test('shows "Saving to storage…" once the file has been sent but the CMS has not replied', () => {
    renderUploader([makeItem({ status: 'uploading', progress: 100 })]);

    expect(screen.getByText('Saving to storage…')).toBeInTheDocument();
    expect(screen.queryByText('100%')).not.toBeInTheDocument();
  });

  // A URL upload is downloaded by the CMS, so there is no browser progress to show.
  test('shows "Saving to storage…" while a URL upload is in progress', () => {
    renderUploader([makeItem({ type: 'url', file: undefined, status: 'uploading', progress: 0 })]);

    expect(screen.getByText('Saving to storage…')).toBeInTheDocument();
  });

  test('shows a message when the video thumbnail could not be saved', () => {
    renderUploader([makeItem({ status: 'completed', progress: 100, thumbnailFailed: true })]);

    expect(
      screen.getByText('The thumbnail could not be saved. Retake it to try again.'),
    ).toBeInTheDocument();
  });

  // ---------------------------------------------------------------------------
  test('queue summary does not show "All N items completed" when items are still uploading', () => {
    renderUploader([
      makeItem({ id: '1', status: 'uploading' }),
      makeItem({ id: '2', status: 'uploading' }),
    ]);

    expect(screen.queryByText('All 2 items completed')).not.toBeInTheDocument();
  });

  // When all items have errors the error-branch is taken, so the banner reads
  // "0 items completed, 2 items failed"
  test('queue summary shows completed/failed counts when all items have errors', () => {
    renderUploader([
      makeItem({ id: '1', status: 'error', error: 'Timeout' }),
      makeItem({ id: '2', status: 'error', error: 'Timeout' }),
    ]);

    expect(screen.queryByText('All 2 items completed')).not.toBeInTheDocument();
    expect(screen.getByText('0 items completed, 2 items failed')).toBeInTheDocument();
  });

  test('queue summary counts a failed thumbnail as a failed item', () => {
    renderUploader([
      makeItem({ id: '1', status: 'completed', progress: 100, thumbnailFailed: true }),
      makeItem({ id: '2', status: 'completed', progress: 100 }),
    ]);

    expect(screen.getByText('1 item completed, 1 item failed')).toBeInTheDocument();
  });
});
