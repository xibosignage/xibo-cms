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

import type { FetchDashboardMediaResponse } from '@/services/dashboardApi';
import { buildCurrentUser, PERSONAS } from '@/testUtils/personas';
import type { LibraryType, MediaDashboardData } from '@/types/dashboard';
import type { Media } from '@/types/media';
import type { User } from '@/types/user';

// -----------------------------------------------------------------------------
// Factory that produces a Media row for the Unused / Unreleased Media tables
// with safe minimal defaults. Those tables read only four fields, so only those
// carry meaningful values; everything else is the zero value for its type.
//
// mediaId: 1 — the ID column, and the table's row id
// name: 'Test Media' — the Name column
// mediaType: 'image' — the Type column (StatusCell); the API sends lower-case strings
// fileSizeFormatted: '12 KiB' — the Size column; the API sends a formatted string
// -----------------------------------------------------------------------------
export const buildDashboardMedia = (overrides: Partial<Media> = {}): Media => ({
  folderId: 0,
  storedAs: '',
  mediaId: 1,
  name: 'Test Media',
  thumbnail: '',
  mediaType: 'image',
  createdDt: '',
  modifiedDt: '',
  ownerId: '',
  valid: false,
  released: 0,
  fileName: '',
  fileSizeFormatted: '12 KiB',
  orientation: null,
  tags: [],
  fileSize: 0,
  duration: 0,
  enableStat: '',
  retired: false,
  expires: '',
  expiresFormatted: '',
  updateInLayouts: false,
  userPermissions: {},
  deleteOldRevisions: false,
  ...overrides,
});

// N rows with unique ids and names, for paging tests (ids 1..n).
export const buildDashboardMediaRows = (count: number, overrides: Partial<Media> = {}): Media[] =>
  Array.from({ length: count }, (_, i) =>
    buildDashboardMedia({ mediaId: i + 1, name: `Test Media ${i + 1}`, ...overrides }),
  );

export const mockMedia = buildDashboardMedia();

export const SINGLE_MEDIA: FetchDashboardMediaResponse = {
  rows: [mockMedia],
  totalCount: 1,
};

export const EMPTY_MEDIA_TABLE: FetchDashboardMediaResponse = {
  rows: [],
  totalCount: 0,
};

// -----------------------------------------------------------------------------
// One entry of library.types (GET /mediamanager).
//
// title: 'Image' — must match a MediaTypeChart TYPE_COLORS key to appear in a chart.
//   The server sends module names ('Image', 'PDF', 'HTML Package', ...),
//   'Widget cache' for module files, and ucfirst(type) for anything else.
// count / size — the per-type values; size is in bytes
// -----------------------------------------------------------------------------
export const buildLibraryType = (overrides: Partial<LibraryType> = {}): LibraryType => ({
  title: 'Image',
  count: 0,
  size: 0,
  ...overrides,
});

// -----------------------------------------------------------------------------
// GET /mediamanager response.
//
// countOf: 3 — the Library Count card and the count chart centre (non-zero so the
//   card isn't confused with an empty library)
// size: '1.5 MiB' — the Library Size card and the size chart centre (sent pre-formatted)
// types: one Image entry so both charts have a non-zero slice
// typesSuffix / typesBase — not read by the page; zero values
// -----------------------------------------------------------------------------
export const buildMediaDashboardData = (
  overrides: Partial<MediaDashboardData['library']> = {},
): MediaDashboardData => ({
  library: {
    countOf: 3,
    size: '1.5 MiB',
    types: [buildLibraryType({ count: 3, size: 1572864 })],
    typesSuffix: '',
    typesBase: 0,
    ...overrides,
  },
});

export const mockMediaDashboardData = buildMediaDashboardData();

// The default logged-in user. Super Admin passes every hasFeature() check, but the
// Tidy Library card also needs SETTING_LIBRARY_TIDY_ENABLED, which is off here:
// tidy tests use tidyUser() instead.
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

// A user for Tidy Library tests. Defaults to Content Manager (has library.modify and
// is not a Super Admin, so the feature gate is really exercised) with tidying on.
// Pass enabled = '0' for "setting off" and null for "setting missing" (not undefined:
// undefined would fall back to the default '1'), and
// PERSONAS.displayManager (no library.modify) for the "no permission" case.
export const tidyUser = (
  base: User = PERSONAS.contentManager,
  enabled: string | null = '1',
): User =>
  buildCurrentUser(base, {
    settings: {
      ...mockUser.settings,
      ...(enabled === null ? {} : { SETTING_LIBRARY_TIDY_ENABLED: enabled }),
    },
  });

// Query keys used by useMediaDashboardStats / useUnusedMedia / useUnreleasedMedia.
// The table keys also include pagination and sorting, so only their prefixes are shared.
export const queryKeys = {
  mediaDashboardStats: ['mediaDashboard', 'stats'] as const,
  unusedMediaPrefix: ['mediaDashboard', 'unused'] as const,
  unreleasedMediaPrefix: ['mediaDashboard', 'unreleased'] as const,
};
