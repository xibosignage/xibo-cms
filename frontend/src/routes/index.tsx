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

import { createBrowserRouter, Navigate } from 'react-router-dom';
import type { RouteObject } from 'react-router-dom';

import { requireAuthLoader, requireAuthOnlyLoader } from './loaders';

import RootLayout from '@/app/RootLayout';
import WithPageWrapper from '@/app/WithPageWrapper';
import ProtectedRoute from '@/components/layout/ProtectedRoute';
import { APP_ROUTES, DEFAULT_INTERNAL_ROUTE } from '@/config/appRoutes';
import type { AppRoute } from '@/config/appRoutes';
import { getRouterBasename, withPublicPath } from '@/config/publicPath';
import ErrorPage from '@/pages/ErrorPage/ErrorPage';

const flattenRoutes = (routes: AppRoute[], base = ''): RouteObject[] => {
  return routes.reduce((acc: RouteObject[], route: AppRoute) => {
    const fullPath = base ? `${base}/${route.path}` : route.path;

    if (route.lazy) {
      acc.push({
        path: fullPath,
        handle: { title: route.labelKey },
        element: <ProtectedRoute route={route} />,
        children: [
          {
            index: true,
            lazy: route.lazy,
          },
        ],
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
      loader: requireAuthLoader,
      errorElement: <ErrorPage />,
      // App loader
      hydrateFallbackElement: <></>,
      children: [
        // Protected
        {
          loader: requireAuthLoader,
          element: <WithPageWrapper />,
          children: [
            // For now it redirect to media page
            {
              index: true,
              element: <Navigate to={DEFAULT_INTERNAL_ROUTE} replace />,
            },
            ...flattenRoutes(APP_ROUTES),
          ],
        },
      ],
    },
    {
      path: 'user/force-change-password',
      loader: requireAuthOnlyLoader,
      errorElement: <ErrorPage />,
      lazy: () =>
        import('@/pages/User/ForceChangePassword/ForceChangePassword').then((m) => ({
          Component: m.default,
        })),
    },
    {
      path: 'application/authorize',
      loader: requireAuthOnlyLoader,
      errorElement: <ErrorPage />,
      lazy: () =>
        import('@/pages/Administration/Applications/Authorize/AuthorizeApplication').then((m) => ({
          Component: m.default,
        })),
    },
    {
      path: 'design/layout/:id/editor',
      loader: requireAuthLoader,
      hydrateFallbackElement: <></>,
      errorElement: <ErrorPage />,
      lazy: () =>
        import('@/pages/Design/Layouts/LayoutEditorHost').then((m) => ({
          Component: m.default,
        })),
    },
    {
      path: 'login',
      loader: ({ request }) => {
        // Force to go to server login page. React Router resolves requireAuthLoader's
        // redirect('/login?priorRoute=...') as an internal client-side navigation to
        // this route, so forward its query string (priorRoute) onto the real top-level
        // navigation — otherwise it's silently dropped before ever reaching PHP.
        const { search } = new URL(request.url);
        window.location.href = withPublicPath('login') + search;
        return null;
      },
    },
    {
      path: '*',
      lazy: () => import('@/pages/NotFound/NotFound').then((m) => ({ Component: m.default })),
    },
  ],
  {
    basename: getRouterBasename(),
  },
);
