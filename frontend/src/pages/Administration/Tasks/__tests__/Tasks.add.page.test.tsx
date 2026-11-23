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

import { AVAILABLE_TASKS, buildTask, mockTask, SINGLE_TASK } from './fixtures/task';
import { renderTasksPage } from './helpers/renderTasksPage';
import { mockFetchAvailableTasks, mockFetchTasks } from './mocks/taskApi';

import { createTask } from '@/services/taskApi';
import { testQueryClient } from '@/setupTests';

// =============================================================================
// Module mocks
// =============================================================================

// The Add Task modal is NOT stubbed here: this file drives the real form from the page.
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
// Tests
// =============================================================================

describe('Tasks page - adding a task through the real modal', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    mockFetchTasks(SINGLE_TASK);
    mockFetchAvailableTasks(AVAILABLE_TASKS);
  });

  test(
    'adding a task from the page sends the chosen task, name and schedule, then lists it',
    { tags: ['smoke'] },
    async () => {
      const user = userEvent.setup();
      const otherTask = AVAILABLE_TASKS[1]!;
      const newTask = buildTask({ taskId: 2, name: 'Nightly cleanup', schedule: '0 2 * * *' });
      // Once the task is created the grid's refresh returns it alongside the existing one.
      vi.mocked(createTask).mockImplementation(async () => {
        mockFetchTasks({ rows: [mockTask, newTask], totalCount: 2 });
        return newTask;
      });
      renderTasksPage();
      await screen.findByText(mockTask.name);

      await user.click(screen.getByRole('button', { name: /add task/i }));
      const dialog = await screen.findByRole('dialog', { name: /add task/i });
      await user.click(within(dialog).getByRole('combobox', { name: /^task\b/i }));
      await user.click(await screen.findByRole('option', { name: otherTask.name }));
      await user.type(within(dialog).getByRole('textbox', { name: /^name$/i }), 'Nightly cleanup');
      const schedule = within(dialog).getByRole('textbox', { name: /^schedule/i });
      await user.clear(schedule);
      await user.type(schedule, '0 2 * * *');
      await user.click(within(dialog).getByRole('button', { name: /^save$/i }));

      await waitFor(() => {
        expect(createTask).toHaveBeenCalledWith({
          name: 'Nightly cleanup',
          file: otherTask.file,
          schedule: '0 2 * * *',
        });
      });
      await screen.findByText('Nightly cleanup');
      expect(screen.queryByRole('dialog', { name: /add task/i })).not.toBeInTheDocument();
    },
  );
});
