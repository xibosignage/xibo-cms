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
import { AxiosError, AxiosHeaders } from 'axios';
import type { ComponentProps } from 'react';
import { vi, beforeEach, describe, test, expect } from 'vitest';

import EditTagsMultipleModal from '../EditTagsMultipleModal';

import { editMultipleTags, fetchTags } from '@/services/tagApi';
import type { EditMultipleTagsResult } from '@/services/tagApi';
import { testQueryClient } from '@/setupTests';
import { knownFailure } from '@/testUtils/knownFailure';
import { mockNotifyError, mockNotifySuccess } from '@/testUtils/notifyMock';
import type { Tag } from '@/types/tag';

// Bulk "Edit Tags" for the selected rows. Used by Displays, Layouts, Display Groups,
// Media, Playlists and Templates. TagInput is REAL here (its own tests live in
// components/ui/__tests__/), so these tests type tags the way a user does.

// ─── Mocks ────────────────────────────────────────────────────────────────────

vi.mock('@/components/ui/modals/Modal');
vi.mock('@/services/tagApi', () => ({
  fetchTags: vi.fn(),
  editMultipleTags: vi.fn(),
}));

// ─── Fixtures ─────────────────────────────────────────────────────────────────

const ALL_UPDATED: EditMultipleTagsResult = { failedCount: 0, failedNames: [] };

const EXISTING_TAGS: Tag[] = [
  { tag: 'lobby', tagId: 1, value: '' },
  { tag: 'region', tagId: 2, value: 'north' },
];

// ─── Helpers ──────────────────────────────────────────────────────────────────

const renderModal = (props: Partial<ComponentProps<typeof EditTagsMultipleModal>> = {}) => {
  const onClose = vi.fn();
  const onSuccess = vi.fn();
  render(
    <QueryClientProvider client={testQueryClient}>
      <EditTagsMultipleModal
        targetType="display"
        ids={[11, 12, 13]}
        existingTags={EXISTING_TAGS}
        onClose={onClose}
        onSuccess={onSuccess}
        {...props}
      />
    </QueryClientProvider>,
  );
  return { onClose, onSuccess };
};

const addBox = () => screen.getByRole('combobox', { name: 'Add Tags' });
const removeBox = () => screen.getByRole('combobox', { name: 'Remove Tags' });
const saveButton = () => screen.getByRole('button', { name: 'Save' });

// The supported way to enter a tag with no value: type it, press Enter, then press
// Enter again in the "Tag Value" box TagInput opens, leaving the value blank.
const enterTag = async (user: UserEvent, box: HTMLElement, name: string) => {
  await user.type(box, `${name}{Enter}`);
  await screen.findByLabelText('Tag Value');
  await user.keyboard('{Enter}');
  await waitFor(() => expect(screen.queryByLabelText('Tag Value')).not.toBeInTheDocument());
};

// ─── Tests ────────────────────────────────────────────────────────────────────

describe('EditTagsMultipleModal', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    vi.mocked(fetchTags).mockResolvedValue({ rows: [], totalCount: 0 } as never);
    vi.mocked(editMultipleTags).mockResolvedValue(ALL_UPDATED);
  });

  describe('what is sent', () => {
    test('a tag entered in Add Tags is added to every selected item', async () => {
      const user = userEvent.setup();
      const { onSuccess } = renderModal();

      await enterTag(user, addBox(), 'promo');
      await user.click(saveButton());

      await waitFor(() => expect(editMultipleTags).toHaveBeenCalledOnce());
      expect(editMultipleTags).toHaveBeenCalledWith({
        targetType: 'display',
        ids: [11, 12, 13],
        addTags: 'promo',
        removeTags: '',
      });
      expect(onSuccess).toHaveBeenCalledOnce();
    });

    test('a tag given a value is sent as tag|value', async () => {
      const user = userEvent.setup();
      renderModal();

      await user.type(addBox(), 'floor{Enter}');
      await user.type(await screen.findByLabelText('Tag Value'), '2{Enter}');
      await user.click(saveButton());

      await waitFor(() => expect(editMultipleTags).toHaveBeenCalledOnce());
      expect(editMultipleTags).toHaveBeenCalledWith(
        expect.objectContaining({ addTags: 'floor|2', removeTags: '' }),
      );
    });

    test('clicking one of the current tags queues it for removal, value and all', async () => {
      const user = userEvent.setup();
      renderModal();

      await user.click(screen.getByRole('button', { name: 'region|north' }));
      await user.click(saveButton());

      await waitFor(() => expect(editMultipleTags).toHaveBeenCalledOnce());
      expect(editMultipleTags).toHaveBeenCalledWith(
        expect.objectContaining({ addTags: '', removeTags: 'region|north' }),
      );
    });

    test('a tag queued for removal is no longer offered as a current tag', async () => {
      const user = userEvent.setup();
      renderModal();

      await user.click(screen.getByRole('button', { name: 'lobby' }));

      expect(screen.queryByRole('button', { name: 'lobby' })).not.toBeInTheDocument();
      expect(screen.getByRole('button', { name: 'region|north' })).toBeInTheDocument();
    });

    test('adding and removing in the same save sends both lists', async () => {
      const user = userEvent.setup();
      renderModal();

      await enterTag(user, addBox(), 'promo');
      await enterTag(user, removeBox(), 'old-campaign');
      await user.click(screen.getByRole('button', { name: 'lobby' }));
      await user.click(saveButton());

      await waitFor(() => expect(editMultipleTags).toHaveBeenCalledOnce());
      const sent = vi.mocked(editMultipleTags).mock.calls[0]![0];
      expect(sent.addTags).toBe('promo');
      expect(sent.removeTags!.split(',').sort()).toEqual(['lobby', 'old-campaign']);
    });

    test('the item type and ids come from the page that opened it', async () => {
      const user = userEvent.setup();
      renderModal({ targetType: 'layout', ids: [861001, 861002] });

      await enterTag(user, addBox(), 'promo');
      await user.click(saveButton());

      await waitFor(() =>
        expect(editMultipleTags).toHaveBeenCalledWith(
          expect.objectContaining({ targetType: 'layout', ids: [861001, 861002] }),
        ),
      );
    });
  });

  describe('typing a tag and moving on without pressing Enter', () => {
    // Open — xibosignage/xibo#4063.
    knownFailure(
      'clicking Save straight after typing a tag saves it',
      async () => {
        const user = userEvent.setup();
        renderModal();
        await user.type(addBox(), 'promo');
        await user.click(saveButton());
        // The click left a half-entered tag behind instead of saving.
        await screen.findByLabelText('Tag Value');
      },
      () => expect(editMultipleTags).toHaveBeenCalledOnce(),
    );

    // Open — xibosignage/xibo#4058.
    knownFailure(
      'text typed into Remove Tags after typing into Add Tags is sent as a tag to remove',
      async () => {
        const user = userEvent.setup();
        renderModal();
        await user.type(addBox(), 'promo');
        // Clicking into Remove Tags blurs Add Tags; focus jumps to promo's Tag Value box,
        // so the next keystrokes land there.
        await user.type(removeBox(), 'old-campaign');
        await user.keyboard('{Enter}');
        await user.click(saveButton());
        await waitFor(() => expect(editMultipleTags).toHaveBeenCalledOnce());
        return vi.mocked(editMultipleTags).mock.calls[0]![0];
      },
      (sent) => expect(sent.removeTags).toBe('old-campaign'),
    );
  });

  describe('when Save is not allowed', () => {
    test('saving with nothing to add or remove asks for a tag and sends nothing', async () => {
      const user = userEvent.setup();
      renderModal();

      await user.click(saveButton());

      expect(screen.getByRole('alert')).toHaveTextContent('Add or remove at least one tag.');
      expect(editMultipleTags).not.toHaveBeenCalled();
    });

    // While the Tag Value box is showing, the tag is half-entered; saving then
    // would drop the value the user is about to type.
    test('Save is locked while a tag value is still being entered', async () => {
      const user = userEvent.setup();
      renderModal();

      await user.type(addBox(), 'floor{Enter}');
      await screen.findByLabelText('Tag Value');

      expect(saveButton()).toBeDisabled();
    });

    test('with no items selected Save is locked', () => {
      renderModal({ ids: [] });

      expect(saveButton()).toBeDisabled();
    });
  });

  describe('results', () => {
    test('when every item is updated the user is told how many', async () => {
      const user = userEvent.setup();
      renderModal();

      await enterTag(user, addBox(), 'promo');
      await user.click(saveButton());

      await waitFor(() => expect(mockNotifySuccess).toHaveBeenCalledOnce());
      expect(mockNotifyError).not.toHaveBeenCalled();
    });

    test('when some items fail the user is told which ones, and the page still refreshes', async () => {
      const user = userEvent.setup();
      vi.mocked(editMultipleTags).mockResolvedValue({
        failedCount: 2,
        failedNames: ['Reception', 'Lobby'],
      });
      const { onSuccess } = renderModal();

      await enterTag(user, addBox(), 'promo');
      await user.click(saveButton());

      await waitFor(() => expect(mockNotifyError).toHaveBeenCalledOnce());
      expect(vi.mocked(mockNotifyError).mock.calls[0]![1]).toEqual({
        description: 'Reception, Lobby',
      });
      expect(mockNotifySuccess).not.toHaveBeenCalled();
      expect(onSuccess).toHaveBeenCalledOnce();
    });

    test('a request the server refuses shows its reason and does not refresh the page', async () => {
      vi.spyOn(console, 'error').mockImplementation(() => {});
      const user = userEvent.setup();
      vi.mocked(editMultipleTags).mockRejectedValue(
        new AxiosError('Request failed', '403', undefined, undefined, {
          status: 403,
          statusText: 'Forbidden',
          headers: {},
          config: { headers: new AxiosHeaders() },
          data: { message: 'You do not have permission to edit these tags' },
        }),
      );
      const { onSuccess } = renderModal();

      await enterTag(user, addBox(), 'promo');
      await user.click(saveButton());

      expect(await screen.findByRole('alert')).toHaveTextContent(
        'You do not have permission to edit these tags',
      );
      expect(onSuccess).not.toHaveBeenCalled();
    });

    test('a request that fails with no reason shows a generic message', async () => {
      vi.spyOn(console, 'error').mockImplementation(() => {});
      const user = userEvent.setup();
      vi.mocked(editMultipleTags).mockRejectedValue(new Error('Network Error'));
      renderModal();

      await enterTag(user, addBox(), 'promo');
      await user.click(saveButton());

      expect(await screen.findByRole('alert')).toHaveTextContent('Failed to update tags.');
    });

    test('while saving, both buttons are locked and Save reads "Saving…"', async () => {
      const user = userEvent.setup();
      let respond!: (r: EditMultipleTagsResult) => void;
      vi.mocked(editMultipleTags).mockReturnValue(new Promise((res) => (respond = res)));
      renderModal();

      await enterTag(user, addBox(), 'promo');
      await user.click(saveButton());

      expect(await screen.findByRole('button', { name: 'Saving…' })).toBeDisabled();
      expect(screen.getByRole('button', { name: 'Cancel' })).toBeDisabled();

      respond(ALL_UPDATED);
      await waitFor(() => expect(saveButton()).toBeEnabled());
    });

    test('Cancel closes the modal without saving', async () => {
      const user = userEvent.setup();
      const { onClose } = renderModal();

      await user.type(addBox(), 'promo');
      await user.click(screen.getByRole('button', { name: 'Cancel' }));

      expect(onClose).toHaveBeenCalledOnce();
      expect(editMultipleTags).not.toHaveBeenCalled();
    });
  });
});
