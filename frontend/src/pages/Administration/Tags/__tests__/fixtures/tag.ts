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

import type { FetchTagsResponse } from '@/services/tagApi';
import type { Tag } from '@/types/tag';
import type { User } from '@/types/user';

// -----------------------------------------------------------------------------
// Factory: minimal defaults — only fields used in assertions have meaningful
// values, everything else uses the zero value for its type.
//
// tagId: 1     — used in delete assertions
// tag: 'location' — asserted in render, filter, and modal tests
// isSystem: 0  — Edit/Delete row actions are only shown when isSystem === 0
// isRequired: 0, options: null, value: '' — zero-value fillers
// -----------------------------------------------------------------------------
export const buildTag = (overrides: Partial<Tag> = {}): Tag => ({
  tagId: 1,
  tag: 'location',
  isSystem: 0,
  isRequired: 0,
  options: null,
  value: '',
  ...overrides,
});

export const mockTag = buildTag();

export const SINGLE_TAG: FetchTagsResponse = { rows: [mockTag], totalCount: 1 };
export const EMPTY_TAG_TABLE: FetchTagsResponse = { rows: [], totalCount: 0 };

// superAdmin (userTypeId: 1): sees Add Tag button, Edit/Delete row actions
export const mockUser: User = {
  userId: 1,
  userName: 'admin',
  userTypeId: 1,
  groupId: 1,
  features: {},
  settings: {
    defaultTimezone: 'UTC',
    defaultLanguage: 'en',
    DATE_FORMAT_JS: 'DD/MM/YYYY',
    TIME_FORMAT_JS: 'HH:mm',
  },
};

// non-superAdmin (userTypeId: 2): no Add Tag button, no Edit/Delete row actions
export const mockNonAdminUser: User = {
  userId: 2,
  userName: 'user',
  userTypeId: 2,
  groupId: 2,
  features: {},
  settings: {
    defaultTimezone: 'UTC',
    defaultLanguage: 'en',
    DATE_FORMAT_JS: 'DD/MM/YYYY',
    TIME_FORMAT_JS: 'HH:mm',
  },
};

// Query key that mirrors what useTableState builds internally for 'tag_page'.
// Centralised here so a key change only requires one update.
export const queryKeys = {
  tagsPage: ['userPref', 'tag_page'] as const,
};
