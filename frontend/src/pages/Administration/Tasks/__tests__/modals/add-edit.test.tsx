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

import { AVAILABLE_TASKS, buildTask } from '../fixtures/task';
import { mockFetchAvailableTasks } from '../mocks/taskApi';

import { renderAddEditTaskModal } from './helpers/renderAddEditTaskModal';

import { createTask, fetchAvailableTasks, updateTask } from '@/services/taskApi';

// =============================================================================
// Module mocks
// =============================================================================

vi.mock('@/services/taskApi');
vi.mock('@/components/ui/modals/Modal');

// =============================================================================
// Helpers
// =============================================================================

const selectFirstAvailableTask = async (user: ReturnType<typeof userEvent.setup>) => {
  await user.click(screen.getByRole('combobox'));
  await user.click(await screen.findByRole('option', { name: AVAILABLE_TASKS[0]!.name }));
};

// =============================================================================
// Tests — Add mode
// =============================================================================

describe('AddEditTaskModal - add mode', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    mockFetchAvailableTasks(AVAILABLE_TASKS);
  });

  test('the task selector loads and lists the available tasks', async () => {
    const user = userEvent.setup();
    renderAddEditTaskModal({ mode: 'add' });

    await waitFor(() => expect(fetchAvailableTasks).toHaveBeenCalled());
    await user.click(screen.getByRole('combobox'));

    expect(await screen.findByRole('option', { name: 'Test Task' })).toBeInTheDocument();
    expect(screen.getByRole('option', { name: 'Other Task' })).toBeInTheDocument();
  });

  test('fetchAvailableTasks is not called in edit mode', async () => {
    renderAddEditTaskModal({ mode: 'edit', task: buildTask() });

    await screen.findByRole('textbox', { name: /^name$/i });

    expect(fetchAvailableTasks).not.toHaveBeenCalled();
  });

  test('submitting without a selected task shows a validation error', async () => {
    const user = userEvent.setup();
    renderAddEditTaskModal({ mode: 'add' });
    await waitFor(() => expect(fetchAvailableTasks).toHaveBeenCalled());

    await user.type(screen.getByRole('textbox', { name: /^name$/i }), 'My Task');
    await user.click(screen.getByRole('button', { name: /^save$/i }));

    expect(await screen.findByText('Please select a task')).toBeInTheDocument();
    expect(createTask).not.toHaveBeenCalled();
  });

  test('submitting without a name shows a validation error', async () => {
    const user = userEvent.setup();
    renderAddEditTaskModal({ mode: 'add' });
    await waitFor(() => expect(fetchAvailableTasks).toHaveBeenCalled());

    await selectFirstAvailableTask(user);
    await user.click(screen.getByRole('button', { name: /^save$/i }));

    expect(await screen.findByText('Name is required')).toBeInTheDocument();
    expect(createTask).not.toHaveBeenCalled();
  });

  test('submitting without a schedule shows a validation error', async () => {
    const user = userEvent.setup();
    renderAddEditTaskModal({ mode: 'add' });
    await waitFor(() => expect(fetchAvailableTasks).toHaveBeenCalled());

    await selectFirstAvailableTask(user);
    await user.type(screen.getByRole('textbox', { name: /^name$/i }), 'My Task');
    await user.clear(screen.getByRole('textbox', { name: /^schedule$/i }));
    await user.click(screen.getByRole('button', { name: /^save$/i }));

    expect(await screen.findByText('Please enter a CRON expression')).toBeInTheDocument();
    expect(createTask).not.toHaveBeenCalled();
  });

  test('valid submission calls createTask and succeeds', async () => {
    const user = userEvent.setup();
    vi.mocked(createTask).mockResolvedValue(buildTask());
    const { onClose, onSuccess } = renderAddEditTaskModal({ mode: 'add' });
    await waitFor(() => expect(fetchAvailableTasks).toHaveBeenCalled());

    await selectFirstAvailableTask(user);
    await user.type(screen.getByRole('textbox', { name: /^name$/i }), 'My Task');
    await user.click(screen.getByRole('button', { name: /^save$/i }));

    await waitFor(() => {
      expect(createTask).toHaveBeenCalledWith({
        name: 'My Task',
        file: AVAILABLE_TASKS[0]!.file,
        schedule: '* * * * *',
      });
    });
    expect(onSuccess).toHaveBeenCalled();
    expect(onClose).toHaveBeenCalled();
  });

  test('Cancel closes without creating a task', async () => {
    const user = userEvent.setup();
    const { onClose } = renderAddEditTaskModal({ mode: 'add' });
    await waitFor(() => expect(fetchAvailableTasks).toHaveBeenCalled());

    await user.click(screen.getByRole('button', { name: /^cancel$/i }));

    expect(createTask).not.toHaveBeenCalled();
    expect(onClose).toHaveBeenCalled();
  });
});

// =============================================================================
// Tests — Edit mode
// =============================================================================

describe('AddEditTaskModal - edit mode', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  test('General tab is shown by default, pre-populated with the task', async () => {
    const task = buildTask({ name: 'Existing Task', schedule: '0 * * * *', isActive: 1 });
    renderAddEditTaskModal({ mode: 'edit', task });

    expect(await screen.findByRole('textbox', { name: /^name$/i })).toHaveValue('Existing Task');
    expect(screen.getByRole('textbox', { name: /^schedule$/i })).toHaveValue('0 * * * *');
    expect(screen.getByRole('checkbox', { name: /^active$/i })).toBeChecked();
  });

  test('the task selector from Add mode is not shown', async () => {
    renderAddEditTaskModal({ mode: 'edit', task: buildTask() });

    await screen.findByRole('textbox', { name: /^name$/i });

    expect(screen.queryByRole('combobox')).not.toBeInTheDocument();
  });

  test('Options tab shows one field per option, pre-populated', async () => {
    const user = userEvent.setup();
    const task = buildTask({ options: { retentionDays: '30', enabled: 'true' } });
    renderAddEditTaskModal({ mode: 'edit', task });
    await screen.findByRole('textbox', { name: /^name$/i });

    await user.click(screen.getByRole('button', { name: 'Options' }));

    expect(screen.getByRole('textbox', { name: 'retentionDays' })).toHaveValue('30');
    expect(screen.getByRole('textbox', { name: 'enabled' })).toHaveValue('true');
  });

  test('a task with no options shows the explanatory message', async () => {
    const user = userEvent.setup();
    const task = buildTask({ options: {} });
    renderAddEditTaskModal({ mode: 'edit', task });
    await screen.findByRole('textbox', { name: /^name$/i });

    await user.click(screen.getByRole('button', { name: 'Options' }));

    expect(screen.getByText('This task has no configurable options.')).toBeInTheDocument();
  });

  test('edits on General persist when switching to Options and back', async () => {
    const user = userEvent.setup();
    const task = buildTask({ options: { retentionDays: '30' } });
    renderAddEditTaskModal({ mode: 'edit', task });
    const nameInput = await screen.findByRole('textbox', { name: /^name$/i });

    await user.clear(nameInput);
    await user.type(nameInput, 'Renamed Task');
    await user.click(screen.getByRole('button', { name: 'Options' }));
    await user.click(screen.getByRole('button', { name: 'General' }));

    expect(screen.getByRole('textbox', { name: /^name$/i })).toHaveValue('Renamed Task');
  });

  test('edits on Options persist when switching to General and back', async () => {
    const user = userEvent.setup();
    const task = buildTask({ options: { retentionDays: '30' } });
    renderAddEditTaskModal({ mode: 'edit', task });
    await screen.findByRole('textbox', { name: /^name$/i });

    await user.click(screen.getByRole('button', { name: 'Options' }));
    const optionInput = screen.getByRole('textbox', { name: 'retentionDays' });
    await user.clear(optionInput);
    await user.type(optionInput, '60');
    await user.click(screen.getByRole('button', { name: 'General' }));
    await user.click(screen.getByRole('button', { name: 'Options' }));

    expect(screen.getByRole('textbox', { name: 'retentionDays' })).toHaveValue('60');
  });

  test('submitting without a name shows a validation error', async () => {
    const user = userEvent.setup();
    const task = buildTask();
    renderAddEditTaskModal({ mode: 'edit', task });
    const nameInput = await screen.findByRole('textbox', { name: /^name$/i });

    await user.clear(nameInput);
    await user.click(screen.getByRole('button', { name: /^save$/i }));

    expect(await screen.findByText('Name is required')).toBeInTheDocument();
    expect(updateTask).not.toHaveBeenCalled();
  });

  test('a validation failure on the Options tab switches back to General', async () => {
    const user = userEvent.setup();
    const task = buildTask({ options: { retentionDays: '30' } });
    renderAddEditTaskModal({ mode: 'edit', task });
    const nameInput = await screen.findByRole('textbox', { name: /^name$/i });

    await user.clear(nameInput);
    await user.click(screen.getByRole('button', { name: 'Options' }));
    await user.click(screen.getByRole('button', { name: /^save$/i }));

    expect(await screen.findByRole('textbox', { name: /^name$/i })).toBeInTheDocument();
    expect(screen.getByText('Name is required')).toBeInTheDocument();
  });

  test('valid submission calls updateTask with the current draft', async () => {
    const user = userEvent.setup();
    const task = buildTask({
      name: 'Existing Task',
      schedule: '* * * * *',
      isActive: 1,
      options: { retentionDays: '30' },
    });
    vi.mocked(updateTask).mockResolvedValue(task);
    const { onClose, onSuccess } = renderAddEditTaskModal({ mode: 'edit', task });
    await screen.findByRole('textbox', { name: /^name$/i });

    await user.click(screen.getByRole('checkbox', { name: /^active$/i }));
    await user.click(screen.getByRole('button', { name: 'Options' }));
    const optionInput = screen.getByRole('textbox', { name: 'retentionDays' });
    await user.clear(optionInput);
    await user.type(optionInput, '60');
    await user.click(screen.getByRole('button', { name: /^save$/i }));

    await waitFor(() => {
      expect(updateTask).toHaveBeenCalledWith(
        task.taskId,
        expect.objectContaining({
          name: 'Existing Task',
          schedule: '* * * * *',
          isActive: 0,
          options: { retentionDays: '60' },
        }),
      );
    });
    expect(onSuccess).toHaveBeenCalled();
    expect(onClose).toHaveBeenCalled();
  });
});

// =============================================================================
// Tests — Saving state & errors (shared by add/edit)
// =============================================================================

describe('AddEditTaskModal - saving state & errors', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    mockFetchAvailableTasks(AVAILABLE_TASKS);
  });

  test('Save shows a pending state and disables Save/Cancel while in progress', async () => {
    const user = userEvent.setup();
    let resolveCreate!: (task: ReturnType<typeof buildTask>) => void;
    vi.mocked(createTask).mockReturnValueOnce(
      new Promise((resolve) => {
        resolveCreate = resolve;
      }),
    );
    renderAddEditTaskModal({ mode: 'add' });
    await waitFor(() => expect(fetchAvailableTasks).toHaveBeenCalled());

    await selectFirstAvailableTask(user);
    await user.type(screen.getByRole('textbox', { name: /^name$/i }), 'My Task');
    await user.click(screen.getByRole('button', { name: /^save$/i }));

    expect(await screen.findByRole('button', { name: /saving/i })).toBeDisabled();
    expect(screen.getByRole('button', { name: /^cancel$/i })).toBeDisabled();

    resolveCreate(buildTask());
    await waitFor(() => expect(screen.getByRole('button', { name: /^save$/i })).toBeEnabled());
  });

  test('an Axios error with a message is shown as the modal error', async () => {
    const user = userEvent.setup();
    vi.mocked(createTask).mockRejectedValueOnce({
      isAxiosError: true,
      response: { data: { message: 'A task with that name already exists.' } },
    });
    renderAddEditTaskModal({ mode: 'add' });
    await waitFor(() => expect(fetchAvailableTasks).toHaveBeenCalled());

    await selectFirstAvailableTask(user);
    await user.type(screen.getByRole('textbox', { name: /^name$/i }), 'My Task');
    await user.click(screen.getByRole('button', { name: /^save$/i }));

    expect(await screen.findByText('A task with that name already exists.')).toBeInTheDocument();
  });

  test('a generic Error is shown using its message', async () => {
    const user = userEvent.setup();
    vi.mocked(createTask).mockRejectedValueOnce(new Error('Something broke'));
    renderAddEditTaskModal({ mode: 'add' });
    await waitFor(() => expect(fetchAvailableTasks).toHaveBeenCalled());

    await selectFirstAvailableTask(user);
    await user.type(screen.getByRole('textbox', { name: /^name$/i }), 'My Task');
    await user.click(screen.getByRole('button', { name: /^save$/i }));

    expect(await screen.findByText('Something broke')).toBeInTheDocument();
  });

  test('an unrecognised failure shows the fallback message', async () => {
    const user = userEvent.setup();
    vi.mocked(createTask).mockRejectedValueOnce('nope');
    renderAddEditTaskModal({ mode: 'add' });
    await waitFor(() => expect(fetchAvailableTasks).toHaveBeenCalled());

    await selectFirstAvailableTask(user);
    await user.type(screen.getByRole('textbox', { name: /^name$/i }), 'My Task');
    await user.click(screen.getByRole('button', { name: /^save$/i }));

    expect(await screen.findByText('An unexpected error occurred.')).toBeInTheDocument();
  });
});
