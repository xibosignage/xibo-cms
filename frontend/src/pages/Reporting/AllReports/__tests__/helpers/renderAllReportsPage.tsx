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
import { render, screen } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';

import AllReports from '../../AllReports';
import { plainUser, queryKeys } from '../fixtures/allReports';

import { UserProvider } from '@/context/UserContext';
import { testQueryClient } from '@/setupTests';
import { escapeRegExp } from '@/testUtils/escapeRegExp';
import type { User } from '@/types/user';

// Pass { hydrated: false } to leave the page-preference cache empty.
export const renderAllReportsPage = ({
  user = plainUser,
  hydrated = true,
}: { user?: User; hydrated?: boolean } = {}) => {
  if (hydrated) {
    testQueryClient.setQueryData(queryKeys.allReportsPage, null);
  }
  return render(
    <QueryClientProvider client={testQueryClient}>
      <UserProvider initialUser={user}>
        <MemoryRouter initialEntries={['/reporting/all-reports']}>
          <AllReports />
        </MemoryRouter>
      </UserProvider>
    </QueryClientProvider>,
  );
};

// A card's name is its description followed by the button text.
export const findCard = (description: string) =>
  screen.findByRole('link', { name: new RegExp(`^${escapeRegExp(description)}`) });

export const queryCard = (description: string) =>
  screen.queryByRole('link', { name: new RegExp(`^${escapeRegExp(description)}`) });
