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

import { screen, waitFor } from '@testing-library/react';
import userEvent, { type UserEvent } from '@testing-library/user-event';
import { vi, beforeEach, describe, test, expect } from 'vitest';

import { EMPTY_USER_GROUP_TABLE, mockUserGroup, SINGLE_USER_GROUP } from './fixtures/userGroup';
import { renderUserGroupsPage } from './helpers/renderUserGroupsPage';
import { mockFetchUserGroups } from './mocks/userGroupApi';

import { testQueryClient } from '@/setupTests';

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

// Page-level wiring only — form content is covered in modals/add-edit/.
vi.mock('../components/AddEditUserGroupModal', async () => ({
  default: (await import('./helpers/stubModal')).makeStubModal((mode) =>
    mode === 'edit' ? 'Edit User Group' : 'Add User Group',
  ),
}));

// =============================================================================
// Helpers
// =============================================================================

const openAddModal = async (user: UserEvent) => {
  await user.click(await screen.findByRole('button', { name: /add user group/i }));
  return screen.findByRole('dialog', { name: /add user group/i });
};

// =============================================================================
// Tests
// =============================================================================

describe('User Groups page - add user group', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    mockFetchUserGroups(EMPTY_USER_GROUP_TABLE);
  });

  test('clicking Add User Group opens the Add modal', async () => {
    const user = userEvent.setup();
    renderUserGroupsPage();

    expect(await openAddModal(user)).toBeInTheDocument();
  });

  test('table is refreshed after a successful add', async () => {
    const user = userEvent.setup();
    renderUserGroupsPage();
    await screen.findByText('No results found.');
    await openAddModal(user);

    // Queue the refetch response before saving.
    mockFetchUserGroups(SINGLE_USER_GROUP);
    await user.click(screen.getByRole('button', { name: 'Stub Save' }));

    await screen.findByText(mockUserGroup.group);
    expect(screen.queryByRole('dialog', { name: /add user group/i })).not.toBeInTheDocument();
  });

  test('closing the Add modal without saving does not refresh the table', async () => {
    const user = userEvent.setup();
    renderUserGroupsPage();
    await screen.findByText('No results found.');
    await openAddModal(user);
    const invalidate = vi.spyOn(testQueryClient, 'invalidateQueries');

    await user.click(screen.getByRole('button', { name: 'Stub Close' }));

    await waitFor(() => {
      expect(screen.queryByRole('dialog', { name: /add user group/i })).not.toBeInTheDocument();
    });
    expect(invalidate).not.toHaveBeenCalled();
    invalidate.mockRestore();
  });
});
