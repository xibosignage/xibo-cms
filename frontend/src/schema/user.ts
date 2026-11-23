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

import type { TFunction } from 'i18next';
import { z } from 'zod';

export const getAddUserSchema = (t: TFunction) =>
  z.object({
    userName: z
      .string()
      .min(1, t('Username is required'))
      .max(50, t('Username must be less than 50 characters')),

    password: z.string().min(1, t('Password is required')),

    email: z.string().email(t('Please enter a valid email address')).or(z.literal('')).optional(),

    userTypeId: z.number(),

    groupId: z.number({ message: t('Initial user group is required') }),

    homePageId: z.string().min(1, t('Homepage is required')),

    libraryQuota: z.number().min(0, t('Library quota must be a positive number')).optional(),

    firstName: z.string().max(254).optional(),
    lastName: z.string().max(254).optional(),
    phone: z.string().max(254).optional(),
    ref1: z.string().max(254).optional(),
    ref2: z.string().max(254).optional(),
    ref3: z.string().max(254).optional(),
    ref4: z.string().max(254).optional(),
    ref5: z.string().max(254).optional(),

    isPasswordChangeRequired: z.number().optional(),
    newUserWizard: z.number().optional(),
    hideNavigation: z.number().optional(),
  });

export const getEditUserSchema = (t: TFunction) =>
  z.object({
    userName: z
      .string()
      .min(1, t('Username is required'))
      .max(50, t('Username must be less than 50 characters')),

    email: z.string().email(t('Please enter a valid email address')).or(z.literal('')).optional(),

    userTypeId: z.number().optional(),

    homePageId: z.string().optional(),

    libraryQuota: z.number().min(0, t('Library quota must be a positive number')).optional(),

    firstName: z.string().max(254).optional(),
    lastName: z.string().max(254).optional(),
    phone: z.string().max(254).optional(),
    ref1: z.string().max(254).optional(),
    ref2: z.string().max(254).optional(),
    ref3: z.string().max(254).optional(),
    ref4: z.string().max(254).optional(),
    ref5: z.string().max(254).optional(),

    retired: z.number().optional(),
    isPasswordChangeRequired: z.number().optional(),
    newUserWizard: z.number().optional(),
    hideNavigation: z.number().optional(),

    newPassword: z.string().optional(),
    retypeNewPassword: z.string().optional(),

    isSystemNotification: z.number().optional(),
    isDisplayNotification: z.number().optional(),
    isDataSetNotification: z.number().optional(),
    isCustomNotification: z.number().optional(),
    isLayoutNotification: z.number().optional(),
    isLibraryNotification: z.number().optional(),
    isReportNotification: z.number().optional(),
    isScheduleNotification: z.number().optional(),
  });

export const getAddUserWizardSchema = (t: TFunction) =>
  z.object({
    userName: z
      .string()
      .min(1, t('Username is required'))
      .max(50, t('Username must be less than 50 characters')),

    password: z.string().min(1, t('Password is required')),

    email: z.string().email(t('Please enter a valid email address')).or(z.literal('')).optional(),

    groupId: z.number({ message: t('Please select a role') }),
  });
