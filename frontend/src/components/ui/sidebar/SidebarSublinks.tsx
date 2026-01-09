import { NavLink, useLocation } from 'react-router-dom';
import { AppRoute } from '@/config/appRoutes';
import { useTranslation } from 'react-i18next';

interface SidebarSubLinksProps {
  route: AppRoute;
  isOpen: boolean;
  isCollapsed: boolean;
}

export function SidebarSubLinks({ route, isOpen, isCollapsed }: SidebarSubLinksProps) {
  const { t } = useTranslation();
  const location = useLocation();

  return (
    <>
      {!isCollapsed && route.subLinks && (
        <div
          className={`overflow-hidden transition-all duration-500 ease-in-out w-full ${
            isOpen ? 'max-h-96 opacity-100' : 'max-h-0 opacity-0'
          }`}
        >
          <div className="flex flex-col w-full px-6 py-2 bg-black/10 border-y-2 border-white/20">
            {route.subLinks.map((sub) => {
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
                  // TODO: Update path once routes are fixed
                  to={sub.path}
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
        </div>
      )}
    </>
  );
}
