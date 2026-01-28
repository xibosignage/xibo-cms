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

import { useTranslation } from 'react-i18next';
import { NavLink, useLocation } from 'react-router-dom';

import type { AppRoute } from '@/config/appRoutes';

interface SidebarSubLinksProps {
  route: AppRoute;
  isOpen: boolean;
  isCollapsed: boolean;
}

export function SidebarSubLinks({ route, isOpen, isCollapsed }: SidebarSubLinksProps) {
  const { t } = useTranslation();
  const location = useLocation();

  return (
    <>
      {!isCollapsed && route.subLinks && (
        <div
          className={`overflow-hidden transition-all duration-500 ease-in-out w-full ${
            isOpen ? 'max-h-96 opacity-100' : 'max-h-0 opacity-0'
          }`}
        >
          <div className="flex flex-col w-full px-6 gap-1 py-2 bg-black/10 border-y-2 border-white/20">
            {route.subLinks.map((sub) => {
              const fullPath = `/${route.path}/${sub.path}`;
              const isSubActive =
                location.pathname === `/${route.path}/${sub.path}` ||
                location.pathname === `/${sub.path}`;

              return sub.externalURL ? (
                <a
                  key={sub.labelKey}
                  href={sub.externalURL}
                  className={`text-sm px-3 py-2 rounded transition-colors hover:bg-white/10 ${
                    isSubActive ? 'text-white bg-white/10' : 'text-xibo-blue-100'
                  }`}
                >
                  {t(sub.labelKey)}
                </a>
              ) : (
                <NavLink
                  key={sub.path}
                  to={fullPath}
                  className={({ isActive }) =>
                    `text-sm px-3 py-2 rounded transition-colors hover:bg-white/10 ${
                      isActive ? 'text-white bg-white/10' : 'text-xibo-blue-100'
                    }`
                  }
                >
                  {t(sub.labelKey)}
                </NavLink>
              );
            })}
          </div>
        </div>
      )}
    </>
  );
}
