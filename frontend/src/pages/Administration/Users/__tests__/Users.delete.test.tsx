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

import { screen, waitFor, within } from '@testing-library/react';
import userEvent, { type UserEvent } from '@testing-library/user-event';
import { vi, beforeEach, describe, test, expect } from 'vitest';

import { mockUser, SINGLE_USER } from './fixtures/user';
import { renderUsersPage } from './helpers/renderUsersPage';
import { mockFetchUsers } from './mocks/userApi';

import { deleteUser } from '@/services/userApi';
import { testQueryClient } from '@/setupTests';

// =============================================================================
// Module mocks
// =============================================================================

vi.mock('@/services/userApi');

vi.mock('@/services/userGroupApi');

vi.mock('@/services/folderApi', () => ({
  fetchFolderTree: vi.fn().mockResolvedValue([]),
}));

vi.mock('@/services/permissionsApi', () => ({
  fetchGroupFolderPermissions: vi.fn().mockResolvedValue(new Map()),
  saveMultiPermissions: vi.fn().mockResolvedValue(undefined),
}));

vi.mock('@/hooks/useFilteredTabs', () => ({
  useFilteredTabs: vi.fn(() => [{ name: 'Users', path: '/administration/users' }]),
}));

vi.mock('@/components/ui/modals/Modal');

// =============================================================================
// Helpers
// =============================================================================

const openDeleteModal = async (user: UserEvent) => {
  await screen.findByText(mockUser.userName);

  await user.click(screen.getByRole('button', { name: /more actions/i }));
  await user.click(await screen.findByRole('button', { name: /^delete$/i }));

  return screen.findByRole('dialog');
};

// =============================================================================
// Tests
// =============================================================================

describe('Delete User', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    // mockUser.userId (2) differs from mockSuperAdmin.userId (1) — not self,
    // so the Delete row action is visible.
    mockFetchUsers(SINGLE_USER);
  });

  // ---------------------------------------------------------------------------
  // Clicking Delete opens the confirmation modal showing the user's name.
  // ---------------------------------------------------------------------------
  test('Delete row action opens the confirmation modal', async () => {
    const user = userEvent.setup();
    renderUsersPage();

    const dialog = await openDeleteModal(user);

    expect(dialog).toBeInTheDocument();
    expect(within(dialog).getByText('Delete User?')).toBeInTheDocument();
    expect(within(dialog).getByText(mockUser.userName)).toBeInTheDocument();
  });

  // ---------------------------------------------------------------------------
  // Clicking Cancel closes the modal without calling the API.
  // ---------------------------------------------------------------------------
  test('Cancel closes the modal without calling deleteUser', async () => {
    const user = userEvent.setup();
    renderUsersPage();
    await openDeleteModal(user);

    await user.click(screen.getByRole('button', { name: 'Cancel' }));

    await waitFor(() => {
      expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
    });
    expect(deleteUser).not.toHaveBeenCalled();
  });

  // ---------------------------------------------------------------------------
  // Clicking "Yes, Delete" on success calls deleteUser and closes the modal.
  // ---------------------------------------------------------------------------
  test('successful delete calls deleteUser and closes the modal', async () => {
    const user = userEvent.setup();
    vi.mocked(deleteUser).mockResolvedValueOnce(undefined);

    renderUsersPage();
    await openDeleteModal(user);

    await user.click(screen.getByRole('button', { name: 'Yes, Delete' }));

    await waitFor(() => {
      expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
    });
    expect(deleteUser).toHaveBeenCalledTimes(1);
    expect(deleteUser).toHaveBeenCalledWith(
      mockUser.userId,
      expect.objectContaining({ deleteAllItems: 0 }),
    );
  });

  // ---------------------------------------------------------------------------
  // While deletion is in progress, the confirm button switches to "Deleting…"
  // and is disabled so the user cannot submit a second time.
  // ---------------------------------------------------------------------------
  test('Yes, Delete button is disabled with loading label while deletion is in progress', async () => {
    const user = userEvent.setup();
    let resolveDelete!: () => void;
    const controlledPromise = new Promise<void>((resolve) => {
      resolveDelete = resolve;
    });
    vi.mocked(deleteUser).mockReturnValue(controlledPromise);

    renderUsersPage();
    await openDeleteModal(user);

    await user.click(screen.getByRole('button', { name: 'Yes, Delete' }));

    await waitFor(() => {
      expect(screen.getByRole('button', { name: 'Deleting...' })).toBeDisabled();
    });
    expect(deleteUser).toHaveBeenCalledTimes(1);

    // Resolve and wait for the component to settle before the test tears down.
    resolveDelete();
    await waitFor(() => {
      expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
    });
  });

  // ---------------------------------------------------------------------------
  // Delete modal shows an error and stays open when deletion fails.
  // ---------------------------------------------------------------------------
  test('failed delete keeps the modal open and shows an error', async () => {
    const user = userEvent.setup();
    vi.mocked(deleteUser).mockRejectedValueOnce({
      isAxiosError: true,
      response: { data: { message: 'Cannot delete — this is the last remaining Super Admin.' } },
    });

    renderUsersPage();
    await openDeleteModal(user);

    await user.click(screen.getByRole('button', { name: 'Yes, Delete' }));

    expect(deleteUser).toHaveBeenCalledTimes(1);
    expect(await screen.findByText(/last remaining super admin/i)).toBeInTheDocument();
    expect(screen.getByRole('dialog')).toBeInTheDocument();
  });
});
