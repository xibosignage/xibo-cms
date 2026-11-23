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
    command,
    onClose,
    onSave,
  }: {
    mode: 'add' | 'edit';
    command: { commandId: number } | null;
    onClose: () => void;
    onSave: (saved: { commandId: number }) => void;
  }) => (
    <div
      role="dialog"
      aria-label={mode === 'edit' ? 'Edit Command' : 'Add Command'}
      data-command-id={command?.commandId ?? ''}
    >
      <button
        onClick={() => {
          onSave({ commandId: command?.commandId ?? 0 });
          onClose();
        }}
      >
        stub-save
      </button>
    </div>
  ),
}));

// =============================================================================
// Tests — Edit Command wiring
// =============================================================================

describe('Commands page - edit wiring', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    mockFetchCommands(SINGLE_COMMAND);
  });

  test('clicking Edit on a row opens the Edit modal for that command', async () => {
    const user = userEvent.setup();
    renderCommandsPage();
    await screen.findByText(mockCommand.command);

    await user.click(screen.getByRole('button', { name: /^edit$/i }));

    const dialog = await screen.findByRole('dialog', { name: /edit command/i });
    expect(dialog).toHaveAttribute('data-command-id', String(mockCommand.commandId));
  });

  test('the Edit action opens the Edit modal (not Share or Delete)', async () => {
    const user = userEvent.setup();
    renderCommandsPage();
    await screen.findByText(mockCommand.command);

    await user.click(screen.getByRole('button', { name: /^edit$/i }));

    await screen.findByRole('dialog', { name: /edit command/i });
    expect(screen.getAllByRole('dialog')).toHaveLength(1);
    expect(screen.queryByRole('dialog', { name: /share command/i })).not.toBeInTheDocument();
    expect(screen.queryByText('Delete Command?')).not.toBeInTheDocument();
  });

  test('the table is refreshed after saving an edit', async () => {
    const user = userEvent.setup();
    renderCommandsPage();
    await screen.findByText(mockCommand.command);

    await user.click(screen.getByRole('button', { name: /^edit$/i }));
    await screen.findByRole('dialog', { name: /edit command/i });
    const fetchCountBeforeSave = vi.mocked(fetchCommands).mock.calls.length;
    await user.click(screen.getByRole('button', { name: /stub-save/i }));

    await waitFor(() => {
      expect(vi.mocked(fetchCommands).mock.calls.length).toBeGreaterThan(fetchCountBeforeSave);
    });
  });
});
