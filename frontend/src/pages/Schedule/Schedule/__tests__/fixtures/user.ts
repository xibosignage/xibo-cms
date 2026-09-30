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

import { buildCurrentUser, PERSONAS } from '@/testUtils/personas';
import type { User } from '@/types/user';

// -----------------------------------------------------------------------------
// The default logged-in user for most Events page tests.
// -----------------------------------------------------------------------------
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

// -----------------------------------------------------------------------------
// A viewer in a +11 timezone whose date format carries a time.
//
// mockUser above is UTC with a date-only DATE_FORMAT_JS, which makes it useless
// for checking that a timestamp is converted rather than merely printed: UTC is
// the identity conversion, and without a time token in the format there is no
// time on screen to be wrong. Use this user wherever a rendered timestamp is the
// thing under test.
// -----------------------------------------------------------------------------
export const mockUserInSydney: User = buildCurrentUser(PERSONAS.superAdmin, {
  userId: 1,
  userName: 'TestUser',
  groupId: 1,
  settings: {
    defaultTimezone: 'Australia/Sydney',
    defaultLanguage: 'en',
    DATE_FORMAT_JS: 'DD/MM/YYYY HH:mm',
    TIME_FORMAT_JS: 'HH:mm',
  },
});
