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

import { screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { vi, beforeEach, describe, test, expect } from 'vitest';

import { buildSyncGroup, mockSyncGroup, SINGLE_SYNC_GROUP } from './fixtures/syncGroup';
import { renderSyncGroupsPage } from './helpers/renderSyncGroupsPage';
import { mockFetchSyncGroups } from './mocks/syncGroupApi';

import { createSyncGroup, fetchSyncGroupDisplays } from '@/services/syncGroupApi';
import { testQueryClient } from '@/setupTests';
import { findRow } from '@/testUtils/rowActions';

// =============================================================================
// Module mocks
// =============================================================================

// No stub for AddAndEditSyncGroupModal or ManageMembersModal here: this file
// drives the real modals from the page, so the payload assertion covers the
// page → modal → API wiring.
vi.mock('@/services/syncGroupApi');
vi.mock('@/services/displaysApi', () => ({
  fetchDisplays: vi.fn().mockResolvedValue({ rows: [], totalCount: 0 }),
}));
vi.mock('@/services/userApi', () => ({
  fetchUserPreference: vi.fn().mockResolvedValue(null),
  saveUserPreference: vi.fn().mockResolvedValue(undefined),
  fetchUsers: vi.fn().mockResolvedValue([]),
}));
vi.mock('@/services/folderApi');
vi.mock('@/components/ui/modals/Modal');
vi.mock('@/components/ui/FolderActionModals', () => ({ default: () => null }));
vi.mock('../hooks/useSyncGroupFilterOptions', () => ({
  useSyncGroupFilterOptions: () => ({ filterOptions: [], isLoading: false }),
}));

// =============================================================================
// Tests — add a sync group through the page
// =============================================================================

describe('Adding a sync group from the Sync Groups page', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    mockFetchSyncGroups(SINGLE_SYNC_GROUP);
    // A new sync group has no members yet.
    vi.mocked(fetchSyncGroupDisplays).mockResolvedValue([]);
  });

  test(
    'adding a sync group from the page sends its settings to the API, lists it and opens its members',
    { tags: ['smoke'] },
    async () => {
      const user = userEvent.setup();
      const created = buildSyncGroup({
        syncGroupId: 2,
        name: 'Lobby Sync',
        syncPublisherPort: 9600,
      });
      // Once the group is created, the next table fetch includes it.
      vi.mocked(createSyncGroup).mockImplementation(async () => {
        mockFetchSyncGroups({ rows: [created, mockSyncGroup], totalCount: 2 });
        return created;
      });

      renderSyncGroupsPage();
      await findRow(mockSyncGroup.name);

      await user.click(screen.getByRole('button', { name: /add sync group/i }));
      const dialog = await screen.findByRole('dialog', { name: /add sync group/i });

      await user.type(within(dialog).getByRole('textbox', { name: /^name$/i }), 'Lobby Sync');
      const portInput = within(dialog).getByRole('spinbutton', { name: /publisher port/i });
      await user.clear(portInput);
      await user.type(portInput, '9600');
      await user.click(within(dialog).getByRole('button', { name: /^save$/i }));

      await waitFor(() => {
        expect(createSyncGroup).toHaveBeenCalledWith({
          name: 'Lobby Sync',
          syncPublisherPort: 9600,
          syncSwitchDelay: 750,
          syncVideoPauseDelay: 100,
        });
      });
      expect(createSyncGroup).toHaveBeenCalledTimes(1);

      // After an add the page moves straight on to choosing the new group's members.
      await screen.findByRole('dialog', { name: 'Manage Members for Lobby Sync' });
      expect(screen.queryByRole('dialog', { name: /add sync group/i })).not.toBeInTheDocument();
      const row = await findRow('Lobby Sync');
      expect(within(row).getByText('9600')).toBeInTheDocument();
    },
  );
});
