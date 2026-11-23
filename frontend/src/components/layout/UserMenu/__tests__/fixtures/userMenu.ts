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
import type { BrandingConfig, User } from '@/types/user';

// -----------------------------------------------------------------------------
// Users. Super admin passes every hasFeature() check.
//
// userId: 7 — distinct from persona defaults
// Jane Doe — avatar initials "JD" and header name
// email — header text
// twoFactorTypeId: 0 — two factor off unless a test overrides it
// -----------------------------------------------------------------------------
export const buildMenuUser = (overrides: Partial<User> = {}): User =>
  buildCurrentUser(PERSONAS.superAdmin, {
    userId: 7,
    userName: 'jdoe',
    firstName: 'Jane',
    lastName: 'Doe',
    email: 'jane@example.com',
    twoFactorTypeId: 0,
    hideLogoutButton: false,
    branding: undefined,
    settings: {},
    ...overrides,
  });

export const mockUser = buildMenuUser();

// No full name, so initials come from the username: "KE".
export const USERNAME_ONLY_USER = buildMenuUser({ firstName: '', lastName: '', userName: 'kent' });

export const NO_LOGOUT_USER = buildMenuUser({ hideLogoutButton: true });

const buildBranding = (overrides: Partial<BrandingConfig> = {}): BrandingConfig => ({
  productName: '',
  appName: '',
  logoUrl: '',
  logoDarkUrl: '',
  faviconUrl: '',
  cssUrl: '',
  supportUrl: '',
  ...overrides,
});

export const BRANDED_USER = buildMenuUser({ branding: buildBranding({ appName: 'Acme Signage' }) });

// Branding present but no app name set.
export const UNNAMED_BRAND_USER = buildMenuUser({ branding: buildBranding() });

// Avatar button names (the avatar is labelled only by its initials).
export const AVATAR_INITIALS = {
  default: 'JD', // Jane Doe
  usernameOnly: 'KE', // first two letters of "kent"
};
