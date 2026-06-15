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
import userEvent, { type UserEvent } from '@testing-library/user-event';
import type React from 'react';
import { vi, beforeEach, describe, test, expect } from 'vitest';

import { MULTIPLE_COMMANDS, mockCommand, SINGLE_COMMAND } from './fixtures/command';
import { renderCommandsPage } from './helpers/renderCommandsPage';
import { mockFetchCommands } from './mocks/commandApi';

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
vi.mock('../components/AddEditCommandModal', () => ({ default: () => null }));

// Stub the shared ShareModal so we can assert it opened and for which entity.
vi.mock('@/components/ui/modals/ShareModal', () => ({
  default: ({ isOpen, entityId }: { isOpen?: boolean; entityId?: number | number[] | null }) =>
    isOpen ? (
      <div
        role="dialog"
        aria-label="Share Command"
        data-entity-id={JSON.stringify(entityId ?? null)}
      />
    ) : null,
}));

// =============================================================================
// Helpers
// =============================================================================

const openMoreActions = async (user: UserEvent) => {
  await screen.findByText(mockCommand.command);
  await user.click(screen.getByRole('button', { name: /more actions/i }));
};

const selectAllRows = async (user: UserEvent) => {
  const checkboxes = screen.getAllByRole('checkbox', { name: /select row/i });
  await user.click(checkboxes[0]!);
};

// =============================================================================
// Tests — Share wiring
// =============================================================================

describe('Commands page - share wiring', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
  });

  test('the Share row action opens the Share modal for the command', async () => {
    const user = userEvent.setup();
    mockFetchCommands(SINGLE_COMMAND);
    renderCommandsPage();

    await openMoreActions(user);
    await user.click(await waitFor(() => screen.getByRole('button', { name: /^share$/i })));

    const dialog = await screen.findByRole('dialog', { name: /share command/i });
    expect(dialog).toHaveAttribute('data-entity-id', String(mockCommand.commandId));
    // Routing: the Share action opens ONLY the Share modal — not Delete/Edit.
    expect(screen.getAllByRole('dialog')).toHaveLength(1);
    expect(screen.queryByText('Delete Command?')).not.toBeInTheDocument();
  }, 20_000);

  test('"Share Selected" opens the Share modal for the selected commands', async () => {
    const user = userEvent.setup();
    mockFetchCommands(MULTIPLE_COMMANDS);
    renderCommandsPage();
    await screen.findByText('Command Alpha');

    await selectAllRows(user);
    await user.click(screen.getByRole('button', { name: /share selected/i }));

    const dialog = await screen.findByRole('dialog', { name: /share command/i });
    expect(dialog).toHaveAttribute('data-entity-id', '[1,2]');
  });
});
