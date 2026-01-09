import { useState, useEffect } from 'react';
import { Outlet, useLocation } from 'react-router-dom';

import SideBar from '@/components/layout/SideBar';
import TopNav from '@/components/layout/TopNav';

export default function RootLayout() {
  const { pathname } = useLocation();
  const [isCollapsed, setIsCollapsed] = useState(false);
  const [openMobileDrawer, setOpenMobileDrawer] = useState(false);

  useEffect(() => {
    // @ts-expect-error - Preline attaches this to window
    window.HSStaticMethods?.autoInit?.();
  }, [pathname]);

  const toggleSidebar = () => {
    setOpenMobileDrawer(!isCollapsed);
  };

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
        <main className="flex-1 overflow-y-auto p-4 bg-white dark:bg-black">
          <Outlet />
        </main>
      </div>
    </div>
  );
}
