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

import { screen, within } from '@testing-library/react';
import userEvent, { type UserEvent } from '@testing-library/user-event';
import { vi, beforeEach, describe, test, expect } from 'vitest';

import { mockUserGroup, SINGLE_USER_GROUP } from './fixtures/userGroup';
import { renderUserGroupsPage } from './helpers/renderUserGroupsPage';
import { mockFetchUserGroups } from './mocks/userGroupApi';

import { testQueryClient } from '@/setupTests';
import { openViaQuickAction } from '@/testUtils/rowActions';

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
// Fixtures / helpers
// =============================================================================

const updatedGroup = { ...mockUserGroup, group: 'Editors - Edited' };

// Edit is both a quick action and a menu item (shared row-actions pattern); use the quick action.
const openEditModal = (user: UserEvent) =>
  openViaQuickAction(user, mockUserGroup.group, /^edit$/i, 'Edit User Group');

// =============================================================================
// Tests
// =============================================================================

describe('User Groups page - edit', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    mockFetchUserGroups(SINGLE_USER_GROUP);
  });

  test('clicking Edit opens the Edit modal for that row', async () => {
    const user = userEvent.setup();
    renderUserGroupsPage();

    const dialog = await openEditModal(user);

    expect(within(dialog).getByText(mockUserGroup.group)).toBeInTheDocument();
  });

  test('saving an edit refreshes the table and shows the updated row name', async () => {
    const user = userEvent.setup();
    renderUserGroupsPage();
    await openEditModal(user);

    // Persistent, so any extra refetch still serves the edited row.
    mockFetchUserGroups({ rows: [updatedGroup], totalCount: 1 });
    await user.click(screen.getByRole('button', { name: 'Stub Save' }));

    await screen.findByText(updatedGroup.group);
    expect(screen.queryByText(mockUserGroup.group)).not.toBeInTheDocument();
  });
});
