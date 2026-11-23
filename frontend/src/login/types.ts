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

export type LoginView = 'login' | 'tfa' | 'recovery' | 'forgot' | 'forgotSent';

export interface SignInProvider {
  id: number | string;
  type: string;
  name: string;
  isPrimary: boolean;
  label: string;
  url: string;
}

export interface LoginConfig {
  upgradeInProgress?: boolean;
  instanceSuspended?: boolean;
  notFound?: boolean;
  priorRoute: string;
  loginError?: string;
  logoUrl: string;
  logoDarkUrl: string;
  passwordReminderEnabled: boolean;
  authCASEnabled: boolean;
  signInProviders?: SignInProvider[];
  // Set when a sign-in provider offers a sign-out link.
  signInProviderLogoutUrl?: string | null;
  version: string;
  appName: string;
  supportUrl: string;
  sourceUrl: string;
  removeLicenceFromLogin: boolean;
  i18n: Record<string, string>;
}

export interface LoginResponse {
  status: 'ok' | '2fa_required' | 'error' | 'rate_limited';
  message?: string;
  priorRoute?: string;
  isPasswordChangeRequired?: boolean;
}

export interface LoginMethodsResponse {
  status: 'ok' | 'rate_limited';
  message?: string;
  methods?: (SignInProvider | { type: 'password' })[];
}

export interface ForgotResponse {
  status: 'ok';
  message: string;
}

declare global {
  interface Window {
    __LOGIN_CONFIG__: LoginConfig;
  }
}
