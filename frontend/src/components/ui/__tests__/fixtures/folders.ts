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

import { buildCurrentUser, PERSONAS } from '@/testUtils/personas';
import type { Folder } from '@/types/folder';
import type { User } from '@/types/user';

// -----------------------------------------------------------------------------
// Factory that produces a Folder with safe minimal defaults.
// SelectFolder only reads id and text — from fetchFolderById and from the
// folder the tree hands to onSelect.
//
// id: 42 — the id passed to onSelect and to fetchFolderById
// text: 'Design' — the name shown on the picker and passed to onSelect
// -----------------------------------------------------------------------------
export const buildFolder = (overrides: Partial<Folder> = {}): Folder => ({
  id: 42,
  type: '',
  text: 'Design',
  parentId: 0,
  isRoot: 0,
  children: [],
  ownerId: 0,
  ownerName: '',
  createdDt: null,
  modifiedDt: null,
  ...overrides,
});

export const mockFolder = buildFolder();

// The current user's home folder — what Clear resets to.
export const mockHomeFolder = buildFolder({ id: 5, text: 'My Home', type: 'home' });

// -----------------------------------------------------------------------------
// Users for useUserContext.
// SelectFolder reads homeFolderId only; folder.view comes from usePermissions,
// which the test mocks — the persona keeps the two consistent.
// -----------------------------------------------------------------------------
export const mockUser: User = buildCurrentUser(PERSONAS.contentManager, {
  homeFolderId: mockHomeFolder.id,
});

// A plain User without folder.view (SelectFolder renders nothing for them)
// and without a home folder (SelectFolder falls back to folder 1).
export const mockNoFolderUser: User = buildCurrentUser(PERSONAS.user);
