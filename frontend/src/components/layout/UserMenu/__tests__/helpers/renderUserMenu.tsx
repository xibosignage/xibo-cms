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
import { render, screen } from '@testing-library/react';
import type { UserEvent } from '@testing-library/user-event';
import type React from 'react';
import { MemoryRouter, Route, Routes } from 'react-router-dom';
import { vi } from 'vitest';

import { AVATAR_INITIALS, mockUser } from '../fixtures/userMenu';

import { mockUseClock, setMenuUser } from './menuContext';

import UserMenu from '@/components/layout/UserMenu/UserMenu';
import { testQueryClient } from '@/setupTests';
import type { User } from '@/types/user';

export { mockUserContext, mockUseClock, setMenuUser } from './menuContext';

// Renders UserMenu with probe pages for the routes its items navigate to. The menu sits
// outside <Routes>, like the top bar, so it stays mounted after navigating. Pass extra
// <Route>s for edition-specific items.
export function renderUserMenu(
  user: User | null = mockUser,
  clock: string | null = null,
  extraRoutes: React.ReactNode = null,
) {
  setMenuUser(user);
  mockUseClock.mockReturnValue(clock);
  return render(
    <QueryClientProvider client={testQueryClient}>
      <MemoryRouter initialEntries={['/']}>
        <UserMenu />
        <Routes>
          <Route path="/" element={null} />
          <Route path="/welcome" element={<h1>Welcome page</h1>} />
          <Route path="/preferences" element={<h1>Preferences page</h1>} />
          {extraRoutes}
        </Routes>
      </MemoryRouter>
    </QueryClientProvider>,
  );
}

// Clicks the avatar (named by its initials) and waits for the menu.
export async function openUserMenu(user: UserEvent, initials = AVATAR_INITIALS.default) {
  await user.click(screen.getByRole('button', { name: initials }));
  return screen.findByRole('button', { name: 'Preferences' });
}

type MenuModal = React.ComponentType<{ isOpen?: boolean; onClose: () => void }>;

// Renders one of the menu's modals, open, for the given user; returns the dialog and an
// onClose spy. Router is needed by modals that render a DataTable.
export function renderMenuModal(Modal: MenuModal, { user = mockUser }: { user?: User } = {}) {
  setMenuUser(user);
  const onClose = vi.fn();
  render(
    <QueryClientProvider client={testQueryClient}>
      <MemoryRouter>
        <Modal isOpen onClose={onClose} />
      </MemoryRouter>
    </QueryClientProvider>,
  );
  return { onClose, dialog: screen.queryByRole('dialog') };
}
