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
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type React from 'react';
import { MemoryRouter } from 'react-router-dom';
import { vi, beforeEach, describe, test, expect } from 'vitest';

import SyncGroupsPage from '../SyncGroups';

import {
  mockSyncGroup,
  mockUser,
  mockUserNoFolderView,
  queryKeys,
  SINGLE_SYNC_GROUP,
} from './fixtures/syncGroup';
import { mockFetchSyncGroups } from './mocks/syncGroupApi';

import { UserProvider } from '@/context/UserContext';
import { fetchSyncGroups } from '@/services/syncGroupApi';
import { testQueryClient } from '@/setupTests';
import type { User } from '@/types/user';

// =============================================================================
// Module mocks
// =============================================================================

vi.mock('react-i18next');

vi.mock('@/services/syncGroupApi');
vi.mock('@/services/userApi', () => ({
  fetchUserPreference: vi.fn().mockResolvedValue(null),
  saveUserPreference: vi.fn().mockResolvedValue(undefined),
  fetchUsers: vi.fn().mockResolvedValue([]),
}));
vi.mock('@/components/ui/modals/Modal');
vi.mock('@/components/ui/FolderActionModals', () => ({ default: () => null }));
vi.mock('./hooks/useSyncGroupFilterOptions', () => ({
  useSyncGroupFilterOptions: () => ({ filterOptions: [], isLoading: false }),
}));

// Stub the folder sidebar — exposes a button that fires onSelect with a
// known folder so tests can drive `handleFolderChange` without rendering
// the real folder tree.
vi.mock('@/components/ui/FolderSidebar', () => ({
  default: ({ onSelect }: { onSelect: (folder: { id: number | null; text: string }) => void }) => (
    <div data-testid="folder-sidebar-stub">
      <button type="button" onClick={() => onSelect({ id: 7, text: 'Folder 7' })}>
        Select folder 7
      </button>
    </div>
  ),
}));
vi.mock('@/components/ui/FolderBreadCrumb', () => ({
  default: () => <div data-testid="folder-breadcrumb-stub" />,
}));

// =============================================================================
// Helper — render with an optional user override.
// =============================================================================

const renderWithUser = (user: User = mockUser) => {
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

// =============================================================================
// Tests
// =============================================================================

describe('Sync Groups page - folder permission', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    mockFetchSyncGroups(SINGLE_SYNC_GROUP);
  });

  // ---------------------------------------------------------------------------
  // canViewFolders is derived from user.features['folder.view'] via
  // usePermissions. When the feature is absent the sidebar and breadcrumb
  // are not rendered at all.
  // ---------------------------------------------------------------------------
  test('folder sidebar and breadcrumb are NOT rendered when the user lacks folder.view', async () => {
    renderWithUser(mockUserNoFolderView);

    // Wait for the row to confirm the page has hydrated and rendered.
    await screen.findByText(mockSyncGroup.name);

    expect(screen.queryByTestId('folder-sidebar-stub')).not.toBeInTheDocument();
    expect(screen.queryByTestId('folder-breadcrumb-stub')).not.toBeInTheDocument();
  });
});

describe('Sync Groups page - folder change side effects', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    mockFetchSyncGroups(SINGLE_SYNC_GROUP);
  });

  // ---------------------------------------------------------------------------
  // handleFolderChange in SyncGroups.tsx sets pageIndex back to 0 alongside
  // applying the new folderId. The next fetchSyncGroups call should carry
  // both `start: 0` and the new folderId.
  // ---------------------------------------------------------------------------
  test('changing folder selection resets pagination to page 1', async () => {
    const user = userEvent.setup();
    renderWithUser();
    await screen.findByText(mockSyncGroup.name);

    await user.click(screen.getByRole('button', { name: /select folder 7/i }));

    await waitFor(() => {
      expect(fetchSyncGroups).toHaveBeenCalledWith(
        expect.objectContaining({ start: 0, folderId: 7 }),
      );
    });
  });

  // ---------------------------------------------------------------------------
  // handleFolderChange also clears rowSelection so a stale selection from a
  // different folder doesn't leak into the new view.
  // ---------------------------------------------------------------------------
  test('changing folder selection clears the current row selection', async () => {
    const user = userEvent.setup();
    renderWithUser();
    await screen.findByText(mockSyncGroup.name);

    // Tick the single row's checkbox (index 0 is the "select all" header
    // toggle; index 1 is the row checkbox).
    const checkboxes = screen.getAllByRole('checkbox', { name: /select/i });
    const rowCheckbox = checkboxes[1] as HTMLInputElement;
    await user.click(rowCheckbox);
    expect(rowCheckbox).toBeChecked();

    // Switch folder — selection should reset.
    await user.click(screen.getByRole('button', { name: /select folder 7/i }));

    await waitFor(() => {
      const refreshed = screen.getAllByRole('checkbox', {
        name: /select/i,
      })[1] as HTMLInputElement;
      expect(refreshed).not.toBeChecked();
    });
  });
});
