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

import {
  buildDisplayProfile,
  mockDisplayProfile,
  SINGLE_DISPLAY_PROFILE,
} from './fixtures/displayProfile';
import { renderDisplayProfilePage } from './helpers/renderDisplayProfilePage';
import { mockFetchDisplayProfile } from './mocks/displayProfileApi';

import { createDisplayProfile } from '@/services/displayProfileApi';
import { testQueryClient } from '@/setupTests';
import { findRow } from '@/testUtils/rowActions';

// =============================================================================
// Module mocks
// =============================================================================

// No stub for AddDisplayProfileModal here: this file drives the real modal
// from the page, so the payload assertion covers the page → modal → API wiring.
vi.mock('@/services/displayProfileApi');
vi.mock('@/services/userApi', () => ({
  fetchUserPreference: vi.fn().mockResolvedValue(null),
  saveUserPreference: vi.fn().mockResolvedValue(undefined),
}));
vi.mock('@/components/ui/modals/Modal');
vi.mock('../hooks/useDisplayProfileFilterOptions', () => ({
  useDisplayProfileFilterOptions: () => ({ filterOptions: [], isLoading: false }),
}));

// =============================================================================
// Tests — add a display profile through the page
// =============================================================================

describe('Adding a display profile from the Display Profiles page', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    mockFetchDisplayProfile(SINGLE_DISPLAY_PROFILE);
  });

  test(
    'adding a display profile from the page sends its details to the API and shows it in the table',
    { tags: ['smoke'] },
    async () => {
      const user = userEvent.setup();
      const created = buildDisplayProfile({
        displayProfileId: 2,
        name: 'Lobby Screens',
        type: 'windows',
        isDefault: 1,
      });
      // Once the profile is created, the next table fetch includes it.
      vi.mocked(createDisplayProfile).mockImplementation(async () => {
        mockFetchDisplayProfile({ rows: [mockDisplayProfile, created], totalCount: 2 });
        return created;
      });

      renderDisplayProfilePage();
      await findRow(mockDisplayProfile.name);

      await user.click(screen.getByRole('button', { name: /add display profile/i }));
      const dialog = await screen.findByRole('dialog', { name: /add display profile/i });

      await user.type(within(dialog).getByRole('textbox', { name: /^name$/i }), 'Lobby Screens');
      await user.click(within(dialog).getByRole('combobox', { name: /display type/i }));
      await user.click(await screen.findByRole('option', { name: 'Windows' }));
      await user.click(within(dialog).getByRole('checkbox', { name: /default profile/i }));
      await user.click(within(dialog).getByRole('button', { name: /^save$/i }));

      await waitFor(() => {
        expect(createDisplayProfile).toHaveBeenCalledWith({
          name: 'Lobby Screens',
          type: 'windows',
          isDefault: 1,
        });
      });
      expect(createDisplayProfile).toHaveBeenCalledTimes(1);

      // The new profile's Type cell shows the type that was picked.
      const row = await findRow('Lobby Screens');
      expect(within(row).getByRole('cell', { name: 'windows' })).toBeVisible();
      expect(
        screen.queryByRole('dialog', { name: /add display profile/i }),
      ).not.toBeInTheDocument();
    },
  );
});
