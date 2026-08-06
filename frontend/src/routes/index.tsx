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
import { getRouterBasename, publicPath } from '@/config/publicPath';
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
      errorElement: <ErrorPage />,
      loader: requireAuthLoader,
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
      errorElement: <ErrorPage />,
      hydrateFallbackElement: <></>,
      lazy: () =>
        import('@/pages/Design/Layouts/LayoutEditorHost').then((m) => ({
          Component: m.default,
        })),
    },
    {
      path: 'login',
      loader: () => {
        // Force to go to server login page
        window.location.href = `${publicPath}login`;
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
