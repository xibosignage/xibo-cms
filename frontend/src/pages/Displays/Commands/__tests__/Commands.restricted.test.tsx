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

// The Commands page as a user who is not a super admin. A super admin passes
// every feature check, so these use a display manager given (or denied) the
// two command features. The row-permission half of the Edit rule is covered in
// Commands.edit.test.tsx; this file covers the feature half.

import { screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { vi, beforeEach, describe, test, expect } from 'vitest';

import { mockCommand, mockUser, SINGLE_COMMAND } from './fixtures/command';
import { renderCommandsPage } from './helpers/renderCommandsPage';
import { mockFetchCommands } from './mocks/commandApi';

import { testQueryClient } from '@/setupTests';
import { buildCurrentUser, PERSONAS } from '@/testUtils/personas';
import { openFirstRowMenu } from '@/testUtils/rtl';
import type { User } from '@/types/user';

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
vi.mock('../components/AddEditCommandModal', () => ({ default: () => null }));

// =============================================================================
// Users
// =============================================================================

// A display manager with both command features on. The persona ships with
// neither, so each test switches off only the one it is about.
const commandManager = (features: Record<string, boolean> = {}): User =>
  buildCurrentUser(PERSONAS.displayManager, {
    settings: mockUser.settings,
    features: { 'command.add': true, 'command.modify': true, ...features },
  });

// =============================================================================
// Tests
// =============================================================================

describe('Commands page - as a user who is not a super admin', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    // The row allows everything, so a missing action can only come from the
    // user's features.
    mockFetchCommands(SINGLE_COMMAND);
  });

  test('a user without Add commands gets no Add Command button', async () => {
    renderCommandsPage({ user: commandManager({ 'command.add': false }) });

    await screen.findByText(mockCommand.command);

    expect(screen.queryByRole('button', { name: /^add command$/i })).not.toBeInTheDocument();
  });

  test('a user with Add commands gets the Add Command button', async () => {
    renderCommandsPage({ user: commandManager() });

    await screen.findByText(mockCommand.command);

    expect(screen.getByRole('button', { name: /^add command$/i })).toBeInTheDocument();
  });

  // Without the edit feature the row has no actions at all, so the row has no
  // menu to open. Wait for the row, then check Edit and Delete appear nowhere.
  test('a user without Edit commands gets no Edit, even on a command they may edit', async () => {
    renderCommandsPage({ user: commandManager({ 'command.modify': false }) });

    await screen.findByText(mockCommand.command);

    expect(screen.queryByRole('button', { name: /more actions/i })).not.toBeInTheDocument();
    expect(screen.queryByRole('button', { name: /^edit$/i })).not.toBeInTheDocument();
    expect(screen.queryByRole('button', { name: /^delete$/i })).not.toBeInTheDocument();
  });

  test('a user with Edit commands gets Edit on a command they may edit', async () => {
    const user = userEvent.setup();
    renderCommandsPage({ user: commandManager() });

    await openFirstRowMenu(user, mockCommand.command);
    expect(screen.getByRole('button', { name: /^delete$/i })).toBeInTheDocument();
  });
});
