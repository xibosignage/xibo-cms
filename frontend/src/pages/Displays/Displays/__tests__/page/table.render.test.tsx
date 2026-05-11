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
import type React from 'react';
import { vi, beforeEach, describe, test, expect } from 'vitest';

import { EMPTY_DISPLAY_TABLE, mockDisplay, SINGLE_DISPLAY } from '../fixtures/display';
import { renderDisplaysPage } from '../helpers/renderDisplaysPage';
import { mockFetchDisplays } from '../mocks/displaysApi';

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

// =============================================================================
// Tests
// =============================================================================

describe('Displays page - render', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
  });

  // ---------------------------------------------------------------------------
  // The "Add Display" button must always be present — it's the primary CTA.
  // ---------------------------------------------------------------------------
  test('Add Display button is present', async () => {
    mockFetchDisplays(EMPTY_DISPLAY_TABLE);
    renderDisplaysPage();

    expect(await screen.findByRole('button', { name: /add display/i })).toBeInTheDocument();
  });

  // ---------------------------------------------------------------------------
  // The search input must be present for quick filtering without opening the
  // advanced filter panel.
  // ---------------------------------------------------------------------------
  test('search input is present with correct placeholder', async () => {
    mockFetchDisplays(EMPTY_DISPLAY_TABLE);
    renderDisplaysPage();

    expect(
      await screen.findByPlaceholderText('Search displays...'),
    ).toBeInTheDocument();
  });

  // ---------------------------------------------------------------------------
  // Once fetchDisplays resolves, the display name should appear in the table.
  // ---------------------------------------------------------------------------
  test('table shows the display name once data has loaded', async () => {
    mockFetchDisplays(SINGLE_DISPLAY);
    renderDisplaysPage();

    expect(await screen.findByText(mockDisplay.display)).toBeInTheDocument();
  });

  // ---------------------------------------------------------------------------
  // When the API returns zero rows, the DataTable renders an empty-state
  // message instead of an empty table body.
  // ---------------------------------------------------------------------------
  test('shows "No results found." when there are no displays', async () => {
    mockFetchDisplays(EMPTY_DISPLAY_TABLE);
    renderDisplaysPage();

    // Wait for the page to hydrate and the fetch to complete first.
    await screen.findByRole('button', { name: /add display/i });

    expect(await screen.findByText('No results found.')).toBeInTheDocument();
  });

  // ---------------------------------------------------------------------------
  // The Filters button must be present so users can open the advanced filter
  // panel.
  // ---------------------------------------------------------------------------
  test('Filters button is present', async () => {
    mockFetchDisplays(EMPTY_DISPLAY_TABLE);
    renderDisplaysPage();

    expect(await screen.findByRole('button', { name: /filters/i })).toBeInTheDocument();
  });
});
