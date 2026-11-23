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

import { vi } from 'vitest';

/**
 * `userApi` mock for the Fonts page tests only. It mocks just the exports the
 * Fonts page reaches, so it is not meant to be shared with other pages. Import
 * this module BEFORE anything that imports `@/services/userApi` (the page, its
 * render helper), so `vi.mock` registers before the real module is loaded:
 *
 *   import './mocks/userApi';
 *
 * Covers what the Fonts page loads at runtime:
 * - useTableState: fetchUserPreference, saveUserPreference
 * - the real Modal (when a test doesn't mock it): autoSubmitPrefQueryKey,
 *   saveAutoSubmitPreference
 *
 * fetchUserPreference resolves to null, so useTableState falls back to the
 * page defaults. Override per test with vi.mocked(...) if needed.
 */
vi.mock('@/services/userApi', () => ({
  fetchUserPreference: vi.fn().mockResolvedValue(null),
  saveUserPreference: vi.fn().mockResolvedValue(undefined),
  autoSubmitPrefQueryKey: vi.fn((formId: string) => ['userPref', `autoSubmit.${formId}`]),
  saveAutoSubmitPreference: vi.fn().mockResolvedValue(undefined),
}));
