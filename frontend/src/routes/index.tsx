import { createBrowserRouter, redirect } from 'react-router-dom';
import type { RouteObject } from 'react-router-dom';

import { requireAuthLoader } from './loaders';

import RootLayout from '@/app/RootLayout';
import WithPageWrapper from '@/app/WithPageWrapper';
import { APP_ROUTES } from '@/config/appRoutes';
import type { AppRoute } from '@/config/appRoutes';

const flattenRoutes = (routes: AppRoute[], base = ''): RouteObject[] => {
  return routes.reduce((acc: RouteObject[], route: AppRoute) => {
    const fullPath = base ? `${base}/${route.path}` : route.path;

    if (route.lazy) {
      acc.push({
        path: fullPath,
        lazy: route.lazy,
      });
    }

    if (route.subLinks) {
      acc.push(...flattenRoutes(route.subLinks, fullPath));
    }

    return acc;
  }, []);
};

export const router = createBrowserRouter(
  [
    {
      path: '/',
      element: <RootLayout />,
      children: [
        // Protected
        {
          loader: requireAuthLoader,
          element: <WithPageWrapper />,
          children: [
            // For now it redirect to media page
            { index: true, loader: () => redirect('/library/media') },
            ...flattenRoutes(APP_ROUTES),
          ],
        },
      ],
    },
    {
      path: '*',
      lazy: () => import('@/pages/NotFound/NotFound').then((m) => ({ Component: m.default })),
    },
  ],
  {
    basename: '/prototype',
  },
);
