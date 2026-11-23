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

import SavedReports from '../../SavedReports';
import type { SavedReportFilterInput } from '../../SavedReportsConfig';
import { mockUser, queryKeys } from '../fixtures/savedReport';

import { UserProvider } from '@/context/UserContext';
import { testQueryClient } from '@/setupTests';
import type { User } from '@/types/user';

// Pass savedFilters to open with filters the user left applied. Pass { hydrated: false } to see the loading placeholder.
export const renderSavedReportsPage = ({
  user = mockUser,
  hydrated = true,
  savedFilters,
}: { user?: User; hydrated?: boolean; savedFilters?: SavedReportFilterInput } = {}) => {
  if (hydrated) {
    // Pre-seed the preference so useTableState skips its hydration delay.
    testQueryClient.setQueryData(
      queryKeys.savedReportsPage,
      savedFilters ? { filterInputs: savedFilters } : null,
    );
  }
  return render(
    <QueryClientProvider client={testQueryClient}>
      <UserProvider initialUser={user}>
        <MemoryRouter initialEntries={['/reporting/saved-reports']}>
          <SavedReports />
        </MemoryRouter>
      </UserProvider>
    </QueryClientProvider>,
  );
};
