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
import type React from 'react';
import { vi, beforeEach, describe, test, expect } from 'vitest';

import { EMPTY_COMMAND_TABLE, mockCommand, SINGLE_COMMAND } from './fixtures/command';
import { renderCommandsPage } from './helpers/renderCommandsPage';
import { mockFetchCommands } from './mocks/commandApi';

import { fetchCommands } from '@/services/commandApi';
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

// =============================================================================
// Helpers
// =============================================================================

const waitForPageReady = () => screen.findByText(mockCommand.command);

// =============================================================================
// Tests — Commands page filters
// =============================================================================

describe('Commands page - filters', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    mockFetchCommands(SINGLE_COMMAND);
  });

  test('filter panel is hidden by default', async () => {
    renderCommandsPage();
    await waitForPageReady();

    expect(screen.queryByRole('button', { name: /reset/i })).not.toBeInTheDocument();
  });

  test('clicking Filters opens the panel', async () => {
    const user = userEvent.setup();
    renderCommandsPage();
    await waitForPageReady();

    await user.click(screen.getByRole('button', { name: /filters/i }));

    expect(await screen.findByRole('textbox', { name: /^name$/i })).toBeInTheDocument();
    expect(screen.getByRole('textbox', { name: /^code$/i })).toBeInTheDocument();
    await screen.findByRole('button', { name: /reset/i });
  });

  test('the Name and Code filters expose AND/OR and regex toggles', async () => {
    const user = userEvent.setup();
    renderCommandsPage();
    await waitForPageReady();

    await user.click(screen.getByRole('button', { name: /filters/i }));
    await screen.findByRole('textbox', { name: /^name$/i });

    expect(screen.getAllByRole('button', { name: 'OR' }).length).toBeGreaterThanOrEqual(2);
    expect(screen.getAllByTitle('Use RegEx pattern matching').length).toBeGreaterThanOrEqual(2);
  });

  test('clicking Filters again closes the panel', async () => {
    const user = userEvent.setup();
    renderCommandsPage();
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
    renderCommandsPage();
    await waitForPageReady();

    await user.type(screen.getByPlaceholderText('Search commands...'), 'Reboot');

    await waitFor(
      () => {
        expect(fetchCommands).toHaveBeenCalledWith(expect.objectContaining({ keyword: 'Reboot' }));
      },
      { timeout: 2000 },
    );
  });

  test('typing in the search box resets pagination to page 1', async () => {
    const user = userEvent.setup();
    renderCommandsPage();
    await waitForPageReady();

    await user.type(screen.getByPlaceholderText('Search commands...'), 'screen');

    await waitFor(
      () => {
        expect(fetchCommands).toHaveBeenCalledWith(
          expect.objectContaining({ start: 0, keyword: 'screen' }),
        );
      },
      { timeout: 2000 },
    );
  });

  test('clearing the search box restores the full list', async () => {
    const user = userEvent.setup();
    // Conditional mock: unfiltered returns the row, 'Alpha' returns empty.
    vi.mocked(fetchCommands).mockImplementation(async (opts) =>
      opts?.keyword === 'Alpha' ? EMPTY_COMMAND_TABLE : SINGLE_COMMAND,
    );

    renderCommandsPage();
    await waitForPageReady();

    const searchInput = screen.getByPlaceholderText('Search commands...') as HTMLInputElement;

    // Type a keyword that matches no rows — the table flips to empty state.
    await user.type(searchInput, 'Alpha');
    expect(await screen.findByText('No results found.')).toBeInTheDocument();

    // Clear via backspaces — the same event path used elsewhere in this file.
    await user.type(searchInput, '{Backspace}{Backspace}{Backspace}{Backspace}{Backspace}');
    expect(searchInput.value).toBe('');

    // The row should reappear once the debounced filter clears.
    expect(await screen.findByText(mockCommand.command)).toBeInTheDocument();
  });

  test('entering a Name filter updates the query and resets to page 1', async () => {
    const user = userEvent.setup();
    renderCommandsPage();
    await waitForPageReady();

    await user.click(screen.getByRole('button', { name: /filters/i }));
    const nameInput = await screen.findByRole('textbox', { name: /^name$/i });
    await user.type(nameInput, 'Alpha');

    await waitFor(
      () => {
        expect(fetchCommands).toHaveBeenCalledWith(
          expect.objectContaining({ start: 0, command: 'Alpha' }),
        );
      },
      { timeout: 2000 },
    );
  });

  test('entering a Code filter updates the query and resets to page 1', async () => {
    const user = userEvent.setup();
    renderCommandsPage();
    await waitForPageReady();

    await user.click(screen.getByRole('button', { name: /filters/i }));
    const codeInput = await screen.findByRole('textbox', { name: /^code$/i });
    await user.type(codeInput, 'CMD_X');

    await waitFor(
      () => {
        expect(fetchCommands).toHaveBeenCalledWith(
          expect.objectContaining({ start: 0, code: 'CMD_X' }),
        );
      },
      { timeout: 2000 },
    );
  });

  test('clicking Reset clears all filter inputs', async () => {
    const user = userEvent.setup();
    renderCommandsPage();
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
        expect(fetchCommands).toHaveBeenCalledWith(expect.objectContaining({ command: 'Alpha' }));
      },
      { timeout: 2000 },
    );

    await user.click(screen.getByRole('button', { name: /reset/i }));

    await waitFor(() => expect(nameInput.value).toBe(''));
  });

  test('Reset keeps the filter panel open', async () => {
    const user = userEvent.setup();
    renderCommandsPage();
    await waitForPageReady();

    await user.click(screen.getByRole('button', { name: /filters/i }));
    await user.click(await screen.findByRole('button', { name: /reset/i }));

    screen.getByRole('button', { name: /reset/i });
  });
});
