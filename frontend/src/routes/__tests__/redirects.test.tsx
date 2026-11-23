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

import { render, screen, waitFor } from '@testing-library/react';
import { createMemoryRouter, Outlet, RouterProvider } from 'react-router-dom';
import { describe, expect, test, vi } from 'vitest';

import { router } from '@/routes';

// Only the route table is under test: the app shell, the auth loaders and the
// pages themselves are stubbed. The route wrapper names the route it stands for.
vi.mock('@/routes/loaders', () => ({
  requireAuthLoader: () => null,
  requireAuthOnlyLoader: () => null,
}));
vi.mock('@/app/RootLayout', () => ({ default: () => <Outlet /> }));
vi.mock('@/app/WithPageWrapper', () => ({ default: () => <Outlet /> }));
vi.mock('@/components/layout/ProtectedRoute', () => ({
  default: ({ route }: { route: { path: string } }) => <h1>Page: {route.path}</h1>,
}));
vi.mock('@/pages/ErrorPage/ErrorPage', () => ({ default: () => <h1>Error page</h1> }));
// The page the redirect leads to, lazy-loaded by its route.
vi.mock('@/pages/Displays/Displays/Displays', () => ({ default: () => <p>Displays page</p> }));

const open = (url: string) => {
  const memoryRouter = createMemoryRouter(router.routes, { initialEntries: [url] });
  render(<RouterProvider router={memoryRouter} />);
  return memoryRouter;
};

// Regression cover for xibosignage/xibo#3933: the Displays page moved from
// /displays/displays to /displays/all-displays, and bookmarks and links to the
// old URL must still reach it.
describe('Routes - the old Displays URL', () => {
  test('/displays/displays redirects to /displays/all-displays', async () => {
    const memoryRouter = open('/displays/displays');

    await waitFor(() => {
      expect(memoryRouter.state.location.pathname).toBe('/displays/all-displays');
    });
  });

  test('the redirect lands on the Displays page', async () => {
    open('/displays/displays');

    expect(await screen.findByRole('heading', { name: 'Page: all-displays' })).toBeInTheDocument();
  });

  // The redirect replaces the history entry, so Back doesn't bounce the user
  // straight into it again.
  test('the redirect replaces the old URL in the history', async () => {
    const memoryRouter = open('/displays/displays');

    await waitFor(() => {
      expect(memoryRouter.state.location.pathname).toBe('/displays/all-displays');
    });
    expect(memoryRouter.state.historyAction).toBe('REPLACE');
  });

  // Pending-connect notifications created before the move link to the old URL with
  // ?addCode=, which reopens Add Display with the code filled in.
  test('the redirect keeps the query string and hash', async () => {
    const memoryRouter = open('/displays/displays?addCode=AB12&displayName=Lobby#top');

    await waitFor(() => {
      expect(memoryRouter.state.location.pathname).toBe('/displays/all-displays');
    });
    expect(memoryRouter.state.location.search).toBe('?addCode=AB12&displayName=Lobby');
    expect(memoryRouter.state.location.hash).toBe('#top');
  });
});
