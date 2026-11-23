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

import { AxiosError, AxiosHeaders } from 'axios';
import { vi } from 'vitest';

import * as userApi from '@/services/userApi';
import { deferred } from '@/testUtils/deferred';

// Requires vi.mock('@/services/userApi') in the test file.
export const api = vi.mocked(userApi);

// Holds a call in flight; settle it from the test with resolve/reject.
const hold = <T>(fn: { mockReturnValue: (p: Promise<T>) => unknown }) => {
  const request = deferred<T>();
  fn.mockReturnValue(request.promise);
  return request;
};

// Gives every service call the Profile tab makes a harmless resolved value, so an
// unstubbed call never returns undefined. Call after vi.clearAllMocks(), then override.
export const stubUserApiDefaults = () => {
  api.updateUserProfile.mockResolvedValue();
  api.fetch2FASetup.mockResolvedValue(null);
  api.generate2FARecoveryCodes.mockResolvedValue([]);
  api.fetch2FARecoveryCodes.mockResolvedValue([]);
  api.saveAutoSubmitPreference.mockResolvedValue();
};

// Builds a real AxiosError so isAxiosError() reads message/property.
export const axiosError = (data: Record<string, unknown>, status = 422) =>
  new AxiosError('Request failed', 'ERR_BAD_REQUEST', undefined, undefined, {
    data,
    status,
    statusText: '',
    headers: {},
    config: { headers: new AxiosHeaders() },
  });

// --- Profile -----------------------------------------------------------------

export const stubProfileSaveApiError = (message: string, property?: string) =>
  api.updateUserProfile.mockRejectedValue(axiosError({ message, property }));

export const stubProfileSaveFail = () =>
  api.updateUserProfile.mockRejectedValue(new Error('Network down'));

export const holdProfileSave = () => hold<void>(api.updateUserProfile);

export const stub2FASetup = (qRUrl: string) => api.fetch2FASetup.mockResolvedValue({ qRUrl });

export const stub2FASetupFail = () =>
  api.fetch2FASetup.mockRejectedValue(new Error('Setup failed'));

export const hold2FASetup = () => hold<{ qRUrl: string } | null>(api.fetch2FASetup);

export const stubGenerateCodes = (codes: readonly string[]) =>
  api.generate2FARecoveryCodes.mockResolvedValue([...codes]);

export const stubGenerateCodesFail = () =>
  api.generate2FARecoveryCodes.mockRejectedValue(new Error('Generate failed'));

export const stubShowCodesFail = () =>
  api.fetch2FARecoveryCodes.mockRejectedValue(new Error('Show failed'));

export const stubShowCodes = (codes: readonly string[]) =>
  api.fetch2FARecoveryCodes.mockResolvedValue([...codes]);
