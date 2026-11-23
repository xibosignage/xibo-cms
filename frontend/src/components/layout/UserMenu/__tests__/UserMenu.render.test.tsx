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

import { screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { vi, beforeEach, describe, test, expect } from 'vitest';

import {
  AVATAR_INITIALS,
  BRANDED_USER,
  UNNAMED_BRAND_USER,
  NO_LOGOUT_USER,
  USERNAME_ONLY_USER,
} from './fixtures/userMenu';
import { mockUseClock, openUserMenu, renderUserMenu } from './helpers/renderUserMenu';

import { testQueryClient } from '@/setupTests';
import { knownFailure } from '@/testUtils/knownFailure';

vi.mock('@/context/UserContext', async () => {
  const { mockUserContext } = await import('./helpers/menuContext');
  return { useUserContext: () => mockUserContext };
});
vi.mock('@/hooks/useClock', async () => {
  const { mockUseClock } = await import('./helpers/menuContext');
  return { useClock: () => mockUseClock() };
});
vi.mock('@/hooks/useDismissOnIframeFocus', () => ({ useDismissOnIframeFocus: vi.fn() }));
vi.mock('@/components/layout/UserMenu/AboutModal', () => ({ default: () => null }));

describe('User menu - render', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
  });

  describe('avatar', () => {
    test('shows the first and last initials, with the menu closed', () => {
      renderUserMenu();

      expect(screen.getByRole('button', { name: AVATAR_INITIALS.default })).toBeInTheDocument();
      expect(screen.queryByRole('button', { name: 'Preferences' })).not.toBeInTheDocument();
    });

    test('falls back to the first two letters of the username', () => {
      renderUserMenu(USERNAME_ONLY_USER);

      expect(
        screen.getByRole('button', { name: AVATAR_INITIALS.usernameOnly }),
      ).toBeInTheDocument();
    });
  });

  describe('header', () => {
    test('shows the full name and email', async () => {
      const user = userEvent.setup();
      renderUserMenu();

      await openUserMenu(user);

      expect(screen.getByText('Jane Doe')).toBeInTheDocument();
      expect(screen.getByText('jane@example.com')).toBeInTheDocument();
    });

    test('shows the username when there is no full name', async () => {
      const user = userEvent.setup();
      renderUserMenu(USERNAME_ONLY_USER);

      await openUserMenu(user, AVATAR_INITIALS.usernameOnly);

      expect(screen.getByText('kent')).toBeInTheDocument();
    });

    test('shows the server clock only while a time is available', async () => {
      const user = userEvent.setup();
      renderUserMenu(undefined, '14:05');

      await openUserMenu(user);
      expect(screen.getByText('14:05')).toBeInTheDocument();

      mockUseClock.mockReturnValue(null);
      await user.click(screen.getByRole('button', { name: AVATAR_INITIALS.default }));
      await openUserMenu(user);

      expect(screen.queryByText('14:05')).not.toBeInTheDocument();
    });

    test('has no Edit Profile item, since the profile lives in Preferences', async () => {
      const user = userEvent.setup();
      renderUserMenu();

      await openUserMenu(user);

      expect(screen.queryByRole('button', { name: 'Edit Profile' })).not.toBeInTheDocument();
    });
  });

  describe('menu items', () => {
    test('names the introduction after the branded app', async () => {
      const user = userEvent.setup();
      renderUserMenu(BRANDED_USER);

      await openUserMenu(user);

      expect(
        screen.getByRole('button', { name: 'Introduction to Acme Signage' }),
      ).toBeInTheDocument();
    });

    test('names the introduction after Xibo when there is no branding', async () => {
      const user = userEvent.setup();
      renderUserMenu();

      await openUserMenu(user);

      expect(screen.getByRole('button', { name: 'Introduction to Xibo' })).toBeInTheDocument();
    });

    // https://github.com/xibosignage/xibo/issues/4106
    knownFailure(
      'leaves no dangling "to" in the introduction when the branding has an empty app name',
      async () => {
        const user = userEvent.setup();
        renderUserMenu(UNNAMED_BRAND_USER);

        await openUserMenu(user);

        expect(screen.getByRole('button', { name: /^Introduction/ })).toBeInTheDocument();
        expect(
          screen.queryByRole('button', { name: 'Introduction to Xibo' }),
        ).not.toBeInTheDocument();
      },
      () =>
        expect(screen.getByRole('button', { name: /^Introduction/ })).not.toHaveAccessibleName(
          /\sto$/,
        ),
    );

    test('hides Sign Out when the logout button is turned off', async () => {
      const user = userEvent.setup();
      renderUserMenu(NO_LOGOUT_USER);

      await openUserMenu(user);

      expect(screen.queryByRole('button', { name: 'Sign Out' })).not.toBeInTheDocument();
    });
  });
});
