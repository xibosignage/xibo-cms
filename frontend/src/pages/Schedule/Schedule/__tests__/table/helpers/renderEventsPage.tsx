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
import { render, screen, fireEvent } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';

import Events from '../../../Events';
import { mockEvent } from '../../fixtures/event';
import { mockUser } from '../../fixtures/user';

import { UserProvider } from '@/context/UserContext';
import { testQueryClient } from '@/setupTests';
import type { User } from '@/types/user';

// -----------------------------------------------------------------------------
// Helpers - parallel to layoutTestUtils.openEditModal etc. They expect:
//   - the DataTableRowActions stub from Layouts.delete.modal.test.tsx (so each
//     row action is a plain button)
//   - a row with the given event name to already be visible.
// -----------------------------------------------------------------------------
export const openEditModal = async () => {
  await screen.findByText(mockEvent.name as string);
  fireEvent.click(screen.getAllByRole('button', { name: 'Edit' })[0]!);
  return screen.findByRole('dialog');
};

export const openDeleteModal = async (eventName: string = mockEvent.name as string) => {
  await screen.findByText(eventName);
  fireEvent.click(screen.getByRole('button', { name: 'More actions' }));
  fireEvent.click(await screen.findByRole('button', { name: 'Delete' }));
  return screen.findByRole('dialog');
};

export const openCopyModal = async () => {
  await screen.findByText(mockEvent.name as string);
  fireEvent.click(screen.getByRole('button', { name: 'More actions' }));
  fireEvent.click(await screen.findByRole('button', { name: 'Make a Copy' }));
  return screen.findByRole('dialog', { name: 'Copy Event' });
};

// -----------------------------------------------------------------------------
// Render wrapper - provides all required context providers.
//
// Tests that need the table to be live should use the default; tests that
// specifically want to assert the unhydrated loading pulse should pass
// `{ hydrate: false }` to skip the cache pre-seed (which is what flips
// isHydrated to true the moment React Query sees a value).
// -----------------------------------------------------------------------------
export const renderEventsPage = (user?: User, { hydrate = true }: { hydrate?: boolean } = {}) => {
  const resolvedUser = user ?? mockUser;
  if (hydrate) {
    testQueryClient.setQueryData(['userPref', 'event_page'], null);
  }
  return render(
    <QueryClientProvider client={testQueryClient}>
      <UserProvider initialUser={resolvedUser}>
        <MemoryRouter>
          <Events />
        </MemoryRouter>
      </UserProvider>
    </QueryClientProvider>,
  );
};
