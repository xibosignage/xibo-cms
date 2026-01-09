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

function getTarget(route: AppRoute, isCollapsed: boolean) {
  // collapsed + has children => go to first child
  if (isCollapsed && route.subLinks?.length) {
    const first = route.subLinks[0];

    return {
      isExternal: !!first?.externalURL,
      to: first?.externalURL ?? `/${route.path}/${first?.path}`,
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
  const activeClasses = 'nav_link text-white dark:text-black focus:outline-hidden group';
  const inactiveClasses =
    'nav_link focus:outline-hidden focus:text-gray-400 dark:text-neutral-400 dark:hover:text-neutral-500 dark:focus:text-neutral-500 group';

  // Changing styles for different states
  function getSidebarItemClasses({ isCollapsed, isOpen, isActive }: SidebarItemClassProps) {
    return [
      'flex cursor-pointer py-2',
      'hover:bg-white/10 hover:text-white text-xibo-blue-100',

      isCollapsed ? 'px-3 w-fit justify-center' : 'px-3 w-full justify-between',

      isOpen && !isCollapsed ? 'bg-white/10 rounded-t-sm rounded-b-0' : 'rounded-sm',

      isActive && isCollapsed && 'bg-white/10 border-b-2 border-white/20',
    ]
      .filter(Boolean)
      .join(' ');
  }

  const { isExternal, to } = getTarget(route, isCollapsed);

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
        <button onClick={() => toggleMenu(route.path)} className="text-white ml-2 cursor-pointer">
          <ChevronDown size={16} className={`transition-transform ${isOpen ? 'rotate-180' : ''}`} />
        </button>
      )}
    </div>
  );
}
