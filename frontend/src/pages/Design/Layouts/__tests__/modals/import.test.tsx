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

import { QueryClientProvider } from '@tanstack/react-query';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent, { type UserEvent } from '@testing-library/user-event';
import { AxiosError, AxiosHeaders } from 'axios';
import { vi, beforeEach, describe, test, expect } from 'vitest';

import ImportLayoutModal from '../../components/ImportLayoutModal';
import { mockUser } from '../layoutTestUtils';

import { UserProvider } from '@/context/UserContext';
import { importLayout, updateLayout } from '@/services/layoutsApi';
import type { ImportLayoutResponse } from '@/services/layoutsApi';
import { testQueryClient } from '@/setupTests';
import { deferred } from '@/testUtils/deferred';
import { knownFailure } from '@/testUtils/knownFailure';
import { buildCurrentUser, PERSONAS } from '@/testUtils/personas';
import { trackSequentialCalls } from '@/testUtils/sequentialMock';
import type { Tag } from '@/types/tag';
import type { User } from '@/types/user';

// ─── Mocks ────────────────────────────────────────────────────────────────────

vi.mock('@/components/ui/modals/Modal');
vi.mock('@/services/layoutsApi', () => ({
  importLayout: vi.fn(),
  updateLayout: vi.fn(),
}));

// SelectFolder and TagInput have their own component tests. Here they
// are single-purpose stubs so the tests can say "the user picked folder 42" or
// "the user added the tag promo" and check what reaches the import request.
vi.mock('@/components/ui/forms/SelectFolder', () => ({
  default: ({ onSelect }: { onSelect: (folder: { id: number }) => void }) => (
    <button type="button" onClick={() => onSelect({ id: 42 })}>
      Pick folder 42
    </button>
  ),
}));
vi.mock('@/components/ui/forms/TagInput', () => ({
  default: ({
    value,
    onChange,
    disabled,
  }: {
    value: Tag[];
    onChange: (tags: Tag[]) => void;
    disabled?: boolean;
  }) => (
    <button
      type="button"
      disabled={disabled}
      onClick={() => onChange([...value, { tag: 'promo', value: '', tagId: 0 } as Tag])}
    >
      Add tag promo
    </button>
  ),
}));

// ─── Fixtures ─────────────────────────────────────────────────────────────────

const zip = (name: string) => new File(['PK'], name, { type: 'application/zip' });

const imported = (id: number): ImportLayoutResponse =>
  ({ files: [{ id, name: `layout ${id}` }] }) as unknown as ImportLayoutResponse;

// homeFolderId 7, not 1, so a test can tell "the user's home folder" apart from
// the component's hard-coded fallback.
const adminUser: User = { ...mockUser, homeFolderId: 7 };

// A plain user: can add layouts, but has neither folder.view nor tag.tagging.
const plainUser: User = buildCurrentUser(PERSONAS.user, {
  homeFolderId: 7,
  settings: mockUser.settings,
});

// ─── Helpers ──────────────────────────────────────────────────────────────────

const renderModal = (user: User = adminUser) => {
  const onClose = vi.fn();
  const onSuccess = vi.fn();
  render(
    <QueryClientProvider client={testQueryClient}>
      <UserProvider initialUser={user}>
        <ImportLayoutModal onClose={onClose} onSuccess={onSuccess} />
      </UserProvider>
    </QueryClientProvider>,
  );
  return { onClose, onSuccess };
};

// react-dropzone's hidden <input type="file"> has no label or role; it is the only
// file input in the modal. Uploading to it is what a drop or "Select Files" does.
const dropFiles = async (user: UserEvent, ...files: File[]) => {
  const input = document.querySelector<HTMLInputElement>('input[type="file"]')!;
  await user.upload(input, files);
};

const lastImportCall = () => {
  const calls = vi.mocked(importLayout).mock.calls;
  return calls[calls.length - 1]![0];
};

// ─── Tests ────────────────────────────────────────────────────────────────────

describe('ImportLayoutModal', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    vi.mocked(importLayout).mockResolvedValue(imported(900));
    vi.mocked(updateLayout).mockResolvedValue({} as never);
  });

  describe('what is sent', () => {
    test('dropping a ZIP imports it straight away, named after the file, into the home folder', async () => {
      const user = userEvent.setup();
      renderModal();

      await dropFiles(user, zip('Lobby.zip'));

      await waitFor(() => expect(importLayout).toHaveBeenCalledOnce());
      const sent = lastImportCall();
      expect(sent.file.name).toBe('Lobby.zip');
      expect(sent).toEqual(
        expect.objectContaining({
          name: 'Lobby',
          tags: '',
          folderId: 7,
          replaceExisting: false,
          importTags: false,
          useExistingDataSets: true,
          importDataSetData: false,
          importFallback: false,
        }),
      );
    });

    test('each ticked option is sent with the import', async () => {
      const user = userEvent.setup();
      renderModal();

      await user.click(screen.getByRole('checkbox', { name: 'Replace Existing' }));
      await user.click(screen.getByRole('checkbox', { name: 'Import Tags' }));
      await user.click(screen.getByRole('checkbox', { name: 'Import Fallback Data' }));
      await dropFiles(user, zip('Lobby.zip'));

      await waitFor(() => expect(importLayout).toHaveBeenCalledOnce());
      expect(lastImportCall()).toEqual(
        expect.objectContaining({
          replaceExisting: true,
          importTags: true,
          importFallback: true,
        }),
      );
    });

    test('unticking Use Existing DataSets lets the DataSet data be imported too', async () => {
      const user = userEvent.setup();
      renderModal();

      await user.click(screen.getByRole('checkbox', { name: 'Use Existing DataSets' }));
      await user.click(screen.getByRole('checkbox', { name: 'Import DataSet Data' }));
      await dropFiles(user, zip('Lobby.zip'));

      await waitFor(() => expect(importLayout).toHaveBeenCalledOnce());
      expect(lastImportCall()).toEqual(
        expect.objectContaining({ useExistingDataSets: false, importDataSetData: true }),
      );
    });

    // Existing DataSets are reused as they are, so importing the file's data into
    // them makes no sense: re-ticking Use Existing DataSets turns Import DataSet Data off.
    test('re-ticking Use Existing DataSets switches Import DataSet Data back off', async () => {
      const user = userEvent.setup();
      renderModal();

      await user.click(screen.getByRole('checkbox', { name: 'Use Existing DataSets' }));
      await user.click(screen.getByRole('checkbox', { name: 'Import DataSet Data' }));
      await user.click(screen.getByRole('checkbox', { name: 'Use Existing DataSets' }));

      expect(screen.getByRole('checkbox', { name: 'Import DataSet Data' })).not.toBeChecked();

      await dropFiles(user, zip('Lobby.zip'));
      await waitFor(() => expect(importLayout).toHaveBeenCalledOnce());
      expect(lastImportCall()).toEqual(
        expect.objectContaining({ useExistingDataSets: true, importDataSetData: false }),
      );
    });

    test('the folder the user picks is the folder the layout is imported into', async () => {
      const user = userEvent.setup();
      renderModal();

      await user.click(screen.getByRole('button', { name: 'Pick folder 42' }));
      await dropFiles(user, zip('Lobby.zip'));

      await waitFor(() => expect(importLayout).toHaveBeenCalledOnce());
      expect(lastImportCall().folderId).toBe(42);
    });

    test('several files are imported one after another, each under its own name', async () => {
      const user = userEvent.setup();
      const tracker = trackSequentialCalls(vi.mocked(importLayout), imported(900), {
        key: ((opts: { name: string }) => opts.name) as never,
      });
      renderModal();

      await dropFiles(user, zip('One.zip'), zip('Two.zip'), zip('Three.zip'));

      await waitFor(() => expect(importLayout).toHaveBeenCalledTimes(3));
      expect(tracker.callOrder).toEqual(['One', 'Two', 'Three']);
      expect(tracker.maxInFlight).toBe(1);
    });
  });

  describe('who sees what', () => {
    test('an admin can choose the folder and add tags', async () => {
      const user = userEvent.setup();
      renderModal(adminUser);

      expect(screen.getByRole('button', { name: 'Pick folder 42' })).toBeInTheDocument();
      await dropFiles(user, zip('Lobby.zip'));
      expect(await screen.findByRole('button', { name: 'Add tag promo' })).toBeInTheDocument();
    });

    test('a user who cannot view folders gets no folder picker, and imports into their home folder', async () => {
      const user = userEvent.setup();
      renderModal(plainUser);

      expect(screen.queryByRole('button', { name: 'Pick folder 42' })).not.toBeInTheDocument();

      await dropFiles(user, zip('Lobby.zip'));
      await waitFor(() => expect(importLayout).toHaveBeenCalledOnce());
      expect(lastImportCall().folderId).toBe(7);
    });

    test('a user without the tagging feature gets no tag field on the imported file', async () => {
      const user = userEvent.setup();
      renderModal(plainUser);

      await dropFiles(user, zip('Lobby.zip'));

      await screen.findByDisplayValue('Lobby');
      expect(screen.queryByRole('button', { name: 'Add tag promo' })).not.toBeInTheDocument();
    });
  });

  describe('progress and completion', () => {
    test('the upload progress is shown as it arrives, then 100% when the import finishes', async () => {
      const user = userEvent.setup();
      const upload = deferred<ImportLayoutResponse>();
      vi.mocked(importLayout).mockReturnValue(upload.promise);
      renderModal();

      await dropFiles(user, zip('Lobby.zip'));
      await waitFor(() => expect(importLayout).toHaveBeenCalledOnce());

      lastImportCall().onProgress?.(40);
      expect(await screen.findByText('40%')).toBeInTheDocument();

      upload.resolve(imported(900));
      expect(await screen.findByText('100%')).toBeInTheDocument();
    });

    test('while a file is importing, Cancel, Done and the file name are locked', async () => {
      const user = userEvent.setup();
      const upload = deferred<ImportLayoutResponse>();
      vi.mocked(importLayout).mockReturnValue(upload.promise);
      renderModal();

      await dropFiles(user, zip('Lobby.zip'));

      await waitFor(() => expect(screen.getByRole('button', { name: 'Done' })).toBeDisabled());
      expect(screen.getByRole('button', { name: 'Cancel' })).toBeDisabled();
      expect(screen.getByRole('button', { name: 'Select Files' })).toBeDisabled();
      expect(screen.getByDisplayValue('Lobby')).toBeDisabled();

      upload.resolve(imported(900));
      await waitFor(() => expect(screen.getByRole('button', { name: 'Done' })).toBeEnabled());
      expect(screen.getByRole('button', { name: 'Cancel' })).toBeEnabled();
    });

    test('Done tells the page the import finished so it can refresh', async () => {
      const user = userEvent.setup();
      const { onSuccess, onClose } = renderModal();

      await dropFiles(user, zip('Lobby.zip'));
      await screen.findByText('100%');
      await user.click(screen.getByRole('button', { name: 'Done' }));

      expect(onSuccess).toHaveBeenCalledOnce();
      expect(onClose).not.toHaveBeenCalled();
    });

    test('Cancel closes the modal', async () => {
      const user = userEvent.setup();
      const { onClose, onSuccess } = renderModal();

      await user.click(screen.getByRole('button', { name: 'Cancel' }));

      expect(onClose).toHaveBeenCalledOnce();
      expect(onSuccess).not.toHaveBeenCalled();
    });
  });

  describe('errors', () => {
    test('a file the server rejects shows the server reason, and the next file still imports', async () => {
      const user = userEvent.setup();
      vi.mocked(importLayout)
        .mockResolvedValueOnce({
          files: [{ error: 'Layout already exists' }],
        } as unknown as ImportLayoutResponse)
        .mockResolvedValueOnce(imported(901));
      renderModal();

      await dropFiles(user, zip('One.zip'), zip('Two.zip'));

      expect(await screen.findByText('Layout already exists')).toBeInTheDocument();
      await waitFor(() => expect(importLayout).toHaveBeenCalledTimes(2));
      expect(await screen.findByText('100%')).toBeInTheDocument();
    });

    test('a request that fails outright shows the message from the API response', async () => {
      const user = userEvent.setup();
      vi.mocked(importLayout).mockRejectedValue(
        new AxiosError('Request failed', '413', undefined, undefined, {
          status: 413,
          statusText: 'Payload Too Large',
          headers: {},
          config: { headers: new AxiosHeaders() },
          data: { message: 'File is too large' },
        }),
      );
      renderModal();

      await dropFiles(user, zip('Huge.zip'));

      expect(await screen.findByText('File is too large')).toBeInTheDocument();
      // A failed file has no progress bar.
      expect(screen.queryByText('0%')).not.toBeInTheDocument();
    });
  });

  describe('editing after import', () => {
    // The import has already created the layout, so a rename now has to be sent to
    // the server as an update. The name box waits 500 ms after typing stops.
    test('renaming an imported file saves the new name to the layout that was created', async () => {
      const user = userEvent.setup();
      renderModal();

      await dropFiles(user, zip('Lobby.zip'));
      await screen.findByText('100%');

      const nameBox = screen.getByDisplayValue('Lobby');
      await user.clear(nameBox);
      await user.type(nameBox, 'Reception');

      // A slow runner can save the cleared name first, so wait on the final content
      await waitFor(() =>
        expect(updateLayout).toHaveBeenLastCalledWith(900, { name: 'Reception', tags: '' }),
      );
    });

    test('adding a tag to an imported file saves it to the layout that was created', async () => {
      const user = userEvent.setup();
      renderModal();

      await dropFiles(user, zip('Lobby.zip'));
      await screen.findByText('100%');
      await user.click(screen.getByRole('button', { name: 'Add tag promo' }));

      await waitFor(() => expect(updateLayout).toHaveBeenCalledOnce());
      expect(updateLayout).toHaveBeenCalledWith(900, { name: 'Lobby', tags: 'promo' });
    });

    test('a rename the server refuses shows the reason on that file', async () => {
      const user = userEvent.setup();
      vi.mocked(updateLayout).mockRejectedValue(new Error('Name already in use'));
      renderModal();

      await dropFiles(user, zip('Lobby.zip'));
      await screen.findByText('100%');
      await user.click(screen.getByRole('button', { name: 'Add tag promo' }));

      expect(await screen.findByText('Name already in use')).toBeInTheDocument();
    });
  });

  describe('removing files', () => {
    test('Remove File takes that one file off the list', async () => {
      const user = userEvent.setup();
      renderModal();

      await dropFiles(user, zip('One.zip'), zip('Two.zip'));
      await waitFor(() => expect(importLayout).toHaveBeenCalledTimes(2));
      await waitFor(() => expect(screen.getAllByText('100%')).toHaveLength(2));

      await user.click(screen.getAllByRole('button', { name: 'Remove File' })[0]!);

      expect(screen.queryByDisplayValue('One')).not.toBeInTheDocument();
      expect(screen.getByDisplayValue('Two')).toBeInTheDocument();
    });

    test('Remove All clears the list once importing has finished', async () => {
      const user = userEvent.setup();
      renderModal();

      await dropFiles(user, zip('One.zip'), zip('Two.zip'));
      await waitFor(() => expect(screen.getAllByText('100%')).toHaveLength(2));

      await user.click(screen.getByRole('button', { name: 'Remove All' }));

      expect(screen.queryByDisplayValue('One')).not.toBeInTheDocument();
      expect(screen.queryByDisplayValue('Two')).not.toBeInTheDocument();
    });

    // Open — xibosignage/xibo#4059.
    knownFailure(
      'removing a file that is still waiting to upload stops it from being imported',
      async () => {
        const user = userEvent.setup();
        const first = deferred<ImportLayoutResponse>();
        vi.mocked(importLayout).mockReturnValueOnce(first.promise).mockResolvedValue(imported(901));
        renderModal();

        await dropFiles(user, zip('One.zip'), zip('Two.zip'));
        await waitFor(() => expect(importLayout).toHaveBeenCalledOnce());

        // "Two" is queued behind "One". Remove it, then let "One" finish.
        await user.click(screen.getAllByRole('button', { name: 'Remove File' })[1]!);
        expect(screen.queryByDisplayValue('Two')).not.toBeInTheDocument();
        first.resolve(imported(900));
        await screen.findByText('100%');
        await waitFor(() => expect(screen.getByRole('button', { name: 'Done' })).toBeEnabled());
      },
      () => expect(importLayout).toHaveBeenCalledOnce(),
    );
  });
});
