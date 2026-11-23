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

import { buildUserGroup, mockUserGroup, SINGLE_USER_GROUP } from './fixtures/userGroup';
import { renderUserGroupsPage } from './helpers/renderUserGroupsPage';
import { mockFetchUserGroups } from './mocks/userGroupApi';

import { createUserGroup } from '@/services/userGroupApi';
import { testQueryClient } from '@/setupTests';

// =============================================================================
// Module mocks
// =============================================================================

// The Add User Group modal is NOT stubbed here: this file drives the real form from the page.
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

describe('User Groups page - adding a user group through the real modal', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    mockFetchUserGroups(SINGLE_USER_GROUP);
  });

  test(
    'adding a user group from the page sends the entered details, then lists the new group',
    { tags: ['smoke'] },
    async () => {
      const user = userEvent.setup();
      const newGroup = buildUserGroup({ groupId: 11, group: 'QA Team', description: 'Testers' });
      // Once the group is created the grid's refresh returns it alongside the existing one.
      vi.mocked(createUserGroup).mockImplementation(async () => {
        mockFetchUserGroups({ rows: [mockUserGroup, newGroup], totalCount: 2 });
        return newGroup;
      });
      renderUserGroupsPage();
      await screen.findByText(mockUserGroup.group);

      await user.click(screen.getByRole('button', { name: /add user group/i }));
      const dialog = await screen.findByRole('dialog', { name: /add user group/i });
      await user.type(within(dialog).getByRole('textbox', { name: /^name$/i }), 'QA Team');
      await user.type(within(dialog).getByRole('textbox', { name: /^description/i }), 'Testers');
      await user.click(
        within(dialog).getByRole('checkbox', { name: /show when onboarding a new user/i }),
      );
      await user.click(within(dialog).getByRole('combobox', { name: /default homepage/i }));
      await user.click(await screen.findByRole('option', { name: 'Status Dashboard' }));
      await user.click(within(dialog).getByRole('button', { name: 'Create Group' }));

      await waitFor(() => expect(createUserGroup).toHaveBeenCalledTimes(1));
      expect(createUserGroup).toHaveBeenCalledWith({
        group: 'QA Team',
        description: 'Testers',
        libraryQuota: 0,
        isShownForAddUser: 1,
        defaultHomepageId: 'statusdashboard.view',
        isSystemNotification: 0,
        isDisplayNotification: 0,
        isDataSetNotification: 0,
        isLayoutNotification: 0,
        isLibraryNotification: 0,
        isReportNotification: 0,
        isScheduleNotification: 0,
        isCustomNotification: 0,
      });
      await screen.findByText('QA Team');
      expect(screen.queryByRole('dialog', { name: /add user group/i })).not.toBeInTheDocument();
    },
  );
});
