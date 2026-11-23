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

import type { FetchPermissionsResponse, PermissionEntry } from '@/services/permissionsApi';
import type { FetchUsersResponse } from '@/services/userApi';
import { UserType, type User } from '@/types/user';

// -----------------------------------------------------------------------------
// Factory that produces a PermissionEntry (one row of the ShareModal table)
// with safe minimal defaults.
// Only fields ShareModal reads carry meaningful values — everything else is
// the zero value for its type.
//
// groupId: 10 — becomes the row id; the key in the saved groupIds payload
// group: 'Alice' — the Name cell, and how tests find the row
// isUserSpecific: 1 — drives the Type cell ("User" vs "Group")
// view / edit / delete: 0 — checkbox state; 1 = ticked, -1 = partly ticked
// -----------------------------------------------------------------------------
export const buildPermissionEntry = (
  overrides: Partial<PermissionEntry> = {},
): PermissionEntry => ({
  permissionId: 0,
  entityId: 0,
  groupId: 10,
  objectId: 0,
  isUser: null,
  entity: '',
  group: 'Alice',
  view: 0,
  edit: 0,
  delete: 0,
  isUserSpecific: 1,
  ...overrides,
});

// The populated table most ShareModal tests load. Several rows, so a test
// can't pass by acting on the wrong row.
// Alice: a user with View only. Editors: a group with View + Edit.
// Viewers: a group with nothing ticked.
export const permissionRows: PermissionEntry[] = [
  buildPermissionEntry({ groupId: 10, group: 'Alice', isUserSpecific: 1, view: 1 }),
  buildPermissionEntry({ groupId: 20, group: 'Editors', isUserSpecific: 0, view: 1, edit: 1 }),
  buildPermissionEntry({ groupId: 30, group: 'Viewers', isUserSpecific: 0 }),
];

// Same rows, but Alice's View is mixed (-1) — the state only a multi-item
// load returns, when the items disagree.
export const mixedPermissionRows: PermissionEntry[] = permissionRows.map((row) =>
  row.groupId === 10 ? { ...row, view: -1 } : row,
);

export const PERMISSION_TABLE: FetchPermissionsResponse = {
  rows: permissionRows,
  totalCount: permissionRows.length,
};

// totalCount > pageSize (10) so the pager has a next page.
export const PAGED_PERMISSION_TABLE: FetchPermissionsResponse = {
  rows: permissionRows,
  totalCount: 25,
};

// -----------------------------------------------------------------------------
// Owner options for ShareModal's "Select Owner" picker (fetchUsers rows).
// Only userId (option value / saved ownerId) and userName (option label) are
// read; userTypeId is required by the type.
// -----------------------------------------------------------------------------
export const buildOwner = (overrides: Partial<User> = {}): User => ({
  userId: 1,
  userName: 'admin',
  userTypeId: UserType.User,
  ...overrides,
});

export const mockOwner = buildOwner();
export const mockOwnerBob = buildOwner({ userId: 2, userName: 'Bob' });

export const OWNER_LIST: FetchUsersResponse = {
  rows: [mockOwner, mockOwnerBob],
  totalCount: 2,
};

export const EMPTY_OWNER_LIST: FetchUsersResponse = {
  rows: [],
  totalCount: 0,
};

// Exactly OWNER_PAGE_SIZE (10) rows — the only shape that makes ShareModal
// offer a second page of owners.
export const FULL_OWNER_PAGE: FetchUsersResponse = {
  rows: Array.from({ length: 10 }, (_, i) =>
    buildOwner({ userId: 100 + i, userName: `owner${i + 1}` }),
  ),
  totalCount: 12,
};
