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
// Test type: Page integration test
// Edits a display through the page's row action with the REAL edit modal, so
// the payload assertion covers the page → modal → API wiring. The stubbed-modal
// page tests live in actions.edit.test.tsx.
// =============================================================================

import { screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { vi, beforeEach, describe, test, expect } from 'vitest';

import { buildDisplay, mockDisplay } from '../fixtures/display';
import { renderDisplaysPage } from '../helpers/renderDisplaysPage';
import { mockFetchDisplays } from '../mocks/displaysApi';

import { fetchDisplayLocales, fetchDisplayVenues, updateDisplay } from '@/services/displaysApi';
import { testQueryClient } from '@/setupTests';
import { escapeRegExp } from '@/testUtils/escapeRegExp';
import { clickQuickAction, findRow } from '@/testUtils/rowActions';

// =============================================================================
// Module mocks
// =============================================================================

vi.mock('@/services/displaysApi');
vi.mock('@/services/displayGroupApi', () => ({
  fetchDisplayGroups: vi.fn().mockResolvedValue({ rows: [], totalCount: 0 }),
  deleteDisplayGroup: vi.fn(),
}));
vi.mock('@/services/displayProfileApi', () => ({
  fetchDisplayProfile: vi.fn().mockResolvedValue({ rows: [], totalCount: 0 }),
  fetchDisplayProfileById: vi.fn().mockResolvedValue(null),
}));
vi.mock('@/services/userApi', () => ({
  fetchUserPreference: vi.fn().mockResolvedValue(null),
  saveUserPreference: vi.fn().mockResolvedValue(undefined),
  fetchUsers: vi.fn().mockResolvedValue([]),
}));
vi.mock('@/services/folderApi');
vi.mock('@/services/layoutsApi', () => ({
  fetchLayouts: vi.fn().mockResolvedValue({ rows: [], totalCount: 0 }),
}));
vi.mock('@/services/playerSoftwareApi', () => ({
  fetchPlayerSoftware: vi.fn().mockResolvedValue({ rows: [], totalCount: 0 }),
}));
vi.mock('@/services/daypartApi', () => ({
  fetchDaypart: vi.fn().mockResolvedValue({ rows: [], totalCount: 0 }),
}));
vi.mock('@/components/ui/modals/Modal');
vi.mock('@/components/ui/FolderActionModals', () => ({ default: () => null }));
vi.mock('../../hooks/useDisplaysFilterOptions', () => ({
  useDisplaysFilterOptions: () => ({ filterOptions: [], isLoading: false }),
}));

// =============================================================================
// Tests
// =============================================================================

describe('Editing a display from the Displays page with the real edit modal', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    mockFetchDisplays({ rows: [mockDisplay], totalCount: 1 });
    // The edit modal loads these lists when it opens; the automock would
    // otherwise return undefined.
    vi.mocked(fetchDisplayVenues).mockResolvedValue([]);
    vi.mocked(fetchDisplayLocales).mockResolvedValue([]);
  });

  test(
    'editing a display from its row sends the new details to the API and shows them in the table',
    { tags: ['smoke'] },
    async () => {
      const user = userEvent.setup();
      const edited = buildDisplay({
        display: 'Lobby Display',
        description: 'Screen by the front door',
      });
      // Once the display is saved, the next table fetch returns the edited row.
      vi.mocked(updateDisplay).mockImplementation(async () => {
        mockFetchDisplays({ rows: [edited], totalCount: 1 });
        return edited;
      });

      renderDisplaysPage();
      await clickQuickAction(user, mockDisplay.display, /^edit$/i);
      const dialogName = new RegExp(escapeRegExp(`Edit "${mockDisplay.display}"`));
      const dialog = await screen.findByRole('dialog', { name: dialogName });

      const nameInput = within(dialog).getByRole('textbox', { name: /^display$/i });
      await user.clear(nameInput);
      await user.type(nameInput, 'Lobby Display');
      const descriptionInput = within(dialog).getByRole('textbox', { name: /description/i });
      await user.clear(descriptionInput);
      await user.type(descriptionInput, 'Screen by the front door');
      await user.click(within(dialog).getByRole('button', { name: /^save$/i }));

      // The fields this test changes, plus the ones that identify the display. The full payload is
      // checked by the modal's own tests (modals/edit/edit.form.test.tsx), so a new field in the
      // modal doesn't break this smoke test.
      await waitFor(() => {
        expect(updateDisplay).toHaveBeenCalledWith(
          mockDisplay.displayId,
          expect.objectContaining({
            display: 'Lobby Display',
            description: 'Screen by the front door',
            license: mockDisplay.license,
            folderId: mockDisplay.folderId,
          }),
        );
      });
      expect(updateDisplay).toHaveBeenCalledTimes(1);

      await findRow('Lobby Display');
      expect(
        screen.queryByRole('row', { name: new RegExp(escapeRegExp(mockDisplay.display)) }),
      ).not.toBeInTheDocument();
      expect(screen.queryByRole('dialog', { name: dialogName })).not.toBeInTheDocument();
    },
  );
});
