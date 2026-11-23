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
import type { User } from '@/types/user';

// Super admin, so the Profile tab is always available. Two factor off unless a test overrides it.
export const buildProfileUser = (overrides: Partial<User> = {}): User =>
  buildCurrentUser(PERSONAS.superAdmin, {
    userId: 7,
    userName: 'jdoe',
    firstName: 'Jane',
    lastName: 'Doe',
    email: 'jane@example.com',
    twoFactorTypeId: 0,
    branding: undefined,
    settings: {},
    ...overrides,
  });

export const PROFILE_USER = buildProfileUser();

export const EMAIL_2FA_USER = buildProfileUser({ twoFactorTypeId: 1 });

export const GOOGLE_2FA_USER = buildProfileUser({ twoFactorTypeId: 2 });

export const RECOVERY_CODES = ['AAAA-1111', 'BBBB-2222'] as const;

// Codes already on the server, distinct from RECOVERY_CODES.
export const EXISTING_RECOVERY_CODES = ['OLD-0000'] as const;

export const QR_URL = 'data:image/png;base64,qr';
