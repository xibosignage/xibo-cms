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

import type { TabNavItem } from '@/components/ui/TabNav';
import { APP_ROUTES } from '@/config/appRoutes';
import { useUserContext } from '@/context/UserContext';
import { useServiceVisibility } from '@/hooks/useCmsVisibility';
import { filterRoutesByUser } from '@/utils/permissions';

export function useFilteredTabs(parentRoutePath: string): TabNavItem[] {
  const { user } = useUserContext();
  const getServiceVisibility = useServiceVisibility();

  const parentRoute = APP_ROUTES.find((route) => route.path === parentRoutePath);

  if (!parentRoute || !parentRoute.subLinks || !user) {
    return [];
  }

  const authorizedSubLinks = filterRoutesByUser(
    parentRoute.subLinks,
    user,
    getServiceVisibility,
  ).filter((route) => !route.hideFromMenu);

  return authorizedSubLinks.map((subLink) => {
    const absolutePath = `/${parentRoute.path}/${subLink.path}`;

    return {
      labelKey: subLink.labelKey,
      path: absolutePath,
      externalURL: subLink.externalURL,
    };
  });
}
