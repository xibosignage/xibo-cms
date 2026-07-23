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
import { vi, beforeEach, describe, test, expect } from 'vitest';

import { buildUser, mockSuperAdmin, mockGroupAdmin, mockUser, SINGLE_USER } from './fixtures/user';
import { renderUsersPage } from './helpers/renderUsersPage';
import { mockFetchUsers } from './mocks/userApi';

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

// useUsersFilterOptions is NOT mocked — getBaseFilterKeys(t) is synchronous, runs for real

// =============================================================================
// Part 1 — Column picker visibility
// =============================================================================

describe('Users page - column visibility', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    mockFetchUsers(SINGLE_USER);
  });

  // ---------------------------------------------------------------------------
  // "First Name" is off by default in the columnVisibility config in Users.tsx.
  // Before opening the column picker it must not appear as a column header.
  // ---------------------------------------------------------------------------
  test('"First Name" column header is not visible by default', async () => {
    renderUsersPage();

    await screen.findByText(mockUser.userName);

    expect(screen.queryByRole('columnheader', { name: /first name/i })).not.toBeInTheDocument();
  });

  test('clicking the Columns button opens the column picker dropdown', async () => {
    const user = userEvent.setup();
    renderUsersPage();
    await screen.findByText(mockUser.userName);

    await user.click(screen.getByRole('button', { name: /toggle columns/i }));

    expect(screen.getByRole('checkbox', { name: /first name/i })).toBeInTheDocument();
  });

  test('checking a hidden column checkbox shows that column in the table', async () => {
    const user = userEvent.setup();
    renderUsersPage();
    await screen.findByText(mockUser.userName);

    await user.click(screen.getByRole('button', { name: /toggle columns/i }));

    const checkbox = screen.getByRole('checkbox', { name: /first name/i });
    expect(checkbox).not.toBeChecked();

    await user.click(checkbox);

    expect(await screen.findByRole('columnheader', { name: /first name/i })).toBeInTheDocument();
  });

  // ---------------------------------------------------------------------------
  // "ID" (userId) is visible by default and hideable (no enableHiding: false).
  // Note: the primary "Username" column has enableHiding: false and is
  // deliberately excluded from the column picker — no checkbox exists for it.
  // ---------------------------------------------------------------------------
  test('unchecking a visible column checkbox hides that column from the table', async () => {
    const user = userEvent.setup();
    renderUsersPage();

    await screen.findByRole('columnheader', { name: /^id$/i });

    await user.click(screen.getByRole('button', { name: /toggle columns/i }));

    const idCheckbox = screen.getByRole('checkbox', { name: /^id$/i });
    expect(idCheckbox).toBeChecked();

    await user.click(idCheckbox);

    expect(screen.queryByRole('columnheader', { name: /^id$/i })).not.toBeInTheDocument();
  });
});

// =============================================================================
// Part 2 — Delete permission gate (isSelf)
//
// Edit / Set Home Folder / User Groups / Features have no permission gate in
// getUserItemActions — they are always present. Only Delete is conditional on
// `user.userId !== currentUser.userId`. Wiring for the always-present actions
// is covered in Users.add.test.tsx, not here.
// =============================================================================

describe('Users page — row actions (viewing another user)', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    // mockUser.userId (2) differs from mockSuperAdmin.userId (1) — not self.
    mockFetchUsers(SINGLE_USER);
  });

  test('Delete is visible in the more-actions dropdown', async () => {
    const user = userEvent.setup();
    renderUsersPage(mockSuperAdmin);

    await screen.findByText(mockUser.userName);
    await user.click(screen.getByRole('button', { name: /more actions/i }));

    // Anchor on a reliably-present action first to confirm the dropdown is open.
    await screen.findByRole('button', { name: /^user groups$/i });

    expect(screen.getByRole('button', { name: /^delete$/i })).toBeInTheDocument();
  });

  test('Edit quick action is visible', async () => {
    renderUsersPage(mockSuperAdmin);

    await screen.findByText(mockUser.userName);

    expect(screen.getByRole('button', { name: /^edit$/i })).toBeInTheDocument();
  });
});

describe('Users page — row actions (viewing self)', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    const selfRow = buildUser({ userId: mockSuperAdmin.userId, userName: 'admin' });
    mockFetchUsers({ rows: [selfRow], totalCount: 1 });
  });

  test('Delete is hidden in the more-actions dropdown', async () => {
    const user = userEvent.setup();
    renderUsersPage(mockSuperAdmin);

    await screen.findByText('admin');
    await user.click(screen.getByRole('button', { name: /more actions/i }));

    // Anchor on a reliably-present action first to confirm the dropdown is open.
    await screen.findByRole('button', { name: /^user groups$/i });

    expect(screen.queryByRole('button', { name: /^delete$/i })).not.toBeInTheDocument();
  });

  test('Edit quick action is still visible', async () => {
    renderUsersPage(mockSuperAdmin);

    await screen.findByText('admin');

    expect(screen.getByRole('button', { name: /^edit$/i })).toBeInTheDocument();
  });
});

// The block above proves this for a Super Admin; this proves the same
// "can't delete yourself" rule applies to any logged-in user, not just admins.
describe('Users page — row actions (viewing self, non-superAdmin)', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    const selfRow = buildUser({
      userId: mockGroupAdmin.userId,
      userName: 'groupadmin',
      userTypeId: mockGroupAdmin.userTypeId,
    });
    mockFetchUsers({ rows: [selfRow], totalCount: 1 });
  });

  test('Delete is hidden in the more-actions dropdown for a non-superAdmin viewing their own row', async () => {
    const user = userEvent.setup();
    renderUsersPage(mockGroupAdmin);

    await screen.findByText('groupadmin');
    await user.click(screen.getByRole('button', { name: /more actions/i }));

    // Anchor on a reliably-present action first to confirm the dropdown is open.
    await screen.findByRole('button', { name: /^user groups$/i });

    expect(screen.queryByRole('button', { name: /^delete$/i })).not.toBeInTheDocument();
  });
});
