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

export interface AuthorizeApplicationInfo {
  name: string;
  description: string | null;
  logo: string | null;
  coverImage: string | null;
  companyName: string | null;
  termsUrl: string | null;
  privacyUrl: string | null;
}

export interface AuthorizeScope {
  id: string;
  description: string;
}

export interface AuthorizeConsentRequired {
  alreadyAuthorized: false;
  application: AuthorizeApplicationInfo;
  scopes: AuthorizeScope[];
}

export interface AuthorizeAlreadyAuthorized {
  alreadyAuthorized: true;
  redirectUrl: string;
}

export type AuthorizeRequestResponse = AuthorizeConsentRequired | AuthorizeAlreadyAuthorized;
