import { AppRoute } from '@/config/appRoutes';

export function isRouteActive(route: AppRoute, pathname: string): boolean {
  if (pathname === `/${route.path}`) return true;

  return (
    route.subLinks?.some(
      (sub) => pathname === `/${sub.path}` || pathname === `/${route.path}/${sub.path}`,
    ) ?? false
  );
}

export function hasActiveChild(route: AppRoute, pathname: string) {
  return route.subLinks?.some(
    (sub) => pathname === `/${sub.path}` || pathname === `/${route.path}/${sub.path}`,
  );
}
