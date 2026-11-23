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

import { screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type React from 'react';
import { vi, beforeEach, describe, test, expect } from 'vitest';

import { mockCommand, SINGLE_COMMAND } from './fixtures/command';
import { renderCommandsPage } from './helpers/renderCommandsPage';
import { mockFetchCommands } from './mocks/commandApi';

import { fetchCommands } from '@/services/commandApi';
import { testQueryClient } from '@/setupTests';

// =============================================================================
// Module mocks
// =============================================================================

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

vi.mock('../components/AddEditCommandModal', () => ({
  default: ({
    mode,
    onClose,
    onSave,
  }: {
    mode: 'add' | 'edit';
    onClose: () => void;
    onSave: (saved: {
      commandId: number;
      command: string;
      code: string;
      description: string | null;
      userId: number;
      commandString: string | null;
      validationString: string | null;
      availableOn: string | null;
      createAlertOn: string;
      groupsWithPermissions: string | null;
    }) => void;
  }) => (
    <div role="dialog" aria-label={mode === 'edit' ? 'Edit Command' : 'Add Command'}>
      <button
        onClick={() => {
          onSave({
            commandId: 999,
            command: 'Brand New Command',
            code: 'NEW_CODE',
            description: null,
            userId: 1,
            commandString: null,
            validationString: null,
            availableOn: null,
            createAlertOn: 'never',
            groupsWithPermissions: null,
          });
          onClose();
        }}
      >
        stub-save
      </button>
    </div>
  ),
}));

// =============================================================================
// Tests — Add Command wiring
// =============================================================================

describe('Commands page - add wiring', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    mockFetchCommands(SINGLE_COMMAND);
  });

  test('"Add Command" button opens the Add modal', async () => {
    const user = userEvent.setup();
    renderCommandsPage();
    // Extended timeout: under full-suite parallel load this can take longer
    // than the default 1000ms, causing intermittent flakes.
    await screen.findByText(mockCommand.command, {}, { timeout: 5000 });

    await user.click(screen.getByRole('button', { name: /add command/i }));

    expect(await screen.findByRole('dialog', { name: /add command/i })).toBeInTheDocument();
  });

  test('the table is refreshed after a successful add', async () => {
    const user = userEvent.setup();
    renderCommandsPage();
    // Extended timeout: under full-suite parallel load this can take longer
    // than the default 1000ms, causing intermittent flakes.
    await screen.findByText(mockCommand.command, {}, { timeout: 5000 });

    await user.click(screen.getByRole('button', { name: /add command/i }));
    const fetchCountBeforeSave = vi.mocked(fetchCommands).mock.calls.length;
    await user.click(screen.getByRole('button', { name: /stub-save/i }));

    await waitFor(() => {
      expect(vi.mocked(fetchCommands).mock.calls.length).toBeGreaterThan(fetchCountBeforeSave);
    });
  });

  test('no second modal opens automatically after add', async () => {
    const user = userEvent.setup();
    renderCommandsPage();
    // Extended timeout: under full-suite parallel load this can take longer
    // than the default 1000ms, causing intermittent flakes.
    await screen.findByText(mockCommand.command, {}, { timeout: 5000 });

    await user.click(screen.getByRole('button', { name: /add command/i }));
    await user.click(screen.getByRole('button', { name: /stub-save/i }));

    await waitFor(() => {
      expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
    });
  });
});
