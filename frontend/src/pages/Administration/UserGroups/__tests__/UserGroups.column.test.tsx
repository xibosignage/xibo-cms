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

import { screen } from '@testing-library/react';
import userEvent, { type UserEvent } from '@testing-library/user-event';
import { vi, beforeEach, describe, test, expect } from 'vitest';

import {
  buildUserGroup,
  mockGroupAdmin,
  mockGroupAdminWithModify,
  mockUser,
  mockUserGroup,
  MULTIPLE_USER_GROUPS,
  SINGLE_USER_GROUP,
} from './fixtures/userGroup';
import { renderUserGroupsPage } from './helpers/renderUserGroupsPage';
import { mockFetchUserGroups } from './mocks/userGroupApi';

import { testQueryClient } from '@/setupTests';
import { knownFailure } from '@/testUtils/knownFailure';
import { openRowMenu, queryMenuItems } from '@/testUtils/rowActions';

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
// Helpers
// =============================================================================

const openMoreActions = async (user: UserEvent) => {
  await openRowMenu(user, mockUserGroup.group);
  return screen.getByRole('group', { name: /^row actions$/i });
};

// =============================================================================
// Part 1 — Column picker
// =============================================================================

describe('User Groups page - column visibility', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    mockFetchUserGroups(SINGLE_USER_GROUP);
  });

  test('default columns are visible and notification columns are hidden', async () => {
    renderUserGroupsPage();
    await screen.findByText(mockUserGroup.group);

    for (const name of [
      /^id$/i,
      /^name$/i,
      /^description$/i,
      /library quota/i,
      /shown for add user/i,
    ]) {
      expect(screen.getByRole('columnheader', { name })).toBeInTheDocument();
    }
    expect(
      screen.queryByRole('columnheader', { name: /system notifications/i }),
    ).not.toBeInTheDocument();
    expect(
      screen.queryByRole('columnheader', { name: /custom notifications/i }),
    ).not.toBeInTheDocument();
  });

  test('clicking the Columns button opens the column picker dropdown', async () => {
    const user = userEvent.setup();
    renderUserGroupsPage();
    await screen.findByText(mockUserGroup.group);

    await user.click(screen.getByRole('button', { name: /toggle columns/i }));

    expect(screen.getByRole('checkbox', { name: /system notifications/i })).toBeInTheDocument();
  });

  test('checking a hidden column checkbox shows that column in the table', async () => {
    const user = userEvent.setup();
    renderUserGroupsPage();
    await screen.findByText(mockUserGroup.group);

    await user.click(screen.getByRole('button', { name: /toggle columns/i }));
    const checkbox = screen.getByRole('checkbox', { name: /system notifications/i });
    expect(checkbox).not.toBeChecked();

    await user.click(checkbox);

    await screen.findByRole('columnheader', { name: /system notifications/i });
  });

  test('unchecking a visible column checkbox hides that column from the table', async () => {
    const user = userEvent.setup();
    renderUserGroupsPage();
    await screen.findByRole('columnheader', { name: /library quota/i });

    await user.click(screen.getByRole('button', { name: /toggle columns/i }));
    const checkbox = screen.getByRole('checkbox', { name: /library quota/i });
    expect(checkbox).toBeChecked();

    await user.click(checkbox);

    expect(screen.queryByRole('columnheader', { name: /library quota/i })).not.toBeInTheDocument();
  });

  // Name has enableHiding: false, so the picker offers no toggle for it.
  test('the Name column cannot be hidden', async () => {
    const user = userEvent.setup();
    renderUserGroupsPage();
    await screen.findByText(mockUserGroup.group);

    await user.click(screen.getByRole('button', { name: /toggle columns/i }));
    await screen.findByRole('checkbox', { name: /^description$/i });

    expect(screen.queryByRole('checkbox', { name: /^name$/i })).not.toBeInTheDocument();
  });
});

// =============================================================================
// Part 2 — Permission-gated row actions
// =============================================================================

describe('User Groups page — row actions (super admin)', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    mockFetchUserGroups(SINGLE_USER_GROUP);
  });

  test('Edit quick action is visible', async () => {
    renderUserGroupsPage({ user: mockUser });
    await screen.findByText(mockUserGroup.group);

    expect(screen.getByRole('button', { name: /^edit$/i })).toBeInTheDocument();
  });

  test('Copy, Members, Features and Delete are in the more-actions dropdown', async () => {
    const user = userEvent.setup();
    renderUserGroupsPage({ user: mockUser });
    await openMoreActions(user);

    expect(screen.getByRole('button', { name: /^copy$/i })).toBeInTheDocument();
    expect(screen.getByRole('button', { name: /^members$/i })).toBeInTheDocument();
    expect(screen.getByRole('button', { name: /^features$/i })).toBeInTheDocument();
    expect(screen.getByRole('button', { name: /^delete$/i })).toBeInTheDocument();
  });

  // Shared row-actions pattern: Edit is both a quick action and a menu item.
  test('Edit appears both as a quick action and in the dropdown', async () => {
    const user = userEvent.setup();
    renderUserGroupsPage({ user: mockUser });
    await openMoreActions(user);

    expect(screen.getAllByRole('button', { name: /^edit$/i })).toHaveLength(2);
  });
});

describe('User Groups page — row actions (group admin with usergroup.modify, editable row)', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    mockFetchUserGroups(SINGLE_USER_GROUP);
  });

  test('Edit quick action is visible', async () => {
    renderUserGroupsPage({ user: mockGroupAdminWithModify });
    await screen.findByText(mockUserGroup.group);

    expect(screen.getByRole('button', { name: /^edit$/i })).toBeInTheDocument();
  });

  test('Members is visible; Features and Delete are hidden', async () => {
    const user = userEvent.setup();
    renderUserGroupsPage({ user: mockGroupAdminWithModify });
    await openMoreActions(user);

    expect(screen.getByRole('button', { name: /^members$/i })).toBeInTheDocument();
    expect(queryMenuItems(/^features$/i)).toHaveLength(0);
    expect(queryMenuItems(/^delete$/i)).toHaveLength(0);
  });
});

describe('User Groups page — row actions (group admin with usergroup.modify, non-editable row)', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    mockFetchUserGroups({
      rows: [buildUserGroup({ userPermissions: { view: 1, edit: 0, delete: 0 } })],
      totalCount: 1,
    });
  });

  test('no row actions are offered', async () => {
    renderUserGroupsPage({ user: mockGroupAdminWithModify });
    await screen.findByText(mockUserGroup.group);

    expect(screen.queryByRole('button', { name: /^edit$/i })).not.toBeInTheDocument();
    expect(screen.queryByRole('button', { name: /more actions/i })).not.toBeInTheDocument();
  });
});

describe('User Groups page — row actions (group admin without usergroup.modify)', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    mockFetchUserGroups(SINGLE_USER_GROUP);
  });

  test('no row actions are offered even on an editable row', async () => {
    renderUserGroupsPage({ user: mockGroupAdmin });
    await screen.findByText(mockUserGroup.group);

    expect(screen.queryByRole('button', { name: /^edit$/i })).not.toBeInTheDocument();
    expect(screen.queryByRole('button', { name: /more actions/i })).not.toBeInTheDocument();
  });
});

// =============================================================================
// Part 2 — Bulk actions
// =============================================================================

describe('User Groups page — bulk actions', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    mockFetchUserGroups(MULTIPLE_USER_GROUPS);
  });

  const selectFirstRow = async (user: UserEvent) => {
    await screen.findByText('Editors');
    await user.click(screen.getAllByRole('checkbox', { name: /select row/i })[0]!);
    // Selection bar shows "1 Selected".
    return screen.findByRole('button', { name: /clear selection/i });
  };

  test('bulk Delete appears for a super admin after row selection', async () => {
    const user = userEvent.setup();
    renderUserGroupsPage({ user: mockUser });

    await selectFirstRow(user);

    // Row menus are closed, so the only Delete button is the bulk action.
    expect(screen.getByRole('button', { name: /^delete$/i })).toBeInTheDocument();
  });

  // Known issue: xibosignage/xibo#4121
  describe('Pinned until https://github.com/xibosignage/xibo/issues/4121 is fixed', () => {
    // getBulkActions has no permission gate, unlike the row-menu Delete.
    knownFailure(
      'bulk Delete is not offered to a group admin without delete rights',
      async () => {
        const user = userEvent.setup();
        renderUserGroupsPage({ user: mockGroupAdmin });
        // Control: a row is selected, so bulk actions would show.
        await selectFirstRow(user);
      },
      () => expect(screen.queryByRole('button', { name: /^delete$/i })).not.toBeInTheDocument(),
    );
  });

  // Known issue: xibosignage/xibo#4130
  describe('Pinned until https://github.com/xibosignage/xibo/issues/4130 is fixed', () => {
    // The button's aria-label replaces its visible "N Selected" text.
    knownFailure(
      'the selection button is announced with the selected count',
      async () => {
        const user = userEvent.setup();
        renderUserGroupsPage({ user: mockUser });
        await selectFirstRow(user);
        // Control: the count is shown on screen.
        await screen.findByText(/1 selected/i);
      },
      () => expect(screen.getByRole('button', { name: /1 selected/i })).toBeInTheDocument(),
    );
  });
});
