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

import { render, screen } from '@testing-library/react';
import { MemoryRouter, Route, Routes } from 'react-router-dom';
import { vi, describe, test, expect } from 'vitest';

import { NO_PROFILE_USER, PROFILE_FEATURE_USER } from './fixtures/preferences';
import { setPreferencesUser } from './helpers/preferencesContext';

import type * as CmsVisibilityModule from '@/hooks/useCmsVisibility';
import PreferencesRedirect from '@/pages/Preferences/PreferencesRedirect';
import type { User } from '@/types/user';

vi.mock('@/context/UserContext', async () => {
  const { mockUserContext } = await import('./helpers/preferencesContext');
  return { useUserContext: () => mockUserContext };
});
// The real hook can need a QueryClient, which this test doesn't render.
vi.mock('@/hooks/useCmsVisibility', async (importOriginal) => ({
  ...(await importOriginal<typeof CmsVisibilityModule>()),
  useServiceVisibility: () => () => 'shown',
}));

const renderRedirect = (user: User) => {
  setPreferencesUser(user);
  render(
    <MemoryRouter initialEntries={['/preferences']}>
      <Routes>
        <Route path="/preferences" element={<PreferencesRedirect />} />
        <Route path="/preferences/profile" element={<h1>Profile page</h1>} />
        <Route path="/preferences/customisation" element={<h1>Customisation page</h1>} />
      </Routes>
    </MemoryRouter>,
  );
};

describe('Preferences - opening /preferences', () => {
  test('a user with the profile feature lands on Profile', () => {
    renderRedirect(PROFILE_FEATURE_USER);

    expect(screen.getByRole('heading', { name: 'Profile page' })).toBeInTheDocument();
  });

  test('a user without the profile feature lands on Customisation', () => {
    renderRedirect(NO_PROFILE_USER);

    expect(screen.getByRole('heading', { name: 'Customisation page' })).toBeInTheDocument();
  });
});
