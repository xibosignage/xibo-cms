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

import { QueryClientProvider } from '@tanstack/react-query';
import { render, screen } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';

import IconDashboard from '../../IconDashboard';

import { UserProvider } from '@/context/UserContext';
import { testQueryClient } from '@/setupTests';
import { PERSONAS } from '@/testUtils/personas';
import type { User } from '@/types/user';

// Renders the Icon Dashboard for a given user. Users come from the shared persona registry
// (no local fixture): the cards depend only on the user's features and rank.
// Call testQueryClient.clear() and vi.clearAllMocks() in beforeEach first.
export const renderIconDashboard = (user: User | null = PERSONAS.superAdmin) =>
  render(
    <QueryClientProvider client={testQueryClient}>
      <UserProvider initialUser={user}>
        <MemoryRouter>
          <IconDashboard />
        </MemoryRouter>
      </UserProvider>
    </QueryClientProvider>,
  );

// Every card in DASHBOARD_ITEMS order, with where it goes. About is a button that opens a
// window, not a link.
export const LINK_CARDS = [
  { name: 'Schedule', href: '/schedule/events' },
  { name: 'Templates', href: '/design/templates' },
  { name: 'Layouts', href: '/design/layout' },
  { name: 'Users', href: '/administration/users' },
  { name: 'Library', href: '/library/media' },
] as const;

export type CardName = (typeof LINK_CARDS)[number]['name'] | 'About';

// The names of the cards on screen, in the order they appear.
export const visibleCards = (): string[] =>
  [...screen.queryAllByRole('link'), ...screen.queryAllByRole('button', { name: 'About' })]
    .sort((a, b) => (a.compareDocumentPosition(b) & Node.DOCUMENT_POSITION_FOLLOWING ? -1 : 1))
    .map((el) => el.textContent ?? '');
