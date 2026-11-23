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

import type { FetchDaypartResponse } from '@/services/daypartApi';
import { buildCurrentUser, PERSONAS } from '@/testUtils/personas';
import type { Daypart, DaypartException } from '@/types/daypart';
import type { User } from '@/types/user';

export const buildDaypart = (overrides: Partial<Daypart> = {}): Daypart => ({
  dayPartId: 1,
  name: 'Test Daypart',
  description: '',
  isRetired: 0,
  userId: 1,
  startTime: '09:00:00',
  endTime: '17:00:00',
  exceptions: [],
  isAlways: 0,
  isCustom: 0,
  adjustedStart: null,
  adjustedEnd: null,
  userPermissions: { view: 1, edit: 1, delete: 1, modifyPermissions: 1 },
  ...overrides,
});

export const mockDaypart = buildDaypart();

export const SINGLE_DAYPART: FetchDaypartResponse = {
  rows: [mockDaypart],
  totalCount: 1,
};

export const EMPTY_DAYPART_TABLE: FetchDaypartResponse = {
  rows: [],
  totalCount: 0,
};

// Two-row fixture for bulk-action / delete tests.
export const MULTIPLE_DAYPARTS: FetchDaypartResponse = {
  rows: [
    buildDaypart({ dayPartId: 1, name: 'Daypart Alpha' }),
    buildDaypart({ dayPartId: 2, name: 'Daypart Beta' }),
  ],
  totalCount: 2,
};

export const ALWAYS_DAYPART = buildDaypart({ dayPartId: 3, name: 'Always', isAlways: 1 });

export const SINGLE_SPECIAL_DAYPART: FetchDaypartResponse = {
  rows: [ALWAYS_DAYPART],
  totalCount: 1,
};

export const buildDaypartException = (
  overrides: Partial<DaypartException> = {},
): DaypartException => ({
  day: 'Mon',
  start: '09:00:00',
  end: '17:00:00',
  ...overrides,
});

export const mockUser: User = buildCurrentUser(PERSONAS.superAdmin, {
  userId: 1,
  userName: 'TestUser',
  groupId: 1,
  settings: {
    defaultTimezone: 'UTC',
    defaultLanguage: 'en',
    DATE_FORMAT_JS: 'DD/MM/YYYY',
    TIME_FORMAT_JS: 'HH:mm',
  },
});

export const queryKeys = {
  daypartPage: ['userPref', 'daypart_page'] as const,
};
