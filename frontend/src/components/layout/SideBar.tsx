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

import { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { useLocation } from 'react-router-dom';

import { SidebarHeader } from '../ui/sidebar/SidebarHeader';
import { SidebarItem } from '../ui/sidebar/SidebarItem';
import { SidebarPopup } from '../ui/sidebar/SidebarPopup';
import { SidebarSubLinks } from '../ui/sidebar/SidebarSublinks';

import { APP_ROUTES } from '@/config/appRoutes';
import { useUserContext } from '@/context/UserContext';
import { filterRoutesByUser } from '@/utils/permissions';
import { isRouteActive } from '@/utils/sidebar';

// Must match the aside's transition duration in RootLayout (duration-300).
const SIDEBAR_TRANSITION_MS = 300;

interface SidebarMenuProps {
  isCollapsed: boolean;
  closeMobileDrawer?: () => void;
}

export default function SidebarMenu({ isCollapsed, closeMobileDrawer }: SidebarMenuProps) {
  const { t } = useTranslation();
  const { user } = useUserContext();
  const location = useLocation();
  const [openMenus, setOpenMenus] = useState<Set<string>>(new Set());

  // Hides text labels, logo, chevrons, and sublinks.
  // On collapse: hides immediately. On expand: delays until container finishes animating.
  const [contentHidden, setContentHidden] = useState(isCollapsed);

  useEffect(() => {
    if (isCollapsed) {
      setContentHidden(true);
    } else {
      const timer = setTimeout(() => setContentHidden(false), SIDEBAR_TRANSITION_MS);
      return () => clearTimeout(timer);
    }
  }, [isCollapsed]);

  const visibleRoutes = !user
    ? []
    : filterRoutesByUser(APP_ROUTES, user)
        .filter((route) => !route.hideFromMenu)
        .map((route) => ({
          ...route,
          subLinks: route.subLinks?.filter((sub) => !sub.hideFromMenu),
        }));

  useEffect(() => {
    // Find the parent menu that contains the active link
    const activeParent = visibleRoutes.find((route) => {
      if (!route.subLinks?.length) {
        return false;
      }

      // Any route under this parent's path keeps it open, including child pages that are
      // hidden from the menu (hideFromMenu, e.g. individual report pages).
      if (
        location.pathname === `/${route.path}` ||
        location.pathname.startsWith(`/${route.path}/`)
      ) {
        return true;
      }

      // External-URL sublinks live outside the parent path, so match them directly.
      return route.subLinks.some(
        (sub) =>
          sub.externalURL &&
          (location.pathname === sub.externalURL ||
            location.pathname.startsWith(`${sub.externalURL}/`)),
      );
    });

    if (activeParent) {
      setOpenMenus((prev) => new Set([...prev, activeParent.path]));
    }
  }, [location.pathname, visibleRoutes]);

  const toggleMenu = (path: string) => {
    setOpenMenus((prev) => {
      const next = new Set(prev);
      if (next.has(path)) {
        next.delete(path);
      } else {
        next.add(path);
      }
      return next;
    });
  };

  return (
    <div className={`flex flex-col h-full py-5`}>
      <div className="flex-none mb-5 px-5">
        <SidebarHeader
          isCollapsed={isCollapsed}
          contentHidden={contentHidden}
          closeMobileDrawer={closeMobileDrawer}
        />
      </div>
      {/* Routes — scrollable */}
      <div
        className={`flex-1 overflow-y-auto overflow-x-hidden flex flex-col gap-y-2 ${isCollapsed ? 'items-center p-0' : 'items-start px-5'}`}
      >
        {visibleRoutes.map((route, index) => {
          const label = !contentHidden ? t(route.labelKey) : null;
          const isOpen = openMenus.has(route.path);
          const isActive = isRouteActive(route, location.pathname);
          return (
            <div
              key={`${route.labelKey}-${index}`}
              className="relative flex flex-col w-full items-center overflow-visible"
            >
              <SidebarPopup route={route} isCollapsed={isCollapsed}>
                <SidebarItem
                  route={route}
                  isCollapsed={isCollapsed}
                  contentHidden={contentHidden}
                  isOpen={isOpen}
                  isActive={isActive}
                  label={label}
                  toggleMenu={toggleMenu}
                />
              </SidebarPopup>
              {/* Sublinks */}
              <SidebarSubLinks contentHidden={contentHidden} isOpen={isOpen} route={route} />
            </div>
          );
        })}
      </div>
    </div>
  );
}
