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

import type { FetchTaskResponse } from '@/services/taskApi';
import type { Task, TaskAvailable } from '@/types/task';
import type { User } from '@/types/user';

// -----------------------------------------------------------------------------
// Factory that produces a Task with safe minimal defaults.
// Only fields used in assertions carry meaningful values — everything else
// is set to the zero value for its type so the component renders without errors.
//
// taskId: 1 — asserted in delete/run-now/edit tests
// name: 'Test Task' — asserted in render, form, and modal tests
// status: 2 (Idle) — drives the Status column label/type
// schedule: '* * * * *' — asserted in edit form pre-population
// isActive: 1 — drives the Active checkbox state in edit mode
// isConfigLocked: false — drives Add button / row action / bulk action visibility
// -----------------------------------------------------------------------------
export const buildTask = (overrides: Partial<Task> = {}): Task => ({
  taskId: 1,
  name: 'Test Task',
  configFile: 'TestTask.php',
  class: 'Xibo\\XTR\\TestTask',
  status: 2,
  pid: 0,
  options: {},
  schedule: '* * * * *',
  isActive: 1,
  runNow: 0,
  lastRunDt: 0,
  lastRunStartDt: 0,
  lastRunMessage: '',
  lastRunStatus: 0,
  lastRunDuration: 0,
  lastRunExitCode: 0,
  nextRunDt: 0,
  isConfigLocked: false,
  ...overrides,
});

export const mockTask = buildTask();

export const SINGLE_TASK: FetchTaskResponse = {
  rows: [mockTask],
  totalCount: 1,
};

export const EMPTY_TASK_TABLE: FetchTaskResponse = {
  rows: [],
  totalCount: 0,
};

// Two-row fixture for bulk-action / delete tests.
export const MULTIPLE_TASKS: FetchTaskResponse = {
  rows: [buildTask({ taskId: 1, name: 'Task Alpha' }), buildTask({ taskId: 2, name: 'Task Beta' })],
  totalCount: 2,
};

// A single row whose isConfigLocked drives the page-level lock (see Tasks.tsx —
// isConfigLocked = taskList[0]?.isConfigLocked).
export const LOCKED_TASK: FetchTaskResponse = {
  rows: [buildTask({ taskId: 1, name: 'Locked Task', isConfigLocked: true })],
  totalCount: 1,
};

export const buildTaskAvailable = (overrides: Partial<TaskAvailable> = {}): TaskAvailable => ({
  name: 'Test Task',
  class: 'Xibo\\XTR\\TestTask',
  options: {},
  file: 'TestTask',
  ...overrides,
});

export const AVAILABLE_TASKS: TaskAvailable[] = [
  buildTaskAvailable(),
  buildTaskAvailable({ name: 'Other Task', file: 'OtherTask' }),
];

// The default logged-in user for Tasks page tests.
export const mockUser: User = {
  userId: 1,
  userName: 'TestUser',
  userTypeId: 1,
  groupId: 1,
  features: { 'task.view': true },
  settings: {
    defaultTimezone: 'UTC',
    defaultLanguage: 'en',
    DATE_FORMAT_JS: 'DD/MM/YYYY',
    TIME_FORMAT_JS: 'HH:mm',
  },
};

// Query keys that mirror what useTableState builds internally.
// Centralised here so a key change only needs one update.
export const queryKeys = {
  tasksPage: ['userPref', 'task_page'] as const,
};
