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
import { vi, beforeEach, describe, test, expect } from 'vitest';

import { mockTask, SINGLE_TASK } from './fixtures/task';
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
// Tests — Add Task wiring
// =============================================================================

describe('Tasks page - add wiring', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    mockFetchTasks(SINGLE_TASK);
  });

  test('"Add Task" button opens the Add modal', async () => {
    const user = userEvent.setup();
    renderTasksPage();
    await screen.findByText(mockTask.name);

    await user.click(screen.getByRole('button', { name: /add task/i }));

    expect(await screen.findByRole('dialog', { name: /add task/i })).toBeInTheDocument();
  });

  test('the table is refreshed after a successful add', async () => {
    const user = userEvent.setup();
    renderTasksPage();
    await screen.findByText(mockTask.name);

    await user.click(screen.getByRole('button', { name: /add task/i }));
    const fetchCountBeforeSave = vi.mocked(fetchTasks).mock.calls.length;
    await user.click(screen.getByRole('button', { name: /stub-save/i }));

    await waitFor(() => {
      expect(vi.mocked(fetchTasks).mock.calls.length).toBeGreaterThan(fetchCountBeforeSave);
    });
  });

  test('no second modal opens automatically after add', async () => {
    const user = userEvent.setup();
    renderTasksPage();
    await screen.findByText(mockTask.name);

    await user.click(screen.getByRole('button', { name: /add task/i }));
    await user.click(screen.getByRole('button', { name: /stub-save/i }));

    await waitFor(() => {
      expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
    });
  });
});
