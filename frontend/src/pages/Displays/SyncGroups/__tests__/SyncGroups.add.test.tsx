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

import { screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type React from 'react';
import { vi, beforeEach, describe, test, expect } from 'vitest';

import { mockSyncGroup, SINGLE_SYNC_GROUP } from './fixtures/syncGroup';
import { renderSyncGroupsPage } from './helpers/renderSyncGroupsPage';
import { mockFetchSyncGroups } from './mocks/syncGroupApi';

import { fetchSyncGroups } from '@/services/syncGroupApi';
import { testQueryClient } from '@/setupTests';

// =============================================================================
// Module mocks
// =============================================================================

vi.mock('react-i18next', () => {
  const t = (key: string) => key;
  return {
    useTranslation: () => ({ t, i18n: { changeLanguage: vi.fn() } }),
    Trans: ({ children }: { children: React.ReactNode }) => children,
  };
});

vi.mock('@/services/syncGroupApi');
vi.mock('@/services/userApi', () => ({
  fetchUserPreference: vi.fn().mockResolvedValue(null),
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
vi.mock('../hooks/useSyncGroupFilterOptions', () => ({
  useSyncGroupFilterOptions: () => ({ filterOptions: [], isLoading: false }),
}));

// Stub the Add/Edit modal so the page test focuses on wiring rather than
// form interaction. Exposes a "Trigger save" button that fires onSave +
// onAfterSave with a created sync group — this is exactly the path the page
// is expected to react to.
vi.mock('../components/AddAndEditSyncGroupModal', () => ({
  default: ({
    mode,
    onSave,
    onAfterSave,
    onClose,
  }: {
    mode: 'add' | 'edit';
    onSave: (s: typeof mockSyncGroup) => void;
    onAfterSave?: (s: typeof mockSyncGroup) => void;
    onClose: () => void;
  }) => (
    <div role="dialog" aria-label={`Stub ${mode === 'add' ? 'Add' : 'Edit'} Sync Group`}>
      <button
        type="button"
        onClick={() => {
          const created = { ...mockSyncGroup, syncGroupId: 42, name: 'New Group' };
          onSave(created);
          onAfterSave?.(created);
        }}
      >
        Trigger save
      </button>
      <button type="button" onClick={onClose}>
        Stub cancel
      </button>
    </div>
  ),
}));

// Stub the Members modal so we can assert it opens after a successful add
// without rendering the full SearchAssignPanel inside.
vi.mock('../components/ManageMembersModal', () => ({
  default: ({ syncGroup }: { syncGroup: typeof mockSyncGroup | null }) => (
    <div role="dialog" aria-label="Stub Manage Members">
      <span>Members for {syncGroup?.name}</span>
    </div>
  ),
}));

// =============================================================================
// Tests
// =============================================================================

describe('Sync Groups page - add sync group wiring', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    mockFetchSyncGroups(SINGLE_SYNC_GROUP);
  });

  // ---------------------------------------------------------------------------
  // Clicking "Add Sync Group" opens the Add modal (stubbed) in add mode.
  // ---------------------------------------------------------------------------
  test('"Add Sync Group" button opens the Add modal', async () => {
    const user = userEvent.setup();
    renderSyncGroupsPage();

    await user.click(await screen.findByRole('button', { name: /add sync group/i }));

    expect(await screen.findByRole('dialog', { name: /stub add sync group/i })).toBeInTheDocument();
  });

  // ---------------------------------------------------------------------------
  // After a successful add the page wires onAfterSave to open the Members
  // modal for the freshly-created sync group. The Add modal should also be
  // gone by that point.
  // ---------------------------------------------------------------------------
  test('after a successful add the Members modal opens automatically', async () => {
    const user = userEvent.setup();
    renderSyncGroupsPage();

    await user.click(await screen.findByRole('button', { name: /add sync group/i }));
    await user.click(await screen.findByRole('button', { name: /trigger save/i }));

    expect(await screen.findByRole('dialog', { name: /stub manage members/i })).toBeInTheDocument();
    expect(screen.queryByRole('dialog', { name: /stub add sync group/i })).not.toBeInTheDocument();
    // The Members modal title should reflect the newly-created sync group.
    expect(screen.getByText(/Members for New Group/)).toBeInTheDocument();
  });

  // ---------------------------------------------------------------------------
  // The page's onSave handler calls handleRefresh, which invalidates the
  // 'syncGroups' query key and triggers a refetch.
  // ---------------------------------------------------------------------------
  test('table is refreshed after save', async () => {
    const user = userEvent.setup();
    renderSyncGroupsPage();

    // Wait for first fetch to land.
    await screen.findByText(mockSyncGroup.name);
    const initialCallCount = vi.mocked(fetchSyncGroups).mock.calls.length;

    await user.click(screen.getByRole('button', { name: /add sync group/i }));
    await user.click(await screen.findByRole('button', { name: /trigger save/i }));

    await waitFor(() => {
      expect(vi.mocked(fetchSyncGroups).mock.calls.length).toBeGreaterThan(initialCallCount);
    });
  });
});
