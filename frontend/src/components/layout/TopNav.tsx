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
.*/

import { Bell, Menu } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { matchPath } from 'react-router-dom';

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
      <nav className="flex flex-row h-[38px] items-center justify-between">
        <div className="flex align-middle font-semibold text-[16px]">{t(pageTitle)}</div>
        <div className="center gap-x-2">
          <div className="center gap-x-3 relative h-[38px] w-[38px]">
            <button>
              <Bell size={16} className="text-xibo-blue-600" />
            </button>
            {/* TODO: Update real badge data */}
            <div className="w-[18px] h-[18px] bg-xibo-blue-600 rounded-full text-[10px] text-white absolute top-0 right-0 flex items-center justify-center">
              2
            </div>
          </div>
          {/* TODO: Update with real user's initial */}
          <div className="h-[38px] w-[38px] center sm:flex hidden">
            <div className="bg-xibo-blue-300 h-[26px] w-[26px] text-[12px] center rounded-full text-xibo-blue-800 font-semibold">
              WA
            </div>
          </div>
          <button
            type="button"
            onClick={onToggleSidebar}
            className="md:hidden center rounded-md p-1.5 text-xibo-blue-600 outline outline-xibo-blue-600"
          >
            <Menu size={14} />
          </button>
        </div>
      </nav>
    </header>
  );
}
