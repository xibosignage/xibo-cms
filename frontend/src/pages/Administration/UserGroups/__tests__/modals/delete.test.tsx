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

import { render, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { vi, beforeEach, describe, test, expect } from 'vitest';

import DeleteUserGroupModal from '../../components/DeleteUserGroupModal';

// =============================================================================
// Module mocks
// =============================================================================

vi.mock('@/components/ui/modals/Modal');

// =============================================================================
// Helpers
// =============================================================================

const renderDeleteModal = (
  props: Partial<React.ComponentProps<typeof DeleteUserGroupModal>> = {},
) => {
  const onClose = vi.fn();
  const onDelete = vi.fn();
  render(
    <DeleteUserGroupModal
      onClose={onClose}
      onDelete={onDelete}
      itemCount={1}
      groupName="Editors"
      {...props}
    />,
  );
  return { onClose, onDelete, dialog: screen.getByRole('dialog') };
};

// =============================================================================
// Tests
// =============================================================================

describe('DeleteUserGroupModal', () => {
  beforeEach(() => vi.clearAllMocks());

  test('single group: singular heading and the group name in bold', () => {
    const { dialog } = renderDeleteModal();

    expect(within(dialog).getByRole('heading', { name: 'Delete User Group?' })).toBeInTheDocument();
    expect(within(dialog).getByText('Editors').tagName).toBe('STRONG');
  });

  test('several groups: plural heading and the count in bold', () => {
    const { dialog } = renderDeleteModal({ itemCount: 3, groupName: undefined });

    expect(
      within(dialog).getByRole('heading', { name: 'Delete User Groups?' }),
    ).toBeInTheDocument();
    expect(within(dialog).getByText('3').tagName).toBe('STRONG');
  });

  test('Yes, Delete calls onDelete', async () => {
    const user = userEvent.setup();
    const { onDelete } = renderDeleteModal();

    await user.click(screen.getByRole('button', { name: 'Yes, Delete' }));

    expect(onDelete).toHaveBeenCalledTimes(1);
  });

  test('Cancel calls onClose', async () => {
    const user = userEvent.setup();
    const { onClose, onDelete } = renderDeleteModal();

    await user.click(screen.getByRole('button', { name: 'Cancel' }));

    expect(onClose).toHaveBeenCalledTimes(1);
    expect(onDelete).not.toHaveBeenCalled();
  });

  test('an error passed in is shown', () => {
    renderDeleteModal({ error: 'Group has users' });
    expect(screen.getByText('Group has users')).toBeInTheDocument();
  });

  test('loading shows "Deleting…" and disables the confirm button', () => {
    renderDeleteModal({ isLoading: true });
    expect(screen.getByRole('button', { name: 'Deleting…' })).toBeDisabled();
  });
});
