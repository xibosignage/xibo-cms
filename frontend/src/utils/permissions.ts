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

import { notify } from '@/components/ui/Notification';
import type { AppRoute } from '@/config/appRoutes';
import { isServiceShown, type GetServiceVisibility } from '@/hooks/useCmsVisibility';
import { UserType, type User } from '@/types/user';

export const hasFeature = (user: User | null, featureKey: string): boolean => {
  if (!user) {
    return false;
  }

  if (user.isSuperAdmin || user.userTypeId === UserType.SuperAdmin) {
    return true;
  }

  if (!user.features) {
    return false;
  }

  return user.features?.[featureKey] === true;
};

export const canSaveInFolder = (
  user: User | null,
  canViewFolders: boolean,
  effectiveFolderId: number,
  homeFolderId: number,
): boolean => {
  const targetFolderId = canViewFolders ? effectiveFolderId : homeFolderId;

  if (targetFolderId !== 1) {
    return true;
  }

  const isSuperAdmin = !!user?.isSuperAdmin || user?.userTypeId === UserType.SuperAdmin;
  return isSuperAdmin || user?.settings?.FOLDERS_ALLOW_SAVE_IN_ROOT === '1';
};

export const filterByPermission = <T>(
  items: T[],
  checkFn: (item: T) => boolean | number | undefined | null,
  t: TFunction,
  actionLabel: string,
): T[] => {
  const permittedItems = items.filter((item) => !!checkFn(item));
  const skippedCount = items.length - permittedItems.length;

  if (permittedItems.length === 0) {
    notify.warning(
      t('You do not have permission to {{action}} any of the selected items.', {
        action: actionLabel,
      }),
    );
    return [];
  }

  if (skippedCount > 0) {
    notify.info(
      t('{{count}} items were skipped due to lack of permissions.', {
        count: skippedCount,
      }),
    );
  }

  return permittedItems;
};

const isRouteAllowed = (
  route: AppRoute,
  user: User,
  getServiceVisibility: GetServiceVisibility,
): boolean => {
  if (!isServiceShown(getServiceVisibility, route.service)) {
    return false;
  }

  if (route.feature && !hasFeature(user, route.feature)) {
    return false;
  }

  if (route.validator && !route.validator(user)) {
    return false;
  }

  return true;
};

/** getServiceVisibility comes from useServiceVisibility(), so routes this CMS doesn't offer are left out too. */
export const filterRoutesByUser = (
  routes: AppRoute[],
  user: User,
  getServiceVisibility: GetServiceVisibility,
): AppRoute[] => {
  return routes.reduce((acc: AppRoute[], route) => {
    // Check if parent is allowed
    if (!isRouteAllowed(route, user, getServiceVisibility)) {
      return acc;
    }

    // If route has children, check them recursively
    if (route.subLinks && route.subLinks.length > 0) {
      const visibleSubLinks = filterRoutesByUser(route.subLinks, user, getServiceVisibility);

      // If parent has sublinks, but user can't see any
      // hide the parent too
      if (visibleSubLinks.length === 0) {
        return acc;
      }

      // Return parent with the filtered list of sublinks
      acc.push({ ...route, subLinks: visibleSubLinks });

      return acc;
    }

    // If it's a node with no children
    // and it passed filtering, keep it
    acc.push(route);
    return acc;
  }, []);
};
