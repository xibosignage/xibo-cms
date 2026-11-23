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

// The Users page as a group admin. Add User is gated on the Add users feature;
// a super admin passes every feature check, so the other Users tests can't tell
// whether the gate is there.

import { screen } from '@testing-library/react';
import { beforeEach, describe, expect, test, vi } from 'vitest';

import { mockGroupAdmin, mockUser, SINGLE_USER } from './fixtures/user';
import { renderUsersPage } from './helpers/renderUsersPage';
import { mockFetchUsers } from './mocks/userApi';

import { testQueryClient } from '@/setupTests';
import { buildCurrentUser } from '@/testUtils/personas';

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
// Users
// =============================================================================

// mockGroupAdmin is the group admin persona, which ships with Add users on.
const groupAdminWithoutAdd = buildCurrentUser(mockGroupAdmin, {
  features: { 'users.add': false },
});

// =============================================================================
// Tests
// =============================================================================

describe('Users page - a group admin', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    mockFetchUsers(SINGLE_USER);
  });

  test('a group admin without Add users gets no Add User button', async () => {
    renderUsersPage(groupAdminWithoutAdd);

    // Wait for the row, so "absent" can't pass because the page isn't drawn yet.
    await screen.findByText(mockUser.userName);
    expect(screen.queryByRole('button', { name: /add user/i })).not.toBeInTheDocument();
  });

  test('a group admin with Add users gets the Add User button', async () => {
    renderUsersPage(mockGroupAdmin);

    await screen.findByText(mockUser.userName);
    expect(screen.getByRole('button', { name: /add user/i })).toBeInTheDocument();
  });
});
