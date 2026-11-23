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
import { vi, beforeEach, describe, test, expect } from 'vitest';

import {
  buildUserGroup,
  EMPTY_USER_GROUP_TABLE,
  mockGroupAdminWithModify,
  mockPopulatedUserGroup,
  mockUserGroup,
  SINGLE_USER_GROUP,
} from './fixtures/userGroup';
import { renderUserGroupsPage } from './helpers/renderUserGroupsPage';
import { mockFetchUserGroups } from './mocks/userGroupApi';

import { fetchUserPreference } from '@/services/userApi';
import { fetchUserGroups } from '@/services/userGroupApi';
import { testQueryClient } from '@/setupTests';
import { deferred } from '@/testUtils/deferred';
import { knownFailure } from '@/testUtils/knownFailure';

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

// =============================================================================
// Tests
// =============================================================================

describe('User Groups page - render', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    vi.mocked(fetchUserPreference).mockResolvedValue(null);
  });

  test('Add User Group button is present for a super admin', async () => {
    mockFetchUserGroups(SINGLE_USER_GROUP);
    renderUserGroupsPage();

    await screen.findByRole('button', { name: /add user group/i });
  });

  test('Add User Group button is hidden for a non-super-admin', async () => {
    mockFetchUserGroups(SINGLE_USER_GROUP);
    renderUserGroupsPage({ user: mockGroupAdminWithModify });

    await screen.findByText(mockUserGroup.group);

    expect(screen.queryByRole('button', { name: /add user group/i })).not.toBeInTheDocument();
  });

  test(
    'table shows the user group name and description once data has loaded',
    { tags: ['smoke'] },
    async () => {
      mockFetchUserGroups(SINGLE_USER_GROUP);
      renderUserGroupsPage();

      await screen.findByText(mockUserGroup.group);
      expect(screen.getByText(mockUserGroup.description!)).toBeInTheDocument();
    },
  );

  test('shows "No results found." when there are no user groups', async () => {
    mockFetchUserGroups(EMPTY_USER_GROUP_TABLE);
    renderUserGroupsPage();

    await screen.findByRole('button', { name: /add user group/i });
    await screen.findByText('No results found.');
  });

  test('shows the error message when loading fails', async () => {
    vi.mocked(fetchUserGroups).mockRejectedValue(new Error('Server Error'));
    renderUserGroupsPage();

    await screen.findByText('Server Error');
    expect(fetchUserGroups).toHaveBeenCalled();
  });

  test('first request asks for the first 10 user groups', async () => {
    mockFetchUserGroups(SINGLE_USER_GROUP);
    renderUserGroupsPage();

    await screen.findByText(mockUserGroup.group);

    expect(fetchUserGroups).toHaveBeenCalledWith(expect.objectContaining({ start: 0, length: 10 }));
  });

  test('shows the preferences placeholder and does not fetch before hydration', async () => {
    const prefs = deferred<null>();
    vi.mocked(fetchUserPreference).mockReturnValue(prefs.promise);
    mockFetchUserGroups(SINGLE_USER_GROUP);
    renderUserGroupsPage({ hydrate: false });

    await screen.findByText('Loading your preferences...');
    expect(fetchUserGroups).not.toHaveBeenCalled();

    // Let hydration finish so nothing is left pending at teardown.
    prefs.resolve(null);
    await screen.findByText(mockUserGroup.group);
  });

  test('Library Quota shows "Unlimited" for 0 and a formatted size otherwise', async () => {
    mockFetchUserGroups({ rows: [mockUserGroup, mockPopulatedUserGroup], totalCount: 2 });
    renderUserGroupsPage();

    const editorsRow = (await screen.findByText(mockUserGroup.group)).closest('tr')!;
    const opsRow = screen.getByText(mockPopulatedUserGroup.group).closest('tr')!;

    expect(within(editorsRow).getByText('Unlimited')).toBeInTheDocument();
    expect(within(opsRow).getByText('1 GiB')).toBeInTheDocument();
  });
});

describe('User Groups page - known accessibility issues', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    vi.mocked(fetchUserPreference).mockResolvedValue(null);
  });

  // Known issue: xibosignage/xibo#4126
  describe('Pinned until https://github.com/xibosignage/xibo/issues/4126 is fixed', () => {
    // The search input has only a placeholder.
    knownFailure(
      'the search box is announced by its name',
      async () => {
        mockFetchUserGroups(SINGLE_USER_GROUP);
        renderUserGroupsPage();
        // Control: the search box is there.
        await screen.findByPlaceholderText('Search user groups...');
      },
      () =>
        expect(screen.getByRole('textbox', { name: /search user groups/i })).toBeInTheDocument(),
    );
  });

  // Known issue: xibosignage/xibo#4128
  describe('Pinned until https://github.com/xibosignage/xibo/issues/4128 is fixed', () => {
    // CheckMarkCell draws an icon with no text alternative.
    knownFailure(
      'a tick cell is announced as on or off',
      async () => {
        mockFetchUserGroups({
          rows: [buildUserGroup({ groupId: 20, group: 'Shown', isShownForAddUser: 1 })],
          totalCount: 1,
        });
        renderUserGroupsPage();
        // Control: the row is rendered.
        return (await screen.findByText('Shown')).closest('tr')!;
      },
      (row) =>
        expect(
          within(row).getByRole('img', { name: /^(yes|no|on|off|enabled|disabled)$/i }),
        ).toBeInTheDocument(),
    );
  });
});
