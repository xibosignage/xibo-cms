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

import { EMPTY_TASK_TABLE, mockTask, SINGLE_TASK } from './fixtures/task';
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

// =============================================================================
// Helpers
// =============================================================================

const waitForPageReady = () => screen.findByText(mockTask.name);

// =============================================================================
// Tests — Tasks page filters
// =============================================================================

describe('Tasks page - filters', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    mockFetchTasks(SINGLE_TASK);
  });

  test('filter panel is hidden by default', async () => {
    renderTasksPage();
    await waitForPageReady();

    expect(screen.queryByRole('button', { name: /reset/i })).not.toBeInTheDocument();
  });

  test('clicking Filters opens the panel', async () => {
    const user = userEvent.setup();
    renderTasksPage();
    await waitForPageReady();

    await user.click(screen.getByRole('button', { name: /filters/i }));

    expect(await screen.findByRole('textbox', { name: /^name$/i })).toBeInTheDocument();
    await screen.findByRole('button', { name: /reset/i });
  });

  test('the Name filter exposes AND/OR and regex toggles', async () => {
    const user = userEvent.setup();
    renderTasksPage();
    await waitForPageReady();

    await user.click(screen.getByRole('button', { name: /filters/i }));
    await screen.findByRole('textbox', { name: /^name$/i });

    expect(screen.getByRole('button', { name: 'OR' })).toBeInTheDocument();
    expect(screen.getByTitle('Use RegEx pattern matching')).toBeInTheDocument();
  });

  test('clicking Filters again closes the panel', async () => {
    const user = userEvent.setup();
    renderTasksPage();
    await waitForPageReady();

    const filtersButton = screen.getByRole('button', { name: /filters/i });
    await user.click(filtersButton);
    await screen.findByRole('button', { name: /reset/i });

    await user.click(filtersButton);

    await waitFor(() => {
      expect(screen.queryByRole('button', { name: /reset/i })).not.toBeInTheDocument();
    });
  });

  test('typing in the search box fetches results with that keyword', async () => {
    const user = userEvent.setup();
    renderTasksPage();
    await waitForPageReady();

    await user.type(screen.getByPlaceholderText('Search tasks...'), 'Reboot');

    await waitFor(
      () => {
        expect(fetchTasks).toHaveBeenCalledWith(expect.objectContaining({ name: 'Reboot' }));
      },
      { timeout: 2000 },
    );
  });

  test('typing in the search box resets pagination to page 1', async () => {
    const user = userEvent.setup();
    renderTasksPage();
    await waitForPageReady();

    await user.type(screen.getByPlaceholderText('Search tasks...'), 'Cleanup');

    await waitFor(
      () => {
        expect(fetchTasks).toHaveBeenCalledWith(
          expect.objectContaining({ start: 0, name: 'Cleanup' }),
        );
      },
      { timeout: 2000 },
    );
  });

  test('clearing the search box restores the full list', async () => {
    const user = userEvent.setup();
    // Conditional mock: unfiltered returns the row, 'Alpha' returns empty.
    vi.mocked(fetchTasks).mockImplementation(async (opts) =>
      opts?.name === 'Alpha' ? EMPTY_TASK_TABLE : SINGLE_TASK,
    );

    renderTasksPage();
    await waitForPageReady();

    const searchInput = screen.getByPlaceholderText('Search tasks...') as HTMLInputElement;

    // Type a keyword that matches no rows — the table flips to empty state.
    await user.type(searchInput, 'Alpha');
    expect(await screen.findByText('No results found.')).toBeInTheDocument();

    // Clear via backspaces — the same event path used elsewhere in this file.
    await user.type(searchInput, '{Backspace}{Backspace}{Backspace}{Backspace}{Backspace}');
    expect(searchInput.value).toBe('');

    // The row should reappear once the debounced filter clears.
    expect(await screen.findByText(mockTask.name)).toBeInTheDocument();
  });

  test('entering a Name filter updates the query and resets to page 1', async () => {
    const user = userEvent.setup();
    renderTasksPage();
    await waitForPageReady();

    await user.click(screen.getByRole('button', { name: /filters/i }));
    const nameInput = await screen.findByRole('textbox', { name: /^name$/i });
    await user.type(nameInput, 'Alpha');

    await waitFor(
      () => {
        expect(fetchTasks).toHaveBeenCalledWith(
          expect.objectContaining({ start: 0, name: 'Alpha' }),
        );
      },
      { timeout: 2000 },
    );
  });

  test('clicking Reset clears the Name filter input', async () => {
    const user = userEvent.setup();
    renderTasksPage();
    await waitForPageReady();

    await user.click(screen.getByRole('button', { name: /filters/i }));
    const nameInput = (await screen.findByRole('textbox', {
      name: /^name$/i,
    })) as HTMLInputElement;
    await user.type(nameInput, 'Alpha');

    // Wait for the debounced value to actually reach the query (externalValue)
    // before resetting. Asserting only on the local input value is not enough —
    // it updates synchronously, so Reset could fire before filterInputs changes,
    // leaving the input un-synced.
    await waitFor(
      () => {
        expect(fetchTasks).toHaveBeenCalledWith(expect.objectContaining({ name: 'Alpha' }));
      },
      { timeout: 2000 },
    );

    await user.click(screen.getByRole('button', { name: /reset/i }));

    await waitFor(() => expect(nameInput.value).toBe(''));
  });

  test('Reset keeps the filter panel open', async () => {
    const user = userEvent.setup();
    renderTasksPage();
    await waitForPageReady();

    await user.click(screen.getByRole('button', { name: /filters/i }));
    await user.click(await screen.findByRole('button', { name: /reset/i }));

    screen.getByRole('button', { name: /reset/i });
  });
});
