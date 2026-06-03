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

import { screen, waitFor, within } from '@testing-library/react';
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

// Stub the Add/Edit modal so the test focuses on the row-action wiring and
// the post-save refresh. The stub exposes the bound sync group's name (to
// prove the page passed it through) and a "Trigger save" button.
vi.mock('../components/AddAndEditSyncGroupModal', () => ({
  default: ({
    mode,
    syncGroup,
    onSave,
    onClose,
  }: {
    mode: 'add' | 'edit';
    syncGroup: { syncGroupId: number; name: string } | null;
    onSave: (s: typeof mockSyncGroup) => void;
    onClose: () => void;
  }) => (
    <div role="dialog" aria-label={`Stub ${mode === 'edit' ? 'Edit' : 'Add'} Sync Group`}>
      <span data-testid="bound-name">{syncGroup?.name ?? ''}</span>
      <button type="button" onClick={() => onSave({ ...mockSyncGroup, name: 'Renamed' })}>
        Trigger save
      </button>
      <button type="button" onClick={onClose}>
        Stub cancel
      </button>
    </div>
  ),
}));

vi.mock('../components/ManageMembersModal', () => ({
  default: () => <div role="dialog" aria-label="Stub Manage Members" />,
}));

// =============================================================================
// Helpers
// =============================================================================

const clickRowEdit = async (user: ReturnType<typeof userEvent.setup>) => {
  await screen.findByText(mockSyncGroup.name);
  const editButtons = await screen.findAllByRole('button', { name: /^edit$/i });
  // The quick action is the first Edit button to render; the dropdown also
  // has one, but it lives inside a portal that may not be mounted yet.
  await user.click(editButtons[0]!);
};

// =============================================================================
// Tests
// =============================================================================

describe('Sync Groups page - edit sync group wiring', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    mockFetchSyncGroups(SINGLE_SYNC_GROUP);
  });

  // ---------------------------------------------------------------------------
  // Clicking Edit on a row opens the Edit modal pre-bound to that row's data.
  // ---------------------------------------------------------------------------
  test('clicking Edit on a row opens the Edit modal for that sync group', async () => {
    const user = userEvent.setup();
    renderSyncGroupsPage();

    await clickRowEdit(user);

    const dialog = await screen.findByRole('dialog', { name: /stub edit sync group/i });
    expect(within(dialog).getByTestId('bound-name')).toHaveTextContent(mockSyncGroup.name);
  }, 20_000);

  // ---------------------------------------------------------------------------
  // After a successful edit save the page calls handleRefresh, which forces
  // a refetch through React Query.
  // ---------------------------------------------------------------------------
  test('table is refreshed after saving an edit', async () => {
    const user = userEvent.setup();
    renderSyncGroupsPage();

    await clickRowEdit(user);
    const initialCallCount = vi.mocked(fetchSyncGroups).mock.calls.length;

    await user.click(await screen.findByRole('button', { name: /trigger save/i }));

    await waitFor(() => {
      expect(vi.mocked(fetchSyncGroups).mock.calls.length).toBeGreaterThan(initialCallCount);
    });
  }, 20_000);
});
