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
// Page-level tests replace SelectDropdown with a native <select>, so only choosing a playlist is
// tested here; paging and search run against the real component in
// components/PlaylistDropdown.test.tsx.
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

// Spot rows have no role or label of their own (xibosignage/xibo#4041). Each row's first child is
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
// name (xibosignage/xibo#4039); callers confirm it through its heading.
export const openDeleteForSpot = async (user: UserEvent, n: number): Promise<HTMLElement> => {
  await user.click(within(getSpot(n)).getByRole('button', { name: 'Delete' }));
  return screen.findByRole('dialog');
};
