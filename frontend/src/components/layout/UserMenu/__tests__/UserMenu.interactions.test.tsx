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

import { act, fireEvent, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { vi, beforeEach, describe, test, expect } from 'vitest';

import { AVATAR_INITIALS } from './fixtures/userMenu';
import { mockUserContext, openUserMenu, renderUserMenu } from './helpers/renderUserMenu';

import { testQueryClient } from '@/setupTests';

vi.mock('@/context/UserContext', async () => {
  const { mockUserContext } = await import('./helpers/menuContext');
  return { useUserContext: () => mockUserContext };
});
vi.mock('@/hooks/useClock', async () => {
  const { mockUseClock } = await import('./helpers/menuContext');
  return { useClock: () => mockUseClock() };
});

// Each modal becomes a dialog named after the real one, with a Close button.
const { stubModal } = vi.hoisted(() => ({
  stubModal: (name: string) => ({
    default: ({ onClose }: { onClose: () => void }) => (
      <div role="dialog" aria-label={name}>
        <button onClick={onClose}>Close</button>
      </div>
    ),
  }),
}));
vi.mock('@/components/layout/UserMenu/AboutModal', () => stubModal('About'));

const menuIsClosed = () =>
  waitFor(() =>
    expect(screen.queryByRole('button', { name: 'Preferences' })).not.toBeInTheDocument(),
  );

describe('User menu - interactions', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
  });

  describe('opening and closing', () => {
    test('clicking the avatar opens the menu and clicking it again closes it', async () => {
      const user = userEvent.setup();
      renderUserMenu();

      await openUserMenu(user);
      await user.click(screen.getByRole('button', { name: AVATAR_INITIALS.default }));

      await menuIsClosed();
    });

    test('pressing Escape closes the menu', async () => {
      const user = userEvent.setup();
      renderUserMenu();
      await openUserMenu(user);

      await user.keyboard('{Escape}');

      await menuIsClosed();
    });

    test('moving focus into an iframe (e.g. a layout preview) closes the menu', async () => {
      const user = userEvent.setup();
      renderUserMenu();
      const iframe = document.body.appendChild(document.createElement('iframe'));
      await openUserMenu(user);

      act(() => {
        iframe.focus();
        fireEvent.blur(window);
      });

      await menuIsClosed();
      iframe.remove();
    });
  });

  describe('menu actions', () => {
    test('Preferences goes to the Preferences page and closes the menu', async () => {
      const user = userEvent.setup();
      renderUserMenu();

      await user.click(await openUserMenu(user));

      await screen.findByRole('heading', { name: 'Preferences page' });
      await menuIsClosed();
    });

    test('My Applications is no longer a menu item (it is a Preferences tab)', async () => {
      const user = userEvent.setup();
      renderUserMenu();
      await openUserMenu(user);

      expect(screen.queryByRole('button', { name: 'My Applications' })).not.toBeInTheDocument();
    });

    test('About opens the About window and closes the menu', async () => {
      const user = userEvent.setup();
      renderUserMenu();
      await openUserMenu(user);

      await user.click(screen.getByRole('button', { name: 'About' }));

      expect(screen.getByRole('dialog', { name: 'About' })).toBeInTheDocument();
      await menuIsClosed();
    });

    // Each window gets its own close callback from the menu, so check every one.
    test.each([['About', 'About']])(
      'closing the %s window leaves no window open',
      async (dialogName, item) => {
        const user = userEvent.setup();
        renderUserMenu();
        await openUserMenu(user);
        await user.click(screen.getByRole('button', { name: item }));
        screen.getByRole('dialog', { name: dialogName });

        await user.click(screen.getByRole('button', { name: 'Close' }));

        expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
      },
    );

    test('the introduction item goes to the Welcome page and closes the menu', async () => {
      const user = userEvent.setup();
      renderUserMenu();
      await openUserMenu(user);

      await user.click(screen.getByRole('button', { name: 'Introduction to Xibo' }));

      await screen.findByRole('heading', { name: 'Welcome page' });
      await menuIsClosed();
    });

    test('Sign Out signs the user out once and closes the menu', async () => {
      const user = userEvent.setup();
      renderUserMenu();
      await openUserMenu(user);

      await user.click(screen.getByRole('button', { name: 'Sign Out' }));

      expect(mockUserContext.logout).toHaveBeenCalledTimes(1);
      await menuIsClosed();
    });
  });
});
