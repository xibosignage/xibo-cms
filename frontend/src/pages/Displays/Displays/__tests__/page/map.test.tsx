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
import type React from 'react';
import { vi, beforeEach, describe, test, expect } from 'vitest';

import { SINGLE_DISPLAY, mockDisplay } from '../fixtures/display';
import { renderDisplaysPage } from '../helpers/renderDisplaysPage';
import { mockFetchDisplays } from '../mocks/displaysApi';

import { testQueryClient } from '@/setupTests';

// =============================================================================
// Module mocks
// =============================================================================

vi.mock('@/services/displaysApi');
vi.mock('@/services/displayGroupApi', () => ({
  fetchDisplayGroups: vi.fn().mockResolvedValue({ rows: [], totalCount: 0 }),
  deleteDisplayGroup: vi.fn(),
}));
vi.mock('@/services/displayProfileApi', () => ({
  fetchDisplayProfile: vi.fn().mockResolvedValue({ rows: [], totalCount: 0 }),
  fetchDisplayProfileById: vi.fn().mockResolvedValue(null),
}));
vi.mock('@/services/userApi', () => ({
  fetchUserPreference: vi.fn().mockResolvedValue(null),
  saveUserPreference: vi.fn().mockResolvedValue(undefined),
  fetchUsers: vi.fn().mockResolvedValue([]),
}));
vi.mock('@/services/folderApi', () => ({
  fetchFolderById: vi.fn().mockResolvedValue({ id: 1, text: 'Root' }),
  fetchFolderTree: vi.fn().mockResolvedValue([]),
  searchFolders: vi.fn().mockResolvedValue([]),
  fetchContextButtons: vi.fn().mockResolvedValue({ create: true }),
  selectFolder: vi.fn().mockResolvedValue({ success: true }),
}));
vi.mock('@/components/ui/modals/Modal');
vi.mock('@/components/ui/FolderActionModals', () => ({ default: () => null }));
vi.mock('../../hooks/useDisplaysFilterOptions', () => ({
  useDisplaysFilterOptions: () => ({ filterOptions: [], isLoading: false }),
}));

// Leaflet cannot run in jsdom — mock the entire component so tests remain
// focused on the page-level view-mode switching logic, not map rendering.
vi.mock('../../components/DisplayMap', () => ({
  default: () => <div data-testid="mock-display-map" />,
}));

// =============================================================================
// Tests
// =============================================================================

describe('Displays page - map view', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    mockFetchDisplays(SINGLE_DISPLAY);
  });

  // ---------------------------------------------------------------------------
  // The default view mode is "table". The data table should be visible and the
  // map container should not be mounted.
  // ---------------------------------------------------------------------------
  test('table is shown in the default view mode', async () => {
    renderDisplaysPage();

    expect(await screen.findByText(mockDisplay.display)).toBeInTheDocument();
    expect(screen.queryByTestId('mock-display-map')).not.toBeInTheDocument();
  });

  // ---------------------------------------------------------------------------
  // Clicking the Map View button swaps the DataTable for the DisplayMap.
  // ---------------------------------------------------------------------------
  test('clicking Map View switches to the map', async () => {
    const user = userEvent.setup();
    renderDisplaysPage();

    // Wait for the table to be visible before attempting the switch.
    await screen.findByText(mockDisplay.display);

    await user.click(screen.getByRole('button', { name: /map view/i }));

    expect(screen.getByTestId('mock-display-map')).toBeInTheDocument();
  });

  // ---------------------------------------------------------------------------
  // Clicking Table View from map mode renders the data table again.
  // ---------------------------------------------------------------------------
  test('clicking Table View from map mode restores the table', async () => {
    const user = userEvent.setup();
    renderDisplaysPage();

    await screen.findByText(mockDisplay.display);

    await user.click(screen.getByRole('button', { name: /map view/i }));
    expect(screen.getByTestId('mock-display-map')).toBeInTheDocument();

    await user.click(screen.getByRole('button', { name: /table view/i }));

    expect(screen.queryByTestId('mock-display-map')).not.toBeInTheDocument();
    expect(await screen.findByText(mockDisplay.display)).toBeInTheDocument();
  });

  // ---------------------------------------------------------------------------
  // The map view header shows a "Map View" label so the user always knows which
  // mode they are in.
  // ---------------------------------------------------------------------------
  test('map view renders a "Map View" heading label', async () => {
    const user = userEvent.setup();
    renderDisplaysPage();

    await screen.findByText(mockDisplay.display);
    await user.click(screen.getByRole('button', { name: /map view/i }));

    expect(screen.getByText('Map View')).toBeInTheDocument();
  });
});
