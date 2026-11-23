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

import type { FetchUserGroupsResponse } from '@/services/userGroupApi';
import { buildCurrentUser, PERSONAS } from '@/testUtils/personas';
import type { User } from '@/types/user';
import { UserType } from '@/types/user';
import type { UserGroup } from '@/types/userGroup';

// -----------------------------------------------------------------------------
// Factory that produces a UserGroup with safe minimal defaults.
// Only fields used in assertions carry meaningful values — everything else
// is set to the zero value for its type so the component renders without errors.
//
// groupId: 10 — asserted in delete / copy / members / features API calls
// group: 'Editors' — asserted in render, row-action and modal tests
// description: 'Content editors' — asserted in render tests
// userPermissions: full — row actions are gated on userPermissions.edit;
//   gating tests override this with { edit: 0 }
// -----------------------------------------------------------------------------
export const buildUserGroup = (overrides: Partial<UserGroup> = {}): UserGroup => ({
  groupId: 10,
  group: 'Editors',
  isUserSpecific: 0,
  isEveryone: 0,
  description: 'Content editors',
  defaultHomepageId: '',
  isShownForAddUser: 0,
  libraryQuota: 0,
  libraryQuotaFormatted: '',
  isSystemNotification: 0,
  isDisplayNotification: 0,
  isDataSetNotification: 0,
  isLayoutNotification: 0,
  isLibraryNotification: 0,
  isReportNotification: 0,
  isScheduleNotification: 0,
  isCustomNotification: 0,
  features: [],
  users: [],
  userPermissions: { view: 1, edit: 1, delete: 1, modifyPermissions: 1 },
  ...overrides,
});

export const mockUserGroup = buildUserGroup();

export const SINGLE_USER_GROUP: FetchUserGroupsResponse = {
  rows: [mockUserGroup],
  totalCount: 1,
};

export const EMPTY_USER_GROUP_TABLE: FetchUserGroupsResponse = {
  rows: [],
  totalCount: 0,
};

// Two-row fixture for bulk delete / partial-failure tests.
export const MULTIPLE_USER_GROUPS: FetchUserGroupsResponse = {
  rows: [
    buildUserGroup({ groupId: 10, group: 'Editors' }),
    buildUserGroup({ groupId: 11, group: 'Viewers', description: '' }),
  ],
  totalCount: 2,
};

// Every editable field set to a non-default value, for Edit pre-population
// and Library Quota formatting tests. 1048576 KB = 1 GiB.
export const mockPopulatedUserGroup = buildUserGroup({
  groupId: 12,
  group: 'Ops',
  description: 'Operations team',
  libraryQuota: 1048576,
  isShownForAddUser: 1,
  defaultHomepageId: 'statusdashboard.view',
  isSystemNotification: 1,
  isDisplayNotification: 1,
  isScheduleNotification: 1,
});

// -----------------------------------------------------------------------------
// Logged-in users. Row actions depend on isSuperAdmin and the
// 'usergroup.modify' feature, so tests need all three combinations.
// -----------------------------------------------------------------------------
const settings = {
  defaultTimezone: 'UTC',
  defaultLanguage: 'en',
  DATE_FORMAT_JS: 'DD/MM/YYYY',
  TIME_FORMAT_JS: 'HH:mm',
};

// Default logged-in user — super admin, sees every action and the Notifications tab.
export const mockUser: User = buildCurrentUser(PERSONAS.superAdmin, {
  userId: 1,
  userName: 'admin',
  settings,
});

// Group admin without 'usergroup.modify' (as shipped in group_admin.json) — no row actions.
export const mockGroupAdmin: User = buildCurrentUser(PERSONAS.groupAdmin, {
  userId: 3,
  userName: 'groupadmin',
  settings,
});

// Group admin with 'usergroup.modify' — Edit / Copy / Members on editable rows only.
export const mockGroupAdminWithModify: User = buildCurrentUser(PERSONAS.groupAdmin, {
  userId: 3,
  userName: 'groupadmin',
  settings,
  features: { 'usergroup.modify': true },
});

// A group member as returned by the users list — only the fields Manage Members reads.
export const buildMember = (overrides: Partial<User> = {}): User => ({
  userId: 2,
  userName: 'alice',
  userTypeId: UserType.User,
  email: '',
  ...overrides,
});

// Query keys that mirror what useTableState builds internally for 'user_groups_page'.
// Centralised here so a key change only needs one update.
export const queryKeys = {
  userGroupsPage: ['userPref', 'user_groups_page'] as const,
};
