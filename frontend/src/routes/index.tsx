/*
 * Xibo Digital Signage
 * Copyright (C) 2006-2026 Xibo Signage Ltd
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published
 * by the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 *
 * This program is also available under a commercial licence from
 * Xibo Signage Ltd. See https://xibosignage.com/terms
 */

import { createBrowserRouter, Navigate, useLocation } from 'react-router-dom';
import type { RouteObject } from 'react-router-dom';

import { requireAuthLoader, requireAuthOnlyLoader } from './loaders';

import RootLayout from '@/app/RootLayout';
import WithPageWrapper from '@/app/WithPageWrapper';
import ProtectedRoute from '@/components/layout/ProtectedRoute';
import { APP_ROUTES, DEFAULT_INTERNAL_ROUTE } from '@/config/appRoutes';
import type { AppRoute } from '@/config/appRoutes';
import { getRouterBasename, withPublicPath } from '@/config/publicPath';
import ErrorPage from '@/pages/ErrorPage/ErrorPage';
import PreferencesRedirect from '@/pages/Preferences/PreferencesRedirect';

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

/** Redirect that keeps the query string and hash, e.g. the ?addCode= deep link on a notification. */
function RedirectKeepingQuery({ to }: { to: string }) {
  const { search, hash } = useLocation();
  return <Navigate to={{ pathname: to, search, hash }} replace />;
}

const routes: RouteObject[] = [
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
          // Old Displays URL — redirect for any external/bookmarked links
          {
            path: 'displays/displays',
            element: <RedirectKeepingQuery to="/displays/all-displays" />,
          },
          // Preferences has no index page; open the first tab the user can see.
          {
            path: 'preferences',
            element: <PreferencesRedirect />,
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
];

export const router = createBrowserRouter(routes, {
  basename: getRouterBasename(),
});
