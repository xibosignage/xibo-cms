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

import type { FetchDisplayGroupsResponse } from '@/services/displayGroupApi';
import type { DisplayGroup } from '@/types/displayGroup';
import type { User } from '@/types/user';

// -----------------------------------------------------------------------------
// Factory that produces a DisplayGroup with safe minimal defaults.
// Only fields used in assertions carry meaningful values — everything else
// is set to the zero value for its type so the component renders without errors.
//
// displayGroupId: 1 — asserted in delete tests
// displayGroup: 'Test Group' — asserted in render, form, and modal tests
// description: 'A test display group' — asserted in form payload tests
// isDynamic: 0 — drives the Dynamic checkbox state and form payload
// folderId / permissionsFolderId: 1 — required by SelectFolder stub
// -----------------------------------------------------------------------------
export const buildDisplayGroup = (overrides: Partial<DisplayGroup> = {}): DisplayGroup => ({
  displayGroupId: 1,
  displayGroup: 'Test Group',
  description: 'A test display group',
  isDisplaySpecific: 0,
  isDynamic: 0,
  dynamicCriteria: '',
  dynamicCriteriaLogicalOperator: 'OR',
  dynamicCriteriaTags: '',
  dynamicCriteriaExactTags: 0,
  dynamicCriteriaTagsLogicalOperator: 'OR',
  userId: 1,
  tags: [],
  bandwidthLimit: 0,
  groupsWithPermissions: '',
  createdDt: '',
  modifiedDt: '',
  folderId: 1,
  permissionsFolderId: 1,
  ref1: '',
  ref2: '',
  ref3: '',
  ref4: '',
  ref5: '',
  ...overrides,
});

export const mockDisplayGroup = buildDisplayGroup();

export const SINGLE_DISPLAY_GROUP: FetchDisplayGroupsResponse = {
  rows: [mockDisplayGroup],
  totalCount: 1,
};

export const EMPTY_DISPLAY_GROUP_TABLE: FetchDisplayGroupsResponse = {
  rows: [],
  totalCount: 0,
};

// The default logged-in user for display group tests.
export const mockUser: User = {
  userId: 1,
  userName: 'TestUser',
  userTypeId: 1,
  groupId: 1,
  features: { 'folder.view': true },
  settings: {
    defaultTimezone: 'UTC',
    defaultLanguage: 'en',
    DATE_FORMAT_JS: 'DD/MM/YYYY',
    TIME_FORMAT_JS: 'HH:mm',
  },
};

// Query keys that mirror what useTableState builds internally.
// Centralised here so a key change only needs one update.
export const queryKeys = {
  displayGroupPage: ['userPref', 'displayGroup_page'] as const,
};
