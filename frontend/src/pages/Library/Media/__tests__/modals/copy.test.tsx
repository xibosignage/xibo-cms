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
import type { ComponentProps } from 'react';
import { vi, beforeEach, describe, test, expect } from 'vitest';

import CopyMediaModal from '../../components/CopyMediaModal';
import { mockEditMedia, mockUser } from '../mediaTestUtils';

import { UserProvider } from '@/context/UserContext';
import { fetchTags } from '@/services/tagApi';
import { testQueryClient } from '@/setupTests';
import { knownFailure } from '@/testUtils/knownFailure';
import { buildCurrentUser, PERSONAS } from '@/testUtils/personas';
import type { User } from '@/types/user';

// "Make a Copy" row action. The modal only collects a name and tags and hands them
// to the page; the cloneMedia request itself is tested in
// hooks/useMediaActions.copy-stats.test.tsx. TagInput is real.

// ─── Mocks ────────────────────────────────────────────────────────────────────

vi.mock('@/components/ui/modals/Modal');
vi.mock('@/services/tagApi', () => ({ fetchTags: vi.fn() }));

// ─── Fixtures ─────────────────────────────────────────────────────────────────

// mockUser is a super admin (userTypeId 1), so every feature check passes.
const adminUser: User = mockUser;

// A plain user has neither tag.tagging nor library features beyond the basics.
const userWithoutTagging: User = buildCurrentUser(PERSONAS.user, {});

const MEDIA = {
  ...mockEditMedia,
  name: 'Welcome',
  tags: [{ tag: 'nature', value: 'forest', tagId: 1 }],
};

// ─── Helpers ──────────────────────────────────────────────────────────────────

const renderModal = (
  props: Partial<ComponentProps<typeof CopyMediaModal>> = {},
  user: User = adminUser,
) => {
  const onClose = vi.fn();
  const onConfirm = vi.fn();
  render(
    <QueryClientProvider client={testQueryClient}>
      <UserProvider initialUser={user}>
        <CopyMediaModal
          media={MEDIA}
          existingNames={['Welcome', 'Menu board']}
          onClose={onClose}
          onConfirm={onConfirm}
          {...props}
        />
      </UserProvider>
    </QueryClientProvider>,
  );
  return { onClose, onConfirm };
};

const nameBox = () => screen.getByRole('textbox', { name: 'New name' });
const save = (user: UserEvent) => user.click(screen.getByRole('button', { name: 'Save' }));

// ─── Tests ────────────────────────────────────────────────────────────────────

describe('CopyMediaModal', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    vi.mocked(fetchTags).mockResolvedValue({ rows: [], totalCount: 0 } as never);
  });

  describe('prefilled values', () => {
    test('suggests the original name with a number after it, so the copy does not clash', () => {
      renderModal();

      expect(nameBox()).toHaveValue('Welcome (1)');
    });

    test('a name that already ends in a number has that number increased', () => {
      renderModal({ media: { ...MEDIA, name: 'Welcome (3)' } });

      expect(nameBox()).toHaveValue('Welcome (4)');
    });

    test('saving straight away copies the media with the suggested name and the original tags', async () => {
      const user = userEvent.setup();
      const { onConfirm } = renderModal();

      await save(user);

      expect(onConfirm).toHaveBeenCalledWith('Welcome (1)', [
        { tag: 'nature', value: 'forest', tagId: 1 },
      ]);
    });
  });

  describe('name', () => {
    test('the name the user types is sent, without surrounding spaces', async () => {
      const user = userEvent.setup();
      const { onConfirm } = renderModal();

      await user.clear(nameBox());
      await user.type(nameBox(), '  Welcome lobby  ');
      await save(user);

      expect(onConfirm).toHaveBeenCalledWith('Welcome lobby', expect.any(Array));
    });

    test('an empty name is refused', async () => {
      const user = userEvent.setup();
      const { onConfirm } = renderModal();

      await user.clear(nameBox());
      await save(user);

      expect(screen.getByText('Name is required')).toBeInTheDocument();
      expect(onConfirm).not.toHaveBeenCalled();
    });

    test('a name already used by another media item is refused, whatever its case', async () => {
      const user = userEvent.setup();
      const { onConfirm } = renderModal();

      await user.clear(nameBox());
      await user.type(nameBox(), 'MENU BOARD');
      await save(user);

      expect(screen.getByText('A media item with this name already exists')).toBeInTheDocument();
      expect(onConfirm).not.toHaveBeenCalled();
    });

    test('typing again clears the warning', async () => {
      const user = userEvent.setup();
      renderModal();

      await user.clear(nameBox());
      await save(user);
      await user.type(nameBox(), 'x');

      expect(screen.queryByText('Name is required')).not.toBeInTheDocument();
    });
  });

  describe('tags', () => {
    test('a tag added in the modal is sent along with the original tags', async () => {
      const user = userEvent.setup();
      const { onConfirm } = renderModal();

      await user.type(screen.getByRole('combobox', { name: 'Tags' }), 'lobby{Enter}');
      await screen.findByLabelText('Tag Value');
      await user.keyboard('{Enter}');
      await waitFor(() => expect(screen.queryByLabelText('Tag Value')).not.toBeInTheDocument());
      await save(user);

      const tags = vi.mocked(onConfirm).mock.calls[0]![1];
      expect(tags.map((t: { tag: string }) => t.tag)).toEqual(['nature', 'lobby']);
    });

    test('an original tag removed in the modal is not copied', async () => {
      const user = userEvent.setup();
      const { onConfirm } = renderModal();

      await user.click(screen.getByRole('button', { name: 'Remove tag nature' }));
      await save(user);

      expect(onConfirm).toHaveBeenCalledWith('Welcome (1)', []);
    });

    test('a user without tagging sees the original tags but cannot change them', () => {
      renderModal({}, userWithoutTagging);

      expect(screen.getByRole('combobox', { name: 'Tags' })).toBeDisabled();
    });

    test('a user without tagging copying untagged media gets no tag field at all', () => {
      renderModal({ media: { ...MEDIA, tags: [] } }, userWithoutTagging);

      expect(screen.queryByRole('combobox', { name: 'Tags' })).not.toBeInTheDocument();
    });

    // Open — xibosignage/xibo#4063.
    knownFailure(
      'clicking Save straight after typing a tag copies the media',
      async () => {
        const user = userEvent.setup();
        const { onConfirm } = renderModal();
        await user.type(screen.getByRole('combobox', { name: 'Tags' }), 'lobby');
        await save(user);
        await screen.findByLabelText('Tag Value');
        return onConfirm;
      },
      (onConfirm) => expect(onConfirm).toHaveBeenCalledOnce(),
    );
  });

  describe('saving and cancelling', () => {
    test('while the copy is being made both buttons are locked', () => {
      renderModal({ isLoading: true });

      expect(screen.getByRole('button', { name: 'Saving…' })).toBeDisabled();
      expect(screen.getByRole('button', { name: 'Cancel' })).toBeDisabled();
    });

    test('Cancel closes the modal without copying', async () => {
      const user = userEvent.setup();
      const { onClose, onConfirm } = renderModal();

      await user.click(screen.getByRole('button', { name: 'Cancel' }));

      expect(onClose).toHaveBeenCalledOnce();
      expect(onConfirm).not.toHaveBeenCalled();
    });
  });
});
