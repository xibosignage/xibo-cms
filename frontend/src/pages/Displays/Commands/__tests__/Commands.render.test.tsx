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
import { vi, beforeEach, describe, test, expect } from 'vitest';

import { EMPTY_COMMAND_TABLE, mockCommand, SINGLE_COMMAND } from './fixtures/command';
import { renderCommandsPage } from './helpers/renderCommandsPage';
import { mockFetchCommands } from './mocks/commandApi';

import { fetchCommands } from '@/services/commandApi';
import { fetchUserPreference } from '@/services/userApi';
import { testQueryClient } from '@/setupTests';

// =============================================================================
// Module mocks
// =============================================================================


vi.mock('@/services/commandApi');
vi.mock('@/services/userApi', () => ({
  fetchUserPreference: vi.fn().mockResolvedValue(null),
  saveUserPreference: vi.fn().mockResolvedValue(undefined),
  fetchUsers: vi.fn().mockResolvedValue([]),
}));
vi.mock('@/components/ui/modals/Modal');
vi.mock('../hooks/useCommandFilterOptions', () => ({
  useCommandFilterOptions: () => ({ filterOptions: [], isLoading: false }),
}));

// =============================================================================
// Tests — Commands page default state
// =============================================================================

describe('Commands page - render', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
  });

  test('table renders with command rows', async () => {
    mockFetchCommands(SINGLE_COMMAND);
    renderCommandsPage();

    expect(await screen.findByText(mockCommand.command)).toBeInTheDocument();
  });

  test('empty state is shown when no commands exist', async () => {
    mockFetchCommands(EMPTY_COMMAND_TABLE);
    renderCommandsPage();

    // Wait for hydration / first fetch to complete before asserting empty state.
    await screen.findByRole('button', { name: /add command/i });

    expect(await screen.findByText('No results found.')).toBeInTheDocument();
  });

  test('"Add Command" button is visible', async () => {
    mockFetchCommands(EMPTY_COMMAND_TABLE);
    renderCommandsPage();

    expect(await screen.findByRole('button', { name: /add command/i })).toBeInTheDocument();
  });

  test('search input is present with the correct placeholder', async () => {
    mockFetchCommands(EMPTY_COMMAND_TABLE);
    renderCommandsPage();

    expect(await screen.findByPlaceholderText('Search commands...')).toBeInTheDocument();
  });

  test('Filters button is visible', async () => {
    mockFetchCommands(EMPTY_COMMAND_TABLE);
    renderCommandsPage();

    expect(await screen.findByRole('button', { name: /filters/i })).toBeInTheDocument();
  });

  test('tab nav includes "Commands"', async () => {
    mockFetchCommands(EMPTY_COMMAND_TABLE);
    renderCommandsPage();

    const buttons = await screen.findAllByRole('button', { name: /^commands$/i });
    expect(buttons.length).toBeGreaterThan(0);
  });

  test('a fetch error renders the error alert above the table', async () => {
    vi.mocked(fetchCommands).mockRejectedValueOnce(new Error('Network failure'));
    renderCommandsPage();

    const alert = await screen.findByRole('alert');
    expect(alert).toHaveTextContent('Network failure');
  });

  test('commands are paginated at 10 per page by default', async () => {
    mockFetchCommands(SINGLE_COMMAND);
    renderCommandsPage();

    await screen.findByText(mockCommand.command);

    expect(fetchCommands).toHaveBeenCalledWith(expect.objectContaining({ start: 0, length: 10 }));
  });

  test('shows the loading pulse and disables controls while preferences load', async () => {
    mockFetchCommands(SINGLE_COMMAND);
    vi.mocked(fetchUserPreference).mockReturnValueOnce(new Promise(() => {}));

    renderCommandsPage({ hydrated: false });

    expect(await screen.findByText('Loading commands...')).toBeInTheDocument();
    expect(screen.getByRole('button', { name: /add command/i })).toBeDisabled();
    expect(screen.getByPlaceholderText('Search commands...')).toBeDisabled();
    expect(screen.getByRole('button', { name: /filters/i })).toBeDisabled();
  });
});
