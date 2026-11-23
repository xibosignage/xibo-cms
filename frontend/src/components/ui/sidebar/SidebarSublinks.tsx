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

import { useTranslation } from 'react-i18next';
import { NavLink, useLocation } from 'react-router-dom';

import type { AppRoute } from '@/config/appRoutes';

interface SidebarSubLinksProps {
  route: AppRoute;
  isOpen: boolean;
  contentHidden: boolean;
}

export function SidebarSubLinks({ route, isOpen, contentHidden }: SidebarSubLinksProps) {
  const { t } = useTranslation();
  const location = useLocation();

  return (
    <>
      {!contentHidden && route.subLinks && (
        <div
          className={`overflow-hidden transition-all duration-500 ease-in-out w-full ${
            isOpen ? 'max-h-200 opacity-100' : 'max-h-0 opacity-0'
          }`}
        >
          <div className="flex flex-col w-full px-6 gap-1 py-2 bg-black/10 border-y-2 border-(--sidebar-overlay-strong)">
            {route.subLinks.map((sub) => {
              const fullPath = `/${route.path}/${sub.path}`;
              const isSubActive =
                location.pathname === `/${route.path}/${sub.path}` ||
                location.pathname === `/${sub.path}`;

              return sub.externalURL ? (
                <a
                  key={sub.labelKey}
                  href={sub.externalURL}
                  className={`text-sm px-3 py-2 rounded transition-colors hover:bg-(--sidebar-overlay) ${
                    isSubActive
                      ? 'text-(--sidebar-fg) bg-(--sidebar-overlay)'
                      : 'text-(--sidebar-fg-muted)'
                  }`}
                >
                  {t(sub.labelKey)}
                </a>
              ) : (
                <NavLink
                  key={sub.path}
                  to={fullPath}
                  className={({ isActive }) =>
                    `text-sm px-3 py-2 rounded transition-colors hover:bg-(--sidebar-overlay) ${
                      isActive
                        ? 'text-(--sidebar-fg) bg-(--sidebar-overlay)'
                        : 'text-(--sidebar-fg-muted)'
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
