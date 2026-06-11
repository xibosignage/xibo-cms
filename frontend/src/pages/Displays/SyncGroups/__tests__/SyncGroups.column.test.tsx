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
import { vi, beforeEach, describe, test, expect } from 'vitest';

import { mockSyncGroup, SINGLE_SYNC_GROUP } from './fixtures/syncGroup';
import { renderSyncGroupsPage } from './helpers/renderSyncGroupsPage';
import { mockFetchSyncGroups } from './mocks/syncGroupApi';

import { testQueryClient } from '@/setupTests';

// =============================================================================
// Module mocks
// =============================================================================

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
// Tests — Sync Groups page column visibility
// =============================================================================

describe('Sync Groups page - column visibility', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    mockFetchSyncGroups(SINGLE_SYNC_GROUP);
  });

  // ---------------------------------------------------------------------------
  // The Name column is configured with enableHiding: false, so it must be
  // visible the moment the table renders.
  // ---------------------------------------------------------------------------
  test('Name column is always visible', async () => {
    renderSyncGroupsPage();
    await screen.findByText(mockSyncGroup.name);

    expect(screen.getByRole('columnheader', { name: /^name$/i })).toBeInTheDocument();
  });

  // ---------------------------------------------------------------------------
  // The ID column is visible by default via columnVisibility on the page.
  // ---------------------------------------------------------------------------
  test('ID column is visible by default', async () => {
    renderSyncGroupsPage();
    await screen.findByText(mockSyncGroup.name);

    expect(screen.getByRole('columnheader', { name: /^id$/i })).toBeInTheDocument();
  });

  // ---------------------------------------------------------------------------
  // Clicking the Columns toggle button opens a dropdown listing checkboxes
  // for every hideable column.
  // ---------------------------------------------------------------------------
  test('Columns button opens the column picker', async () => {
    const user = userEvent.setup();
    renderSyncGroupsPage();
    await screen.findByText(mockSyncGroup.name);

    await user.click(screen.getByRole('button', { name: /toggle columns/i }));

    expect(screen.getByRole('checkbox', { name: /^owner$/i })).toBeInTheDocument();
  });

  // ---------------------------------------------------------------------------
  // The column picker should list every hideable column. The Name column is
  // omitted (enableHiding: false) — verified in the next test.
  // ---------------------------------------------------------------------------
  test('the column picker lists every hideable column', async () => {
    const user = userEvent.setup();
    renderSyncGroupsPage();
    await screen.findByText(mockSyncGroup.name);

    await user.click(screen.getByRole('button', { name: /toggle columns/i }));

    expect(screen.getByRole('checkbox', { name: /^id$/i })).toBeInTheDocument();
    expect(screen.getByRole('checkbox', { name: /^created date$/i })).toBeInTheDocument();
    expect(screen.getByRole('checkbox', { name: /^modified date$/i })).toBeInTheDocument();
    expect(screen.getByRole('checkbox', { name: /^owner$/i })).toBeInTheDocument();
    expect(screen.getByRole('checkbox', { name: /^modified by$/i })).toBeInTheDocument();
    expect(screen.getByRole('checkbox', { name: /^publisher port$/i })).toBeInTheDocument();
    expect(screen.getByRole('checkbox', { name: /^switch delay$/i })).toBeInTheDocument();
    expect(screen.getByRole('checkbox', { name: /^video pause delay$/i })).toBeInTheDocument();
    expect(screen.getByRole('checkbox', { name: /^lead display$/i })).toBeInTheDocument();
  });

  // ---------------------------------------------------------------------------
  // The Name column has enableHiding: false in SyncGroupsConfig, so the
  // column picker should NOT render a toggle for it.
  // ---------------------------------------------------------------------------
  test('the Name column cannot be hidden', async () => {
    const user = userEvent.setup();
    renderSyncGroupsPage();
    await screen.findByText(mockSyncGroup.name);

    await user.click(screen.getByRole('button', { name: /toggle columns/i }));

    expect(screen.queryByRole('checkbox', { name: /^name$/i })).not.toBeInTheDocument();
  });

  // ---------------------------------------------------------------------------
  // Un-checking a visible column removes its header from the table.
  // ---------------------------------------------------------------------------
  test('unchecking a column hides it from the table', async () => {
    const user = userEvent.setup();
    renderSyncGroupsPage();
    await screen.findByText(mockSyncGroup.name);

    await user.click(screen.getByRole('button', { name: /toggle columns/i }));
    const ownerCheckbox = screen.getByRole('checkbox', { name: /^owner$/i });
    expect(ownerCheckbox).toBeChecked();

    await user.click(ownerCheckbox);

    expect(screen.queryByRole('columnheader', { name: /^owner$/i })).not.toBeInTheDocument();
  });

  // ---------------------------------------------------------------------------
  // Re-checking a previously hidden column brings the column header back to
  // the table.
  // ---------------------------------------------------------------------------
  test('re-checking a hidden column brings it back', async () => {
    const user = userEvent.setup();
    renderSyncGroupsPage();
    await screen.findByText(mockSyncGroup.name);

    await user.click(screen.getByRole('button', { name: /toggle columns/i }));
    const ownerCheckbox = screen.getByRole('checkbox', { name: /^owner$/i });

    await user.click(ownerCheckbox);
    expect(screen.queryByRole('columnheader', { name: /^owner$/i })).not.toBeInTheDocument();

    await user.click(ownerCheckbox);
    expect(await screen.findByRole('columnheader', { name: /^owner$/i })).toBeInTheDocument();
  });
});
