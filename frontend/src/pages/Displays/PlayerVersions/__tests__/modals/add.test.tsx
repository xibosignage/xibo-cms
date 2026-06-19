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

import { act, render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type React from 'react';
import { vi, beforeEach, describe, test, expect } from 'vitest';

import AddPlayerVersionModal from '../../components/AddPlayerVersionModal';

import { uploadPlayerVersion } from '@/services/playerVersionApi';

// =============================================================================
// Module mocks
// =============================================================================

vi.mock('@/components/ui/modals/Modal');

vi.mock('@/services/playerVersionApi', () => ({
  uploadPlayerVersion: vi.fn(),
}));

let capturedOnDrop: ((files: File[]) => void) | undefined;
vi.mock('react-dropzone', () => ({
  useDropzone: ({ onDrop }: { onDrop: (files: File[]) => void }) => {
    capturedOnDrop = onDrop;
    return {
      getRootProps: () => ({ 'data-testid': 'dropzone' }),
      getInputProps: () => ({}),
      isDragActive: false,
      open: vi.fn(),
    };
  },
}));

// =============================================================================
// Helpers
// =============================================================================

const makeFile = (name = 'player.apk') =>
  new File(['x'], name, { type: 'application/vnd.android.package-archive' });

const renderModal = (props: Partial<React.ComponentProps<typeof AddPlayerVersionModal>> = {}) => {
  const onClose = props.onClose ?? vi.fn();
  const onSave = props.onSave ?? vi.fn();
  return {
    onClose,
    onSave,
    ...render(<AddPlayerVersionModal onClose={onClose} onSave={onSave} {...props} />),
  };
};

// =============================================================================
// Tests — AddPlayerVersionModal (upload)
// =============================================================================

describe('AddPlayerVersionModal', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    capturedOnDrop = undefined;
    vi.mocked(uploadPlayerVersion).mockResolvedValue({ id: 1, md5: 'x', name: 'player.apk' });
  });

  test('modal opens with the title "Upload Player Version"', () => {
    renderModal();

    expect(screen.getByRole('dialog', { name: /upload player version/i })).toBeInTheDocument();
  });

  test('the dropzone, Select Files button and supported-formats line are shown', () => {
    renderModal();

    expect(screen.getByTestId('dropzone')).toBeInTheDocument();
    expect(screen.getByText('Select Files')).toBeInTheDocument();
    expect(screen.getByText('Supported formats: .apk, .ipk, .wgt, .chrome')).toBeInTheDocument();
  });

  test('no files are listed when the modal first opens', () => {
    renderModal();

    // The file-list controls only appear once a file has been added.
    expect(screen.queryByRole('button', { name: /remove all/i })).not.toBeInTheDocument();
  });

  test('selecting a file lists it by name', async () => {
    renderModal();

    act(() => capturedOnDrop?.([makeFile('xibo-player.apk')]));

    expect(await screen.findByText('xibo-player.apk')).toBeInTheDocument();
  });

  test('a selected file shows its formatted size', async () => {
    renderModal();

    // 1024 bytes formats as "1 KB" via formatFileSize.
    const file = new File(['a'.repeat(1024)], 'sized.apk', {
      type: 'application/vnd.android.package-archive',
    });
    act(() => capturedOnDrop?.([file]));

    expect(await screen.findByText('1 KB')).toBeInTheDocument();
  });

  test('dropping a file uploads it', async () => {
    renderModal();

    const file = makeFile('xibo-player.apk');
    act(() => capturedOnDrop?.([file]));

    await waitFor(() => {
      expect(uploadPlayerVersion).toHaveBeenCalledWith(file, expect.any(Function));
    });
  });

  test('a completion message is shown after a successful upload', async () => {
    renderModal();

    act(() => capturedOnDrop?.([makeFile()]));

    expect(await screen.findByText('1 item completed')).toBeInTheDocument();
  });

  test('the upload progress percentage is shown while a file is uploading', async () => {
    // Report 42% then leave the upload in flight so the progress bar stays put.
    vi.mocked(uploadPlayerVersion).mockImplementationOnce((_file, onProgress) => {
      onProgress?.(42);
      return new Promise(() => {});
    });
    renderModal();

    act(() => capturedOnDrop?.([makeFile()]));

    expect(await screen.findByText('42%')).toBeInTheDocument();
  });

  test('all files completing shows the "all completed" message', async () => {
    renderModal();

    act(() => capturedOnDrop?.([makeFile('a.apk'), makeFile('b.apk')]));

    expect(await screen.findByText('All 2 items completed')).toBeInTheDocument();
  });

  test('a mix of success and failure reports both counts', async () => {
    vi.mocked(uploadPlayerVersion)
      .mockResolvedValueOnce({ id: 1, md5: 'x', name: 'a.apk' })
      .mockRejectedValueOnce(new Error('Invalid package'));
    renderModal();

    act(() => capturedOnDrop?.([makeFile('a.apk'), makeFile('b.apk')]));

    expect(await screen.findByText('1 item completed, 1 item failed')).toBeInTheDocument();
  });

  test('a failed upload shows the per-file error', async () => {
    vi.mocked(uploadPlayerVersion).mockRejectedValueOnce(new Error('Invalid package'));
    renderModal();

    act(() => capturedOnDrop?.([makeFile('broken.apk')]));

    expect(await screen.findByText('Invalid package')).toBeInTheDocument();
  });

  test('an individual file can be removed via its Remove File control', async () => {
    const user = userEvent.setup();
    // A failed upload leaves the file in an "error" state, where the per-file
    // remove control is shown.
    vi.mocked(uploadPlayerVersion).mockRejectedValueOnce(new Error('Invalid package'));
    renderModal();

    act(() => capturedOnDrop?.([makeFile('broken.apk')]));
    await screen.findByText('broken.apk');

    await user.click(await screen.findByTitle('Remove File'));

    expect(screen.queryByText('broken.apk')).not.toBeInTheDocument();
  });

  test('Remove All clears the file list', async () => {
    const user = userEvent.setup();
    renderModal();

    act(() => capturedOnDrop?.([makeFile('xibo-player.apk')]));
    await screen.findByText('xibo-player.apk');

    await user.click(await screen.findByRole('button', { name: /remove all/i }));

    expect(screen.queryByText('xibo-player.apk')).not.toBeInTheDocument();
  });

  test('buttons show "Uploading..." and are disabled while uploading', async () => {
    let resolveUpload: (value: { id: number; md5: string; name: string }) => void = () => {};
    vi.mocked(uploadPlayerVersion).mockReturnValueOnce(
      new Promise((resolve) => {
        resolveUpload = resolve;
      }),
    );
    renderModal();

    act(() => capturedOnDrop?.([makeFile()]));

    const uploadingButton = await screen.findByRole('button', { name: /uploading/i });
    expect(uploadingButton).toBeDisabled();
    expect(screen.getByRole('button', { name: /^cancel$/i })).toBeDisabled();

    resolveUpload({ id: 1, md5: 'x', name: 'player.apk' });
  });

  test('the modal cannot be closed while an upload is in flight', async () => {
    const user = userEvent.setup();
    let resolveUpload: (value: { id: number; md5: string; name: string }) => void = () => {};
    vi.mocked(uploadPlayerVersion).mockReturnValueOnce(
      new Promise((resolve) => {
        resolveUpload = resolve;
      }),
    );
    const { onClose } = renderModal();

    act(() => capturedOnDrop?.([makeFile()]));
    await screen.findByRole('button', { name: /uploading/i });

    // Both actions are disabled while uploading, so clicking them does nothing.
    await user.click(screen.getByRole('button', { name: /^cancel$/i }));
    expect(onClose).not.toHaveBeenCalled();

    resolveUpload({ id: 1, md5: 'x', name: 'player.apk' });
  });

  test('clicking Done closes the modal when idle', async () => {
    const user = userEvent.setup();
    const { onClose } = renderModal();

    await user.click(screen.getByRole('button', { name: /^done$/i }));

    expect(onClose).toHaveBeenCalled();
  });

  test('clicking Cancel closes the modal when idle', async () => {
    const user = userEvent.setup();
    const { onClose } = renderModal();

    await user.click(screen.getByRole('button', { name: /^cancel$/i }));

    expect(onClose).toHaveBeenCalled();
  });
});
