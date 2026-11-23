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

import { QueryClientProvider } from '@tanstack/react-query';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent, { type UserEvent } from '@testing-library/user-event';
import { vi, beforeEach, afterEach, describe, test, expect } from 'vitest';

import ReplaceFileModal from '../../components/ReplaceFileModal';
import { mockEditMedia, mockUser } from '../mediaTestUtils';

import { UserProvider } from '@/context/UserContext';
import { replaceMedia } from '@/services/mediaApi';
import { fetchTags } from '@/services/tagApi';
import { testQueryClient } from '@/setupTests';
import { knownFailure } from '@/testUtils/knownFailure';
import { mockNotifyError, mockNotifySuccess } from '@/testUtils/notifyMock';
import { buildCurrentUser, PERSONAS } from '@/testUtils/personas';
import type { Media, MediaType } from '@/types/media';
import type { User } from '@/types/user';

// "Replace File" row action: upload a new file in place of an existing media item.
// TagInput is real; replaceMedia is the boundary.

// ─── Mocks ────────────────────────────────────────────────────────────────────

vi.mock('@/components/ui/modals/Modal');
vi.mock('@/services/mediaApi', () => ({ replaceMedia: vi.fn() }));
vi.mock('@/services/tagApi', () => ({ fetchTags: vi.fn() }));

// ─── Fixtures ─────────────────────────────────────────────────────────────────

// A thumbnail, so the preview <img> renders and the picked file is shown in it.
const MEDIA: Media = {
  ...mockEditMedia,
  mediaId: 42,
  name: 'Welcome',
  folderId: 5,
  thumbnail: '/library/thumbnail/42',
  tags: [{ tag: 'nature', value: 'forest', tagId: 1 }],
};

// The two library settings that decide whether the checkboxes start ticked. The
// CMS stores them as '1'/'0' or 'Checked'/'Unchecked' depending on its age.
const withSettings = (deleteOld: string, updateAll: string): User => ({
  ...mockUser,
  settings: {
    ...mockUser.settings,
    LIBRARY_MEDIA_DELETEOLDVER_CHECKB: deleteOld,
    LIBRARY_MEDIA_UPDATEINALL_CHECKB: updateAll,
  },
});

const replaced = { data: { files: [{ mediaId: 43, name: 'Welcome' }] } };

const png = (name = 'welcome-v2.png') => new File(['png'], name, { type: 'image/png' });

// ─── Helpers ──────────────────────────────────────────────────────────────────

const createObjectURL = vi.fn();
const revokeObjectURL = vi.fn();
let urlCount = 0;

const renderModal = ({ user = mockUser, data = MEDIA }: { user?: User; data?: Media } = {}) => {
  const onClose = vi.fn();
  const onSave = vi.fn();
  const utils = render(
    <QueryClientProvider client={testQueryClient}>
      <UserProvider initialUser={user}>
        <ReplaceFileModal data={data} onClose={onClose} onSave={onSave} />
      </UserProvider>
    </QueryClientProvider>,
  );
  return { onClose, onSave, ...utils };
};

// The real file input is hidden behind the "Select File" button; it is the only one.
const fileInput = () => document.querySelector<HTMLInputElement>('input[type="file"]')!;
const pickFile = (user: UserEvent, file = png()) => user.upload(fileInput(), file);
const saveButton = () => screen.getByRole('button', { name: 'Save' });

const lastReplaceCall = () => {
  const calls = vi.mocked(replaceMedia).mock.calls;
  return calls[calls.length - 1]![0];
};

// ─── Tests ────────────────────────────────────────────────────────────────────

describe('ReplaceFileModal', () => {
  const originalCreate = window.URL.createObjectURL;
  const originalRevoke = window.URL.revokeObjectURL;

  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    urlCount = 0;
    createObjectURL.mockImplementation(() => `blob:picked-${++urlCount}`);
    Object.assign(window.URL, { createObjectURL, revokeObjectURL });
    vi.mocked(fetchTags).mockResolvedValue({ rows: [], totalCount: 0 } as never);
    vi.mocked(replaceMedia).mockResolvedValue(replaced as never);
  });

  afterEach(() => {
    Object.assign(window.URL, { createObjectURL: originalCreate, revokeObjectURL: originalRevoke });
  });

  describe('picking a file', () => {
    test('Save stays locked until a replacement file has been picked', async () => {
      const user = userEvent.setup();
      renderModal();

      expect(saveButton()).toBeDisabled();
      await pickFile(user);

      expect(saveButton()).toBeEnabled();
    });

    test('the picked file name replaces the current one in the file details', async () => {
      const user = userEvent.setup();
      renderModal();
      expect(screen.getByText('test-image.png')).toBeInTheDocument();

      await pickFile(user, png('lobby-2026.png'));

      expect(screen.getByText('lobby-2026.png')).toBeInTheDocument();
      expect(screen.queryByText('test-image.png')).not.toBeInTheDocument();
    });

    // The accept attribute is what limits the operating system's file picker to the
    // same kind of file, so an image can only be replaced by an image.
    test.each([
      ['image', 'image/*'],
      ['video', 'video/*'],
      ['audio', 'audio/*'],
      ['pdf', 'application/pdf,.pdf'],
      ['powerpoint', '.ppt,.pps,.pptx,.ppsx'],
      ['htmlpackage', '.htz'],
    ] as Array<[MediaType, string]>)(
      'a %s can only be replaced by the same kind of file',
      (type, accept) => {
        renderModal({ data: { ...MEDIA, mediaType: type } });

        expect(fileInput()).toHaveAttribute('accept', accept);
      },
    );
  });

  describe('what is sent', () => {
    test('saving uploads the new file in place of this media, keeping its name, folder and tags', async () => {
      const user = userEvent.setup();
      renderModal({ user: withSettings('0', '0') });
      const file = png();

      await pickFile(user, file);
      await user.click(saveButton());

      await waitFor(() => expect(replaceMedia).toHaveBeenCalledOnce());
      expect(lastReplaceCall()).toEqual(
        expect.objectContaining({
          file,
          oldMediaId: 42,
          name: 'Welcome',
          folderId: 5,
          tags: 'nature|forest',
          updateInLayouts: false,
          deleteOldRevisions: false,
        }),
      );
    });

    test('a new name typed in the modal is sent', async () => {
      const user = userEvent.setup();
      renderModal();

      await pickFile(user);
      await user.clear(screen.getByRole('textbox', { name: 'Name' }));
      await user.type(screen.getByRole('textbox', { name: 'Name' }), 'Welcome v2');
      await user.click(saveButton());

      await waitFor(() => expect(lastReplaceCall().name).toBe('Welcome v2'));
    });

    test.each([
      ['1', '1'],
      ['Checked', 'Checked'],
    ])(
      'when the library settings say %s, both options start ticked and are sent as on',
      async (deleteOld, updateAll) => {
        const user = userEvent.setup();
        renderModal({ user: withSettings(deleteOld, updateAll) });

        expect(screen.getByRole('checkbox', { name: 'Delete Old Version' })).toBeChecked();
        expect(screen.getByRole('checkbox', { name: 'Update across all Layouts' })).toBeChecked();
        await pickFile(user);
        await user.click(saveButton());

        await waitFor(() =>
          expect(lastReplaceCall()).toEqual(
            expect.objectContaining({ deleteOldRevisions: true, updateInLayouts: true }),
          ),
        );
      },
    );

    test('unticking and ticking the options changes what is sent', async () => {
      const user = userEvent.setup();
      renderModal({ user: withSettings('1', '0') });

      await user.click(screen.getByRole('checkbox', { name: 'Delete Old Version' }));
      await user.click(screen.getByRole('checkbox', { name: 'Update across all Layouts' }));
      await pickFile(user);
      await user.click(saveButton());

      await waitFor(() =>
        expect(lastReplaceCall()).toEqual(
          expect.objectContaining({ deleteOldRevisions: false, updateInLayouts: true }),
        ),
      );
    });

    test('a user without tagging cannot change the tags, which are still sent unchanged', async () => {
      const user = userEvent.setup();
      renderModal({ user: buildCurrentUser(PERSONAS.user, { settings: mockUser.settings }) });

      expect(screen.getByRole('combobox', { name: 'Tags' })).toBeDisabled();
      await pickFile(user);
      await user.click(saveButton());

      await waitFor(() => expect(lastReplaceCall().tags).toBe('nature|forest'));
    });
  });

  describe('saving', () => {
    test('shows upload progress while the new file is sent', async () => {
      const user = userEvent.setup();
      let respond!: (v: unknown) => void;
      vi.mocked(replaceMedia).mockReturnValue(new Promise((res) => (respond = res)) as never);
      renderModal();

      await pickFile(user);
      await user.click(saveButton());
      await waitFor(() => expect(replaceMedia).toHaveBeenCalled());
      lastReplaceCall().onProgress?.(65);

      expect(await screen.findByText('65%')).toBeInTheDocument();
      expect(screen.getByRole('button', { name: 'Saving…' })).toBeDisabled();
      expect(screen.getByRole('button', { name: 'Cancel' })).toBeDisabled();

      respond(replaced);
      await waitFor(() => expect(mockNotifySuccess).toHaveBeenCalled());
    });

    test('a successful replace hands the new media to the page and closes', async () => {
      const user = userEvent.setup();
      const { onSave, onClose } = renderModal();

      await pickFile(user);
      await user.click(saveButton());

      await waitFor(() => expect(onClose).toHaveBeenCalledOnce());
      expect(onSave).toHaveBeenCalledWith(expect.objectContaining({ mediaId: 43 }));
      expect(mockNotifySuccess).toHaveBeenCalledWith('Media Replaced Successfully');
    });

    test('a file the server rejects shows the reason and keeps the modal open', async () => {
      vi.spyOn(console, 'error').mockImplementation(() => {});
      const user = userEvent.setup();
      vi.mocked(replaceMedia).mockResolvedValue({
        data: { files: [{ error: 'File type not allowed' }] },
      } as never);
      const { onClose, onSave } = renderModal();

      await pickFile(user);
      await user.click(saveButton());

      await waitFor(() => expect(mockNotifyError).toHaveBeenCalledWith('File type not allowed'));
      expect(onClose).not.toHaveBeenCalled();
      expect(onSave).not.toHaveBeenCalled();
      expect(saveButton()).toBeEnabled();
    });

    test('a failed request tells the user and keeps the modal open', async () => {
      vi.spyOn(console, 'error').mockImplementation(() => {});
      const user = userEvent.setup();
      vi.mocked(replaceMedia).mockRejectedValue(new Error('Network Error'));
      const { onClose } = renderModal();

      await pickFile(user);
      await user.click(saveButton());

      await waitFor(() => expect(mockNotifyError).toHaveBeenCalledWith('Network Error'));
      expect(onClose).not.toHaveBeenCalled();
    });

    test('Cancel closes without uploading anything', async () => {
      const user = userEvent.setup();
      const { onClose } = renderModal();

      await pickFile(user);
      await user.click(screen.getByRole('button', { name: 'Cancel' }));

      expect(onClose).toHaveBeenCalledOnce();
      expect(replaceMedia).not.toHaveBeenCalled();
    });
  });

  // Open — xibosignage/xibo#4065.
  knownFailure(
    'the preview of the picked file is released from memory when the modal closes',
    async () => {
      const user = userEvent.setup();
      const { unmount } = renderModal();
      await pickFile(user);
      const shown = screen.getByRole('img', { name: 'test-image.png' }).getAttribute('src')!;
      expect(shown).toMatch(/^blob:picked-/);
      unmount();
      return shown;
    },
    (shown) => expect(revokeObjectURL).toHaveBeenCalledWith(shown),
  );
});
