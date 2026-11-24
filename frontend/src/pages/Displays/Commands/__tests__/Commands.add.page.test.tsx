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

import { buildCommand, mockCommand, SINGLE_COMMAND } from './fixtures/command';
import { renderCommandsPage } from './helpers/renderCommandsPage';
import { mockFetchCommands } from './mocks/commandApi';

import { createCommand } from '@/services/commandApi';
import { testQueryClient } from '@/setupTests';
import { findRow } from '@/testUtils/rowActions';

// =============================================================================
// Module mocks
// =============================================================================

// No stub for AddEditCommandModal here: this file drives the real modal from
// the page, so the payload assertion covers the page → modal → API wiring.
vi.mock('@/services/commandApi');
vi.mock('@/services/userApi', () => ({
  fetchUserPreference: vi.fn().mockResolvedValue(null),
  saveUserPreference: vi.fn().mockResolvedValue(undefined),
  fetchUsers: vi.fn().mockResolvedValue([]),
}));
vi.mock('@/components/ui/modals/Modal');
vi.mock('../hooks/useCommandFilterOptions', () => ({
  useCommandFilterOptions: () => ({ filterOptions: [], isLoading: false }),
}));

// =============================================================================
// Tests — add a command through the page
// =============================================================================

describe('Adding a command from the Commands page', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    mockFetchCommands(SINGLE_COMMAND);
  });

  test(
    'adding a command from the page sends its details to the API and shows it in the table',
    { tags: ['smoke'] },
    async () => {
      const user = userEvent.setup();
      const created = buildCommand({
        commandId: 2,
        command: 'Restart Player',
        code: 'RESTART_PLAYER',
        description: 'Restarts the player app',
        commandString: 'reboot',
        validationString: 'ok',
      });
      // Once the command is created, the next table fetch includes it.
      vi.mocked(createCommand).mockImplementation(async () => {
        mockFetchCommands({ rows: [mockCommand, created], totalCount: 2 });
        return created;
      });

      renderCommandsPage();
      await findRow(mockCommand.command);

      await user.click(screen.getByRole('button', { name: /add command/i }));
      const dialog = await screen.findByRole('dialog', { name: /add command/i });

      await user.type(within(dialog).getByRole('textbox', { name: /^name$/i }), 'Restart Player');
      await user.type(within(dialog).getByRole('textbox', { name: /^code$/i }), 'RESTART_PLAYER');
      await user.type(
        within(dialog).getByRole('textbox', { name: /^description/i }),
        'Restarts the player app',
      );
      await user.type(within(dialog).getByRole('textbox', { name: /^command string$/i }), 'reboot');
      await user.type(within(dialog).getByRole('textbox', { name: /^validation$/i }), 'ok');
      await user.click(within(dialog).getByRole('button', { name: /^save$/i }));

      await waitFor(() => {
        expect(createCommand).toHaveBeenCalledWith({
          command: 'Restart Player',
          code: 'RESTART_PLAYER',
          description: 'Restarts the player app',
          commandString: 'reboot',
          validationString: 'ok',
          createAlertOn: 'never',
        });
      });
      expect(createCommand).toHaveBeenCalledTimes(1);

      expect(await findRow('Restart Player')).toHaveTextContent('RESTART_PLAYER');
      expect(screen.queryByRole('dialog', { name: /add command/i })).not.toBeInTheDocument();
    },
  );
});
