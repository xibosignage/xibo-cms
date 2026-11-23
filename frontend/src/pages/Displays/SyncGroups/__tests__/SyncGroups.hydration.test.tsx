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

// =============================================================================
// Test type: Page integration — pre-hydration state
// useTableState's `isHydrated` flag stays false until the user-preference
// query resolves. While it's false the page shows a loading pulse and
// disables the Add button. These tests intentionally keep that query
// pending so we can observe the un-hydrated UI — the other test files
// pre-seed the cache to skip past this state.
// =============================================================================

import { QueryClientProvider } from '@tanstack/react-query';
import { render, screen } from '@testing-library/react';
import type React from 'react';
import { MemoryRouter } from 'react-router-dom';
import { vi, beforeEach, describe, test, expect } from 'vitest';

import SyncGroupsPage from '../SyncGroups';

import { mockUser } from './fixtures/syncGroup';

import { UserProvider } from '@/context/UserContext';
import { fetchUserPreference } from '@/services/userApi';
import { testQueryClient } from '@/setupTests';

// =============================================================================
// Module mocks
// =============================================================================

vi.mock('@/services/syncGroupApi');
vi.mock('@/services/userApi', () => ({
  // Never-resolving promise keeps the userPref query pending, so isHydrated
  // stays false for the lifetime of the test.
  fetchUserPreference: vi.fn().mockReturnValue(new Promise(() => {})),
  saveUserPreference: vi.fn().mockResolvedValue(undefined),
  fetchUsers: vi.fn().mockResolvedValue([]),
}));
vi.mock('@/services/folderApi', () => ({
  fetchFolderById: vi.fn().mockResolvedValue({ id: 1, text: 'Root' }),
  fetchFolderTree: vi.fn().mockResolvedValue([]),
  searchFolders: vi.fn().mockResolvedValue([]),
  fetchContextButtons: vi.fn().mockResolvedValue({ create: true }),
  selectFolder: vi.fn().mockResolvedValue({ success: true }),
}));
vi.mock('@/components/ui/modals/Modal');
vi.mock('@/components/ui/FolderActionModals', () => ({ default: () => null }));
vi.mock('./hooks/useSyncGroupFilterOptions', () => ({
  useSyncGroupFilterOptions: () => ({ filterOptions: [], isLoading: false }),
}));

// =============================================================================
// Helper — renders without pre-seeding the userPref cache.
// =============================================================================

const renderUnhydrated = () =>
  render(
    <QueryClientProvider client={testQueryClient}>
      <UserProvider initialUser={mockUser}>
        <MemoryRouter>
          <SyncGroupsPage />
        </MemoryRouter>
      </UserProvider>
    </QueryClientProvider>,
  );

// =============================================================================
// Tests
// =============================================================================

describe('Sync Groups page - hydration', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();

    vi.mocked(fetchUserPreference).mockReturnValue(new Promise(() => {}));
  });

  // ---------------------------------------------------------------------------
  // While the user-preference query is still pending the table area is
  // replaced by a loading pulse with the "Loading sync groups..." copy.
  // ---------------------------------------------------------------------------
  test('a loading pulse message appears while user preferences are being restored', async () => {
    renderUnhydrated();

    expect(await screen.findByText('Loading sync groups...')).toBeInTheDocument();
  });

  // ---------------------------------------------------------------------------
  // The "Add Sync Group" CTA is `disabled={!isHydrated}` so users can't
  // open the Add modal before the page knows what their saved column /
  // filter preferences are.
  // ---------------------------------------------------------------------------
  test('"Add Sync Group" button is disabled while preferences are loading', async () => {
    renderUnhydrated();

    const addButton = await screen.findByRole('button', { name: /add sync group/i });
    expect(addButton).toBeDisabled();
  });
});
