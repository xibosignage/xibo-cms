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

import { screen, waitFor, within } from '@testing-library/react';
import userEvent, { type UserEvent } from '@testing-library/user-event';
import { vi, beforeEach, describe, test, expect } from 'vitest';

import {
  buildUserGroup,
  mockUserGroup,
  MULTIPLE_USER_GROUPS,
  SINGLE_USER_GROUP,
} from './fixtures/userGroup';
import { renderUserGroupsPage } from './helpers/renderUserGroupsPage';
import { mockFetchUserGroups } from './mocks/userGroupApi';

import { notify } from '@/components/ui/Notification';
import { deleteUserGroup, fetchUserGroups } from '@/services/userGroupApi';
import type { FetchUserGroupsRequest } from '@/services/userGroupApi';
import { testQueryClient } from '@/setupTests';
import { alreadyDeleted, apiError } from '@/testUtils/apiErrors';
import { deferred } from '@/testUtils/deferred';
import { openViaMenu } from '@/testUtils/rowActions';

// =============================================================================
// Module mocks
// =============================================================================

vi.mock('@/services/userGroupApi');
vi.mock('@/services/userApi', () => ({
  fetchUserPreference: vi.fn().mockResolvedValue(null),
  saveUserPreference: vi.fn().mockResolvedValue(undefined),
  fetchUsers: vi.fn().mockResolvedValue({ rows: [], totalCount: 0 }),
}));
vi.mock('@/hooks/useFilteredTabs', () => ({
  useFilteredTabs: vi.fn(() => [{ name: 'User Groups', path: '/administration/usergroups' }]),
}));
vi.mock('@/components/ui/modals/Modal');
vi.mock('@/components/ui/Notification', () => ({
  notify: { success: vi.fn(), error: vi.fn(), info: vi.fn(), warning: vi.fn() },
}));

// =============================================================================
// Helpers
// =============================================================================

const openRowDeleteModal = (user: UserEvent) => openViaMenu(user, mockUserGroup.group, /^delete$/i);

const openBulkDeleteModal = async (user: UserEvent) => {
  await screen.findByText('Viewers');
  // Re-query each time — selecting a row re-renders the checkboxes.
  await user.click(screen.getAllByRole('checkbox', { name: /select row/i })[0]!);
  await user.click(screen.getAllByRole('checkbox', { name: /select row/i })[1]!);
  // The count isn't in the button's accessible name (xibosignage/xibo#4130), so match by text.
  await screen.findByText(/2 selected/i);
  // Row menus are closed, so the only "Delete" button is the bulk action.
  await user.click(await screen.findByRole('button', { name: /^delete$/i }));
  return screen.findByRole('dialog');
};

// =============================================================================
// Tests — single delete
// =============================================================================

describe('User Groups page - single delete', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    mockFetchUserGroups(SINGLE_USER_GROUP);
  });

  test('row Delete opens the confirmation naming the group', async () => {
    const user = userEvent.setup();
    renderUserGroupsPage();

    const dialog = await openRowDeleteModal(user);

    expect(within(dialog).getByRole('heading', { name: 'Delete User Group?' })).toBeInTheDocument();
    expect(within(dialog).getByText(mockUserGroup.group)).toBeInTheDocument();
  });

  test('Cancel closes the modal without calling deleteUserGroup', async () => {
    const user = userEvent.setup();
    renderUserGroupsPage();
    await openRowDeleteModal(user);

    await user.click(screen.getByRole('button', { name: 'Cancel' }));

    await waitFor(() => expect(screen.queryByRole('dialog')).not.toBeInTheDocument());
    expect(deleteUserGroup).not.toHaveBeenCalled();
  });

  test('successful delete calls the API, notifies, refreshes and closes', async () => {
    const user = userEvent.setup();
    vi.mocked(deleteUserGroup).mockResolvedValue(undefined);
    renderUserGroupsPage();
    await openRowDeleteModal(user);
    const callsBefore = vi.mocked(fetchUserGroups).mock.calls.length;

    await user.click(screen.getByRole('button', { name: 'Yes, Delete' }));

    await waitFor(() => expect(screen.queryByRole('dialog')).not.toBeInTheDocument());
    expect(deleteUserGroup).toHaveBeenCalledTimes(1);
    expect(deleteUserGroup).toHaveBeenCalledWith(mockUserGroup.groupId);
    expect(notify.success).toHaveBeenCalledWith('1 user group(s) deleted successfully.');
    await waitFor(() =>
      expect(vi.mocked(fetchUserGroups).mock.calls.length).toBeGreaterThan(callsBefore),
    );
  });

  test('Yes, Delete is disabled with a loading label while deletion is in progress', async () => {
    const user = userEvent.setup();
    const pending = deferred<void>();
    vi.mocked(deleteUserGroup).mockReturnValue(pending.promise);
    renderUserGroupsPage();
    await openRowDeleteModal(user);

    await user.click(screen.getByRole('button', { name: 'Yes, Delete' }));

    await waitFor(() => {
      expect(screen.getByRole('button', { name: 'Deleting…' })).toBeDisabled();
    });
    expect(deleteUserGroup).toHaveBeenCalledTimes(1);

    pending.resolve();
    await waitFor(() => expect(screen.queryByRole('dialog')).not.toBeInTheDocument());
  });

  test('a group already deleted elsewhere (404) counts as success', async () => {
    const user = userEvent.setup();
    vi.mocked(deleteUserGroup).mockRejectedValue(alreadyDeleted());
    renderUserGroupsPage();
    await openRowDeleteModal(user);

    await user.click(screen.getByRole('button', { name: 'Yes, Delete' }));

    await waitFor(() => expect(screen.queryByRole('dialog')).not.toBeInTheDocument());
    expect(notify.success).toHaveBeenCalledWith('1 user group(s) deleted successfully.');
  });

  test('server error message is shown and the modal stays open', async () => {
    const user = userEvent.setup();
    vi.mocked(deleteUserGroup).mockRejectedValue(apiError('Group has users', 409));
    renderUserGroupsPage();
    await openRowDeleteModal(user);

    await user.click(screen.getByRole('button', { name: 'Yes, Delete' }));

    expect(deleteUserGroup).toHaveBeenCalledTimes(1);
    await screen.findByText('Group has users');
    expect(screen.getByRole('dialog')).toBeInTheDocument();
    expect(notify.success).not.toHaveBeenCalled();
  });

  test('failure without a server message shows the generic count message', async () => {
    const user = userEvent.setup();
    vi.mocked(deleteUserGroup).mockRejectedValue(new Error('boom'));
    renderUserGroupsPage();
    await openRowDeleteModal(user);

    await user.click(screen.getByRole('button', { name: 'Yes, Delete' }));

    await screen.findByText('1 user group(s) could not be deleted.');
    expect(deleteUserGroup).toHaveBeenCalledWith(mockUserGroup.groupId);
    expect(screen.getByRole('dialog')).toBeInTheDocument();
  });

  // Cancel's own error reset isn't observable: every way of reopening also clears it.
  test('reopening Delete after a failed attempt shows no stale error', async () => {
    const user = userEvent.setup();
    vi.mocked(deleteUserGroup).mockRejectedValue(new Error('boom'));
    renderUserGroupsPage();
    await openRowDeleteModal(user);
    await user.click(screen.getByRole('button', { name: 'Yes, Delete' }));
    await screen.findByText('1 user group(s) could not be deleted.');

    await user.click(screen.getByRole('button', { name: 'Cancel' }));
    await waitFor(() => expect(screen.queryByRole('dialog')).not.toBeInTheDocument());
    await openRowDeleteModal(user);

    expect(screen.queryByText('1 user group(s) could not be deleted.')).not.toBeInTheDocument();
  });
});

// =============================================================================
// Tests — bulk delete
// =============================================================================

describe('User Groups page - bulk delete', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    mockFetchUserGroups(MULTIPLE_USER_GROUPS);
  });

  test('bulk Delete asks to delete the selected count', async () => {
    const user = userEvent.setup();
    renderUserGroupsPage();

    const dialog = await openBulkDeleteModal(user);

    expect(
      within(dialog).getByRole('heading', { name: 'Delete User Groups?' }),
    ).toBeInTheDocument();
    expect(within(dialog).getByText('2')).toBeInTheDocument();
  });

  test('confirming a bulk delete deletes every selected group and clears the selection', async () => {
    const user = userEvent.setup();
    vi.mocked(deleteUserGroup).mockResolvedValue(undefined);
    renderUserGroupsPage();
    await openBulkDeleteModal(user);

    await user.click(screen.getByRole('button', { name: 'Yes, Delete' }));

    await waitFor(() => expect(screen.queryByRole('dialog')).not.toBeInTheDocument());
    expect(deleteUserGroup).toHaveBeenCalledWith(10);
    expect(deleteUserGroup).toHaveBeenCalledWith(11);
    expect(notify.success).toHaveBeenCalledWith('2 user group(s) deleted successfully.');
    expect(screen.queryByRole('button', { name: /clear selection/i })).not.toBeInTheDocument();
  });

  test('partial failure keeps the modal open with a combined message and no toast', async () => {
    const user = userEvent.setup();
    // Promise.allSettled calls deletes in row order: 10 succeeds, 11 fails.
    vi.mocked(deleteUserGroup)
      .mockResolvedValueOnce(undefined)
      .mockRejectedValueOnce(apiError('Group has users', 409));
    renderUserGroupsPage();
    await openBulkDeleteModal(user);
    const callsBefore = vi.mocked(fetchUserGroups).mock.calls.length;

    await user.click(screen.getByRole('button', { name: 'Yes, Delete' }));

    await screen.findByText('1 user group(s) deleted successfully. Group has users');
    expect(deleteUserGroup).toHaveBeenCalledWith(10);
    expect(deleteUserGroup).toHaveBeenCalledWith(11);
    expect(screen.getByRole('dialog')).toBeInTheDocument();
    expect(notify.success).not.toHaveBeenCalled();
    await waitFor(() =>
      expect(vi.mocked(fetchUserGroups).mock.calls.length).toBeGreaterThan(callsBefore),
    );
  });

  test('rows selected on different pages are all included', async () => {
    const user = userEvent.setup();
    vi.mocked(deleteUserGroup).mockResolvedValue(undefined);
    // Branch needed: React Query may refetch, so call order isn't fixed — serve by page.
    vi.mocked(fetchUserGroups).mockImplementation(async (opts?: FetchUserGroupsRequest) =>
      opts?.start === 10
        ? { rows: [buildUserGroup({ groupId: 11, group: 'Viewers' })], totalCount: 11 }
        : { rows: [mockUserGroup], totalCount: 11 },
    );
    renderUserGroupsPage();

    await screen.findByText(mockUserGroup.group);
    await user.click(screen.getByRole('checkbox', { name: /select row/i }));
    await user.click(screen.getByRole('button', { name: /^next$/i }));
    await screen.findByText('Viewers');
    await user.click(screen.getByRole('checkbox', { name: /select row/i }));

    await user.click(await screen.findByRole('button', { name: /^delete$/i }));
    await screen.findByRole('heading', { name: 'Delete User Groups?' });
    await user.click(screen.getByRole('button', { name: 'Yes, Delete' }));

    await waitFor(() => expect(deleteUserGroup).toHaveBeenCalledTimes(2));
    expect(deleteUserGroup).toHaveBeenCalledWith(10);
    expect(deleteUserGroup).toHaveBeenCalledWith(11);
  });
});
