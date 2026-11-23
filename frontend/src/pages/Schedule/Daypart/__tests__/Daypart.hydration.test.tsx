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
import { vi, beforeEach, describe, test, expect } from 'vitest';

import DaypartPage from '../Daypart';

import { mockUser, SINGLE_DAYPART } from './fixtures/daypart';
import { mockFetchDaypart } from './mocks/daypartApi';

import { UserProvider } from '@/context/UserContext';
import { testQueryClient } from '@/setupTests';

// =============================================================================
// Module mocks
// =============================================================================

vi.mock('@/services/daypartApi');
vi.mock('@/services/userApi', () => ({
  fetchUserPreference: vi.fn().mockReturnValue(new Promise(() => {})),
  saveUserPreference: vi.fn().mockResolvedValue(undefined),
}));
vi.mock('@/components/ui/modals/Modal');
vi.mock('../hooks/useDaypartFilterOptions', () => ({
  useDaypartFilterOptions: () => ({ filterOptions: [], isLoading: false }),
}));

const renderUnhydrated = () =>
  render(
    <QueryClientProvider client={testQueryClient}>
      <UserProvider initialUser={mockUser}>
        <MemoryRouter>
          <DaypartPage />
        </MemoryRouter>
      </UserProvider>
    </QueryClientProvider>,
  );

// =============================================================================
// Tests
// =============================================================================

describe('Dayparting page - hydration', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    mockFetchDaypart(SINGLE_DAYPART);
  });

  test('a loading pulse message appears while preferences are being restored', async () => {
    renderUnhydrated();

    expect(await screen.findByText('Loading your daypart preferences...')).toBeInTheDocument();
  });

  test('the "Add Daypart" button is disabled while preferences are loading', async () => {
    renderUnhydrated();

    const addButton = await screen.findByRole('button', { name: /add daypart/i });
    expect(addButton).toBeDisabled();
  });
});
