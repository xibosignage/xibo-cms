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
import type React from 'react';
import { vi, beforeEach, describe, test, expect } from 'vitest';

import { mockPlayerVersion, SINGLE_PLAYER_VERSION } from './fixtures/playerVersion';
import { renderPlayerVersionsPage } from './helpers/renderPlayerVersionsPage';
import { mockFetchPlayerVersions } from './mocks/playerVersionApi';

import { testQueryClient } from '@/setupTests';

// =============================================================================
// Module mocks
// =============================================================================

vi.mock('@/services/playerVersionApi');
vi.mock('@/services/userApi', () => ({
  fetchUserPreference: vi.fn().mockResolvedValue(null),
  saveUserPreference: vi.fn().mockResolvedValue(undefined),
  fetchUsers: vi.fn().mockResolvedValue([]),
}));
vi.mock('@/components/ui/modals/Modal');
vi.mock('../hooks/usePlayerVersionsFilterOptions', () => ({
  usePlayerVersionFilterOptions: () => ({ filterOptions: [] }),
}));

// =============================================================================
// Tests — Player Versions page column visibility
// =============================================================================

describe('Player Versions page - column visibility', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    mockFetchPlayerVersions(SINGLE_PLAYER_VERSION);
  });

  test('Player Version Name column is always visible', async () => {
    renderPlayerVersionsPage();
    await screen.findByText(mockPlayerVersion.playerShowVersion);

    expect(screen.getByRole('columnheader', { name: /player version name/i })).toBeInTheDocument();
  });

  test('the default columns are visible on first load', async () => {
    renderPlayerVersionsPage();
    await screen.findByText(mockPlayerVersion.playerShowVersion);

    for (const name of [
      /version id/i,
      /player version name/i,
      /^type$/i,
      /^version$/i,
      /^code$/i,
      /file name/i,
      /^size$/i,
    ]) {
      expect(screen.getByRole('columnheader', { name })).toBeInTheDocument();
    }
  });

  test('columns hidden by default are not shown on first load', async () => {
    renderPlayerVersionsPage();
    await screen.findByText(mockPlayerVersion.playerShowVersion);

    expect(screen.queryByRole('columnheader', { name: /created at/i })).not.toBeInTheDocument();
    expect(screen.queryByRole('columnheader', { name: /modified at/i })).not.toBeInTheDocument();
    expect(screen.queryByRole('columnheader', { name: /modified by/i })).not.toBeInTheDocument();
  });

  test('Columns button opens the column picker', async () => {
    const user = userEvent.setup();
    renderPlayerVersionsPage();
    await screen.findByText(mockPlayerVersion.playerShowVersion);

    await user.click(screen.getByRole('button', { name: /toggle columns/i }));

    expect(screen.getByRole('checkbox', { name: /version id/i })).toBeInTheDocument();
  });

  test('the column picker lists every hideable column', async () => {
    const user = userEvent.setup();
    renderPlayerVersionsPage();
    await screen.findByText(mockPlayerVersion.playerShowVersion);

    await user.click(screen.getByRole('button', { name: /toggle columns/i }));

    for (const name of [
      /version id/i,
      /^type$/i,
      /^version$/i,
      /^code$/i,
      /file name/i,
      /^size$/i,
      /created at/i,
      /modified at/i,
      /modified by/i,
    ]) {
      expect(screen.getByRole('checkbox', { name })).toBeInTheDocument();
    }
  });

  test('the Player Version Name column cannot be hidden', async () => {
    const user = userEvent.setup();
    renderPlayerVersionsPage();
    await screen.findByText(mockPlayerVersion.playerShowVersion);

    await user.click(screen.getByRole('button', { name: /toggle columns/i }));

    expect(
      screen.queryByRole('checkbox', { name: /player version name/i }),
    ).not.toBeInTheDocument();
  });

  test('unchecking a column hides it from the table', async () => {
    const user = userEvent.setup();
    renderPlayerVersionsPage();
    await screen.findByText(mockPlayerVersion.playerShowVersion);

    await user.click(screen.getByRole('button', { name: /toggle columns/i }));
    const typeCheckbox = screen.getByRole('checkbox', { name: /^type$/i });
    expect(typeCheckbox).toBeChecked();

    await user.click(typeCheckbox);

    expect(screen.queryByRole('columnheader', { name: /^type$/i })).not.toBeInTheDocument();
  });

  test('re-checking a hidden column brings it back', async () => {
    const user = userEvent.setup();
    renderPlayerVersionsPage();
    await screen.findByText(mockPlayerVersion.playerShowVersion);

    await user.click(screen.getByRole('button', { name: /toggle columns/i }));
    const typeCheckbox = screen.getByRole('checkbox', { name: /^type$/i });

    await user.click(typeCheckbox);
    expect(screen.queryByRole('columnheader', { name: /^type$/i })).not.toBeInTheDocument();

    await user.click(typeCheckbox);
    expect(await screen.findByRole('columnheader', { name: /^type$/i })).toBeInTheDocument();
  });
});
