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

vi.mock('react-i18next');

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
    await screen.findByText(mockCommand.command);

    await user.click(screen.getByRole('button', { name: /add command/i }));

    expect(await screen.findByRole('dialog', { name: /add command/i })).toBeInTheDocument();
  });

  test('the table is refreshed after a successful add', async () => {
    const user = userEvent.setup();
    renderCommandsPage();
    await screen.findByText(mockCommand.command);

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
    await screen.findByText(mockCommand.command);

    await user.click(screen.getByRole('button', { name: /add command/i }));
    await user.click(screen.getByRole('button', { name: /stub-save/i }));

    await waitFor(() => {
      expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
    });
  });
});
