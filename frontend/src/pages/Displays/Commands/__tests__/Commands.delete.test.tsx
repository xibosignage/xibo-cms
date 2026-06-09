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

import { deleteCommand, fetchCommands } from '@/services/commandApi';
import { testQueryClient } from '@/setupTests';

// =============================================================================
// Module mocks
// =============================================================================

vi.mock('react-i18next', () => {
  const t = (key: string) => key;
  return {
    useTranslation: () => ({ t, i18n: { changeLanguage: vi.fn() } }),
    Trans: ({ children }: { children: React.ReactNode }) => children,
  };
});

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

// =============================================================================
// Helpers
// =============================================================================

const openRowDeleteModal = async (user: UserEvent) => {
  await screen.findByText(mockCommand.command);
  await user.click(screen.getByRole('button', { name: /more actions/i }));

  const deleteButton = await waitFor(() => screen.getByRole('button', { name: /^delete$/i }), {
    timeout: 5000,
  });
  await user.click(deleteButton);
};

const selectAllRows = async (user: UserEvent) => {
  // The first checkbox is the column header's "select all" toggle.
  const checkboxes = screen.getAllByRole('checkbox', { name: /select row/i });
  await user.click(checkboxes[0]!);
};

// =============================================================================
// Tests — Single delete
// =============================================================================

describe('Commands page - single delete', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    mockFetchCommands(SINGLE_COMMAND);
  });

  test('Delete row action opens the confirmation modal showing the command name', async () => {
    const user = userEvent.setup();
    renderCommandsPage();

    await openRowDeleteModal(user);

    expect(await screen.findByText('Delete Command?')).toBeInTheDocument();
    expect(screen.getByText(mockCommand.command, { selector: 'strong' })).toBeInTheDocument();
    // Routing: the Delete action opens ONLY the Delete modal — not Share/Edit.
    expect(screen.queryByRole('dialog', { name: /share command/i })).not.toBeInTheDocument();
  }, 20_000);

  test('clicking Cancel closes the modal without deleting', async () => {
    const user = userEvent.setup();
    renderCommandsPage();

    await openRowDeleteModal(user);
    await user.click(screen.getByRole('button', { name: /^cancel$/i }));

    expect(screen.queryByText('Delete Command?')).not.toBeInTheDocument();
    expect(deleteCommand).not.toHaveBeenCalled();
  }, 20_000);

  test('clicking Yes, Delete removes the command and closes the modal', async () => {
    const user = userEvent.setup();
    vi.mocked(deleteCommand).mockResolvedValue(undefined);
    renderCommandsPage();

    await openRowDeleteModal(user);
    await user.click(screen.getByRole('button', { name: /yes, delete/i }));

    await waitFor(() => {
      expect(deleteCommand).toHaveBeenCalledWith(mockCommand.commandId);
    });
    await waitFor(() => {
      expect(screen.queryByText('Delete Command?')).not.toBeInTheDocument();
    });
  }, 20_000);

  test('Delete button shows "Deleting…" while the request is in progress', async () => {
    const user = userEvent.setup();
    let resolveDelete: () => void = () => {};
    vi.mocked(deleteCommand).mockReturnValueOnce(
      new Promise<void>((resolve) => {
        resolveDelete = resolve;
      }),
    );
    renderCommandsPage();

    await openRowDeleteModal(user);
    await user.click(screen.getByRole('button', { name: /yes, delete/i }));

    expect(await screen.findByRole('button', { name: /deleting/i })).toBeDisabled();

    // Resolve so the test doesn't leak a pending promise.
    resolveDelete();
  }, 20_000);

  test('a failed delete keeps the modal open and shows the error', async () => {
    const user = userEvent.setup();
    vi.mocked(deleteCommand).mockRejectedValueOnce({
      isAxiosError: true,
      response: { data: { message: 'Cannot delete — command is in use.' } },
    });
    renderCommandsPage();

    await openRowDeleteModal(user);
    await user.click(screen.getByRole('button', { name: /yes, delete/i }));

    expect(await screen.findByText('Cannot delete — command is in use.')).toBeInTheDocument();
    expect(screen.getByText('Delete Command?')).toBeInTheDocument();
  }, 20_000);

  test('single item: heading is singular', async () => {
    const user = userEvent.setup();
    renderCommandsPage();

    await openRowDeleteModal(user);

    expect(await screen.findByText('Delete Command?')).toBeInTheDocument();
    expect(screen.queryByText('Delete Commands?')).not.toBeInTheDocument();
  }, 20_000);
});

// =============================================================================
// Tests — Bulk delete
// =============================================================================

describe('Commands page - bulk delete', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    mockFetchCommands(MULTIPLE_COMMANDS);
  });

  test('selecting rows reveals the bulk action buttons', async () => {
    const user = userEvent.setup();
    renderCommandsPage();
    await screen.findByText('Command Alpha');

    expect(screen.queryByRole('button', { name: /delete selected/i })).not.toBeInTheDocument();
    expect(screen.queryByRole('button', { name: /share selected/i })).not.toBeInTheDocument();

    await selectAllRows(user);

    expect(await screen.findByRole('button', { name: /share selected/i })).toBeInTheDocument();
    expect(screen.getByRole('button', { name: /delete selected/i })).toBeInTheDocument();
  });

  test('multiple items: heading is plural with the count', async () => {
    const user = userEvent.setup();
    renderCommandsPage();
    await screen.findByText('Command Alpha');

    await selectAllRows(user);
    await user.click(screen.getByRole('button', { name: /delete selected/i }));

    expect(await screen.findByText('Delete Commands?')).toBeInTheDocument();
    expect(screen.getByText('2', { selector: 'strong' })).toBeInTheDocument();
  });

  test('bulk confirm deletes every selected item', async () => {
    const user = userEvent.setup();
    vi.mocked(deleteCommand).mockResolvedValue(undefined);
    renderCommandsPage();
    await screen.findByText('Command Alpha');

    await selectAllRows(user);
    await user.click(screen.getByRole('button', { name: /delete selected/i }));
    await screen.findByText('Delete Commands?');
    await user.click(screen.getByRole('button', { name: /yes, delete/i }));

    await waitFor(() => {
      expect(deleteCommand).toHaveBeenCalledTimes(2);
    });
    const calledIds = vi
      .mocked(deleteCommand)
      .mock.calls.map((c) => c[0])
      .sort();
    expect(calledIds).toEqual([1, 2]);
  });

  test('a partial bulk delete failure shows the error and refreshes the table', async () => {
    const user = userEvent.setup();
    vi.mocked(deleteCommand).mockImplementation((id: number) => {
      if (id === 2) {
        return Promise.reject({
          isAxiosError: true,
          response: { data: { message: 'Command 2 is in use.' } },
        });
      }
      return Promise.resolve(undefined);
    });
    renderCommandsPage();
    await screen.findByText('Command Alpha');
    const initialFetchCount = vi.mocked(fetchCommands).mock.calls.length;

    await selectAllRows(user);
    await user.click(screen.getByRole('button', { name: /delete selected/i }));
    await screen.findByText('Delete Commands?');
    await user.click(screen.getByRole('button', { name: /yes, delete/i }));

    // Error surfaces and the modal stays open.
    expect(await screen.findByText('Command 2 is in use.')).toBeInTheDocument();
    expect(screen.getByText('Delete Commands?')).toBeInTheDocument();

    // Both rows were attempted and the table was refreshed.
    expect(deleteCommand).toHaveBeenCalledTimes(2);
    await waitFor(() => {
      expect(vi.mocked(fetchCommands).mock.calls.length).toBeGreaterThan(initialFetchCount);
    });
  });
});
