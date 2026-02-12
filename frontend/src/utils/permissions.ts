/*
 * Copyright (C) 2026 Xibo Signage Ltd
 *
 * Xibo - Digital Signage - https://xibosignage.com
 *
 * This file is part of Xibo.
 *
 * Xibo is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * any later version.
 *
 * Xibo is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with Xibo.  If not, see <http://www.gnu.org/licenses/>.
 */

import type { AppRoute } from '@/config/appRoutes';
import type { User } from '@/types/user';

export const hasFeature = (user: User, featureKey: string): boolean => {
  if (!user || !user.features) {
    return false;
  }

  return user.features?.[featureKey] === true;
};

const isRouteAllowed = (route: AppRoute, user: User): boolean => {
  if (route.feature && !hasFeature(user, route.feature)) {
    return false;
  }

  if (route.validator && !route.validator(user)) {
    return false;
  }

  return true;
};

export const filterRoutesByUser = (routes: AppRoute[], user: User): AppRoute[] => {
  return routes.reduce((acc: AppRoute[], route) => {
    // Check if parent is allowed
    if (!isRouteAllowed(route, user)) {
      return acc;
    }

    // If route has children, check them recursively
    if (route.subLinks && route.subLinks.length > 0) {
      const visibleSubLinks = filterRoutesByUser(route.subLinks, user);

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
