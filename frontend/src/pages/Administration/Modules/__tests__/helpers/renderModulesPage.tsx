/*
 * Copyright (C) 2026 Xibo Signage Ltd
 *
 * Xibo - Digital Signage - https://xibosignage.com
 *
 * This file is part of Xibo.
 *
 * Xibo is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * any later version.
 *
 * Xibo is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with Xibo.  If not, see <http://www.gnu.org/licenses/>.
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
