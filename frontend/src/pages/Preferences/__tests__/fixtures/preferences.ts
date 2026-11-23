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

import type { UserApplication } from '@/services/userApi';
import { buildCurrentUser, PERSONAS } from '@/testUtils/personas';
import type { User } from '@/types/user';

// -----------------------------------------------------------------------------
// Factory that produces a UserApplication (one row of "My Applications").
//
// id: 1 — asserted in the revoke API call
// name: 'Zapier' — asserted in rows and the "Access to Zapier revoked" toast
// approvedDate / approvedIp — asserted in the row cells
// -----------------------------------------------------------------------------
export const buildApplication = (overrides: Partial<UserApplication> = {}): UserApplication => ({
  id: 1,
  name: 'Zapier',
  approvedDate: '2026-01-02 10:00',
  approvedIp: '10.0.0.1',
  ...overrides,
});

export const mockApplication = buildApplication();

// Builds App 1..App n with ids 1..n, for pagination tests.
export const buildApplications = (count: number): UserApplication[] =>
  Array.from({ length: count }, (_, i) => buildApplication({ id: i + 1, name: `App ${i + 1}` }));

// GET /user/{id}/applications returns a plain array, not { rows, totalCount }.
export const SINGLE_APPLICATION: UserApplication[] = [mockApplication];

export const EMPTY_APPLICATION_TABLE: UserApplication[] = [];

// -----------------------------------------------------------------------------
// userId: 7 — asserted in fetch/revoke calls (distinct from persona defaults)
// settings: {} — Customisation falls back to its defaults
// -----------------------------------------------------------------------------
export const buildPreferencesUser = (overrides: Partial<User> = {}): User =>
  buildCurrentUser(PERSONAS.superAdmin, {
    userId: 7,
    userName: 'jdoe',
    firstName: 'Jane',
    lastName: 'Doe',
    settings: {},
    ...overrides,
  });

export const mockUser = buildPreferencesUser();

// Plain user persona: user.profile is false, so no Profile tab.
export const NO_PROFILE_USER = buildCurrentUser(PERSONAS.user, { userId: 7, settings: {} });

// Content manager persona: not an admin, but user.profile is true.
export const PROFILE_FEATURE_USER = buildCurrentUser(PERSONAS.contentManager, {
  userId: 7,
  settings: {},
});

// Keys the feature reads/writes. Pref keys are literals because tests automock
// @/services/userApi, which would turn autoSubmitPrefQueryKey() into undefined.
export const queryKeys = {
  autoSubmitPref: ['userPref', 'autoSubmit.layoutForm'] as const,
  otherPref: ['userPref', 'folderTree'] as const,
};
