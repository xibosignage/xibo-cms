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

import { UserType } from '@/types/user';

interface UserNameFields {
  firstName?: string;
  lastName?: string;
  userName?: string;
}

export function getUserTypeLabel(t: TFunction, userTypeId: number): string {
  switch (userTypeId) {
    case UserType.SuperAdmin:
      return t('Super Admin');
    case UserType.GroupAdmin:
      return t('Group Admin');
    case UserType.User:
      return t('User');
    default:
      return t('Unknown');
  }
}

export function getUserInitials(user: UserNameFields | null) {
  if (!user) {
    return '??';
  }
  if (user.firstName && user.lastName) {
    return `${user.firstName[0]}${user.lastName[0]}`.toUpperCase();
  }
  return (user.userName?.substring(0, 2) || 'U').toUpperCase();
}

export function getUserDisplayName(user: UserNameFields | null) {
  return [user?.firstName, user?.lastName].filter(Boolean).join(' ') || user?.userName || 'User';
}
