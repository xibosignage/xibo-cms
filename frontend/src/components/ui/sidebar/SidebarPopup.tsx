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

import {
  useFloating,
  autoUpdate,
  flip,
  shift,
  useHover,
  useFocus,
  useDismiss,
  useInteractions,
  FloatingPortal,
  safePolygon,
} from '@floating-ui/react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { NavLink, useLocation } from 'react-router-dom';

import type { AppRoute } from '@/config/appRoutes';

interface SidebarPopupProps {
  route: AppRoute;
  isCollapsed: boolean;
  children: React.ReactNode;
}

export function SidebarPopup({ route, isCollapsed, children }: SidebarPopupProps) {
  const { t } = useTranslation();
  const location = useLocation();
  const [open, setOpen] = useState(false);

  const { refs, floatingStyles, context } = useFloating({
    open: open,
    onOpenChange: setOpen,
    placement: 'right-start',
    whileElementsMounted: autoUpdate,
    middleware: [flip(), shift()],
  });

  const hover = useHover(context, {
    enabled: isCollapsed,
    handleClose: safePolygon({ buffer: 1 }),
    delay: { close: 150 },
  });
  const focus = useFocus(context, { enabled: isCollapsed });
  const dismiss = useDismiss(context);
  const { getReferenceProps, getFloatingProps } = useInteractions([hover, focus, dismiss]);

  return (
    <>
      <div ref={refs.setReference} {...getReferenceProps()} className="w-full flex justify-center">
        {children}
      </div>

      <FloatingPortal>
        {open && isCollapsed && (
          <div
            ref={refs.setFloating}
            style={floatingStyles}
            {...getFloatingProps()}
            className="z-100 min-w-50 text-white bg-xibo-blue-800 rounded-e-md shadow-lg"
          >
            {/* Parent label */}
            <a
              href={!route.subLinks ? route.externalURL || route.path : ''}
              className={`block px-4 py-2 text-sm bg-white/10 ${route.subLinks ? 'pointer-events-none' : 'cursor-pointer'}`}
            >
              {t(route.labelKey)}
            </a>

            {route.subLinks && (
              <div className="flex flex-col w-full gap-1 px-6 py-2 bg-black/10 border-white/20">
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
            )}
          </div>
        )}
      </FloatingPortal>
    </>
  );
}
