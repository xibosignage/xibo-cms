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

import type { FetchFontsResponse } from '@/services/fontApi';
import { buildCurrentUser, PERSONAS } from '@/testUtils/personas';
import type { Font, FontDetails } from '@/types/font';
import type { User } from '@/types/user';

// -----------------------------------------------------------------------------
// Factory that produces a Font with safe minimal defaults.
// Only fields used in assertions carry meaningful values — everything else
// is set to the zero value for its type so the component renders without errors.
//
// id: 1 — asserted in delete, details and download tests
// name: 'Roboto' — asserted in render, filter and modal title tests
// fileName: 'Roboto-Regular.ttf' — asserted in download toast/API args and details modal
// familyName: 'Roboto' — visible column, asserted in render tests
// modifiedAt / modifiedBy — visible columns, asserted in render tests
// size: 2048 — rendered via formatFileSize in the table and details modal
// createdAt / md5 — hidden or unused, zero values
// -----------------------------------------------------------------------------
export const buildFont = (overrides: Partial<Font> = {}): Font => ({
  id: 1,
  name: 'Roboto',
  fileName: 'Roboto-Regular.ttf',
  familyName: 'Roboto',
  createdAt: '',
  modifiedAt: '2026-01-02 10:00:00',
  modifiedBy: 'admin',
  size: 2048,
  md5: '',
  ...overrides,
});

export const mockFont = buildFont();

// Second row for bulk-delete and multi-row tests.
export const mockFont2 = buildFont({
  id: 2,
  name: 'Open Sans',
  fileName: 'OpenSans.woff',
  familyName: 'Open Sans',
});

export const SINGLE_FONT: FetchFontsResponse = {
  rows: [mockFont],
  totalCount: 1,
};

export const TWO_FONTS: FetchFontsResponse = {
  rows: [mockFont, mockFont2],
  totalCount: 2,
};

export const EMPTY_FONT_TABLE: FetchFontsResponse = {
  rows: [],
  totalCount: 0,
};

// Response for fetchFontDetails. 'Subfamily ID' is empty so the modal's
// '—' fallback for empty values can be asserted.
export const mockFontDetails: FontDetails = {
  Name: 'Roboto',
  'SubFamily Name': 'Regular',
  'Subfamily ID': '',
  'Full Name': 'Roboto Regular',
  Version: '2.137',
  'Font Weight': '400',
  'Font Postscript Name': 'Roboto-Regular',
  'Font Copyright': 'Google',
};

const userSettings = {
  defaultTimezone: 'UTC',
  defaultLanguage: 'en',
  DATE_FORMAT_JS: 'DD/MM/YYYY',
  TIME_FORMAT_JS: 'HH:mm',
};

// Users are built from the shared PERSONAS registry rather than hand-typed.

// Super Admin: hasFeature() is always true — sees Upload Font, Delete row
// action and Delete Selected bulk action.
export const mockUser: User = buildCurrentUser(PERSONAS.superAdmin, { settings: userSettings });

// Plain User: font.add / font.delete are false — no Upload Font, no Delete
// actions. Details and Download are still available.
export const mockNoFeatureUser: User = buildCurrentUser(PERSONAS.user, { settings: userSettings });

// Plain User granted font.add and font.delete on top of the persona's features.
export const mockFontManagerUser: User = buildCurrentUser(PERSONAS.user, {
  settings: userSettings,
  features: { 'font.add': true, 'font.delete': true },
});

// Query keys that mirror what useTableState builds internally for 'font_page'.
// Centralised here so a key change only needs one update.
export const queryKeys = {
  fontsPage: ['userPref', 'font_page'] as const,
};
