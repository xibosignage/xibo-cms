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

import { QueryClientProvider } from '@tanstack/react-query';
import { render, screen, fireEvent } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { MemoryRouter } from 'react-router-dom';

import DisplayProfile from '../../DisplayProfile';
import { mockDisplayProfile, mockUser, queryKeys } from '../fixtures/displayProfile';

import { UserProvider } from '@/context/UserContext';
import { testQueryClient } from '@/setupTests';
import { clickMenuAction } from '@/testUtils/rowActions';
import type { User } from '@/types/user';

// Pass { user } to render as someone other than the default super admin.
export const renderDisplayProfilePage = ({
  hydrated = true,
  user = mockUser,
}: { hydrated?: boolean; user?: User } = {}) => {
  if (hydrated) {
    testQueryClient.setQueryData(queryKeys.displayProfilePage, null);
  }
  return render(
    <QueryClientProvider client={testQueryClient}>
      <UserProvider initialUser={user}>
        <MemoryRouter>
          <DisplayProfile />
        </MemoryRouter>
      </UserProvider>
    </QueryClientProvider>,
  );
};

export const openEditModal = async () => {
  await screen.findByText(mockDisplayProfile.name);
  fireEvent.click(screen.getByTitle('Edit'));
  await screen.findByPlaceholderText('Enter name');
};

// Opens the row menu then clicks one of its items (Copy / Delete).
export const clickRowMenuItem = (name: RegExp) =>
  clickMenuAction(userEvent.setup({ delay: null }), mockDisplayProfile.name, name);
