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

import { buildDisplayGroup, mockDisplayGroup, SINGLE_DISPLAY_GROUP } from './fixtures/displayGroup';
import { renderDisplayGroupPage } from './helpers/renderDisplayGroupPage';
import { mockFetchDisplayGroups } from './mocks/displayGroupApi';

import { updateDisplayGroup } from '@/services/displayGroupApi';
import { testQueryClient } from '@/setupTests';
import { clickMenuAction, findRow } from '@/testUtils/rowActions';

// =============================================================================
// Module mocks
// =============================================================================

// No stub for AddAndEditDisplayGroupModal here: this file drives the real
// modal from the page, so the payload assertion covers the page → modal → API
// wiring.
vi.mock('@/services/displayGroupApi');
vi.mock('@/services/displaysApi', () => ({
  fetchDisplays: vi.fn().mockResolvedValue({ rows: [], totalCount: 0 }),
  sendCommand: vi.fn(),
  triggerWebhook: vi.fn(),
}));
vi.mock('@/services/userApi', () => ({
  fetchUserPreference: vi.fn().mockResolvedValue(null),
  saveUserPreference: vi.fn().mockResolvedValue(undefined),
  fetchUsers: vi.fn().mockResolvedValue([]),
}));
vi.mock('@/services/folderApi');
vi.mock('@/components/ui/modals/Modal');
vi.mock('@/components/ui/FolderActionModals', () => ({ default: () => null }));

// =============================================================================
// Tests
// =============================================================================

describe('Editing a display group from the Display Groups page', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    mockFetchDisplayGroups(SINGLE_DISPLAY_GROUP);
  });

  test(
    'editing a display group from the page sends the new details to the API and shows them in the table',
    { tags: ['smoke'] },
    async () => {
      const user = userEvent.setup();
      const edited = buildDisplayGroup({
        displayGroup: 'Lobby Screens',
        description: 'Screens in the main lobby',
      });
      // Once the group is saved, the next table fetch returns the edited row.
      vi.mocked(updateDisplayGroup).mockImplementation(async () => {
        mockFetchDisplayGroups({ rows: [edited], totalCount: 1 });
        return edited;
      });

      renderDisplayGroupPage();
      await clickMenuAction(user, mockDisplayGroup.displayGroup, /^edit$/i);
      const dialog = await screen.findByRole('dialog', { name: /edit display group/i });

      const nameInput = within(dialog).getByRole('textbox', { name: /^name$/i });
      await user.clear(nameInput);
      await user.type(nameInput, 'Lobby Screens');
      const descriptionInput = within(dialog).getByRole('textbox', { name: /^description/i });
      await user.clear(descriptionInput);
      await user.type(descriptionInput, 'Screens in the main lobby');
      await user.click(within(dialog).getByRole('button', { name: /^save$/i }));

      await waitFor(() => {
        expect(updateDisplayGroup).toHaveBeenCalledWith(mockDisplayGroup.displayGroupId, {
          displayGroup: 'Lobby Screens',
          description: 'Screens in the main lobby',
          isDynamic: 0,
          folderId: mockDisplayGroup.folderId,
        });
      });
      expect(updateDisplayGroup).toHaveBeenCalledTimes(1);

      expect(await findRow('Lobby Screens')).toHaveTextContent('Screens in the main lobby');
      expect(screen.queryByRole('dialog', { name: /edit display group/i })).not.toBeInTheDocument();
    },
  );
});
