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

import DisplayProfile from '../../DisplayProfile';
import { mockDisplayProfile, mockUser, queryKeys } from '../fixtures/displayProfile';

import { UserProvider } from '@/context/UserContext';
import { testQueryClient } from '@/setupTests';

export const renderDisplayProfilePage = ({ hydrated = true }: { hydrated?: boolean } = {}) => {
  if (hydrated) {
    testQueryClient.setQueryData(queryKeys.displayProfilePage, null);
  }
  return render(
    <QueryClientProvider client={testQueryClient}>
      <UserProvider initialUser={mockUser}>
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

// Opens the per-row actions dropdown (the "More actions" button).
export const openRowMenu = async () => {
  await screen.findByText(mockDisplayProfile.name);
  fireEvent.click(screen.getByRole('button', { name: /more actions/i }));
};

// Opens the row menu then clicks one of its items (Copy / Delete).
export const clickRowMenuItem = async (name: RegExp) => {
  await openRowMenu();
  fireEvent.click(await screen.findByRole('button', { name }));
};
