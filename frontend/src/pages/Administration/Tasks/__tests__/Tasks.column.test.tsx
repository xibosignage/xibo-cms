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

import { screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { vi, beforeEach, describe, test, expect } from 'vitest';

import { buildTask, mockTask, SINGLE_TASK } from './fixtures/task';
import { renderTasksPage } from './helpers/renderTasksPage';
import { mockFetchTasks } from './mocks/taskApi';

import { runTaskNow } from '@/services/taskApi';
import { testQueryClient } from '@/setupTests';

// =============================================================================
// Module mocks
// =============================================================================

vi.mock('@/services/taskApi');
vi.mock('@/services/userApi', () => ({
  fetchUserPreference: vi.fn().mockResolvedValue(null),
  saveUserPreference: vi.fn().mockResolvedValue(undefined),
  autoSubmitPrefQueryKey: (formId: string) => ['userPref', `autoSubmit.${formId}`],
  fetchAutoSubmitPreference: vi.fn().mockResolvedValue(true),
}));
vi.mock('@/components/ui/modals/Modal');
vi.mock('../hooks/useTaskFilterOptions', () => ({
  useTaskFilterOptions: () => ({ filterOptions: [], isLoading: false }),
}));
vi.mock('@/components/ui/Notification', () => ({
  notify: { success: vi.fn(), error: vi.fn() },
}));

// =============================================================================
// Tests — Tasks page column visibility
// =============================================================================

describe('Tasks page - column visibility', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    mockFetchTasks(SINGLE_TASK);
  });

  test('Name column is always visible', async () => {
    renderTasksPage();
    await screen.findByText(mockTask.name);

    expect(screen.getByRole('columnheader', { name: /^name$/i })).toBeInTheDocument();
  });

  test('the column picker lists every hideable column', async () => {
    const user = userEvent.setup();
    renderTasksPage();
    await screen.findByText(mockTask.name);

    await user.click(screen.getByRole('button', { name: /toggle columns/i }));

    for (const name of [
      /^id$/i,
      /^active$/i,
      /^status$/i,
      /^next run$/i,
      /^run now$/i,
      /^last run$/i,
      /^last status$/i,
      /^last duration$/i,
    ]) {
      expect(screen.getByRole('checkbox', { name })).toBeInTheDocument();
    }
  });

  test('the Name column cannot be hidden', async () => {
    const user = userEvent.setup();
    renderTasksPage();
    await screen.findByText(mockTask.name);

    await user.click(screen.getByRole('button', { name: /toggle columns/i }));

    expect(screen.queryByRole('checkbox', { name: /^name$/i })).not.toBeInTheDocument();
  });

  test('unchecking a column hides it from the table', async () => {
    const user = userEvent.setup();
    renderTasksPage();
    await screen.findByText(mockTask.name);

    await user.click(screen.getByRole('button', { name: /toggle columns/i }));
    const statusCheckbox = screen.getByRole('checkbox', { name: /^status$/i });
    expect(statusCheckbox).toBeChecked();

    await user.click(statusCheckbox);

    expect(screen.queryByRole('columnheader', { name: /^status$/i })).not.toBeInTheDocument();
  });

  test('re-checking a hidden column brings it back', async () => {
    const user = userEvent.setup();
    renderTasksPage();
    await screen.findByText(mockTask.name);

    await user.click(screen.getByRole('button', { name: /toggle columns/i }));
    const statusCheckbox = screen.getByRole('checkbox', { name: /^status$/i });

    await user.click(statusCheckbox);
    expect(screen.queryByRole('columnheader', { name: /^status$/i })).not.toBeInTheDocument();

    await user.click(statusCheckbox);
    expect(await screen.findByRole('columnheader', { name: /^status$/i })).toBeInTheDocument();
  });
});

// =============================================================================
// Tests — row actions (task is not config-locked)
// Condition in getTaskItemActions: !task.isConfigLocked
// =============================================================================

describe('Tasks page — row actions (not config-locked)', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    mockFetchTasks(SINGLE_TASK);
  });

  test('Edit quick action is visible', async () => {
    renderTasksPage();
    await screen.findByText(mockTask.name);

    expect(screen.getByRole('button', { name: /^edit$/i })).toBeInTheDocument();
  });

  test('Delete action is visible in the more-actions dropdown', async () => {
    const user = userEvent.setup();
    renderTasksPage();
    await screen.findByText(mockTask.name);

    await user.click(screen.getByRole('button', { name: /more actions/i }));

    expect(await screen.findByRole('button', { name: /^delete$/i })).toBeInTheDocument();
  });
});

// =============================================================================
// Tests — row actions (task IS config-locked)
// isConfigLocked is read from taskList[0]?.isConfigLocked in Tasks.tsx, so the
// fixture's row (not the user) drives this gate.
// =============================================================================

describe('Tasks page — row actions (config-locked)', () => {
  const LOCKED_TASK_TABLE = {
    rows: [buildTask({ taskId: 1, name: 'Locked Task', isConfigLocked: true })],
    totalCount: 1,
  };

  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    mockFetchTasks(LOCKED_TASK_TABLE);
  });

  test('Edit quick action is hidden for a config-locked task', async () => {
    renderTasksPage();
    await screen.findByText('Locked Task');

    expect(screen.queryByRole('button', { name: /^edit$/i })).not.toBeInTheDocument();
  });

  test('Delete action is hidden for a config-locked task', async () => {
    const user = userEvent.setup();
    renderTasksPage();
    await screen.findByText('Locked Task');

    await user.click(screen.getByRole('button', { name: /more actions/i }));

    // Wait for the dropdown's Run Now copy to confirm it is fully open (there are
    // now 2 "Run Now" buttons — the quick action plus this dropdown copy), then
    // assert Delete absent.
    await screen.findAllByRole('button', { name: /^run now$/i });
    expect(screen.queryByRole('button', { name: /^delete$/i })).not.toBeInTheDocument();
  });

  test('Add Task button is hidden for a config-locked task', async () => {
    renderTasksPage();
    await screen.findByText('Locked Task');

    expect(screen.queryByRole('button', { name: /add task/i })).not.toBeInTheDocument();
  });
});

// =============================================================================
// Tests — bulk actions
// getBulkActions returns [] entirely when the page is config-locked.
// =============================================================================

describe('Tasks page — bulk actions', () => {
  const TWO_TASKS = {
    rows: [
      buildTask({ taskId: 1, name: 'Task Alpha' }),
      buildTask({ taskId: 2, name: 'Task Beta' }),
    ],
    totalCount: 2,
  };

  const TWO_LOCKED_TASKS = {
    rows: [
      buildTask({ taskId: 1, name: 'Task Alpha', isConfigLocked: true }),
      buildTask({ taskId: 2, name: 'Task Beta', isConfigLocked: true }),
    ],
    totalCount: 2,
  };

  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
  });

  test('Delete Selected appears after selecting rows when not config-locked', async () => {
    const user = userEvent.setup();
    mockFetchTasks(TWO_TASKS);
    renderTasksPage();
    await screen.findByText('Task Alpha');

    const checkboxes = screen.getAllByRole('checkbox', { name: /select row/i });
    await user.click(checkboxes[0]!);

    expect(await screen.findByRole('button', { name: /delete selected/i })).toBeEnabled();
  });

  test('Delete Selected never appears when config-locked, even with rows selected', async () => {
    const user = userEvent.setup();
    mockFetchTasks(TWO_LOCKED_TASKS);
    renderTasksPage();
    await screen.findByText('Task Alpha');

    const checkboxes = screen.getAllByRole('checkbox', { name: /select row/i });
    await user.click(checkboxes[0]!);

    expect(screen.queryByRole('button', { name: /delete selected/i })).not.toBeInTheDocument();
  });
});

// =============================================================================
// Tests — Run Now row action
// Not permission-gated: always visible, calls runTaskNow directly (no modal).
// =============================================================================

describe('Tasks page — Run Now action', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
  });

  test('Run Now quick action is visible even for a config-locked task', async () => {
    mockFetchTasks({
      rows: [buildTask({ isConfigLocked: true })],
      totalCount: 1,
    });
    renderTasksPage();
    await screen.findByText(mockTask.name);

    expect(screen.getByRole('button', { name: /^run now$/i })).toBeInTheDocument();
  });

  test('clicking Run Now calls the run-now API and shows a success toast', async () => {
    const { notify } = await import('@/components/ui/Notification');
    const user = userEvent.setup();
    mockFetchTasks(SINGLE_TASK);
    vi.mocked(runTaskNow).mockResolvedValue(undefined);
    renderTasksPage();
    await screen.findByText(mockTask.name);

    await user.click(screen.getByRole('button', { name: /^run now$/i }));

    expect(runTaskNow).toHaveBeenCalledWith(mockTask.taskId);
    expect(notify.success).toHaveBeenCalled();
  });

  test('a failed Run Now call shows an error toast', async () => {
    const { notify } = await import('@/components/ui/Notification');
    const user = userEvent.setup();
    mockFetchTasks(SINGLE_TASK);
    vi.mocked(runTaskNow).mockRejectedValue(new Error('Failed'));
    renderTasksPage();
    await screen.findByText(mockTask.name);

    await user.click(screen.getByRole('button', { name: /^run now$/i }));

    expect(notify.error).toHaveBeenCalled();
  });
});
