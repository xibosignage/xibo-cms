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
import userEvent, { type UserEvent } from '@testing-library/user-event';
import { vi, beforeEach, describe, test, expect } from 'vitest';

import { mockFont, mockUser } from '../fixtures/font';
import { mockUploadFont } from '../mocks/fontApi';

import { UserProvider } from '@/context/UserContext';
import UploadFontModal from '@/pages/Administration/Fonts/components/UploadFontModal';
import { uploadFont } from '@/services/fontApi';
import { trackSequentialCalls } from '@/testUtils/sequentialMock';
import type { Font } from '@/types/font';
import type { User } from '@/types/user';

// =============================================================================
// Module mocks
// =============================================================================

vi.mock('@/services/fontApi');
vi.mock('@/components/ui/modals/Modal');

// The real react-dropzone runs so its accept / maxSize checks are exercised.
// Files are added through its hidden <input type="file">.

// =============================================================================
// Helpers
// =============================================================================

const onClose = vi.fn();
const onSuccess = vi.fn();

const fontFile = (name: string, size = 4) =>
  new File(['x'.repeat(size)], name, { type: name.endsWith('.otf') ? 'font/otf' : 'font/ttf' });

const renderModal = (user: User = mockUser) =>
  render(
    <UserProvider initialUser={user}>
      <UploadFontModal onClose={onClose} onSuccess={onSuccess} />
    </UserProvider>,
  );

// applyAccept: false lets every file reach react-dropzone, which does its own
// accept check — otherwise userEvent would filter unsupported files itself.
const setupUser = () => userEvent.setup({ applyAccept: false });

const addFiles = async (user: UserEvent, container: HTMLElement, files: File | File[]) => {
  const input = container.querySelector<HTMLInputElement>('input[type="file"]')!;
  await user.upload(input, files);
};

// =============================================================================
// Tests
// =============================================================================

describe('UploadFontModal', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    mockUploadFont();
  });

  // ---------------------------------------------------------------------------
  // Default state
  // ---------------------------------------------------------------------------

  test('shows the title, drop prompt, Select Files button and supported formats', () => {
    renderModal();

    expect(screen.getByRole('heading', { name: 'Upload Font' })).toBeInTheDocument();
    expect(screen.getByText('Drag & drop file here or')).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Select Files' })).toBeInTheDocument();
    expect(
      screen.getByText('Supported formats: .otf, .ttf, .eot, .svg, .woff'),
    ).toBeInTheDocument();
    expect(screen.queryByRole('button', { name: /remove all/i })).not.toBeInTheDocument();
  });

  // ---------------------------------------------------------------------------
  // Uploading
  // ---------------------------------------------------------------------------

  test('dropping a font uploads it and shows it as completed', async () => {
    const user = setupUser();
    const { container } = renderModal();
    const file = fontFile('Roboto.ttf');

    await addFiles(user, container, file);

    await screen.findByText('1 item completed');
    expect(uploadFont).toHaveBeenCalledWith(file, expect.any(Function));
    expect(screen.getByText('Roboto.ttf')).toBeInTheDocument();
    expect(screen.getByText('100%')).toBeInTheDocument();
  });

  test('onSuccess is called once after a successful upload', async () => {
    const user = setupUser();
    const { container } = renderModal();

    await addFiles(user, container, fontFile('Roboto.ttf'));
    await screen.findByText('1 item completed');

    expect(onSuccess).toHaveBeenCalledTimes(1);
  });

  test('several files upload one after another', async () => {
    const user = setupUser();
    // Call order alone doesn't prove the uploads are sequential — the tracker
    // records how many uploadFont calls were in flight at the same time.
    const tracker = trackSequentialCalls(vi.mocked(uploadFont), mockFont);
    const { container } = renderModal();
    const a = fontFile('A.ttf');
    const b = fontFile('B.otf');

    await addFiles(user, container, [a, b]);

    await screen.findByText('All 2 items completed');
    expect(tracker.maxInFlight).toBe(1);
    expect(uploadFont).toHaveBeenCalledTimes(2);
    expect(vi.mocked(uploadFont).mock.calls[0]![0]).toBe(a);
    expect(vi.mocked(uploadFont).mock.calls[1]![0]).toBe(b);
    expect(onSuccess).toHaveBeenCalledTimes(1);
  });

  test('Cancel and Done are disabled while uploading', async () => {
    const user = setupUser();
    let resolveUpload!: (font: Font) => void;
    vi.mocked(uploadFont).mockReturnValue(
      new Promise<Font>((resolve) => {
        resolveUpload = resolve;
      }),
    );
    const { container } = renderModal();

    await addFiles(user, container, fontFile('Roboto.ttf'));

    expect(await screen.findByRole('button', { name: 'Uploading...' })).toBeDisabled();
    expect(screen.getByRole('button', { name: 'Cancel' })).toBeDisabled();
    expect(screen.queryByRole('button', { name: /remove all/i })).not.toBeInTheDocument();

    resolveUpload(mockFont);

    await waitFor(() => {
      expect(screen.getByRole('button', { name: 'Done' })).toBeEnabled();
    });
  });

  // ---------------------------------------------------------------------------
  // Error handling
  // ---------------------------------------------------------------------------

  test('a failed upload shows the error message on the file row', async () => {
    const user = setupUser();
    vi.mocked(uploadFont).mockRejectedValue(new Error('Invalid font file'));
    const { container } = renderModal();
    const file = fontFile('Bad.ttf');

    await addFiles(user, container, file);

    await screen.findByText('Invalid font file');
    expect(uploadFont).toHaveBeenCalledWith(file, expect.any(Function));
    expect(screen.getByText('Bad.ttf')).toBeInTheDocument();
  });

  // Known bug (xibosignage/xibo#4047): when every upload fails, the
  // summary currently reads "0 items completed, 1 item failed".
  // Kept as test.fails (not test.skip) so a real fix surfaces here immediately.
  test.fails(
    'the summary reads "1 item failed" when every upload fails',
    { retry: 0 },
    async () => {
      const user = setupUser();
      vi.mocked(uploadFont).mockRejectedValue(new Error('Invalid font file'));
      const { container } = renderModal();

      await addFiles(user, container, fontFile('Bad.ttf'));
      await screen.findByText('Invalid font file');

      expect(await screen.findByText('1 item failed')).toBeInTheDocument();
      expect(screen.queryByText(/0 items completed/i)).not.toBeInTheDocument();
    },
  );

  test('mixed results show how many completed and how many failed', async () => {
    const user = setupUser();
    vi.mocked(uploadFont)
      .mockResolvedValueOnce(mockFont)
      .mockRejectedValueOnce(new Error('Invalid font file'));
    const { container } = renderModal();

    await addFiles(user, container, [fontFile('A.ttf'), fontFile('Bad.ttf')]);

    await screen.findByText('1 item completed, 1 item failed');
    expect(onSuccess).toHaveBeenCalledTimes(1);
  });

  test('onSuccess is not called when every upload fails', async () => {
    const user = setupUser();
    vi.mocked(uploadFont).mockRejectedValue(new Error('Invalid font file'));
    const { container } = renderModal();

    await addFiles(user, container, fontFile('Bad.ttf'));
    await screen.findByText('Invalid font file');
    await waitFor(() => {
      expect(screen.getByRole('button', { name: 'Done' })).toBeEnabled();
    });

    expect(uploadFont).toHaveBeenCalledTimes(1);
    expect(onSuccess).not.toHaveBeenCalled();
  });

  // ---------------------------------------------------------------------------
  // Rejected files (wrong type, over the size limit) must not be uploaded, and
  // the user must be told why. The second half is a known bug
  // (xibosignage/xibo#4046): there is no onDropRejected, so the
  // file is silently dropped. The exact wording isn't decided, so the reason is
  // matched loosely. Kept as test.fails (not test.skip) so a real fix surfaces
  // here immediately.
  // ---------------------------------------------------------------------------
  const notesFile = () => new File(['x'], 'notes.txt', { type: 'text/plain' });
  // Product Question: The per-file limit comes from LIBRARY_SIZE_LIMIT_KB, which may be meant for
  // the whole library. The size-limit tests assert the current source; update them once decided.
  const limitedUser = { ...mockUser, settings: { ...mockUser.settings, LIBRARY_SIZE_LIMIT_KB: 1 } };

  test('a file with an unsupported type is not uploaded', async () => {
    const user = setupUser();
    const { container } = renderModal();

    await addFiles(user, container, notesFile());

    expect(uploadFont).not.toHaveBeenCalled();
    expect(onSuccess).not.toHaveBeenCalled();
  });

  test.fails(
    'a file with an unsupported type is listed with the reason',
    { retry: 0 },
    async () => {
      const user = setupUser();
      const { container } = renderModal();

      await addFiles(user, container, notesFile());

      await screen.findByText('notes.txt');
      expect(screen.getByText(/not supported|file type|invalid type/i)).toBeInTheDocument();
    },
  );

  test('a file over the library size limit is not uploaded', async () => {
    const user = setupUser();
    const { container } = renderModal(limitedUser);

    await addFiles(user, container, fontFile('Big.ttf', 2048));

    expect(uploadFont).not.toHaveBeenCalled();
    expect(onSuccess).not.toHaveBeenCalled();
  });

  test.fails(
    'a file over the library size limit is listed with the reason',
    { retry: 0 },
    async () => {
      const user = setupUser();
      const { container } = renderModal(limitedUser);

      await addFiles(user, container, fontFile('Big.ttf', 2048));

      await screen.findByText('Big.ttf');
      expect(screen.getByText(/too large|larger than|size limit/i)).toBeInTheDocument();
    },
  );

  // Product Question: A failed file is uploaded again, without asking, when another file is
  // dropped. Intended, or should it need a Retry button? This test asserts the current behaviour;
  // update it once decided.
  test('a failed file is retried when another file is dropped', async () => {
    const user = setupUser();
    vi.mocked(uploadFont).mockRejectedValueOnce(new Error('Invalid font file'));
    const { container } = renderModal();
    const bad = fontFile('Bad.ttf');
    const good = fontFile('Good.ttf');

    await addFiles(user, container, bad);
    await screen.findByText('Invalid font file');
    await waitFor(() => {
      expect(screen.getByRole('button', { name: 'Done' })).toBeEnabled();
    });

    await addFiles(user, container, good);

    await screen.findByText('All 2 items completed');
    expect(vi.mocked(uploadFont).mock.calls.map(([file]) => file)).toEqual([bad, bad, good]);
  });

  // ---------------------------------------------------------------------------
  // Managing the list
  // ---------------------------------------------------------------------------

  test('Remove File removes a failed file from the list', async () => {
    const user = setupUser();
    vi.mocked(uploadFont).mockRejectedValue(new Error('Invalid font file'));
    const { container } = renderModal();

    await addFiles(user, container, fontFile('Bad.ttf'));
    await screen.findByText('Invalid font file');

    // Remove File only appears once the upload loop has finished.
    await user.click(await screen.findByRole('button', { name: 'Remove File' }));

    expect(screen.queryByText('Bad.ttf')).not.toBeInTheDocument();
  });

  test('Remove All clears the file list', async () => {
    const user = setupUser();
    const { container } = renderModal();

    await addFiles(user, container, [fontFile('A.ttf'), fontFile('B.otf')]);
    await screen.findByText('All 2 items completed');

    await user.click(screen.getByRole('button', { name: 'Remove All' }));

    expect(screen.queryByText('A.ttf')).not.toBeInTheDocument();
    expect(screen.queryByText('B.otf')).not.toBeInTheDocument();
    expect(screen.queryByText('All 2 items completed')).not.toBeInTheDocument();
  });

  // ---------------------------------------------------------------------------
  // Closing — Cancel and Done both call handleClose.
  // Product Question: Keep only one of the two buttons? These tests assert the current behaviour;
  // update them once decided.
  // ---------------------------------------------------------------------------

  test('Cancel calls onClose', async () => {
    const user = setupUser();
    renderModal();

    await user.click(screen.getByRole('button', { name: 'Cancel' }));

    expect(onClose).toHaveBeenCalledTimes(1);
  });

  test('Done calls onClose', async () => {
    const user = setupUser();
    renderModal();

    await user.click(screen.getByRole('button', { name: 'Done' }));

    expect(onClose).toHaveBeenCalledTimes(1);
  });
});
