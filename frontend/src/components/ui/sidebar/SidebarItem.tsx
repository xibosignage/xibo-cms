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

import { ChevronDown } from 'lucide-react';
import { NavLink } from 'react-router-dom';

import type { AppRoute } from '@/config/appRoutes';

interface SidebarItemProps {
  route: AppRoute;
  isCollapsed: boolean;
  isOpen: boolean;
  isActive: boolean;
  label: string | null;
  toggleMenu: (path: string) => void;
}

interface SidebarItemClassProps {
  isCollapsed: boolean;
  isOpen: boolean;
  isActive: boolean;
}

function getTarget(route: AppRoute) {
  // if route has sublinks, and no path, go to the first child
  if (route.subLinks && route.subLinks.length > 0) {
    const firstChild = route.subLinks[0];

    if (!firstChild) {
      return { isExternal: false, to: `/${route.path}` };
    }

    if (firstChild.externalURL) {
      return {
        isExternal: true,
        to: firstChild.externalURL,
      };
    }

    return {
      isExternal: false,
      to: `/${route.path}/${firstChild.path}`,
    };
  }

  // normal route
  if (route.externalURL) {
    return { isExternal: true, to: route.externalURL };
  }

  return { isExternal: false, to: `/${route.path}` };
}

export function SidebarItem({
  route,
  isCollapsed,
  isOpen,
  isActive,
  label,
  toggleMenu,
}: SidebarItemProps) {
  const activeClasses =
    'font-medium text-sm w-full flex items-center flex-row text-white dark:text-black focus:outline-hidden group';
  const inactiveClasses =
    'font-medium text-sm w-full flex items-center flex-row focus:outline-hidden focus:text-gray-400 dark:text-neutral-400 dark:hover:text-neutral-500 dark:focus:text-neutral-500 group';

  // Changing styles for different states
  function getSidebarItemClasses({ isCollapsed, isOpen, isActive }: SidebarItemClassProps) {
    return [
      'flex cursor-pointer py-2 relative',
      'hover:bg-white/10 hover:text-white text-xibo-blue-100',

      isCollapsed ? 'px-3 w-fit justify-center' : 'px-3 w-full justify-between',

      isOpen && !isCollapsed ? 'bg-white/10 rounded-t-sm rounded-b-0' : 'rounded-sm',

      isActive && isCollapsed && 'bg-white/10 border-b-2 border-white/20',
    ]
      .filter(Boolean)
      .join(' ');
  }

  const { isExternal, to } = getTarget(route);

  const content = (
    <>
      {route.icon && <route.icon width={20} height={20} className={isCollapsed ? '' : 'mr-2'} />}
      {!isCollapsed && <span className="flex-1">{label}</span>}
    </>
  );

  return (
    <div
      className={getSidebarItemClasses({
        isCollapsed,
        isOpen,
        isActive,
      })}
    >
      {isExternal ? (
        <a href={to} className={isActive ? activeClasses : inactiveClasses}>
          {content}
        </a>
      ) : (
        <NavLink to={to} className={({ isActive }) => (isActive ? activeClasses : inactiveClasses)}>
          {content}
        </NavLink>
      )}

      {!isCollapsed && route.subLinks && (
        <button
          onClick={(e) => {
            e.preventDefault();
            toggleMenu(route.path);
          }}
          className="text-white absolute right-0 top-0 size-9 z-10 flex items-center justify-center cursor-pointer"
        >
          <ChevronDown size={16} className={`transition-transform ${isOpen ? 'rotate-180' : ''}`} />
        </button>
      )}
    </div>
  );
}
