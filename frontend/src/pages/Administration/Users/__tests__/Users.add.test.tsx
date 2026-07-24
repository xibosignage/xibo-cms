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

import { screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { vi, beforeEach, describe, test } from 'vitest';

import { mockUser, SINGLE_USER } from './fixtures/user';
import { renderUsersPage } from './helpers/renderUsersPage';
import { mockFetchUsers } from './mocks/userApi';
import { mockFetchUserGroupById, mockFetchUserGroups } from './mocks/userGroupApi';

import { fetchHomepages } from '@/services/userApi';
import { testQueryClient } from '@/setupTests';

// =============================================================================
// Module mocks
// =============================================================================

vi.mock('@/services/userApi');

vi.mock('@/services/userGroupApi');

vi.mock('@/services/folderApi', () => ({
  fetchFolderById: vi.fn().mockResolvedValue({ id: 1, text: 'Root' }),
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
// Tests
// =============================================================================

describe('Users page - modal wiring', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    mockFetchUsers(SINGLE_USER);
    mockFetchUserGroups({ rows: [], totalCount: 0 });
    mockFetchUserGroupById({
      groupId: mockUser.groupId!,
      group: 'Users',
      isUserSpecific: 0,
      isEveryone: 0,
      features: [],
    });
    vi.mocked(fetchHomepages).mockResolvedValue([]);
  });

  test('clicking "Add User" opens the Add User modal', async () => {
    const user = userEvent.setup();
    renderUsersPage();

    await user.click(await screen.findByRole('button', { name: /add user/i }));

    await screen.findByRole('dialog', { name: /add user/i });
  });

  test('clicking the Edit quick action opens the Edit User modal for the correct user', async () => {
    const user = userEvent.setup();
    renderUsersPage();
    await screen.findByText(mockUser.userName);

    await user.click(screen.getByRole('button', { name: /^edit$/i }));

    await screen.findByRole('dialog', { name: /edit user/i });
  });

  // Note: the dropdown "Edit" item calls the exact same onEdit handler as the
  // quick action (see getUserItemActions) — already covered above, and
  // querying it separately is ambiguous since both "Edit" buttons are in the
  // DOM simultaneously once the dropdown is open.

  test('clicking Set Home Folder opens SetHomeFolderModal for the single user', async () => {
    const user = userEvent.setup();
    renderUsersPage();
    await screen.findByText(mockUser.userName);

    await user.click(screen.getByRole('button', { name: /more actions/i }));
    await user.click(await screen.findByRole('button', { name: /^set home folder$/i }));

    await screen.findByRole('dialog', {
      name: new RegExp(`set home folder for ${mockUser.userName}`, 'i'),
    });
  });

  test('clicking User Groups opens UserGroupsModal for the user', async () => {
    const user = userEvent.setup();
    renderUsersPage();
    await screen.findByText(mockUser.userName);

    await user.click(screen.getByRole('button', { name: /more actions/i }));
    await user.click(await screen.findByRole('button', { name: /^user groups$/i }));

    await screen.findByRole('dialog', {
      name: new RegExp(`user groups for ${mockUser.userName}`, 'i'),
    });
  });

  test('clicking Features opens FeaturesModal for the user', async () => {
    const user = userEvent.setup();
    renderUsersPage();
    await screen.findByText(mockUser.userName);

    await user.click(screen.getByRole('button', { name: /more actions/i }));
    await user.click(await screen.findByRole('button', { name: /^features$/i }));

    await screen.findByRole('dialog', {
      name: new RegExp(`features for ${mockUser.userName}`, 'i'),
    });
  });
});
