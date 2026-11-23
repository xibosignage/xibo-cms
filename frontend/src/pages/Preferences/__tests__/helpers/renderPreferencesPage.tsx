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
import { render } from '@testing-library/react';
import { MemoryRouter, Route, Routes } from 'react-router-dom';

import { mockUser } from '../fixtures/preferences';

import { setPreferencesUser } from './preferencesContext';

import Applications from '@/pages/Preferences/Applications/Applications';
import Customisation from '@/pages/Preferences/Customisation/Customisation';
import Profile from '@/pages/Preferences/Profile/Profile';
import { testQueryClient } from '@/setupTests';
import type { User } from '@/types/user';

export { mockUserContext, setPreferencesUser } from './preferencesContext';

// Renders the Preferences tabs at `path` for the given user. The core tabs are real pages, so
// clicking a tab link swaps the page. Requires vi.mock('@/context/UserContext') in the test file.
export function renderPreferencesPage(
  path = '/preferences/customisation',
  { user = mockUser }: { user?: User } = {},
) {
  setPreferencesUser(user);
  return render(
    <QueryClientProvider client={testQueryClient}>
      <MemoryRouter initialEntries={[path]}>
        <Routes>
          <Route path="/preferences/profile" element={<Profile />} />
          <Route path="/preferences/customisation" element={<Customisation />} />
          <Route path="/preferences/applications" element={<Applications />} />
          <Route path="/preferences/:tab" element={<h1>Other tab page</h1>} />
        </Routes>
      </MemoryRouter>
    </QueryClientProvider>,
  );
}
