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

import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { vi, beforeEach, describe, test, expect } from 'vitest';

import { buildUser } from '../fixtures/user';

import DeleteUserModal from '@/pages/Administration/Users/components/DeleteUserModal';
import { fetchUsers } from '@/services/userApi';

// =============================================================================
// Module mocks
// =============================================================================

vi.mock('@/services/userApi');

vi.mock('@/components/ui/modals/Modal');

// =============================================================================
// Tests
// =============================================================================

describe('DeleteUserModal', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    vi.mocked(fetchUsers).mockResolvedValue({ rows: [], totalCount: 0 });
  });

  test('confirmation message includes the target user name', async () => {
    render(
      <DeleteUserModal isOpen userName="jbloggs" userId={2} onClose={vi.fn()} onDelete={vi.fn()} />,
    );

    expect(await screen.findByText('jbloggs')).toBeInTheDocument();
  });

  test('"Delete all content" checkbox is shown only for a non-super-admin', async () => {
    render(
      <DeleteUserModal
        isOpen
        userName="jbloggs"
        userId={2}
        isSuperAdmin={false}
        onClose={vi.fn()}
        onDelete={vi.fn()}
      />,
    );

    expect(
      await screen.findByRole('checkbox', { name: /delete all content/i }),
    ).toBeInTheDocument();
  });

  test('"Delete all content" checkbox is hidden for a super admin', async () => {
    render(
      <DeleteUserModal
        isOpen
        userName="jbloggs"
        userId={2}
        isSuperAdmin={true}
        onClose={vi.fn()}
        onDelete={vi.fn()}
      />,
    );

    await screen.findByText('jbloggs');

    expect(screen.queryByRole('checkbox', { name: /delete all content/i })).not.toBeInTheDocument();
  });

  test('checking "Delete all content" hides the reassignment dropdown', async () => {
    const user = userEvent.setup();
    render(
      <DeleteUserModal
        isOpen
        userName="jbloggs"
        userId={2}
        isSuperAdmin={false}
        onClose={vi.fn()}
        onDelete={vi.fn()}
      />,
    );

    await screen.findByRole('combobox', { name: /reassign content to/i });

    await user.click(screen.getByRole('checkbox', { name: /delete all content/i }));

    expect(
      screen.queryByRole('combobox', { name: /reassign content to/i }),
    ).not.toBeInTheDocument();
  });

  test('fetches reassignment candidates excluding the user being deleted', async () => {
    const user = userEvent.setup();
    const deletedUser = buildUser({ userId: 2, userName: 'jbloggs' });
    const otherUser = buildUser({ userId: 5, userName: 'other' });
    vi.mocked(fetchUsers).mockResolvedValue({ rows: [deletedUser, otherUser], totalCount: 2 });

    render(
      <DeleteUserModal
        isOpen
        userName="jbloggs"
        userId={2}
        isSuperAdmin={false}
        onClose={vi.fn()}
        onDelete={vi.fn()}
      />,
    );

    const combobox = await screen.findByRole('combobox', { name: /reassign content to/i });
    await user.click(combobox);

    expect(await screen.findByRole('option', { name: 'other' })).toBeInTheDocument();
    expect(screen.queryByRole('option', { name: 'jbloggs' })).not.toBeInTheDocument();
  });

  test('"Yes, Delete" calls onDelete with the chosen options', async () => {
    const user = userEvent.setup();
    const otherUser = buildUser({ userId: 5, userName: 'other' });
    vi.mocked(fetchUsers).mockResolvedValue({ rows: [otherUser], totalCount: 1 });
    const onDelete = vi.fn();

    render(
      <DeleteUserModal
        isOpen
        userName="jbloggs"
        userId={2}
        isSuperAdmin={false}
        onClose={vi.fn()}
        onDelete={onDelete}
      />,
    );

    const combobox = await screen.findByRole('combobox', { name: /reassign content to/i });
    await user.click(combobox);
    await user.click(await screen.findByRole('option', { name: 'other' }));

    await user.click(screen.getByRole('button', { name: 'Yes, Delete' }));

    expect(onDelete).toHaveBeenCalledWith({ deleteAllItems: false, reassignUserId: 5 });
  });

  test('Cancel calls onClose', async () => {
    const user = userEvent.setup();
    const onClose = vi.fn();

    render(
      <DeleteUserModal isOpen userName="jbloggs" userId={2} onClose={onClose} onDelete={vi.fn()} />,
    );

    await screen.findByText('jbloggs');
    await user.click(screen.getByRole('button', { name: 'Cancel' }));

    expect(onClose).toHaveBeenCalledTimes(1);
  });

  test('error message is shown when the error prop is set', async () => {
    render(
      <DeleteUserModal
        isOpen
        userName="jbloggs"
        userId={2}
        onClose={vi.fn()}
        onDelete={vi.fn()}
        error="Cannot delete the last Super Admin"
      />,
    );

    expect(await screen.findByText('Cannot delete the last Super Admin')).toBeInTheDocument();
  });

  test('"Yes, Delete" is disabled and shows "Deleting..." while isLoading', async () => {
    render(
      <DeleteUserModal
        isOpen
        userName="jbloggs"
        userId={2}
        onClose={vi.fn()}
        onDelete={vi.fn()}
        isLoading
      />,
    );

    expect(await screen.findByRole('button', { name: 'Deleting...' })).toBeDisabled();
  });
});
