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

import { screen, waitFor, within } from '@testing-library/react';
import userEvent, { type UserEvent } from '@testing-library/user-event';
import { vi, beforeEach, describe, test, expect } from 'vitest';

import { MULTIPLE_TASKS, mockTask, SINGLE_TASK } from './fixtures/task';
import { renderTasksPage } from './helpers/renderTasksPage';
import { mockFetchTasks } from './mocks/taskApi';

import { fetchTasks } from '@/services/taskApi';
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

vi.mock('../components/AddEditTaskModal', () => ({
  default: ({
    mode,
    onClose,
    onSuccess,
  }: {
    mode: 'add' | 'edit';
    onClose: () => void;
    onSuccess: () => void;
  }) => (
    <div role="dialog" aria-label={mode === 'edit' ? 'Edit Task' : 'Add Task'}>
      <button
        onClick={() => {
          onSuccess();
          onClose();
        }}
      >
        stub-save
      </button>
    </div>
  ),
}));

// =============================================================================
// Helpers
// =============================================================================

// Edit is a quick action (rendered inline, not behind "more actions"), so click
// it directly — scoped to the row so this also works with multi-row fixtures.
const openEditModal = async (user: UserEvent, taskName: string) => {
  await screen.findByText(taskName);
  const row = screen.getByText(taskName).closest('tr');
  if (!row) throw new Error(`Could not find row for task "${taskName}"`);

  await user.click(within(row).getByRole('button', { name: /^edit$/i }));
  return screen.findByRole('dialog', { name: /edit task/i });
};

// =============================================================================
// Tests — Edit Task wiring
// =============================================================================

describe('Tasks page - edit wiring', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    mockFetchTasks(SINGLE_TASK);
  });

  test('Edit action opens the Edit modal for the selected task', async () => {
    const user = userEvent.setup();
    renderTasksPage();

    await openEditModal(user, mockTask.name);

    expect(screen.getByRole('dialog', { name: /edit task/i })).toBeInTheDocument();
  });

  test('the table is refreshed and the modal closes after a successful edit', async () => {
    const user = userEvent.setup();
    renderTasksPage();

    await openEditModal(user, mockTask.name);
    const fetchCountBeforeSave = vi.mocked(fetchTasks).mock.calls.length;
    await user.click(screen.getByRole('button', { name: /stub-save/i }));

    await waitFor(() => {
      expect(vi.mocked(fetchTasks).mock.calls.length).toBeGreaterThan(fetchCountBeforeSave);
    });
    expect(screen.queryByRole('dialog', { name: /edit task/i })).not.toBeInTheDocument();
  });

  test('opening Edit for one task does not also open a Delete confirmation', async () => {
    mockFetchTasks(MULTIPLE_TASKS);
    const user = userEvent.setup();
    renderTasksPage();

    await openEditModal(user, 'Task Alpha');

    expect(screen.getAllByRole('dialog')).toHaveLength(1);
    expect(screen.queryByText('Delete Task?')).not.toBeInTheDocument();
    expect(screen.queryByText('Delete Tasks?')).not.toBeInTheDocument();
  });
});
