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

import { buildUser, mockGroupAdmin, mockUser } from './fixtures/user';
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

const secondUser = buildUser({ userId: 4, userName: 'msmith' });

describe('Users page - bulk Set Home Folder', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    mockFetchUsers({ rows: [mockUser, secondUser], totalCount: 2 });
  });

  // ---------------------------------------------------------------------------
  // getBulkActions has no role check — the button must appear regardless of
  // who is logged in, unlike Tags' superAdmin-gated "Delete Selected".
  // ---------------------------------------------------------------------------
  test('selecting a row reveals the "Set Home Folder" bulk action button', async () => {
    const user = userEvent.setup();
    renderUsersPage();
    await screen.findByText(mockUser.userName);

    const checkboxes = screen.getAllByRole('checkbox', { name: /select row/i });
    await user.click(checkboxes[0]!);

    expect(await screen.findByRole('button', { name: /set home folder/i })).toBeEnabled();
  });

  // Proves the gate is by feature, not rank: a non-super-admin with the
  // feature added (it's not on by default) sees the button too.
  test('the bulk button is visible for a non-superAdmin user who has folder.userHome', async () => {
    const user = userEvent.setup();
    renderUsersPage(buildCurrentUser(mockGroupAdmin, { features: { 'folder.userHome': true } }));
    await screen.findByText(mockUser.userName);

    const checkboxes = screen.getAllByRole('checkbox', { name: /select row/i });
    await user.click(checkboxes[0]!);

    expect(await screen.findByRole('button', { name: /set home folder/i })).toBeEnabled();
  });

  // ---------------------------------------------------------------------------
  // UsersModals renders SetHomeFolderModal with itemsToSetHomeFolder when the
  // bulk button is used — must include every selected user, not just one.
  // ---------------------------------------------------------------------------
  test('clicking the bulk button opens SetHomeFolderModal with every selected user', async () => {
    const user = userEvent.setup();
    renderUsersPage();
    await screen.findByText(mockUser.userName);

    await user.click(screen.getAllByRole('checkbox', { name: /select row/i })[0]!);
    await user.click(screen.getAllByRole('checkbox', { name: /select row/i })[1]!);

    await user.click(await screen.findByRole('button', { name: /set home folder/i }));

    await screen.findByRole('dialog', { name: /set home folder for 2 users/i });
  });
});
