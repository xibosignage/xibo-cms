import { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { useLocation } from 'react-router-dom';

import { SidebarHeader } from '../ui/sidebar/SidebarHeader';
import { SidebarItem } from '../ui/sidebar/SidebarItem';
import { SidebarPopup } from '../ui/sidebar/SidebarPopup';
import { SidebarSubLinks } from '../ui/sidebar/SidebarSublinks';

import { APP_ROUTES } from '@/config/appRoutes';
import { isRouteActive } from '@/hooks/sidebar';

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
