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

import { buildDisplay, mockDisplay, SINGLE_DISPLAY } from '../fixtures/display';
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

describe('Displays page - column visibility', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    mockFetchDisplays(SINGLE_DISPLAY);
  });

  // ---------------------------------------------------------------------------
  // "Device Name" is off by default in the columnVisibility config in
  // Displays.tsx. Before opening the column picker it must not appear as a
  // column header in the table.
  // ---------------------------------------------------------------------------
  test('"Device Name" column header is not visible by default', async () => {
    renderDisplaysPage();

    // Wait for the page to hydrate and data to load before asserting absence.
    await screen.findByText(mockDisplay.display);

    expect(screen.queryByRole('columnheader', { name: /device name/i })).not.toBeInTheDocument();
  });

  // ---------------------------------------------------------------------------
  // Clicking the "Columns" toggle button opens a dropdown listing all hideable
  // columns with checkboxes.
  // ---------------------------------------------------------------------------
  test('clicking the Columns button opens the column picker dropdown', async () => {
    const user = userEvent.setup();
    renderDisplaysPage();
    await screen.findByText(mockDisplay.display);

    await user.click(screen.getByRole('button', { name: /toggle columns/i }));

    expect(screen.getByRole('checkbox', { name: /device name/i })).toBeInTheDocument();
  });

  // ---------------------------------------------------------------------------
  // Checking the "Device Name" checkbox in the column picker adds the column
  // header to the table.
  // ---------------------------------------------------------------------------
  test('checking a hidden column checkbox shows that column in the table', async () => {
    const user = userEvent.setup();
    renderDisplaysPage();
    await screen.findByText(mockDisplay.display);

    await user.click(screen.getByRole('button', { name: /toggle columns/i }));

    const checkbox = screen.getByRole('checkbox', { name: /device name/i });
    expect(checkbox).not.toBeChecked();

    await user.click(checkbox);

    expect(await screen.findByRole('columnheader', { name: /device name/i })).toBeInTheDocument();
  });

  // ---------------------------------------------------------------------------
  // Un-checking a visible column removes its header from the table.
  // "ID" (displayId) is visible by default and is hideable (no enableHiding:false).
  // ---------------------------------------------------------------------------
  test('unchecking a visible column checkbox hides that column from the table', async () => {
    const user = userEvent.setup();
    renderDisplaysPage();

    // Wait for data to load (confirms isHydrated=true) before interacting.
    await screen.findByRole('columnheader', { name: /^id$/i });

    await user.click(screen.getByRole('button', { name: /toggle columns/i }));

    const idCheckbox = screen.getByRole('checkbox', { name: /^id$/i });
    expect(idCheckbox).toBeChecked();

    await user.click(idCheckbox);

    expect(screen.queryByRole('columnheader', { name: /^id$/i })).not.toBeInTheDocument();
  });

  // ---------------------------------------------------------------------------
  // The row action menu label for the authorise action depends on the display's
  // licensed value: "Authorise" for unlicensed, "Unauthorise" for licensed.
  // ---------------------------------------------------------------------------
  test('row action menu shows Authorise for an unlicensed display', async () => {
    const user = userEvent.setup();
    mockFetchDisplays({ rows: [buildDisplay({ licensed: 0 })], totalCount: 1 });
    renderDisplaysPage();

    await screen.findByText(mockDisplay.display);
    await user.click(screen.getByRole('button', { name: /more actions/i }));

    screen.getByRole('button', { name: /^authorise$/i });
  });

  test('row action menu shows Unauthorise for a licensed display', async () => {
    const user = userEvent.setup();
    renderDisplaysPage();

    await screen.findByText(mockDisplay.display);
    await user.click(screen.getByRole('button', { name: /more actions/i }));

    screen.getByRole('button', { name: /^unauthorise$/i });
  });
});
