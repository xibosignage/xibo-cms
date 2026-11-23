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

// Sync Groups had no Share action at all (xibo#3990, fixed in xibo-cms PR #3979).
// Share needs three things: the Sync Group edit feature, the Sharing feature,
// and share permission on the row itself. Every test runs as a non-admin,
// because a super admin passes every feature check and would hide a gate on
// the wrong feature.

import { screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type React from 'react';
import { vi, beforeEach, describe, test, expect } from 'vitest';

import {
  buildSyncGroup,
  mockSyncGroup,
  mockUser,
  MULTIPLE_SYNC_GROUPS,
  SINGLE_SYNC_GROUP,
} from './fixtures/syncGroup';
import { renderSyncGroupsPage } from './helpers/renderSyncGroupsPage';
import { mockFetchSyncGroups } from './mocks/syncGroupApi';

import { testQueryClient } from '@/setupTests';
import { buildCurrentUser, PERSONAS } from '@/testUtils/personas';
import { openFirstRowMenu, selectAllRowsAndWait } from '@/testUtils/rtl';
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
vi.mock('../components/AddAndEditSyncGroupModal', () => ({ default: () => null }));
vi.mock('../components/ManageMembersModal', () => ({ default: () => null }));

// Stub the shared ShareModal so we can assert it opened and for which entity.
vi.mock('@/components/ui/modals/ShareModal', () => ({
  default: ({ isOpen, entityId }: { isOpen?: boolean; entityId?: number | number[] | null }) =>
    isOpen ? (
      <div
        role="dialog"
        aria-label="Share Sync Group"
        data-entity-id={JSON.stringify(entityId ?? null)}
      />
    ) : null,
}));

// =============================================================================
// Users
// =============================================================================

// A display manager given the two features Share needs. The persona ships with
// neither, so they are switched on here.
const syncSharer = (features: Record<string, boolean> = {}): User =>
  buildCurrentUser(PERSONAS.displayManager, {
    settings: mockUser.settings,
    features: { 'display.syncModify': true, 'user.sharing': true, ...features },
  });

// =============================================================================
// Tests — row menu
// =============================================================================

describe('Sync Groups page - who can share', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
  });

  test('a user who can edit and share sync groups gets Share in the row menu', async () => {
    const user = userEvent.setup();
    mockFetchSyncGroups(SINGLE_SYNC_GROUP);
    renderSyncGroupsPage({ user: syncSharer() });

    await openFirstRowMenu(user, mockSyncGroup.name);

    expect(screen.getByRole('button', { name: /^share$/i })).toBeInTheDocument();
  });

  test('without the Sharing feature there is no Share', async () => {
    const user = userEvent.setup();
    mockFetchSyncGroups(SINGLE_SYNC_GROUP);
    renderSyncGroupsPage({ user: syncSharer({ 'user.sharing': false }) });

    await openFirstRowMenu(user, mockSyncGroup.name);

    expect(screen.queryByRole('button', { name: /^share$/i })).not.toBeInTheDocument();
  });

  // Without the edit feature the row has no actions at all, so there is no
  // menu to open. Wait for the row, then check no Share appears anywhere.
  test('without the Sync Group edit feature there is no Share', async () => {
    mockFetchSyncGroups(SINGLE_SYNC_GROUP);
    renderSyncGroupsPage({ user: syncSharer({ 'display.syncModify': false }) });

    await screen.findByText(mockSyncGroup.name);

    expect(screen.queryByRole('button', { name: /more actions/i })).not.toBeInTheDocument();
    expect(screen.queryByRole('button', { name: /^share$/i })).not.toBeInTheDocument();
  });

  test('there is no Share on a sync group the user may edit but not share', async () => {
    const user = userEvent.setup();
    mockFetchSyncGroups({
      rows: [
        buildSyncGroup({
          userPermissions: { view: 1, edit: 1, delete: 1, modifyPermissions: 0 },
        }),
      ],
      totalCount: 1,
    });
    renderSyncGroupsPage({ user: syncSharer() });

    await openFirstRowMenu(user, mockSyncGroup.name);

    expect(screen.queryByRole('button', { name: /^share$/i })).not.toBeInTheDocument();
  });

  // ===========================================================================
  // Bulk menu
  // ===========================================================================

  test('a user who can edit and share sync groups gets Share Selected in the bulk menu', async () => {
    const user = userEvent.setup();
    mockFetchSyncGroups(MULTIPLE_SYNC_GROUPS);
    renderSyncGroupsPage({ user: syncSharer() });

    await selectAllRowsAndWait(user, 'Sync Group Alpha');

    expect(screen.getByRole('button', { name: /^share selected$/i })).toBeInTheDocument();
  });

  test('without the Sharing feature there is no Share Selected', async () => {
    const user = userEvent.setup();
    mockFetchSyncGroups(MULTIPLE_SYNC_GROUPS);
    renderSyncGroupsPage({ user: syncSharer({ 'user.sharing': false }) });

    await selectAllRowsAndWait(user, 'Sync Group Alpha');

    expect(screen.queryByRole('button', { name: /^share selected$/i })).not.toBeInTheDocument();
  });

  test('without the Sync Group edit feature there is no Share Selected', async () => {
    const user = userEvent.setup();
    mockFetchSyncGroups(MULTIPLE_SYNC_GROUPS);
    renderSyncGroupsPage({ user: syncSharer({ 'display.syncModify': false }) });

    await selectAllRowsAndWait(user, 'Sync Group Alpha');

    expect(screen.queryByRole('button', { name: /^share selected$/i })).not.toBeInTheDocument();
  });

  // ===========================================================================
  // Wiring
  // ===========================================================================

  test('Share opens the share dialog for that sync group', async () => {
    const user = userEvent.setup();
    mockFetchSyncGroups(SINGLE_SYNC_GROUP);
    renderSyncGroupsPage({ user: syncSharer() });

    await openFirstRowMenu(user, mockSyncGroup.name);
    await user.click(screen.getByRole('button', { name: /^share$/i }));

    const dialog = await screen.findByRole('dialog', { name: /share sync group/i });
    expect(dialog).toHaveAttribute('data-entity-id', String(mockSyncGroup.syncGroupId));
  });
});
