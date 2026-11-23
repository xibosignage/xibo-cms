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

// GET about/config payload; AboutModalContent's own interface is private.
export interface AboutConfig {
  version: string;
  revision: string;
  appName: string;
  productName: string;
  logoUrl: string;
  logoIconUrl: string;
  supportUrl: string;
  sourceUrl: string;
  aboutText: string;
}

export const buildAboutConfig = (overrides: Partial<AboutConfig> = {}): AboutConfig => ({
  version: '4.4.0',
  revision: 'abc1234',
  appName: 'Xibo',
  productName: '',
  logoUrl: '',
  logoIconUrl: '',
  supportUrl: '',
  sourceUrl: 'https://github.com/xibosignage/xibo-cms',
  aboutText: '',
  ...overrides,
});

export const WHITE_LABEL_ABOUT_CONFIG = buildAboutConfig({
  appName: 'Acme Signage',
  sourceUrl: 'https://git.example.com/acme/cms',
});

export const CUSTOM_TEXT_ABOUT_CONFIG = buildAboutConfig({
  aboutText:
    '<h2>Acme About</h2><p>Custom terms. <a href="https://example.com/terms">Terms</a></p>',
});

export const EMPTY_ABOUT_CONFIG = {};

// Plain User: About has no feature gate.
export const mockUser: User = buildCurrentUser(PERSONAS.user, {
  firstName: 'Test',
  lastName: 'User',
});
