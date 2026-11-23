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
import { Component, type ReactNode } from 'react';
import { MemoryRouter } from 'react-router-dom';

import { settingsQueryKey } from '../../../__tests__/fixtures/reportRows';
import { superAdmin } from '../../../__tests__/helpers/renderReportPage';
import LibraryUsage from '../../LibraryUsage';
import type { LibraryUsageFilter } from '../../LibraryUsageConfig';

import { UserProvider } from '@/context/UserContext';
import { testQueryClient } from '@/setupTests';

export const SETTINGS_KEY = 'libraryusage_report_page';

// Shows a fallback instead of unmounting when the page throws.
class CrashBoundary extends Component<{ children: ReactNode }, { crashed: boolean }> {
  state = { crashed: false };
  static getDerivedStateFromError() {
    return { crashed: true };
  }
  render() {
    return this.state.crashed ? <p role="alert">Page crashed</p> : this.props.children;
  }
}

export const renderLibraryUsage = (savedFilters?: Partial<LibraryUsageFilter>) => {
  testQueryClient.setQueryData(
    settingsQueryKey(SETTINGS_KEY),
    savedFilters ? { filterInputs: savedFilters } : null,
  );
  return render(
    <QueryClientProvider client={testQueryClient}>
      <UserProvider initialUser={superAdmin}>
        <MemoryRouter initialEntries={['/reporting/library-usage']}>
          <CrashBoundary>
            <LibraryUsage />
          </CrashBoundary>
        </MemoryRouter>
      </UserProvider>
    </QueryClientProvider>,
  );
};
