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
import userEvent from '@testing-library/user-event';
import { vi, beforeEach, describe, test, expect } from 'vitest';

import { buildUser, mockUser, mockUserGroup, SINGLE_USER } from './fixtures/user';
import { renderUsersPage } from './helpers/renderUsersPage';
import { setupAddEditUserModalMocks } from './mocks/addEditUserModal';
import { mockFetchUsers } from './mocks/userApi';
import { mockFetchUserGroupById } from './mocks/userGroupApi';

import { notify } from '@/components/ui/Notification';
import { createUser, fetchHomepages } from '@/services/userApi';
import { testQueryClient } from '@/setupTests';
import { UserType } from '@/types/user';

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

vi.mock('@/components/ui/Notification', () => ({
  notify: { success: vi.fn(), error: vi.fn(), info: vi.fn(), warning: vi.fn() },
}));

vi.mock('@/components/ui/modals/Modal');

// =============================================================================
// Tests
// =============================================================================

describe('Users page - adding a user through the real modal', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    setupAddEditUserModalMocks();
    mockFetchUsers(SINGLE_USER);
    mockFetchUserGroupById({
      groupId: mockUser.groupId!,
      group: 'Users',
      isUserSpecific: 0,
      isEveryone: 0,
      features: [],
    });
    vi.mocked(fetchHomepages).mockResolvedValue([
      { homepage: 'icondashboard.view', title: 'Icon Dashboard' },
    ]);
  });

  test(
    'adding a user from the page sends the entered details, confirms the save and lists the user',
    { tags: ['smoke'] },
    async () => {
      const user = userEvent.setup();
      const newUser = buildUser({ userId: 5, userName: 'newuser', email: 'new@example.com' });
      // Once the user is created the grid's refresh returns them alongside the existing one.
      vi.mocked(createUser).mockImplementation(async () => {
        mockFetchUsers({ rows: [mockUser, newUser], totalCount: 2 });
        return newUser;
      });
      renderUsersPage();
      await screen.findByText(mockUser.userName);

      await user.click(screen.getByRole('button', { name: /^add user$/i }));
      const dialog = await screen.findByRole('dialog', { name: /^add user$/i });
      await user.type(within(dialog).getByRole('textbox', { name: /^username$/i }), 'newuser');
      await user.type(within(dialog).getByRole('textbox', { name: /^email/i }), 'new@example.com');
      await user.type(within(dialog).getByLabelText(/^password$/i), 'Password1!');
      await user.type(within(dialog).getByRole('textbox', { name: /^first name/i }), 'Nora');
      await user.click(within(dialog).getByRole('combobox', { name: /initial user group/i }));
      await user.click(await screen.findByRole('option', { name: mockUserGroup.group }));
      await user.click(within(dialog).getByRole('combobox', { name: /^homepage$/i }));
      await user.click(await screen.findByRole('option', { name: /icon dashboard/i }));
      await user.click(within(dialog).getByRole('button', { name: /create user/i }));

      await waitFor(() => expect(createUser).toHaveBeenCalledTimes(1));
      expect(createUser).toHaveBeenCalledWith({
        userName: 'newuser',
        password: 'Password1!',
        email: 'new@example.com',
        userTypeId: UserType.User,
        groupId: mockUserGroup.groupId,
        homePageId: 'icondashboard.view',
        firstName: 'Nora',
      });
      await waitFor(() => expect(notify.success).toHaveBeenCalledWith('User saved'));
      await screen.findByText('newuser');
      expect(screen.queryByRole('dialog', { name: /^add user$/i })).not.toBeInTheDocument();
    },
  );
});
