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
// Test type: Page integration — Delete Sync Group (single + bulk)
// Covers the row action wiring, the DeleteSyncGroupModal copy variants, the
// loading state, the error path, and the Promise.allSettled bulk flow.
// =============================================================================

import { screen, waitFor } from '@testing-library/react';
import userEvent, { type UserEvent } from '@testing-library/user-event';
import { vi, beforeEach, describe, test, expect } from 'vitest';

import {
  MULTIPLE_SYNC_GROUPS,
  mockSyncGroup,
  mockUser,
  SINGLE_SYNC_GROUP,
} from './fixtures/syncGroup';
import { renderSyncGroupsPage } from './helpers/renderSyncGroupsPage';
import { mockFetchSyncGroups } from './mocks/syncGroupApi';

import { deleteSyncGroup, fetchSyncGroups } from '@/services/syncGroupApi';
import { testQueryClient } from '@/setupTests';
import { knownFailure } from '@/testUtils/knownFailure';
import { buildCurrentUser, PERSONAS } from '@/testUtils/personas';
import { selectAllRowsAndWait, waitForDialogToClose } from '@/testUtils/rtl';
import type { User } from '@/types/user';

// =============================================================================
// Module mocks
// =============================================================================

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

vi.mock('../components/AddAndEditSyncGroupModal', () => ({
  default: () => null,
}));
vi.mock('../components/ManageMembersModal', () => ({
  default: () => null,
}));

// =============================================================================
// Helpers
// =============================================================================

const openRowDeleteModal = async (user: UserEvent) => {
  await screen.findByText(mockSyncGroup.name);
  await user.click(screen.getByRole('button', { name: /more actions/i }));

  const deleteButton = await waitFor(() => screen.getByRole('button', { name: /^delete$/i }), {
    timeout: 5000,
  });
  await user.click(deleteButton);
};

const selectAllRows = async (user: UserEvent) => {
  // The first checkbox is the column header's "select all" toggle.
  const checkboxes = screen.getAllByRole('checkbox', { name: /select/i });
  await user.click(checkboxes[0]!);
};

// =============================================================================
// Tests — Single delete
// =============================================================================

describe('Sync Groups page - single delete', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    mockFetchSyncGroups(SINGLE_SYNC_GROUP);
  });

  // ---------------------------------------------------------------------------
  // The row Delete action opens the confirmation modal with the singular
  // heading and the row's name in the body.
  // ---------------------------------------------------------------------------
  test('Delete row action opens the confirmation modal showing the sync group name', async () => {
    const user = userEvent.setup();
    renderSyncGroupsPage();

    await openRowDeleteModal(user);

    expect(await screen.findByText('Delete Sync Group?')).toBeInTheDocument();
    // The body wraps the name in a <strong>; match by partial text.
    expect(screen.getByText(mockSyncGroup.name, { selector: 'strong' })).toBeInTheDocument();
  });

  // ---------------------------------------------------------------------------
  // Cancel closes the modal without calling deleteSyncGroup.
  // ---------------------------------------------------------------------------
  test('clicking Cancel closes the modal without deleting', async () => {
    const user = userEvent.setup();
    renderSyncGroupsPage();

    await openRowDeleteModal(user);
    await user.click(screen.getByRole('button', { name: /^cancel$/i }));

    expect(screen.queryByText('Delete Sync Group?')).not.toBeInTheDocument();
    expect(deleteSyncGroup).not.toHaveBeenCalled();
  });

  // ---------------------------------------------------------------------------
  // Confirming the delete calls deleteSyncGroup with the row's id and closes
  // the modal on success.
  // ---------------------------------------------------------------------------
  test('clicking Yes, Delete removes the sync group and closes the modal', async () => {
    const user = userEvent.setup();
    vi.mocked(deleteSyncGroup).mockResolvedValue(undefined);
    renderSyncGroupsPage();

    await openRowDeleteModal(user);
    await user.click(screen.getByRole('button', { name: /yes, delete/i }));

    await waitFor(() => {
      expect(deleteSyncGroup).toHaveBeenCalledWith(mockSyncGroup.syncGroupId);
    });
    await waitForDialogToClose('Delete Sync Group?');
  });

  // ---------------------------------------------------------------------------
  // While the delete request is in flight the action button reads "Deleting…"
  // and is disabled so it can't be clicked twice.
  // ---------------------------------------------------------------------------
  test('Delete button shows "Deleting…" while the request is in progress', async () => {
    const user = userEvent.setup();
    let resolveDelete: () => void = () => {};
    vi.mocked(deleteSyncGroup).mockReturnValueOnce(
      new Promise<void>((resolve) => {
        resolveDelete = resolve;
      }),
    );
    renderSyncGroupsPage();

    await openRowDeleteModal(user);
    await user.click(screen.getByRole('button', { name: /yes, delete/i }));

    expect(await screen.findByRole('button', { name: /deleting/i })).toBeDisabled();

    // Wait for the resulting close so the update isn't left outside act().
    resolveDelete();
    await waitForDialogToClose('Delete Sync Group?');
  });

  // ---------------------------------------------------------------------------
  // A failed delete sets deleteError on the actions hook, which is forwarded
  // to the modal's error slot. The modal stays open.
  // ---------------------------------------------------------------------------
  test('a failed delete keeps the modal open and shows the error', async () => {
    const user = userEvent.setup();
    vi.mocked(deleteSyncGroup).mockRejectedValueOnce({
      isAxiosError: true,
      response: { data: { message: 'Cannot delete — group is in use.' } },
    });
    renderSyncGroupsPage();

    await openRowDeleteModal(user);
    await user.click(screen.getByRole('button', { name: /yes, delete/i }));

    expect(await screen.findByText('Cannot delete — group is in use.')).toBeInTheDocument();
    expect(screen.getByText('Delete Sync Group?')).toBeInTheDocument();
  });

  // ---------------------------------------------------------------------------
  // Repeats the singular-heading assertion for clarity, paired with the
  // plural-heading test below.
  // ---------------------------------------------------------------------------
  test('single item: heading is singular', async () => {
    const user = userEvent.setup();
    renderSyncGroupsPage();

    await openRowDeleteModal(user);

    expect(await screen.findByText('Delete Sync Group?')).toBeInTheDocument();
    expect(screen.queryByText('Delete Sync Groups?')).not.toBeInTheDocument();
  });
});

// =============================================================================
// Tests — Bulk delete
// =============================================================================

describe('Sync Groups page - bulk delete', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    mockFetchSyncGroups(MULTIPLE_SYNC_GROUPS);
  });

  // ---------------------------------------------------------------------------
  // With multiple rows selected the modal shows the plural heading and the
  // count in the body.
  // ---------------------------------------------------------------------------
  test('multiple items: heading is plural', async () => {
    const user = userEvent.setup();
    renderSyncGroupsPage();
    await screen.findByText('Sync Group Alpha');

    await selectAllRows(user);
    await user.click(screen.getByRole('button', { name: /delete selected/i }));

    expect(await screen.findByText('Delete Sync Groups?')).toBeInTheDocument();
    expect(screen.getByText('2', { selector: 'strong' })).toBeInTheDocument();
  });

  // ---------------------------------------------------------------------------
  // Bulk confirm fires deleteSyncGroup once per selected row. Order is not
  // guaranteed (Promise.allSettled fires concurrently), so we only assert
  // call count and the set of ids called.
  // ---------------------------------------------------------------------------
  test('bulk confirm deletes every selected item', async () => {
    const user = userEvent.setup();
    vi.mocked(deleteSyncGroup).mockResolvedValue(undefined);
    renderSyncGroupsPage();
    await screen.findByText('Sync Group Alpha');

    await selectAllRows(user);
    await user.click(screen.getByRole('button', { name: /delete selected/i }));
    await screen.findByText('Delete Sync Groups?');
    await user.click(screen.getByRole('button', { name: /yes, delete/i }));

    await waitFor(() => {
      expect(deleteSyncGroup).toHaveBeenCalledTimes(2);
    });
    const calledIds = vi
      .mocked(deleteSyncGroup)
      .mock.calls.map((c) => c[0])
      .sort();
    expect(calledIds).toEqual([1, 2]);
  });

  // ---------------------------------------------------------------------------
  // Partial bulk delete failure path — one row succeeds, another fails.
  // `useSyncGroupActions.confirmDelete` uses Promise.allSettled, so all
  // calls fire. On any rejection it surfaces the API error, clears the row
  // selection, and refreshes the table — but it does NOT close the modal
  // ---------------------------------------------------------------------------
  test('a partial bulk delete failure shows the error and refreshes the table', async () => {
    const user = userEvent.setup();
    // Row 1 resolves, row 2 rejects with a server message.
    vi.mocked(deleteSyncGroup).mockImplementation((id: number) => {
      if (id === 2) {
        return Promise.reject({
          isAxiosError: true,
          response: { data: { message: 'Sync group 2 is in use.' } },
        });
      }
      return Promise.resolve(undefined);
    });
    renderSyncGroupsPage();
    await screen.findByText('Sync Group Alpha');
    const initialFetchCount = vi.mocked(fetchSyncGroups).mock.calls.length;

    await selectAllRows(user);
    await user.click(screen.getByRole('button', { name: /delete selected/i }));
    await screen.findByText('Delete Sync Groups?');
    await user.click(screen.getByRole('button', { name: /yes, delete/i }));

    // Error surfaces in the modal and the modal stays open.
    expect(
      await screen.findByText('1 sync group(s) deleted successfully. Sync group 2 is in use.'),
    ).toBeInTheDocument();
    expect(screen.getByText('Delete Sync Groups?')).toBeInTheDocument();

    // Both rows were attempted and the table was refreshed (handleRefresh
    // invalidates the syncGroups query, triggering another fetch).
    expect(deleteSyncGroup).toHaveBeenCalledTimes(2);
    await waitFor(() => {
      expect(vi.mocked(fetchSyncGroups).mock.calls.length).toBeGreaterThan(initialFetchCount);
    });
  });
});

// =============================================================================
// Tests — Bulk delete as a user who is not a super admin
// =============================================================================

// A display manager given the Sync Group view and edit features. Every other
// test here runs as a super admin, who passes every feature check.
const syncEditor = (features: Record<string, boolean> = {}): User =>
  buildCurrentUser(PERSONAS.displayManager, {
    settings: mockUser.settings,
    features: { 'display.syncView': true, 'display.syncModify': true, ...features },
  });

describe('Sync Groups page - bulk delete as a user who is not a super admin', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    mockFetchSyncGroups(MULTIPLE_SYNC_GROUPS);
  });

  test('a user who can edit sync groups gets Delete Selected', async () => {
    const user = userEvent.setup();
    renderSyncGroupsPage({ user: syncEditor() });

    await selectAllRowsAndWait(user, 'Sync Group Alpha');

    expect(screen.getByRole('button', { name: /^delete selected$/i })).toBeInTheDocument();
  });

  // xibo#4086 — open. Delete Selected has no feature check, so a
  // user without the Sync Group edit feature is offered it, while the row
  // Delete needs that feature. The server refuses each delete with 403
  // "Feature not enabled", so nothing is lost, but the user is offered an
  // action that can only fail.
  knownFailure(
    'without the Sync Group edit feature there is no Delete Selected',
    async () => {
      const user = userEvent.setup();
      renderSyncGroupsPage({ user: syncEditor({ 'display.syncModify': false }) });

      await selectAllRowsAndWait(user, 'Sync Group Alpha');
      // Control: the rows themselves offer this user no Delete.
      expect(screen.queryByRole('button', { name: /more actions/i })).not.toBeInTheDocument();
    },
    () =>
      expect(screen.queryByRole('button', { name: /^delete selected$/i })).not.toBeInTheDocument(),
  );
});
