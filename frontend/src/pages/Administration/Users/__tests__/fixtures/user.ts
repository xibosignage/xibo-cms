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
import { buildCurrentUser, PERSONAS } from '@/testUtils/personas';
import type { Folder } from '@/types/folder';
import type { User } from '@/types/user';
import { UserType } from '@/types/user';
import type { UserGroup } from '@/types/userGroup';

// Factory for a row-level User with safe minimal defaults; unused fields are
// just zero-valued. userId: 2 differs from mockSuperAdmin's id (1) so
// Delete is visible by default (isSelf === false).
//
// userPermissions defaults to full access — mirrors PermissionFactory::
// getFullPermissions() in lib/Factory/PermissionFactory.php, i.e. what the
// server sends for a row the logged-in user (e.g. Super Admin) can fully
// manage. Tests proving a row is NOT editable/deletable must override this.
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
  userPermissions: { view: 1, edit: 1, delete: 1, modifyPermissions: 1 },
  ...overrides,
});

export const mockUser = buildUser();

export const SINGLE_USER: FetchUsersResponse = { rows: [mockUser], totalCount: 1 };
export const EMPTY_USER_TABLE: FetchUsersResponse = { rows: [], totalCount: 0 };

// Shared display settings, passed explicitly since settings aren't part of
// a persona.
const usersSuiteSettings = {
  defaultTimezone: 'UTC',
  defaultLanguage: 'en',
  DATE_FORMAT_JS: 'DD/MM/YYYY',
  TIME_FORMAT_JS: 'HH:mm',
};

// The default logged-in user for Users page tests — a Super Admin, so the
// Folder Permission and Notifications tabs show up in AddEditUserModal.
export const mockSuperAdmin: User = buildCurrentUser(PERSONAS.superAdmin, {
  userId: 1,
  userName: 'admin',
  groupId: 1,
  settings: usersSuiteSettings,
});

// Non-superAdmin logged-in user — Notifications tab hidden, isSuperAdmin-gated
// update fields absent from payloads, "Delete all content" checkbox shown.
export const mockGroupAdmin: User = buildCurrentUser(PERSONAS.groupAdmin, {
  userId: 3,
  userName: 'groupadmin',
  groupId: 1,
  settings: usersSuiteSettings,
});

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
