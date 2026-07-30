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

import { QueryClientProvider } from '@tanstack/react-query';
import { render, screen, waitFor } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import { beforeEach, describe, expect, test, vi } from 'vitest';

import TasksPage from '../Tasks';

import {
  buildTask,
  EMPTY_TASK_TABLE,
  LOCKED_TASK,
  mockTask,
  mockUser,
  SINGLE_TASK,
} from './fixtures/task';
import { renderTasksPage } from './helpers/renderTasksPage';
import { mockFetchTasks } from './mocks/taskApi';

import { UserProvider } from '@/context/UserContext';
import { fetchTasks } from '@/services/taskApi';
import { fetchUserPreference } from '@/services/userApi';
import { testQueryClient } from '@/setupTests';

// =============================================================================
// Module mocks
// =============================================================================

vi.mock('@/services/taskApi');

vi.mock('@/services/userApi', () => ({
  fetchUserPreference: vi.fn().mockResolvedValue(null),
  saveUserPreference: vi.fn().mockResolvedValue(undefined),
}));

vi.mock('@/hooks/useFilteredTabs', () => ({
  useFilteredTabs: vi.fn(() => [{ name: 'Tasks', path: '/administration/tasks' }]),
}));

vi.mock('@/components/ui/modals/Modal');

vi.mock('../hooks/useTaskFilterOptions', () => ({
  useTaskFilterOptions: () => ({ filterOptions: [], isLoading: false }),
}));

// =============================================================================
// Helper for loading-state tests (does NOT pre-seed the userPref cache)
// =============================================================================

const renderWithPendingPrefs = () =>
  render(
    <QueryClientProvider client={testQueryClient}>
      <UserProvider initialUser={mockUser}>
        <MemoryRouter>
          <TasksPage />
        </MemoryRouter>
      </UserProvider>
    </QueryClientProvider>,
  );

// =============================================================================
// Tests
// =============================================================================

describe('Tasks page - render', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    mockFetchTasks(SINGLE_TASK);
  });

  // ---------------------------------------------------------------------------
  // Table rows
  // ---------------------------------------------------------------------------
  test('renders task rows once data has loaded', async () => {
    renderTasksPage();

    await screen.findByText(mockTask.name);
  });

  // ---------------------------------------------------------------------------
  // Empty state
  // ---------------------------------------------------------------------------
  test('shows "No results found." when there are no tasks', async () => {
    mockFetchTasks(EMPTY_TASK_TABLE);
    renderTasksPage();

    // Wait for hydration to complete (Add Task button confirms it)
    await screen.findByRole('button', { name: /add task/i });

    await screen.findByText('No results found.');
  });

  // ---------------------------------------------------------------------------
  // Add Task button
  // ---------------------------------------------------------------------------
  test('"Add Task" button is present when the first row is not config-locked', async () => {
    renderTasksPage();

    expect(await screen.findByRole('button', { name: /add task/i })).toBeEnabled();
  });

  test('"Add Task" button is hidden when the first row is config-locked', async () => {
    mockFetchTasks(LOCKED_TASK);
    renderTasksPage();

    await screen.findByText('Locked Task');

    expect(screen.queryByRole('button', { name: /add task/i })).not.toBeInTheDocument();
  });

  test('"Add Task" button is disabled while not hydrated', async () => {
    let resolvePref!: (v: null) => void;
    vi.mocked(fetchUserPreference).mockReturnValueOnce(
      new Promise<null>((res) => {
        resolvePref = res;
      }),
    );

    renderWithPendingPrefs();

    expect(await screen.findByRole('button', { name: /add task/i })).toBeDisabled();

    // Resolve to let the component settle and avoid act() warnings.
    resolvePref(null);
    await waitFor(() => expect(screen.getByRole('button', { name: /add task/i })).toBeEnabled());
  });

  // ---------------------------------------------------------------------------
  // Filters button
  // ---------------------------------------------------------------------------
  test('Filters button is present', async () => {
    renderTasksPage();

    await screen.findByRole('button', { name: /filters/i });
  });

  // ---------------------------------------------------------------------------
  // Error state
  // ---------------------------------------------------------------------------
  test('shows an error alert when the API call fails', async () => {
    vi.mocked(fetchTasks).mockRejectedValue(new Error('Server Error'));

    renderTasksPage();

    expect(await screen.findByRole('alert')).toHaveTextContent('Server Error');
  });

  // ---------------------------------------------------------------------------
  // Loading pulse
  // ---------------------------------------------------------------------------
  test('shows "Loading..." when the page is not yet hydrated', async () => {
    let resolvePref!: (v: null) => void;
    vi.mocked(fetchUserPreference).mockReturnValueOnce(
      new Promise<null>((res) => {
        resolvePref = res;
      }),
    );

    renderWithPendingPrefs();

    await screen.findByText('Loading...');

    resolvePref(null);
    await waitFor(() => expect(screen.queryByText('Loading...')).not.toBeInTheDocument());
  });

  // ---------------------------------------------------------------------------
  // Status column label/type mapping
  // ---------------------------------------------------------------------------
  test('Status column shows the correct label for every known status', async () => {
    mockFetchTasks({
      rows: [
        buildTask({ taskId: 1, name: 'Running Task', status: 1 }),
        buildTask({ taskId: 2, name: 'Idle Task', status: 2 }),
        buildTask({ taskId: 3, name: 'Error Task', status: 3 }),
        buildTask({ taskId: 4, name: 'Success Task', status: 4 }),
        buildTask({ taskId: 5, name: 'Timeout Task', status: 5 }),
        buildTask({ taskId: 6, name: 'Unknown Task', status: 99 }),
      ],
      totalCount: 6,
    });
    renderTasksPage();

    await screen.findByText('Running Task');

    expect(screen.getByText('Running')).toBeInTheDocument();
    expect(screen.getByText('Idle')).toBeInTheDocument();
    expect(screen.getByText('Error')).toBeInTheDocument();
    expect(screen.getByText('Success')).toBeInTheDocument();
    expect(screen.getByText('Timed Out')).toBeInTheDocument();
    expect(screen.getByText('Unknown')).toBeInTheDocument();
  });

  // ---------------------------------------------------------------------------
  // Timestamp formatting
  // ---------------------------------------------------------------------------
  test('Next Run and Last Run cells are blank for a zero timestamp', async () => {
    mockFetchTasks({
      rows: [buildTask({ nextRunDt: 0, lastRunDt: 0 })],
      totalCount: 1,
    });
    renderTasksPage();

    await screen.findByText(mockTask.name);

    const row = screen.getByText(mockTask.name).closest('tr');
    expect(row).not.toBeNull();
    // Neither timestamp cell renders any date text — spot-checked via the
    // absence of any 4-digit year, which every non-zero formatted date has.
    expect(row!.textContent).not.toMatch(/\d{4}/);
  });

  test('Last Run cell shows a formatted date for a non-zero timestamp', async () => {
    mockFetchTasks({
      rows: [buildTask({ lastRunDt: 1700000000 })],
      totalCount: 1,
    });
    renderTasksPage();

    await screen.findByText(mockTask.name);

    const row = screen.getByText(mockTask.name).closest('tr');
    expect(row).not.toBeNull();
    expect(row!.textContent).toMatch(/\d{4}/);
  });

  // ---------------------------------------------------------------------------
  // Duration formatting
  // ---------------------------------------------------------------------------
  test('Last Duration cell shows a formatted HH:MM:SS duration', async () => {
    mockFetchTasks({
      rows: [buildTask({ lastRunDuration: 3725 })],
      totalCount: 1,
    });
    renderTasksPage();

    await screen.findByText(mockTask.name);

    expect(screen.getByText('01:02:05')).toBeInTheDocument();
  });
});
