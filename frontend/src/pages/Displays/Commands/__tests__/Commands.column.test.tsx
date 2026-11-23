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

import { screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { vi, beforeEach, describe, test, expect } from 'vitest';

import { mockCommand, SINGLE_COMMAND } from './fixtures/command';
import { renderCommandsPage } from './helpers/renderCommandsPage';
import { mockFetchCommands } from './mocks/commandApi';

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
// Tests — Commands page column visibility
// =============================================================================

describe('Commands page - column visibility', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    mockFetchCommands(SINGLE_COMMAND);
  });

  test('Name column is always visible', async () => {
    renderCommandsPage();
    await screen.findByText(mockCommand.command);

    expect(screen.getByRole('columnheader', { name: /^name$/i })).toBeInTheDocument();
  });

  test('ID column is visible by default', async () => {
    renderCommandsPage();
    await screen.findByText(mockCommand.command);

    expect(screen.getByRole('columnheader', { name: /^id$/i })).toBeInTheDocument();
  });

  // All seven data columns are visible on first load (none are hidden by the
  // initial columnVisibility state).
  test('all default columns are visible on first load', async () => {
    renderCommandsPage();
    await screen.findByText(mockCommand.command);

    for (const name of [
      /^id$/i,
      /^name$/i,
      /^code$/i,
      /^description$/i,
      /^available on$/i,
      /^create alert on$/i,
      /^sharing$/i,
    ]) {
      expect(screen.getByRole('columnheader', { name })).toBeInTheDocument();
    }
  });

  test('Columns button opens the column picker', async () => {
    const user = userEvent.setup();
    renderCommandsPage();
    await screen.findByText(mockCommand.command);

    await user.click(screen.getByRole('button', { name: /toggle columns/i }));

    expect(screen.getByRole('checkbox', { name: /^code$/i })).toBeInTheDocument();
  });

  test('the column picker lists every hideable column', async () => {
    const user = userEvent.setup();
    renderCommandsPage();
    await screen.findByText(mockCommand.command);

    await user.click(screen.getByRole('button', { name: /toggle columns/i }));

    expect(screen.getByRole('checkbox', { name: /^id$/i })).toBeInTheDocument();
    expect(screen.getByRole('checkbox', { name: /^code$/i })).toBeInTheDocument();
    expect(screen.getByRole('checkbox', { name: /^description$/i })).toBeInTheDocument();
    expect(screen.getByRole('checkbox', { name: /^available on$/i })).toBeInTheDocument();
    expect(screen.getByRole('checkbox', { name: /^create alert on$/i })).toBeInTheDocument();
    expect(screen.getByRole('checkbox', { name: /^sharing$/i })).toBeInTheDocument();
  });

  test('the Name column cannot be hidden', async () => {
    const user = userEvent.setup();
    renderCommandsPage();
    await screen.findByText(mockCommand.command);

    await user.click(screen.getByRole('button', { name: /toggle columns/i }));

    expect(screen.queryByRole('checkbox', { name: /^name$/i })).not.toBeInTheDocument();
  });

  test('unchecking a column hides it from the table', async () => {
    const user = userEvent.setup();
    renderCommandsPage();
    await screen.findByText(mockCommand.command);

    await user.click(screen.getByRole('button', { name: /toggle columns/i }));
    const codeCheckbox = screen.getByRole('checkbox', { name: /^code$/i });
    expect(codeCheckbox).toBeChecked();

    await user.click(codeCheckbox);

    expect(screen.queryByRole('columnheader', { name: /^code$/i })).not.toBeInTheDocument();
  });

  test('re-checking a hidden column brings it back', async () => {
    const user = userEvent.setup();
    renderCommandsPage();
    await screen.findByText(mockCommand.command);

    await user.click(screen.getByRole('button', { name: /toggle columns/i }));
    const codeCheckbox = screen.getByRole('checkbox', { name: /^code$/i });

    await user.click(codeCheckbox);
    expect(screen.queryByRole('columnheader', { name: /^code$/i })).not.toBeInTheDocument();

    await user.click(codeCheckbox);
    expect(await screen.findByRole('columnheader', { name: /^code$/i })).toBeInTheDocument();
  });
});
