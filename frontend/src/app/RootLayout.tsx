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

import { useState } from 'react';
import { Outlet, useLocation } from 'react-router-dom';

import SideBar from '@/components/layout/SideBar';
import TopNav from '@/components/layout/TopNav';
import { usePreline } from '@/hooks/usePreline';

export default function RootLayout() {
  const { pathname } = useLocation();
  const [isCollapsed, setIsCollapsed] = useState(false);
  const [openMobileDrawer, setOpenMobileDrawer] = useState(false);

  // Init preline
  usePreline();

  return (
    <div className="flex h-screen w-full overflow-hidden bg-gray-50 dark:bg-neutral-900">
      {/* Desktop Sidebar Drawer */}
      <aside
        className={`flex-none bg-xibo-blue-800 dark:bg-orange-300 transition-[width] duration-300 ease-in-out md:block hidden relative
          ${isCollapsed ? 'w-[84px]' : 'w-60'}
        `}
      >
        <SideBar isCollapsed={isCollapsed} toggleSidebar={() => setIsCollapsed(!isCollapsed)} />
      </aside>
      {/* Mobile drawer */}
      <div
        className={`
          fixed inset-y-0 left-0 z-40 w-full
          bg-xibo-blue-800 dark:bg-orange-300
          transform transition-transform duration-300 ease-in-out
          md:hidden sm:px-8 px-0 overflow-visible
          ${openMobileDrawer ? 'translate-x-0' : '-translate-x-full'}
        `}
      >
        <SideBar
          isCollapsed={false}
          toggleSidebar={() => setIsCollapsed(!isCollapsed)}
          closeMobileDrawer={() => setOpenMobileDrawer(!openMobileDrawer)}
        />
      </div>

      <div className="flex-1 flex flex-col min-w-0">
        <TopNav
          pathName={pathname}
          onToggleMobileDrawer={() => setOpenMobileDrawer(!openMobileDrawer)}
        />
        <main className="flex-1 flex flex-col min-h-0 p-5 bg-white dark:bg-black">
          <Outlet />
        </main>
      </div>
    </div>
  );
}
