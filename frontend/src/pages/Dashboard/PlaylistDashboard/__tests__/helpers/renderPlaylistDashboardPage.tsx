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
import { render, screen, within } from '@testing-library/react';
import type { UserEvent } from '@testing-library/user-event';
import { MemoryRouter } from 'react-router-dom';

import PlaylistDashboard from '../../PlaylistDashboard';
import { mockUser } from '../fixtures/playlistDashboard';

import { UserProvider } from '@/context/UserContext';
import { testQueryClient } from '@/setupTests';
import type { User } from '@/types/user';

// Renders the Playlist Dashboard inside all required context providers.
// Call testQueryClient.clear() and vi.clearAllMocks() in beforeEach first.
// Page-level tests replace SelectDropdown with a native <select> (see Dashboard/DASHBOARD_TESTS.md).
export const renderPlaylistDashboardPage = (user: User = mockUser) =>
  render(
    <QueryClientProvider client={testQueryClient}>
      <UserProvider initialUser={user}>
        <MemoryRouter>
          <PlaylistDashboard />
        </MemoryRouter>
      </UserProvider>
    </QueryClientProvider>,
  );

// The playlist picker (a native <select> in page-level tests).
export const getPicker = () => screen.getByRole('combobox', { name: 'Playlist' });

// Chooses a playlist by its visible name once the option has loaded.
export const selectPlaylist = async (user: UserEvent, name: string) => {
  await screen.findByRole('option', { name });
  await user.selectOptions(getPicker(), name);
};

// Spot rows have no role or label of their own (known issue K4). Each row's first child is
// its 1-based number in a <span>; the row is that span's parent. Used only to scope queries.
export const getSpot = (n: number): HTMLElement => {
  const number = screen
    .getAllByText(String(n), { selector: 'span' })
    .find((el) => el.parentElement?.firstElementChild === el);
  if (!number) throw new Error(`Spot ${n} not found`);
  return number.parentElement as HTMLElement;
};

// The hidden file input inside a spot. It has no role, which is normal for a hidden
// <input type="file">; userEvent.upload drives it directly.
export const getFileInput = (spot: HTMLElement): HTMLInputElement =>
  spot.querySelector('input[type="file"]') as HTMLInputElement;

// Opens the delete confirmation for spot n and returns it. The dialog has no accessible
// name (known issue K5); callers confirm it through its heading.
export const openDeleteForSpot = async (user: UserEvent, n: number): Promise<HTMLElement> => {
  await user.click(within(getSpot(n)).getByRole('button', { name: 'Delete' }));
  return screen.findByRole('dialog');
};
