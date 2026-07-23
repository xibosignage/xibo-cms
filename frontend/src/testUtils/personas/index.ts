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

// =============================================================================
// Shared persona registry for tests — the single source of truth for "what
// can this role do," used by every test suite (not just this one).
//
// Need one extra permission for a test? Compose it with buildCurrentUser()
// below — don't add or edit a PERSONAS entry for a one-off need.
// =============================================================================

import groupAdminJson from './data/group_admin.json';
import superAdminJson from './data/super_admin.json';
import userJson from './data/user.json';
import contentManagerJson from './data/user_content_manager.json';
import displayManagerJson from './data/user_display_manager.json';
import playlistManagerJson from './data/user_playlist_manager.json';
import scheduleManagerJson from './data/user_schedule_manager.json';

import type { User, UserFeatures, UserType } from '@/types/user';

// Real /user/me captures (Content/Playlist/Schedule/Display Manager, Super
// Admin) or freshly created accounts with the same rank + the generic
// "Users" group (Group Admin, User — original passwords unknown).
// Verified 7-for-7 against the live CMS on 2026-07-20, zero drift.
//
// groupAdmin and user have byte-for-byte identical `features` — but keep
// both anyway: userTypeId (rank) is independently checked in
// src/config/appRoutes.ts (canViewUsers) and IconDashboard.tsx, which both
// gate on `userTypeId === SuperAdmin || userTypeId === GroupAdmin` on top
// of the feature flag. A plain User (rank 3) is blocked there even with
// the same features Group Admin (rank 2) has — so the two ranks are not
// interchangeable despite matching permissions.
interface RawPersonaJson {
  userId: number;
  userName: string;
  userTypeId: number;
  groupId: number;
  features: UserFeatures;
}

// Raw JSON is data, not a `User` — sidebarTestUtils.ts already established
// this pluck-and-shape pattern; reused here rather than reinvented.
// `settings` is deliberately excluded: it's not persona data at all
// ("Global CMS settings are not a persona property") — different suites
// need different values, so each supplies its own via buildCurrentUser()'s
// overrides.
const toUser = (data: RawPersonaJson): Omit<User, 'settings'> & { features: UserFeatures } => ({
  userId: data.userId,
  userName: data.userName,
  userTypeId: data.userTypeId as UserType,
  groupId: data.groupId,
  features: data.features,
});

export const PERSONAS = {
  superAdmin: toUser(superAdminJson),
  groupAdmin: toUser(groupAdminJson),
  user: toUser(userJson),
  contentManager: toUser(contentManagerJson),
  playlistManager: toUser(playlistManagerJson),
  scheduleManager: toUser(scheduleManagerJson),
  displayManager: toUser(displayManagerJson),
} as const;

export type PersonaName = keyof typeof PERSONAS;

// Builds a test user from a persona plus a few extra features, without
// dropping the persona's existing ones:
//
//   buildCurrentUser(PERSONAS.contentManager, { features: { 'users.add': true } })
export const buildCurrentUser = (base: User, overrides: Partial<User> = {}): User => ({
  ...base,
  ...overrides,
  features: { ...base.features, ...overrides.features },
});
