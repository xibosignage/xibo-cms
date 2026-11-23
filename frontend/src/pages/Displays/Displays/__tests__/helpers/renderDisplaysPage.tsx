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
import { MemoryRouter } from 'react-router-dom';

import DisplaysPage from '../../Displays';
import { mockUser, queryKeys } from '../fixtures/display';

import { UserProvider } from '@/context/UserContext';
import { testQueryClient } from '@/setupTests';
import type { User } from '@/types/user';

// Renders the full Displays page inside all required context providers.
// Call testQueryClient.clear() and vi.clearAllMocks() in beforeEach to
// guarantee test isolation before using this helper.
// The default user is a Super Admin; pass { user } to test a restricted user,
// and { initialEntries } to open the page at a URL.
export const renderDisplaysPage = ({
  initialEntries,
  user = mockUser,
}: { initialEntries?: string[]; user?: User } = {}) => {
  testQueryClient.setQueryData(queryKeys.displaysPage, null);
  return render(
    <QueryClientProvider client={testQueryClient}>
      <UserProvider initialUser={user}>
        <MemoryRouter initialEntries={initialEntries}>
          <DisplaysPage />
        </MemoryRouter>
      </UserProvider>
    </QueryClientProvider>,
  );
};
