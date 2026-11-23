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

import Modules from '../../Modules';
import { mockUser, queryKeys } from '../fixtures/module';

import { UserProvider } from '@/context/UserContext';
import { testQueryClient } from '@/setupTests';

// Pass { hydrated: false } to skip seeding the preference cache — used by the
// hydration test, which keeps the page on the "Loading your preferences..." pulse
// (with the search box and Filters button disabled) while preferences load.
export const renderModulesPage = ({ hydrated = true }: { hydrated?: boolean } = {}) => {
  if (hydrated) {
    testQueryClient.setQueryData(queryKeys.modulePage, null);
  }
  return render(
    <QueryClientProvider client={testQueryClient}>
      <UserProvider initialUser={mockUser}>
        <MemoryRouter>
          <Modules />
        </MemoryRouter>
      </UserProvider>
    </QueryClientProvider>,
  );
};
