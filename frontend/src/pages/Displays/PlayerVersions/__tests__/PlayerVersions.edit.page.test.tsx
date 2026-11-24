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
  buildPlayerVersion,
  mockPlayerVersion,
  SINGLE_PLAYER_VERSION,
} from './fixtures/playerVersion';
import { renderPlayerVersionsPage } from './helpers/renderPlayerVersionsPage';
import { mockFetchPlayerVersions } from './mocks/playerVersionApi';

import { updatePlayerVersion } from '@/services/playerVersionApi';
import { testQueryClient } from '@/setupTests';
import { escapeRegExp } from '@/testUtils/escapeRegExp';
import { clickQuickAction, findRow } from '@/testUtils/rowActions';

// =============================================================================
// Module mocks
// =============================================================================

// No stub for EditPlayerVersionModal here: this file drives the real modal
// from the page, so the payload assertion covers the page → modal → API wiring.
vi.mock('@/services/playerVersionApi');
vi.mock('@/services/userApi', () => ({
  fetchUserPreference: vi.fn().mockResolvedValue(null),
  saveUserPreference: vi.fn().mockResolvedValue(undefined),
  fetchUsers: vi.fn().mockResolvedValue([]),
}));
vi.mock('@/components/ui/modals/Modal');
vi.mock('../hooks/usePlayerVersionsFilterOptions', () => ({
  usePlayerVersionFilterOptions: () => ({ filterOptions: [] }),
}));

// =============================================================================
// Tests — edit a player version through the page
// =============================================================================

describe('Editing a player version from the Player Versions page', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    mockFetchPlayerVersions(SINGLE_PLAYER_VERSION);
  });

  test(
    'editing a player version from its row sends the new values to the API and shows them in the table',
    { tags: ['smoke'] },
    async () => {
      const user = userEvent.setup();
      const edited = buildPlayerVersion({
        playerShowVersion: 'Android v4.1',
        version: '4.1.0',
        code: 410,
      });
      // Once the version is saved, the next table fetch returns the edited row.
      vi.mocked(updatePlayerVersion).mockImplementation(async () => {
        mockFetchPlayerVersions({ rows: [edited], totalCount: 1 });
        return edited;
      });

      renderPlayerVersionsPage();
      await clickQuickAction(user, mockPlayerVersion.playerShowVersion, /^edit$/i);
      const dialogName = new RegExp(escapeRegExp(`Edit "${mockPlayerVersion.playerShowVersion}"`));
      const dialog = await screen.findByRole('dialog', { name: dialogName });

      const nameInput = within(dialog).getByRole('textbox', { name: /player version name/i });
      await user.clear(nameInput);
      await user.type(nameInput, 'Android v4.1');
      const versionInput = within(dialog).getByRole('textbox', { name: /^version$/i });
      await user.clear(versionInput);
      await user.type(versionInput, '4.1.0');
      const codeInput = within(dialog).getByRole('spinbutton', { name: /^code$/i });
      await user.clear(codeInput);
      await user.type(codeInput, '410');
      await user.click(within(dialog).getByRole('button', { name: /^save$/i }));

      await waitFor(() => {
        expect(updatePlayerVersion).toHaveBeenCalledWith(mockPlayerVersion.versionId, {
          playerShowVersion: 'Android v4.1',
          version: '4.1.0',
          code: 410,
        });
      });
      expect(updatePlayerVersion).toHaveBeenCalledTimes(1);

      expect(await findRow('Android v4.1')).toHaveTextContent('4.1.0');
      expect(screen.queryByRole('dialog', { name: dialogName })).not.toBeInTheDocument();
    },
  );
});
