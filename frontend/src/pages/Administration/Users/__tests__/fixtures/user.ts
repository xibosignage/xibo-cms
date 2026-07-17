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

import type { FetchUsersResponse } from '@/services/userApi';
import type { Folder } from '@/types/folder';
import type { User } from '@/types/user';
import { UserType } from '@/types/user';
import type { UserGroup } from '@/types/userGroup';

// -----------------------------------------------------------------------------
// Factory that produces a row-level User with safe minimal defaults.
// Only fields used in assertions carry meaningful values — everything else
// is set to the zero value for its type so the component renders without errors.
//
// userId: 2 — deliberately differs from mockCurrentUser's id (1) so the
//   Delete row action is visible by default (isSelf === false)
// userName: 'jbloggs' — asserted in render, filter, and modal tests
// userTypeId: UserType.User — drives the "User Type" column label
// groupId: 2 — used by UserGroupsModal / FeaturesModal fetches
// homeFolderId: 1 — required by SetHomeFolderModal / FolderPermissionTab
// -----------------------------------------------------------------------------
export const buildUser = (overrides: Partial<User> = {}): User => ({
  userId: 2,
  userName: 'jbloggs',
  userTypeId: UserType.User,
  email: '',
  homePageId: '',
  homeFolderId: 1,
  groupId: 2,
  retired: 0,
  loggedIn: 0,
  libraryQuota: 0,
  libraryQuotaFormatted: '0 KiB',
  twoFactorDescription: '',
  firstName: '',
  lastName: '',
  phone: '',
  ref1: '',
  ref2: '',
  ref3: '',
  ref4: '',
  ref5: '',
  isPasswordChangeRequired: 0,
  newUserWizard: 0,
  features: {},
  groups: [],
  ...overrides,
});

export const mockUser = buildUser();

export const SINGLE_USER: FetchUsersResponse = { rows: [mockUser], totalCount: 1 };
export const EMPTY_USER_TABLE: FetchUsersResponse = { rows: [], totalCount: 0 };

// The default logged-in user for Users page tests. superAdmin + folder.view
// so the Folder Permission and Notifications tabs are visible in
// AddEditUserModal by default.
export const mockCurrentUser: User = {
  userId: 1,
  userName: 'admin',
  userTypeId: UserType.SuperAdmin,
  groupId: 1,
  features: { 'folder.view': true },
  settings: {
    defaultTimezone: 'UTC',
    defaultLanguage: 'en',
    DATE_FORMAT_JS: 'DD/MM/YYYY',
    TIME_FORMAT_JS: 'HH:mm',
  },
};

// Non-superAdmin logged-in user — Notifications tab hidden, isSuperAdmin-gated
// update fields absent from payloads, "Delete all content" checkbox shown.
export const mockNonAdminCurrentUser: User = {
  ...mockCurrentUser,
  userId: 3,
  userName: 'groupadmin',
  userTypeId: UserType.GroupAdmin,
  features: {},
};

export const mockUserGroup: UserGroup = {
  groupId: 2,
  group: 'Users',
  isUserSpecific: 0,
  isEveryone: 0,
  isShownForAddUser: 1,
  features: [],
};

// Root's permission checkboxes are always disabled (see FolderPermissionTree) —
// "Marketing" is the child folder tests grant/revoke permissions on.
export const mockFolderTree: Folder[] = [
  {
    id: 1,
    type: 'root',
    text: 'Root',
    parentId: 0,
    isRoot: 1,
    children: [
      {
        id: 2,
        type: '',
        text: 'Marketing',
        parentId: 1,
        isRoot: 0,
        children: [],
        ownerId: 1,
        ownerName: 'admin',
        createdDt: null,
        modifiedDt: null,
      },
    ],
    ownerId: 1,
    ownerName: 'admin',
    createdDt: null,
    modifiedDt: null,
  },
];

// Query key that mirrors what useTableState builds internally for 'users_page'.
// Centralised here so a key change only requires one update.
export const queryKeys = {
  usersPage: ['userPref', 'users_page'] as const,
};
