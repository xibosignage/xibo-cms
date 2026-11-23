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

import SyncGroupsPage from '../../SyncGroups';
import { mockUser, queryKeys } from '../fixtures/syncGroup';

import { UserProvider } from '@/context/UserContext';
import { testQueryClient } from '@/setupTests';
import type { User } from '@/types/user';

// Renders the full Sync Groups page inside all required context providers.
// Call testQueryClient.clear() and vi.clearAllMocks() in beforeEach to
// guarantee test isolation before using this helper.
// Pass { user } to render as someone other than the default super admin.
export const renderSyncGroupsPage = ({ user = mockUser }: { user?: User } = {}) => {
  // Pre-seed the user-preference cache so useTableState skips its hydration
  // delay (otherwise the page sits on the "Loading sync groups..." pulse and
  // the Add button + search input stay disabled).
  testQueryClient.setQueryData(queryKeys.syncGroupPage, null);
  return render(
    <QueryClientProvider client={testQueryClient}>
      <UserProvider initialUser={user}>
        <MemoryRouter>
          <SyncGroupsPage />
        </MemoryRouter>
      </UserProvider>
    </QueryClientProvider>,
  );
};
