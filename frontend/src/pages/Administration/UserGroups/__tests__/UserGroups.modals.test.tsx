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
import userEvent, { type UserEvent } from '@testing-library/user-event';
import { vi, beforeEach, describe, test, expect } from 'vitest';

import { mockUserGroup, SINGLE_USER_GROUP } from './fixtures/userGroup';
import { renderUserGroupsPage } from './helpers/renderUserGroupsPage';
import { mockFetchUserGroups } from './mocks/userGroupApi';

import { fetchUserGroups } from '@/services/userGroupApi';
import { testQueryClient } from '@/setupTests';
import { openViaMenu } from '@/testUtils/rowActions';

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

// Page-level wiring only — each modal's content has its own test in modals/.
vi.mock('../components/CopyUserGroupModal', async () => ({
  default: (await import('./helpers/stubModal')).makeStubModal('Copy User Group'),
}));
vi.mock('../components/ManageMembersModal', async () => ({
  default: (await import('./helpers/stubModal')).makeStubModal('Manage Members'),
}));
vi.mock('../components/UserGroupFeaturesModal', async () => ({
  default: (await import('./helpers/stubModal')).makeStubModal('Features'),
}));

// =============================================================================
// Helpers
// =============================================================================

const ROW_ACTIONS = [
  { action: /^copy$/i, dialog: 'Copy User Group' },
  { action: /^members$/i, dialog: 'Manage Members' },
  { action: /^features$/i, dialog: 'Features' },
] as const;

const openFromMenu = (user: UserEvent, action: RegExp, dialog: string) =>
  openViaMenu(user, mockUserGroup.group, action, dialog);

// =============================================================================
// Tests
// =============================================================================

describe('User Groups page - Copy / Members / Features wiring', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    mockFetchUserGroups(SINGLE_USER_GROUP);
  });

  test.each(ROW_ACTIONS)('$dialog opens for the row', async ({ action, dialog }) => {
    const user = userEvent.setup();
    renderUserGroupsPage();

    const modal = await openFromMenu(user, action, dialog);

    expect(within(modal).getByText(mockUserGroup.group)).toBeInTheDocument();
  });

  test.each(ROW_ACTIONS)('saving in $dialog refreshes the table', async ({ action, dialog }) => {
    const user = userEvent.setup();
    renderUserGroupsPage();
    await openFromMenu(user, action, dialog);
    const callsBefore = vi.mocked(fetchUserGroups).mock.calls.length;

    await user.click(screen.getByRole('button', { name: 'Stub Save' }));

    await waitFor(() =>
      expect(screen.queryByRole('dialog', { name: dialog })).not.toBeInTheDocument(),
    );
    await waitFor(() =>
      expect(vi.mocked(fetchUserGroups).mock.calls.length).toBeGreaterThan(callsBefore),
    );
  });
});
