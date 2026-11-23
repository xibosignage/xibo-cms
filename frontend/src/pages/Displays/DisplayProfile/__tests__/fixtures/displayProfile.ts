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

import type { FetchDisplayProfileResponse } from '@/services/displayProfileApi';
import type { DisplayProfile } from '@/types/displayProfile';
import type { User } from '@/types/user';

export const buildDisplayProfile = (overrides: Partial<DisplayProfile> = {}): DisplayProfile => ({
  displayProfileId: 1,
  name: 'Android Profile',
  type: 'android',
  isDefault: 1,
  ...overrides,
});

export const mockDisplayProfile: DisplayProfile = buildDisplayProfile();

export const SINGLE_DISPLAY_PROFILE: FetchDisplayProfileResponse = {
  rows: [mockDisplayProfile],
  totalCount: 1,
};

// Two distinct rows for bulk-selection / bulk-delete tests.
export const MULTIPLE_DISPLAY_PROFILES: FetchDisplayProfileResponse = {
  rows: [
    buildDisplayProfile({ displayProfileId: 1, name: 'Android Profile', type: 'android' }),
    buildDisplayProfile({
      displayProfileId: 2,
      name: 'Windows Profile',
      type: 'windows',
      isDefault: 0,
    }),
  ],
  totalCount: 2,
};

export const EMPTY_DISPLAY_PROFILE_TABLE: FetchDisplayProfileResponse = {
  rows: [],
  totalCount: 0,
};

// The default logged-in user for display profile tests.
export const mockUser: User = {
  userId: 1,
  userName: 'TestUser',
  userTypeId: 1,
  groupId: 1,
  features: { 'displayprofile.view': true, 'folder.view': true },
  settings: {
    defaultTimezone: 'UTC',
    defaultLanguage: 'en',
    DATE_FORMAT_JS: 'DD/MM/YYYY',
    TIME_FORMAT_JS: 'HH:mm',
  },
};

export const queryKeys = {
  displayProfilePage: ['userPref', 'displayProfile_page'] as const,
};
