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
import { render, screen, waitFor } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import { beforeEach, describe, expect, test, vi } from 'vitest';

import Users from '../Users';

import { mockUser, mockSuperAdmin, SINGLE_USER, EMPTY_USER_TABLE } from './fixtures/user';
import { renderUsersPage } from './helpers/renderUsersPage';
import { mockFetchUsers } from './mocks/userApi';

import { UserProvider } from '@/context/UserContext';
import { fetchUsers, fetchUserPreference } from '@/services/userApi';
import { testQueryClient } from '@/setupTests';
import { escapeRegExp } from '@/testUtils/escapeRegExp';

// =============================================================================
// Module mocks
// =============================================================================

vi.mock('@/services/userApi');

vi.mock('@/services/userGroupApi');

vi.mock('@/services/folderApi', () => ({
  fetchFolderTree: vi.fn().mockResolvedValue([]),
}));

vi.mock('@/services/permissionsApi', () => ({
  fetchGroupFolderPermissions: vi.fn().mockResolvedValue(new Map()),
  saveMultiPermissions: vi.fn().mockResolvedValue(undefined),
}));

vi.mock('@/hooks/useFilteredTabs', () => ({
  useFilteredTabs: vi.fn(() => [{ name: 'Users', path: '/administration/users' }]),
}));

vi.mock('@/components/ui/modals/Modal');

// useUsersFilterOptions is NOT mocked — getBaseFilterKeys(t) is synchronous, runs for real

// =============================================================================
// Helper for loading-state tests (does NOT pre-seed the userPref cache)
// =============================================================================

const renderWithPendingPrefs = () =>
  render(
    <QueryClientProvider client={testQueryClient}>
      <UserProvider initialUser={mockSuperAdmin}>
        <MemoryRouter>
          <Users />
        </MemoryRouter>
      </UserProvider>
    </QueryClientProvider>,
  );

// =============================================================================
// Tests
// =============================================================================

describe('Users page - render', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    mockFetchUsers(SINGLE_USER);
  });

  // ---------------------------------------------------------------------------
  // Table rows
  // ---------------------------------------------------------------------------
  test('renders user rows once data has loaded', { tags: ['smoke'] }, async () => {
    renderUsersPage();

    // In a table row, not just somewhere on the page
    expect(
      await screen.findByRole('row', { name: new RegExp(escapeRegExp(mockUser.userName)) }),
    ).toBeVisible();
  });

  // ---------------------------------------------------------------------------
  // Empty state
  // ---------------------------------------------------------------------------
  test('shows "No results found." when there are no users', async () => {
    mockFetchUsers(EMPTY_USER_TABLE);
    renderUsersPage();

    // Wait for hydration to complete (Add User button confirms it)
    await screen.findByRole('button', { name: /add user/i });

    await screen.findByText('No results found.');
  });

  // ---------------------------------------------------------------------------
  // Add User button
  // ---------------------------------------------------------------------------
  test('"Add User" button is present', async () => {
    renderUsersPage();

    expect(await screen.findByRole('button', { name: /add user/i })).toBeEnabled();
  });

  // ---------------------------------------------------------------------------
  // Search input
  // ---------------------------------------------------------------------------
  test('search input is present with the correct placeholder', async () => {
    renderUsersPage();

    await screen.findByText(mockUser.userName);

    expect(screen.getByPlaceholderText('Search user...')).toBeInTheDocument();
  });

  // ---------------------------------------------------------------------------
  // Filters button
  // ---------------------------------------------------------------------------
  test('Filters button is present', async () => {
    renderUsersPage();

    await screen.findByRole('button', { name: /filters/i });
  });

  // ---------------------------------------------------------------------------
  // Error state
  // ---------------------------------------------------------------------------
  test('shows an error alert when the API call fails', async () => {
    vi.mocked(fetchUsers).mockRejectedValue(new Error('Server Error'));

    renderUsersPage();

    expect(await screen.findByRole('alert')).toHaveTextContent('Server Error');
  });

  // ---------------------------------------------------------------------------
  // Loading pulse
  // ---------------------------------------------------------------------------
  test('shows "Loading your preferences..." when the page is not yet hydrated', async () => {
    // useTableState fetches two independent keys on mount: the page prefs
    // ('users_page') and the folder-id ('globalSelectedFolderId'). Only the
    // page-prefs fetch should stay pending here — the folder-id fetch must
    // resolve immediately or it never reaches its own hydrated state.
    let resolvePref!: (v: null) => void;
    vi.mocked(fetchUserPreference).mockImplementation((key: string) => {
      if (key === 'users_page') {
        return new Promise<null>((res) => {
          resolvePref = res;
        });
      }
      return Promise.resolve(null);
    });

    renderWithPendingPrefs();

    await screen.findByText('Loading your preferences...');

    resolvePref(null);
    await waitFor(() =>
      expect(screen.queryByText('Loading your preferences...')).not.toBeInTheDocument(),
    );
  });
});
