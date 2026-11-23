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
import { render, screen, waitFor } from '@testing-library/react';
import userEvent, { type UserEvent } from '@testing-library/user-event';
import { MemoryRouter } from 'react-router-dom';
import { vi, beforeEach, describe, test, expect } from 'vitest';

import UserMenu from '@/components/layout/UserMenu/UserMenu';
import { testQueryClient } from '@/setupTests';

vi.mock('@/components/layout/UserMenu/AboutModal', () => ({
  default: ({ onClose }: { onClose: () => void }) => (
    <div role="dialog" aria-label="About">
      <button onClick={onClose}>Close</button>
    </div>
  ),
}));
vi.mock('@/context/UserContext', async () => {
  const { mockUser } = await import('../fixtures/about');
  return { useUserContext: () => ({ user: mockUser, logout: vi.fn() }) };
});
vi.mock('@/hooks/useClock', () => ({ useClock: () => null }));
// The user menu's Debug item reads the licence; this test doesn't need it, so the Debug item stays hidden.
vi.mock('@/pages/Pro/Licence/licenceApi', () => ({
  fetchLicence: vi.fn().mockResolvedValue({ debugTools: false }),
}));
vi.mock('@/hooks/useDismissOnIframeFocus', () => ({ useDismissOnIframeFocus: vi.fn() }));

const renderUserMenu = () =>
  render(
    <QueryClientProvider client={testQueryClient}>
      <MemoryRouter>
        <UserMenu />
      </MemoryRouter>
    </QueryClientProvider>,
  );

// mockUser is Test User, so the avatar shows "TU".
const openUserMenu = async (user: UserEvent) => {
  await user.click(screen.getByRole('button', { name: 'TU' }));
  return screen.findByRole('button', { name: 'About' });
};

describe('User menu - About', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
  });

  test('choosing About opens the About window and closes the menu', async () => {
    const user = userEvent.setup();
    renderUserMenu();

    await user.click(await openUserMenu(user));

    expect(screen.getByRole('dialog', { name: 'About' })).toBeInTheDocument();
    expect(screen.queryByRole('button', { name: 'About' })).not.toBeInTheDocument();
  });

  test('closing the About window leaves no window open', async () => {
    const user = userEvent.setup();
    renderUserMenu();
    await user.click(await openUserMenu(user));

    await user.click(screen.getByRole('button', { name: 'Close' }));

    await waitFor(() => expect(screen.queryByRole('dialog')).not.toBeInTheDocument());
  });
});
