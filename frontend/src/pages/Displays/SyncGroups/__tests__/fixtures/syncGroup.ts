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

import type { FetchSyncGroupsResponse } from '@/services/syncGroupApi';
import type { SyncGroup, SyncGroupDisplay } from '@/types/syncGroup';
import type { User } from '@/types/user';

// -----------------------------------------------------------------------------
// Factory that produces a SyncGroup with safe minimal defaults.
// Only fields used in assertions carry meaningful values — everything else
// is set to a zero-ish value for its type so the component renders without
// errors.
//
// syncGroupId: 1     — asserted in row-action / delete tests
// name: 'Test Sync Group' — asserted in render / modal tests
// folderId / permissionsFolderId: 1 — required by SelectFolder stubs
// -----------------------------------------------------------------------------
export const buildSyncGroup = (overrides: Partial<SyncGroup> = {}): SyncGroup => ({
  syncGroupId: 1,
  name: 'Test Sync Group',
  createdDt: '2026-01-01 00:00:00',
  modifiedDt: null,
  modifiedBy: 0,
  modifiedByName: '',
  ownerId: 1,
  owner: 'Test Owner',
  syncPublisherPort: 9590,
  syncSwitchDelay: 750,
  syncVideoPauseDelay: 100,
  leadDisplayId: 0,
  leadDisplay: '',
  folderId: 1,
  permissionsFolderId: 1,
  userPermissions: { view: 1, edit: 1, delete: 1, modifyPermissions: 1 },
  ...overrides,
});

export const mockSyncGroup = buildSyncGroup();

export const SINGLE_SYNC_GROUP: FetchSyncGroupsResponse = {
  rows: [mockSyncGroup],
  totalCount: 1,
};

export const EMPTY_SYNC_GROUP_TABLE: FetchSyncGroupsResponse = {
  rows: [],
  totalCount: 0,
};

// Two-row fixture for bulk-action / delete tests.
export const MULTIPLE_SYNC_GROUPS: FetchSyncGroupsResponse = {
  rows: [
    buildSyncGroup({ syncGroupId: 1, name: 'Sync Group Alpha' }),
    buildSyncGroup({ syncGroupId: 2, name: 'Sync Group Beta' }),
  ],
  totalCount: 2,
};

// -----------------------------------------------------------------------------
// Member-display fixtures used by Add/Edit (Lead Display dropdown) and
// Manage Members (assigned-display panel).
// -----------------------------------------------------------------------------
export const buildSyncGroupDisplay = (
  overrides: Partial<SyncGroupDisplay> = {},
): SyncGroupDisplay => ({
  displayId: 100,
  display: 'Member Display',
  syncGroupId: 1,
  leadDisplayId: 0,
  displayGroupId: 100,
  layoutId: null,
  ...overrides,
});

export const MEMBERS_TWO: SyncGroupDisplay[] = [
  buildSyncGroupDisplay({ displayId: 100, display: 'Lobby Screen 1' }),
  buildSyncGroupDisplay({ displayId: 101, display: 'Lobby Screen 2' }),
];

export const MEMBERS_EMPTY: SyncGroupDisplay[] = [];

// -----------------------------------------------------------------------------
// Default logged-in user for sync group tests — includes folder.view so the
// folder sidebar / breadcrumb render in tests that need them.
// -----------------------------------------------------------------------------
export const mockUser: User = {
  userId: 1,
  userName: 'TestUser',
  userTypeId: 1,
  groupId: 1,
  features: {
    'folder.view': true,
    'display.syncView': true,
    'displays.view': true,
    'display.syncModify': true,
  },
  settings: {
    defaultTimezone: 'UTC',
    defaultLanguage: 'en',
    DATE_FORMAT_JS: 'DD/MM/YYYY',
    TIME_FORMAT_JS: 'HH:mm',
  },
};

// Variant without 'folder.view' — drives the no-permission branch in
// SyncGroups page rendering (sidebar + breadcrumb are conditionally rendered
// based on canViewFolders from usePermissions).
export const mockUserNoFolderView: User = {
  ...mockUser,
  features: { 'display.syncView': true, 'displays.view': true },
};

// Query keys that mirror what useTableState builds internally for the
// 'syncgroup_page' preference key. Pre-seeding this in the render helper
// skips the loading-pulse delay.
export const queryKeys = {
  syncGroupPage: ['userPref', 'syncgroup_page'] as const,
};
