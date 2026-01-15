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

import { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { useLocation } from 'react-router-dom';

import { SidebarHeader } from '../ui/sidebar/SidebarHeader';
import { SidebarItem } from '../ui/sidebar/SidebarItem';
import { SidebarPopup } from '../ui/sidebar/SidebarPopup';
import { SidebarSubLinks } from '../ui/sidebar/SidebarSublinks';

import { APP_ROUTES } from '@/config/appRoutes';
import { isRouteActive } from '@/utils/sidebar';

interface SidebarMenuProps {
  isCollapsed: boolean;
  toggleSidebar: () => void;
  closeMobileDrawer?: () => void;
}

export default function SidebarMenu({
  isCollapsed,
  toggleSidebar,
  closeMobileDrawer,
}: SidebarMenuProps) {
  const { t } = useTranslation();
  const location = useLocation();
  const [openMenu, setOpenMenu] = useState<string | null>(null);

  useEffect(() => {
    APP_ROUTES.forEach((route) => {
      // Check if any sublink matches the current location
      if (
        route.subLinks?.some((sub) => {
          const fullPath = `/${route.path}/${sub.path}`;
          return location.pathname === fullPath || location.pathname.startsWith(`${fullPath}/`);
        })
      ) {
        setOpenMenu(route.path);
      }
    });
  }, [location.pathname]);

  const toggleMenu = (path: string) => {
    setOpenMenu((prev) => (prev === path ? null : path));
  };

  return (
    <div className={`flex flex-col gap-5 py-5  ${isCollapsed ? 'px-0' : 'p-5'}`}>
      <SidebarHeader
        isCollapsed={isCollapsed}
        toggleSidebar={toggleSidebar}
        closeMobileDrawer={closeMobileDrawer}
      />
      {/* Routes */}
      <div className={`flex flex-col gap-y-2 ${isCollapsed ? 'items-center' : 'items-start'}`}>
        {APP_ROUTES.map((route, index) => {
          const label = !isCollapsed ? t(route.labelKey) : null;
          const isOpen = openMenu === route.path;
          const isActive = isRouteActive(route, location.pathname);
          return (
            <div
              key={`${route.labelKey}-${index}`}
              className="relative group flex flex-col w-full items-center overflow-visible"
            >
              <SidebarItem
                route={route}
                isCollapsed={isCollapsed}
                isOpen={isOpen}
                isActive={isActive}
                label={label}
                toggleMenu={toggleMenu}
              />
              {/* Popup Hover */}
              <SidebarPopup route={route} isCollapsed={isCollapsed} />
              {/* Sublinks */}
              <SidebarSubLinks isCollapsed={isCollapsed} isOpen={isOpen} route={route} />
            </div>
          );
        })}
      </div>
    </div>
  );
}
