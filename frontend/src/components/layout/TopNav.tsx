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

import { Menu } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { matchPath } from 'react-router-dom';

import CloudDropdown from './CloudDropdown';
import NotificationDropdown from './NotificationDropdown';
import UserMenu from './UserMenu/UserMenu';

import HelpIconMenu from '@/components/help/HelpIconMenu';
import { APP_ROUTES } from '@/config/appRoutes';

interface TopNavProps {
  pathName: string;
  onToggleMobileDrawer: () => void;
}

function findActiveRoute(pathname: string) {
  for (const route of APP_ROUTES) {
    // Match top-level route
    if (matchPath({ path: `/${route.path}`, end: false }, pathname)) {
      return route;
    }

    // Match sub-routes
    if (route.subLinks) {
      for (const sub of route.subLinks) {
        if (matchPath({ path: `/${route.path}/${sub.path}`, end: false }, pathname)) {
          return sub;
        }
      }
    }
  }

  return null;
}

export default function TopNav({ pathName, onToggleMobileDrawer: onToggleSidebar }: TopNavProps) {
  const { t } = useTranslation();

  const activeRoute = findActiveRoute(pathName);
  const pageTitle = activeRoute?.labelKey ?? 'Dashboard';

  return (
    <header className="sticky top-0 w-full px-5 py-4 bg-gray-50 dark:bg-gray-800 text-gray-800 dark:text-gray-200">
      <nav className="flex flex-row h-9.5 items-center justify-between">
        <div className="flex items-center gap-3">
          <button
            type="button"
            onClick={onToggleSidebar}
            className="md:hidden center rounded-md p-1.5 text-xibo-blue-600 outline outline-xibo-blue-600"
          >
            <Menu size={14} />
          </button>
          <div className="flex align-middle font-semibold text-[16px]">{t(pageTitle)}</div>
        </div>
        <div className="center gap-x-2">
          {/* Self-hides unless super admin on an Xibo-themed Cloud instance */}
          <CloudDropdown />
          <div className="h-9.5 w-9.5 center flex md:hidden">
            <HelpIconMenu />
          </div>
          <div className="h-9.5 w-9.5 center flex md:hidden">
            <NotificationDropdown />
          </div>
          <div className="h-9.5 w-9.5 center flex md:hidden">
            <UserMenu />
          </div>
        </div>
      </nav>
    </header>
  );
}
