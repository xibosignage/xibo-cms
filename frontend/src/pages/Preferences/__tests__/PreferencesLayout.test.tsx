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

import { screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { vi, beforeEach, describe, test, expect } from 'vitest';

import { NO_PROFILE_USER } from './fixtures/preferences';
import { renderPreferencesPage } from './helpers/renderPreferencesPage';
import { stubUserApiDefaults } from './mocks/preferencesApi';

import { testQueryClient } from '@/setupTests';

vi.mock('@/services/userApi');
vi.mock('@/context/UserContext', async () => {
  const { mockUserContext } = await import('./helpers/preferencesContext');
  return { useUserContext: () => mockUserContext };
});

const tabNav = () => screen.getByRole('navigation', { name: 'Preferences' });

describe('Preferences - tabs', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    stubUserApiDefaults();
  });

  test('lists Profile, Customisation then My Applications first, with the current tab marked', () => {
    renderPreferencesPage('/preferences/customisation');

    const tabs = within(tabNav()).getAllByRole('link');
    expect(tabs.slice(0, 3).map((tab) => tab.textContent)).toEqual([
      'Profile',
      'Customisation',
      'My Applications',
    ]);
    expect(tabs[0]).toHaveAttribute('href', '/preferences/profile');
    expect(tabs[0]).not.toHaveAttribute('aria-current');
    expect(tabs[1]).toHaveAttribute('href', '/preferences/customisation');
    expect(tabs[1]).toHaveAttribute('aria-current', 'page');
    expect(tabs[2]).not.toHaveAttribute('aria-current');
  });

  test('hides the Profile tab from users without the profile feature', () => {
    renderPreferencesPage('/preferences/customisation', { user: NO_PROFILE_USER });

    const tabs = within(tabNav()).getAllByRole('link');
    expect(tabs[0]).toHaveTextContent('Customisation');
    expect(within(tabNav()).queryByRole('link', { name: 'Profile' })).not.toBeInTheDocument();
  });

  test('choosing a tab opens that tab', async () => {
    const user = userEvent.setup();
    renderPreferencesPage('/preferences/customisation');

    await user.click(within(tabNav()).getByRole('link', { name: 'My Applications' }));

    await screen.findByRole('heading', { name: 'My Applications' });
    expect(within(tabNav()).getByRole('link', { name: 'My Applications' })).toHaveAttribute(
      'aria-current',
      'page',
    );
  });
});
