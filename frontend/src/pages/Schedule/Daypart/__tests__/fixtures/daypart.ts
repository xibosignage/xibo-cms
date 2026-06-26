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

import type { FetchDaypartResponse } from '@/services/daypartApi';
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

export const mockUser: User = {
  userId: 1,
  userName: 'TestUser',
  userTypeId: 1,
  groupId: 1,
  features: { 'daypart.view': true },
  settings: {
    defaultTimezone: 'UTC',
    defaultLanguage: 'en',
    DATE_FORMAT_JS: 'DD/MM/YYYY',
    TIME_FORMAT_JS: 'HH:mm',
  },
};

export const queryKeys = {
  daypartPage: ['userPref', 'daypart_page'] as const,
};
