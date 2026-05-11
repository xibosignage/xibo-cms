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

import { screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type React from 'react';
import { vi, beforeEach, describe, test, expect } from 'vitest';

import { mockDisplay, SINGLE_DISPLAY } from '../fixtures/display';
import { renderDisplaysPage } from '../helpers/renderDisplaysPage';
import { mockFetchDisplays } from '../mocks/displaysApi';

import { fetchDisplays } from '@/services/displaysApi';
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

// useDisplaysFilterOptions is NOT mocked here — the real hook runs with the
// already-mocked displayGroupApi / displayProfileApi above, so static options
// (Status, Logged In, Authorised, etc.) are available in the filter panel.

// =============================================================================
// Helpers
// =============================================================================

// Both the Filters button and the search input are disabled while isHydrated
// is false. isHydrated only becomes true after the page preferences load AND
// useDisplaysData fires its first fetch. Waiting for the display row to appear
// guarantees both preconditions are satisfied before we click anything.
const waitForPageReady = () => screen.findByText(mockDisplay.display);

// =============================================================================
// Tests
// =============================================================================

describe('Displays page - filters', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    mockFetchDisplays(SINGLE_DISPLAY);
  });

  // ---------------------------------------------------------------------------
  // FilterInputs wraps its content in aria-hidden="true" when closed, which
  // removes Reset (and all other filter controls) from the accessibility tree.
  // ---------------------------------------------------------------------------
  test('filter panel controls are not accessible before Filters is clicked', async () => {
    renderDisplaysPage();
    await waitForPageReady();

    expect(screen.queryByRole('button', { name: /reset/i })).not.toBeInTheDocument();
  });

  // ---------------------------------------------------------------------------
  // Clicking Filters opens the panel — the Reset button becomes accessible.
  // ---------------------------------------------------------------------------
  test('clicking Filters makes the filter panel accessible', async () => {
    const user = userEvent.setup();
    renderDisplaysPage();
    await waitForPageReady();

    await user.click(screen.getByRole('button', { name: /filters/i }));

    await screen.findByRole('button', { name: /reset/i });
  });

  // ---------------------------------------------------------------------------
  // Clicking Filters a second time collapses the panel — Reset goes back to
  // being aria-hidden and disappears from the accessibility tree.
  // ---------------------------------------------------------------------------
  test('clicking Filters again hides the filter panel', async () => {
    const user = userEvent.setup();
    renderDisplaysPage();
    await waitForPageReady();

    const filtersButton = screen.getByRole('button', { name: /filters/i });
    await user.click(filtersButton);
    await screen.findByRole('button', { name: /reset/i });

    await user.click(filtersButton);

    await waitFor(() => {
      expect(screen.queryByRole('button', { name: /reset/i })).not.toBeInTheDocument();
    });
  });

  // ---------------------------------------------------------------------------
  // Typing in the global search input eventually triggers a fetchDisplays call
  // that carries the typed keyword. The search is debounced (500 ms), so we
  // assert inside waitFor with an extended timeout.
  // ---------------------------------------------------------------------------
  test('typing in the search input calls fetchDisplays with the keyword', async () => {
    const user = userEvent.setup();
    renderDisplaysPage();
    await waitForPageReady();

    await user.type(screen.getByRole('textbox', { name: /search displays/i }), 'lobby');

    await waitFor(
      () => {
        expect(fetchDisplays).toHaveBeenCalledWith(
          expect.objectContaining({ keyword: 'lobby' }),
        );
      },
      { timeout: 2000 },
    );
  });

  // ---------------------------------------------------------------------------
  // The search onChange always resets pageIndex to 0 so the user never lands
  // on an empty page after narrowing the result set.
  // ---------------------------------------------------------------------------
  test('typing in the search input resets pagination to the first page', async () => {
    const user = userEvent.setup();
    renderDisplaysPage();
    await waitForPageReady();

    await user.type(screen.getByPlaceholderText('Search displays...'), 'screen');

    await waitFor(
      () => {
        expect(fetchDisplays).toHaveBeenCalledWith(
          expect.objectContaining({ start: 0, keyword: 'screen' }),
        );
      },
      { timeout: 2000 },
    );
  });

  // ---------------------------------------------------------------------------
  // The Reset button resets the advanced filterInputs state to
  // INITIAL_FILTER_STATE. It does NOT close the panel — the user may want to
  // apply a different filter straight away.
  // ---------------------------------------------------------------------------
  test('clicking Reset keeps the filter panel open', async () => {
    const user = userEvent.setup();
    renderDisplaysPage();
    await waitForPageReady();

    await user.click(screen.getByRole('button', { name: /filters/i }));
    await user.click(await screen.findByRole('button', { name: /reset/i }));

    // Panel stays open — Reset button is still accessible.
    screen.getByRole('button', { name: /reset/i });
  });
});
