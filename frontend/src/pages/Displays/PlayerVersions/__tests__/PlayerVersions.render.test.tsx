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

import {
  EMPTY_PLAYER_VERSION_TABLE,
  mockPlayerVersion,
  SINGLE_PLAYER_VERSION,
} from './fixtures/playerVersion';
import { renderPlayerVersionsPage } from './helpers/renderPlayerVersionsPage';
import { mockFetchPlayerVersions } from './mocks/playerVersionApi';

import { downloadPlayerVersion, fetchPlayerVersions } from '@/services/playerVersionApi';
import { fetchUserPreference } from '@/services/userApi';
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
// Tests — Player Versions page default state
// =============================================================================

describe('Player Versions page - render', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
  });

  test('table renders with player version rows', async () => {
    mockFetchPlayerVersions(SINGLE_PLAYER_VERSION);
    renderPlayerVersionsPage();

    expect(await screen.findByText(mockPlayerVersion.playerShowVersion)).toBeInTheDocument();
  });

  test('empty state is shown when no player versions exist', async () => {
    mockFetchPlayerVersions(EMPTY_PLAYER_VERSION_TABLE);
    renderPlayerVersionsPage();

    // Wait for hydration / first fetch to complete before asserting empty state.
    await screen.findByRole('button', { name: /add version/i });

    expect(await screen.findByText('No results found.')).toBeInTheDocument();
  });

  test('"Add Version" button is visible', async () => {
    mockFetchPlayerVersions(EMPTY_PLAYER_VERSION_TABLE);
    renderPlayerVersionsPage();

    expect(await screen.findByRole('button', { name: /add version/i })).toBeInTheDocument();
  });

  test('search input is present with the correct placeholder', async () => {
    mockFetchPlayerVersions(EMPTY_PLAYER_VERSION_TABLE);
    renderPlayerVersionsPage();

    expect(await screen.findByPlaceholderText('Search player versions...')).toBeInTheDocument();
  });

  test('Filters button is visible', async () => {
    mockFetchPlayerVersions(EMPTY_PLAYER_VERSION_TABLE);
    renderPlayerVersionsPage();

    expect(await screen.findByRole('button', { name: /filters/i })).toBeInTheDocument();
  });

  test('tab nav includes "Player Versions"', async () => {
    mockFetchPlayerVersions(EMPTY_PLAYER_VERSION_TABLE);
    renderPlayerVersionsPage();

    const tabs = await screen.findAllByRole('button', { name: /player versions/i });
    expect(tabs.length).toBeGreaterThan(0);
  });

  test('player versions are paginated at 10 per page by default', async () => {
    mockFetchPlayerVersions(SINGLE_PLAYER_VERSION);
    renderPlayerVersionsPage();

    await screen.findByText(mockPlayerVersion.playerShowVersion);

    expect(fetchPlayerVersions).toHaveBeenCalledWith(
      expect.objectContaining({ start: 0, length: 10 }),
    );
  });

  test('a fetch error renders the error alert above the table', async () => {
    vi.mocked(fetchPlayerVersions).mockRejectedValueOnce(new Error('Network failure'));
    renderPlayerVersionsPage();

    const alert = await screen.findByRole('alert');
    expect(alert).toHaveTextContent('Network failure');
  });

  test('shows the loading pulse and disables controls while preferences load', async () => {
    mockFetchPlayerVersions(SINGLE_PLAYER_VERSION);
    vi.mocked(fetchUserPreference).mockReturnValueOnce(new Promise(() => {}));

    renderPlayerVersionsPage({ hydrated: false });

    expect(await screen.findByText('Loading player versions...')).toBeInTheDocument();
    expect(screen.getByRole('button', { name: /add version/i })).toBeDisabled();
    expect(screen.getByPlaceholderText('Search player versions...')).toBeDisabled();
    expect(screen.getByRole('button', { name: /filters/i })).toBeDisabled();
  });

  test('the Download row action downloads that version', async () => {
    const user = userEvent.setup();
    mockFetchPlayerVersions(SINGLE_PLAYER_VERSION);
    renderPlayerVersionsPage();

    await screen.findByText(mockPlayerVersion.playerShowVersion);
    await user.click(screen.getByRole('button', { name: /^download$/i }));

    expect(downloadPlayerVersion).toHaveBeenCalledWith(
      mockPlayerVersion.versionId,
      mockPlayerVersion.fileName,
    );
  });
});
