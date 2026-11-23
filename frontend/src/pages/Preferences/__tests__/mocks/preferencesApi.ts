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

import * as userApi from '@/services/userApi';
import type { UserApplication } from '@/services/userApi';
import { deferred } from '@/testUtils/deferred';

// Requires vi.mock('@/services/userApi') in the test file.
export const api = vi.mocked(userApi);

// Holds a call in flight; settle it from the test with resolve/reject.
const hold = <T>(fn: { mockReturnValue: (p: Promise<T>) => unknown }) => {
  const request = deferred<T>();
  fn.mockReturnValue(request.promise);
  return request;
};

// Gives every service call the Preferences tabs make a harmless resolved value, so an
// unstubbed call never returns undefined. Call after vi.clearAllMocks(), then override.
export const stubUserApiDefaults = () => {
  api.fetchUserApplications.mockResolvedValue([]);
  api.revokeApplicationAccess.mockResolvedValue();
  api.saveUserPreferencesBulk.mockResolvedValue();
};

// --- My Applications ---------------------------------------------------------

export const stubApplications = (apps: UserApplication[]) =>
  api.fetchUserApplications.mockResolvedValue(apps);

export const stubApplicationsFail = () =>
  api.fetchUserApplications.mockRejectedValue(new Error('Load failed'));

export const holdApplications = () => hold<UserApplication[]>(api.fetchUserApplications);

export const stubRevokeFail = () =>
  api.revokeApplicationAccess.mockRejectedValue(new Error('Revoke failed'));

export const holdRevoke = () => hold<void>(api.revokeApplicationAccess);

// --- Customisation -----------------------------------------------------------

export const stubSavePreferencesFail = () =>
  api.saveUserPreferencesBulk.mockRejectedValue(new Error('Save failed'));

export const holdSavePreferences = () => hold<void>(api.saveUserPreferencesBulk);
