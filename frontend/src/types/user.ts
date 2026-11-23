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

import type { UserGroup } from './userGroup';

export interface BrandingConfig {
  productName: string;
  appName: string;
  logoUrl: string;
  logoDarkUrl: string;
  faviconUrl: string;
  cssUrl: string | null;
  supportUrl: string;
}

/**
 * Xibo in the Cloud subscription metadata. Populated by /user/me only for super admins on
 * Xibo-themed Cloud instances; null otherwise (e.g. self-hosted).
 */
export interface CloudHosting {
  renewalDate: string; // 'YYYY-MM-DD'
  isDemo: boolean;
  isMonthly: boolean;
  willRenew: boolean;
}

export enum UserType {
  SuperAdmin = 1,
  GroupAdmin = 2,
  User = 3,
}

export type UserFeatures = Record<string, boolean>;

export interface UserPermissions {
  view?: number;
  edit?: number;
  delete?: number;
  modifyPermissions?: number;
}

export interface TranslateConfig {
  locale: string;
  jsLocale: string;
  jsShortLocale: string;
}

export interface UserSettings {
  defaultTimezone?: string;
  defaultLanguage?: string;
  DATE_FORMAT_JS?: string;
  TIME_FORMAT_JS?: string;
  homeFolder?: string;
  translate?: TranslateConfig;
  [key: string]: string | number | boolean | object | null | undefined;
}

export interface User {
  userId: number;
  userName: string;
  userTypeId: UserType;

  email?: string;
  firstName?: string;
  lastName?: string;
  phone?: string;

  homeFolderId?: number;
  homePageId?: string;

  features?: UserFeatures;
  settings?: UserSettings;

  groupId?: number;
  group?: string;

  groups?: UserGroup[];

  retired?: number;
  loggedIn?: number;
  lastAccessed?: string;

  libraryQuota?: number;
  libraryQuotaFormatted?: string;

  ref1?: string;
  ref2?: string;
  ref3?: string;
  ref4?: string;
  ref5?: string;

  isPasswordChangeRequired?: number;
  twoFactorTypeId?: number;
  twoFactorDescription?: string;
  newUserWizard?: number;

  isSystemNotification?: number;
  isDisplayNotification?: number;
  isDataSetNotification?: number;
  isLayoutNotification?: number;
  isLibraryNotification?: number;
  isReportNotification?: number;
  isScheduleNotification?: number;
  isCustomNotification?: number;

  homePage?: string;
  homeFolder?: string;
  isSuperAdmin?: boolean;
  userPermissions?: UserPermissions;

  branding?: BrandingConfig;
  cloudHosting?: CloudHosting | null;

  /** Whether SAML authentication is configured for this CMS instance. */
  samlEnabled?: boolean;
  /**
   * Hide the Sign Out control - set when SAML is configured but the IdP doesn't
   * support Single Logout, so a local-only logout would just be silently
   * re-authenticated via SSO on the next protected-page visit.
   */
  hideLogoutButton?: boolean;
}
