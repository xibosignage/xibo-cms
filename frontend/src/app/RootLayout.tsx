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

import { ChevronLeftSquare, ChevronRightSquare } from 'lucide-react';
import { useState } from 'react';
import { Outlet, useLocation, useLoaderData } from 'react-router-dom';

import { SessionExpiredModal } from '@/components/auth/SessionExpiredModal';
import SideBar from '@/components/layout/SideBar';
import TopNav from '@/components/layout/TopNav';
import { BrandingProvider } from '@/context/BrandingContext';
import { UserProvider } from '@/context/UserContext';
import { usePreline } from '@/hooks/usePreline';
import NotificationInterruptCheck from '@/pages/Notification/components/NotificationInterruptCheck';
import type { User } from '@/types/user';

export default function RootLayout() {
  const { pathname } = useLocation();
  const [isCollapsed, setIsCollapsed] = useState(false);
  const [openMobileDrawer, setOpenMobileDrawer] = useState(false);
  const { user } = useLoaderData() as { user: User | null };

  // Init preline
  usePreline();

  return (
    <BrandingProvider branding={user?.branding}>
      <UserProvider initialUser={user}>
        <div className="flex h-screen w-full overflow-hidden bg-gray-50 dark:bg-neutral-900">
          {/* Desktop Sidebar Drawer */}
          <div className="relative md:block hidden flex-none">
            <aside
              className={`h-full bg-xibo-blue-800 dark:bg-orange-300 transition-[width] duration-300 ease-in-out overflow-clip whitespace-nowrap will-change-[width]
            ${isCollapsed ? 'w-21' : 'w-60'}
          `}
            >
              <SideBar isCollapsed={isCollapsed} />
            </aside>
            {/* Toggle button */}
            <button
              onClick={() => setIsCollapsed(!isCollapsed)}
              aria-label={isCollapsed ? 'Expand sidebar' : 'Collapse sidebar'}
              className="absolute top-5 -right-3 z-10 md:flex hidden items-center justify-center w-6 h-6 rounded-lg bg-xibo-blue-800 dark:bg-orange-300 text-xibo-white dark:text-black transition-colors hover:bg-xibo-blue-700 dark:hover:bg-orange-400 cursor-pointer"
            >
              {isCollapsed ? <ChevronRightSquare size={16} /> : <ChevronLeftSquare size={16} />}
            </button>
          </div>
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
              closeMobileDrawer={() => setOpenMobileDrawer(!openMobileDrawer)}
            />
          </div>

          <div className="flex-1 flex flex-col min-w-0 transition-all duration-300 ease-in-out">
            <TopNav
              pathName={pathname}
              onToggleMobileDrawer={() => setOpenMobileDrawer(!openMobileDrawer)}
            />
            <main className="flex-1 flex flex-col min-h-0 bg-white overflow-auto dark:bg-black">
              <Outlet />
            </main>
          </div>

          <SessionExpiredModal />
          <NotificationInterruptCheck />
        </div>
      </UserProvider>
    </BrandingProvider>
  );
}
