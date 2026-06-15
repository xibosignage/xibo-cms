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

import { EMPTY_SYNC_GROUP_TABLE, mockSyncGroup, SINGLE_SYNC_GROUP } from './fixtures/syncGroup';
import { renderSyncGroupsPage } from './helpers/renderSyncGroupsPage';
import { mockFetchSyncGroups } from './mocks/syncGroupApi';

import { fetchSyncGroups } from '@/services/syncGroupApi';
import { testQueryClient } from '@/setupTests';

// =============================================================================
// Module mocks
// =============================================================================

vi.mock('react-i18next');

vi.mock('@/services/syncGroupApi');
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
vi.mock('../hooks/useSyncGroupFilterOptions', () => ({
  useSyncGroupFilterOptions: () => ({ filterOptions: [], isLoading: false }),
}));

// =============================================================================
// Tests — Sync Groups page default state
// =============================================================================

describe('Sync Groups page - render', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
  });

  // ---------------------------------------------------------------------------
  // Once fetchSyncGroups resolves, the sync group name should appear in the
  // table — confirms the data path is wired up end-to-end.
  // ---------------------------------------------------------------------------
  test('table renders with sync group rows', async () => {
    mockFetchSyncGroups(SINGLE_SYNC_GROUP);
    renderSyncGroupsPage();

    expect(await screen.findByText(mockSyncGroup.name)).toBeInTheDocument();
  });

  // ---------------------------------------------------------------------------
  // When the API returns zero rows, the DataTable renders the empty-state
  // message instead of a row.
  // ---------------------------------------------------------------------------
  test('empty state is shown when no sync groups exist', async () => {
    mockFetchSyncGroups(EMPTY_SYNC_GROUP_TABLE);
    renderSyncGroupsPage();

    // Wait for hydration / first fetch to complete before asserting empty state.
    await screen.findByRole('button', { name: /add sync group/i });

    expect(await screen.findByText('No results found.')).toBeInTheDocument();
  });

  // ---------------------------------------------------------------------------
  // The "Add Sync Group" button is the primary CTA for the page and must
  // always be present.
  // ---------------------------------------------------------------------------
  test('"Add Sync Group" button is visible', async () => {
    mockFetchSyncGroups(EMPTY_SYNC_GROUP_TABLE);
    renderSyncGroupsPage();

    expect(await screen.findByRole('button', { name: /add sync group/i })).toBeInTheDocument();
  });

  // ---------------------------------------------------------------------------
  // The search input must be present with the expected placeholder so users
  // can quickly filter without opening the advanced filter panel.
  // ---------------------------------------------------------------------------
  test('search input is present with the correct placeholder', async () => {
    mockFetchSyncGroups(EMPTY_SYNC_GROUP_TABLE);
    renderSyncGroupsPage();

    expect(await screen.findByPlaceholderText('Search sync groups...')).toBeInTheDocument();
  });

  // ---------------------------------------------------------------------------
  // The Filters button must be present so users can open the advanced filter
  // panel.
  // ---------------------------------------------------------------------------
  test('Filters button is visible', async () => {
    mockFetchSyncGroups(EMPTY_SYNC_GROUP_TABLE);
    renderSyncGroupsPage();

    expect(await screen.findByRole('button', { name: /filters/i })).toBeInTheDocument();
  });

  // ---------------------------------------------------------------------------
  // The TabNav at the top of the page should include the active "Sync Groups"
  // tab so the user can see where they are in the Displays section.
  // ---------------------------------------------------------------------------
  test('tab nav includes "Sync Groups"', async () => {
    mockFetchSyncGroups(EMPTY_SYNC_GROUP_TABLE);
    renderSyncGroupsPage();

    // TabNav renders each tab as a <button>. The page may contain "Sync Groups"
    // in multiple buttons (e.g. the primary "Add Sync Group" CTA also contains
    // the phrase), so we filter to the exact-match tab label.
    const buttons = await screen.findAllByRole('button', { name: /^sync groups$/i });
    expect(buttons.length).toBeGreaterThan(0);
  });

  // ---------------------------------------------------------------------------
  // When the list fetch fails the page surfaces the error message in an
  // alert banner above the table.
  // ---------------------------------------------------------------------------
  test('a fetch error renders the error alert above the table', async () => {
    vi.mocked(fetchSyncGroups).mockRejectedValueOnce(new Error('Network failure'));
    renderSyncGroupsPage();

    const alert = await screen.findByRole('alert');
    expect(alert).toHaveTextContent('Network failure');
  });

  // ---------------------------------------------------------------------------
  // The page initialises useTableState with pageSize: 10, so the very first
  // fetch must request 10 rows (length: 10) and start at offset 0.
  // ---------------------------------------------------------------------------
  test('sync groups are paginated at 10 per page by default', async () => {
    mockFetchSyncGroups(SINGLE_SYNC_GROUP);
    renderSyncGroupsPage();

    await screen.findByText(mockSyncGroup.name);

    expect(fetchSyncGroups).toHaveBeenCalledWith(expect.objectContaining({ start: 0, length: 10 }));
  });
});
