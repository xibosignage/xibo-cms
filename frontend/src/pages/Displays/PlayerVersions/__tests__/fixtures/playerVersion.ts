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

import type { FetchPlayerVersionsResponse } from '@/services/playerVersionApi';
import type { PlayerVersion } from '@/types/playerVersion';
import type { User } from '@/types/user';

export const buildPlayerVersion = (overrides: Partial<PlayerVersion> = {}): PlayerVersion => ({
  versionId: 1,
  type: 'android',
  version: '4.0.0',
  code: 400,
  playerShowVersion: 'Android v4',
  createdAt: '2026-01-01 00:00:00',
  modifiedAt: '2026-01-02 00:00:00',
  modifiedBy: 'TestUser',
  fileName: 'xibo-player-v4.apk',
  size: 1048576,
  md5: 'abc123',
  fileSizeFormatted: '1 MB',
  ...overrides,
});

export const mockPlayerVersion = buildPlayerVersion();

export const SINGLE_PLAYER_VERSION: FetchPlayerVersionsResponse = {
  rows: [mockPlayerVersion],
  totalCount: 1,
};

export const EMPTY_PLAYER_VERSION_TABLE: FetchPlayerVersionsResponse = {
  rows: [],
  totalCount: 0,
};

// Two-row fixture for bulk-action / delete tests.
export const MULTIPLE_PLAYER_VERSIONS: FetchPlayerVersionsResponse = {
  rows: [
    buildPlayerVersion({
      versionId: 1,
      playerShowVersion: 'Android v4',
      fileName: 'player-v4.apk',
    }),
    buildPlayerVersion({
      versionId: 2,
      playerShowVersion: 'webOS v3',
      type: 'lg',
      fileName: 'player-v3.ipk',
    }),
  ],
  totalCount: 2,
};

export const mockUser: User = {
  userId: 1,
  userName: 'TestUser',
  userTypeId: 1,
  groupId: 1,
  features: { 'playersoftware.view': true },
  settings: {
    defaultTimezone: 'UTC',
    defaultLanguage: 'en',
    DATE_FORMAT_JS: 'DD/MM/YYYY',
    TIME_FORMAT_JS: 'HH:mm',
  },
};

export const queryKeys = {
  playerVersionsPage: ['userPref', 'playerVersions_page'] as const,
};
