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
import { vi, beforeEach, describe, test, expect } from 'vitest';

import { MULTIPLE_TASKS, mockTask, SINGLE_TASK } from './fixtures/task';
import { renderTasksPage } from './helpers/renderTasksPage';
import { mockFetchTasks } from './mocks/taskApi';

import { deleteTask, fetchTasks } from '@/services/taskApi';
import { testQueryClient } from '@/setupTests';

// =============================================================================
// Module mocks
// =============================================================================

vi.mock('@/services/taskApi');
vi.mock('@/services/userApi', () => ({
  fetchUserPreference: vi.fn().mockResolvedValue(null),
  saveUserPreference: vi.fn().mockResolvedValue(undefined),
}));
vi.mock('@/components/ui/modals/Modal');
vi.mock('../hooks/useTaskFilterOptions', () => ({
  useTaskFilterOptions: () => ({ filterOptions: [], isLoading: false }),
}));

// =============================================================================
// Helpers
// =============================================================================

const openRowDeleteModal = async (user: UserEvent) => {
  await screen.findByText(mockTask.name);
  await user.click(screen.getByRole('button', { name: /more actions/i }));

  const deleteButton = await waitFor(() => screen.getByRole('button', { name: /^delete$/i }), {
    timeout: 5000,
  });
  await user.click(deleteButton);
};

const selectAllRows = async (user: UserEvent) => {
  await user.click(screen.getByRole('checkbox', { name: /select all rows/i }));
};

// =============================================================================
// Tests — Single delete
// =============================================================================

describe('Tasks page - single delete', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    mockFetchTasks(SINGLE_TASK);
  });

  test('Delete row action opens the confirmation modal showing the task name', async () => {
    const user = userEvent.setup();
    renderTasksPage();

    await openRowDeleteModal(user);

    expect(await screen.findByText('Delete Task?')).toBeInTheDocument();
    expect(screen.getByText(mockTask.name, { selector: 'strong' })).toBeInTheDocument();
    // Routing: the Delete action opens ONLY the Delete modal — not Edit.
    expect(screen.queryByRole('dialog', { name: /edit task/i })).not.toBeInTheDocument();
  });

  test('clicking Cancel closes the modal without deleting', async () => {
    const user = userEvent.setup();
    renderTasksPage();

    await openRowDeleteModal(user);
    await user.click(screen.getByRole('button', { name: /^cancel$/i }));

    expect(screen.queryByText('Delete Task?')).not.toBeInTheDocument();
    expect(deleteTask).not.toHaveBeenCalled();
  });

  test('clicking Yes, Delete removes the task and closes the modal', async () => {
    const user = userEvent.setup();
    vi.mocked(deleteTask).mockResolvedValue(undefined);
    renderTasksPage();

    await openRowDeleteModal(user);
    await user.click(screen.getByRole('button', { name: /yes, delete/i }));

    await waitFor(() => {
      expect(deleteTask).toHaveBeenCalledWith(mockTask.taskId);
    });
    await waitFor(() => {
      expect(screen.queryByText('Delete Task?')).not.toBeInTheDocument();
    });
  });

  test('Delete button shows "Deleting…" while the request is in progress', async () => {
    const user = userEvent.setup();
    let resolveDelete: () => void = () => {};
    vi.mocked(deleteTask).mockReturnValueOnce(
      new Promise<void>((resolve) => {
        resolveDelete = resolve;
      }),
    );
    renderTasksPage();

    await openRowDeleteModal(user);
    await user.click(screen.getByRole('button', { name: /yes, delete/i }));

    expect(await screen.findByRole('button', { name: /deleting/i })).toBeDisabled();

    // Resolve so the test doesn't leak a pending promise.
    resolveDelete();
  });

  test('a failed delete keeps the modal open and shows the error', async () => {
    const user = userEvent.setup();
    vi.mocked(deleteTask).mockRejectedValueOnce({
      isAxiosError: true,
      response: { data: { message: 'Cannot delete — task is running.' } },
    });
    renderTasksPage();

    await openRowDeleteModal(user);
    await user.click(screen.getByRole('button', { name: /yes, delete/i }));

    expect(await screen.findByText('Cannot delete — task is running.')).toBeInTheDocument();
    expect(screen.getByText('Delete Task?')).toBeInTheDocument();
  });

  test('single item: heading is singular', async () => {
    const user = userEvent.setup();
    renderTasksPage();

    await openRowDeleteModal(user);

    expect(await screen.findByText('Delete Task?')).toBeInTheDocument();
    expect(screen.queryByText('Delete Tasks?')).not.toBeInTheDocument();
  });
});

// =============================================================================
// Tests — Bulk delete
// =============================================================================

describe('Tasks page - bulk delete', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    mockFetchTasks(MULTIPLE_TASKS);
  });

  test('selecting rows reveals the bulk delete button', async () => {
    const user = userEvent.setup();
    renderTasksPage();
    await screen.findByText('Task Alpha');

    expect(screen.queryByRole('button', { name: /delete selected/i })).not.toBeInTheDocument();

    await selectAllRows(user);

    expect(await screen.findByRole('button', { name: /delete selected/i })).toBeInTheDocument();
  });

  test('multiple items: heading is plural with the count', async () => {
    const user = userEvent.setup();
    renderTasksPage();
    await screen.findByText('Task Alpha');

    await selectAllRows(user);
    await user.click(screen.getByRole('button', { name: /delete selected/i }));

    expect(await screen.findByText('Delete Tasks?')).toBeInTheDocument();
    expect(screen.getByText('2', { selector: 'strong' })).toBeInTheDocument();
  });

  test('bulk confirm deletes every selected item', async () => {
    const user = userEvent.setup();
    vi.mocked(deleteTask).mockResolvedValue(undefined);
    renderTasksPage();
    await screen.findByText('Task Alpha');

    await selectAllRows(user);
    await user.click(screen.getByRole('button', { name: /delete selected/i }));
    await screen.findByText('Delete Tasks?');
    await user.click(screen.getByRole('button', { name: /yes, delete/i }));

    await waitFor(() => {
      expect(deleteTask).toHaveBeenCalledTimes(2);
    });
    const calledIds = vi
      .mocked(deleteTask)
      .mock.calls.map((c) => c[0])
      .sort();
    expect(calledIds).toEqual([1, 2]);
  });

  test('a partial bulk delete failure shows the error and refreshes the table', async () => {
    const user = userEvent.setup();
    vi.mocked(deleteTask).mockImplementation((id: number) => {
      if (id === 2) {
        return Promise.reject({
          isAxiosError: true,
          response: { data: { message: 'Task 2 is in use.' } },
        });
      }
      return Promise.resolve(undefined);
    });
    renderTasksPage();
    await screen.findByText('Task Alpha');
    const initialFetchCount = vi.mocked(fetchTasks).mock.calls.length;

    await selectAllRows(user);
    await user.click(screen.getByRole('button', { name: /delete selected/i }));
    await screen.findByText('Delete Tasks?');
    await user.click(screen.getByRole('button', { name: /yes, delete/i }));

    // Error surfaces and the modal stays open.
    expect(await screen.findByText('Task 2 is in use.')).toBeInTheDocument();
    expect(screen.getByText('Delete Tasks?')).toBeInTheDocument();

    // Both rows were attempted and the table was refreshed.
    expect(deleteTask).toHaveBeenCalledTimes(2);
    await waitFor(() => {
      expect(vi.mocked(fetchTasks).mock.calls.length).toBeGreaterThan(initialFetchCount);
    });
  });
});
