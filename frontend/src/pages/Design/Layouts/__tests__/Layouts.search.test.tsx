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

import { screen, fireEvent, waitFor, within, act } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type React from 'react';
import { vi, beforeEach, describe, test, expect } from 'vitest';

import { useLayoutData } from '../hooks/useLayoutData';

import {
  EMPTY_LAYOUT_TABLE,
  mockLayout,
  mockLayoutData,
  renderLayoutsPage,
} from './layoutTestUtils';

import { testQueryClient } from '@/setupTests';

// -----------------------------------------------------------------------------
// Module mocks
// -----------------------------------------------------------------------------

// 3rd-party
vi.mock('react-i18next', () => ({
  useTranslation: () => ({ t: (key: string) => key, i18n: { changeLanguage: vi.fn() } }),
  Trans: ({ children }: { children: React.ReactNode }) => children,
}));

vi.mock('i18next', () => {
  const t = (key: string) => key;
  return { default: { t, language: 'en', isInitialized: true }, t };
});

// Services
vi.mock('@/services/folderApi', () => ({
  fetchFolderById: vi.fn().mockResolvedValue({ id: 1, text: 'Root' }),
  fetchFolderTree: vi.fn().mockResolvedValue([]),
  searchFolders: vi.fn().mockResolvedValue([]),
  fetchContextButtons: vi.fn().mockResolvedValue({ create: true }),
  selectFolder: vi.fn().mockResolvedValue({ success: true }),
}));
vi.mock('@/services/userApi', () => ({
  fetchUserPreference: vi.fn().mockResolvedValue(null),
  saveUserPreference: vi.fn().mockResolvedValue(undefined),
}));

// Hooks
// Bypass the 500ms debounce delay so tests don't wait on real setTimeout.
vi.mock('@/hooks/useDebounce');
vi.mock('../hooks/useLayoutData', () => ({ useLayoutData: vi.fn() }));
vi.mock('@/hooks/useOwner', () => ({
  useOwner: vi.fn().mockReturnValue({ owner: null, loading: false }),
}));
vi.mock('../hooks/useLayoutFilterOptions', () => ({
  useLayoutFilterOptions: vi.fn(() => ({
    filterOptions: [
      {
        label: 'Retired',
        name: 'retired',
        options: [
          { label: 'Any', value: null },
          { label: 'No', value: 0 },
          { label: 'Yes', value: 1 },
        ],
        shouldTranslateOptions: false,
        showAllOption: false,
      },
      {
        label: 'Orientation',
        name: 'orientation',
        options: [
          { label: 'Portrait', value: 'portrait' },
          { label: 'Landscape', value: 'landscape' },
          { label: 'Square', value: 'square' },
        ],
        shouldTranslateOptions: false,
        showAllOption: false,
      },
      {
        label: 'Last Modified',
        name: 'lastModified',
        options: [
          { label: 'Any time', value: '' },
          { label: 'Today', value: 'today' },
          { label: 'Last 7 days', value: '7d' },
        ],
        shouldTranslateOptions: true,
        showAllOption: false,
      },
    ],
    isLoading: false,
  })),
}));

// UI
vi.mock('@/components/ui/FolderActionModals', () => ({ default: () => null }));
vi.mock('@/components/ui/modals/Modal');
// Stub FolderBreadcrumb with a single clickable folder link
vi.mock('@/components/ui/FolderBreadCrumb', () => ({
  default: ({ onNavigate }: { onNavigate?: (folder: { id: number; text: string }) => void }) => (
    <button onClick={() => onNavigate?.({ id: 5, text: 'Sub Folder' })}>Sub Folder</button>
  ),
}));

// ─── Fixtures ─────────────────────────────────────────────────────────────────

// 10 rows with totalCount: 25 so pagination controls render.
const PAGINATED_LAYOUTS = {
  rows: Array.from({ length: 10 }).map((_, i) => ({
    ...mockLayout,
    layoutId: i + 1,
    layout: `Layout ${i + 1}`,
    campaignId: i + 10,
  })),
  totalCount: 25,
};

// =============================================================================
// Tests
// =============================================================================

describe('Layouts page - search and pagination', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    mockLayoutData(EMPTY_LAYOUT_TABLE);
  });

  // -------------------------------------------------------------------------
  // The search input is present and accepts text.
  // -------------------------------------------------------------------------
  test('search input accepts typed text', async () => {
    const user = userEvent.setup({ delay: null });
    renderLayoutsPage();

    const searchInput = await screen.findByPlaceholderText('Search layouts...');
    await user.type(searchInput, 'welcome');

    expect(searchInput).toHaveValue('welcome');
  });

  // -------------------------------------------------------------------------
  // Clearing the search resets the input to empty.
  // -------------------------------------------------------------------------
  test('clearing search resets the input to empty', async () => {
    const user = userEvent.setup({ delay: null });
    renderLayoutsPage();

    const searchInput = await screen.findByPlaceholderText('Search layouts...');
    await user.type(searchInput, 'welcome');
    expect(searchInput).toHaveValue('welcome');

    await user.clear(searchInput);
    expect(searchInput).toHaveValue('');
  });

  // -------------------------------------------------------------------------
  // The typed value is wired through to the data hook's filter param.
  // The useDebounce mock returns the value immediately so there is no delay.
  // -------------------------------------------------------------------------
  test('typing in search passes filter param to useLayoutData', async () => {
    const user = userEvent.setup({ delay: null });
    renderLayoutsPage();

    await user.type(await screen.findByPlaceholderText('Search layouts...'), 'welcome');

    await waitFor(() => {
      expect(useLayoutData).toHaveBeenLastCalledWith(
        expect.objectContaining({ filter: 'welcome' }),
      );
    });
  });

  // -------------------------------------------------------------------------
  // Pagination: clicking Next increments pageIndex.
  // totalCount: 25 with default page size of 10 means two more pages exist.
  // -------------------------------------------------------------------------
  test.skip('clicking Next passes pageIndex 1 to useLayoutData', async () => {
    mockLayoutData(PAGINATED_LAYOUTS);

    renderLayoutsPage();

    fireEvent.click(await screen.findByRole('button', { name: /Next/i }));

    await waitFor(() => {
      expect(useLayoutData).toHaveBeenLastCalledWith(
        expect.objectContaining({
          pagination: expect.objectContaining({ pageIndex: 1 }),
        }),
      );
    });
  });

  // -------------------------------------------------------------------------
  // Pagination controls render only when there is more than one page.
  // -------------------------------------------------------------------------
  test('pagination controls are visible when totalCount exceeds page size', async () => {
    mockLayoutData(PAGINATED_LAYOUTS);

    renderLayoutsPage();

    expect(await screen.findByRole('button', { name: /Next/i })).toBeInTheDocument();
  });

  // -------------------------------------------------------------------------
  // Clicking a folder in the breadcrumb passes its folderId to useLayoutData.
  // -------------------------------------------------------------------------
  test('navigating into a subfolder via breadcrumb filters the table', async () => {
    // The stub breadcrumb renders a "Sub Folder" button that fires onNavigate
    // with id: 5. After the click, the page must pass folderId: 5 into
    // useLayoutData so only layouts inside that folder are fetched.
    renderLayoutsPage();

    fireEvent.click(await screen.findByRole('button', { name: 'Sub Folder' }));

    await waitFor(() => {
      expect(useLayoutData).toHaveBeenLastCalledWith(expect.objectContaining({ folderId: 5 }));
    });
  });

  // -------------------------------------------------------------------------
  // Clicking the Filters button opens the filter panel (aria-hidden toggle).
  // The panel is hidden by default; clicking Filters makes it accessible.
  // -------------------------------------------------------------------------
  test('clicking the Filters button opens the filter panel', async () => {
    // On first render the filter panel exists in the DOM but is hidden.
    // After clicking Filters the Reset button is reachable.
    renderLayoutsPage();

    // The Reset button lives inside the filter panel. When the panel is hidden
    // (aria-hidden="true") RTL excludes it from the accessibility tree.
    expect(screen.queryByRole('button', { name: 'Reset' })).not.toBeInTheDocument();

    await act(async () => {
      fireEvent.click(await screen.findByRole('button', { name: 'Filters' }));
    });

    expect(screen.getByRole('button', { name: 'Reset' })).toBeInTheDocument();
  });

  // -------------------------------------------------------------------------
  // Selecting Retired = Yes passes retired: 1 to useLayoutData.
  // -------------------------------------------------------------------------
  test('selecting Retired = Yes passes retired to useLayoutData', async () => {
    // Open the filter panel, then pick "Yes" from the Retired dropdown.
    // The Layouts page must pass the chosen value into useLayoutData via
    // advancedFilters so the API call uses the right filter parameter.
    renderLayoutsPage();

    await act(async () => {
      fireEvent.click(await screen.findByRole('button', { name: 'Filters' }));
    });

    // Open the Retired SelectFilter dropdown via its toggle button.
    const retiredLabel = screen.getByText('Retired');
    const retiredContainer = retiredLabel.closest('div')!;
    await act(async () => {
      fireEvent.click(within(retiredContainer).getByRole('button'));
    });

    // Click the "Yes" option inside the Retired container.
    await act(async () => {
      fireEvent.click(within(retiredContainer).getByText('Yes'));
    });

    await waitFor(() => {
      expect(useLayoutData).toHaveBeenLastCalledWith(
        expect.objectContaining({
          advancedFilters: expect.objectContaining({ retired: 1 }),
        }),
      );
    });
  });

  // -------------------------------------------------------------------------
  // Selecting Orientation = Landscape passes orientation: 'landscape'.
  // -------------------------------------------------------------------------
  test('selecting Orientation = Landscape passes orientation to useLayoutData', async () => {
    // Open the filter panel, then pick "Landscape" from the Orientation dropdown.
    renderLayoutsPage();

    await act(async () => {
      fireEvent.click(await screen.findByRole('button', { name: 'Filters' }));
    });

    const orientationLabel = screen.getByText('Orientation');
    const orientationContainer = orientationLabel.closest('div')!;
    await act(async () => {
      fireEvent.click(within(orientationContainer).getByRole('button'));
    });
    await act(async () => {
      fireEvent.click(within(orientationContainer).getByText('Landscape'));
    });

    await waitFor(() => {
      expect(useLayoutData).toHaveBeenLastCalledWith(
        expect.objectContaining({
          advancedFilters: expect.objectContaining({ orientation: 'landscape' }),
        }),
      );
    });
  });

  // -------------------------------------------------------------------------
  // Selecting Last Modified = Today passes lastModified: 'today'.
  // -------------------------------------------------------------------------
  test('selecting Last Modified = Today passes lastModified to useLayoutData', async () => {
    // Open the filter panel, then pick "Today" from the Last Modified dropdown.
    renderLayoutsPage();

    await act(async () => {
      fireEvent.click(await screen.findByRole('button', { name: 'Filters' }));
    });

    const lastModLabel = screen.getByText('Last Modified');
    const lastModContainer = lastModLabel.closest('div')!;
    await act(async () => {
      fireEvent.click(within(lastModContainer).getByRole('button'));
    });
    await act(async () => {
      fireEvent.click(within(lastModContainer).getByText('Today'));
    });

    await waitFor(() => {
      expect(useLayoutData).toHaveBeenLastCalledWith(
        expect.objectContaining({
          advancedFilters: expect.objectContaining({ lastModified: 'today' }),
        }),
      );
    });
  });

  // -------------------------------------------------------------------------
  // Clicking Reset clears all active filter values back to their defaults.
  // -------------------------------------------------------------------------
  test('clicking Reset clears all advanced filter values', async () => {
    // Open the filter panel, select a filter value, then click Reset.
    // useLayoutData should be called again with the initial empty filter state
    // so the table shows all layouts with no filter applied.
    renderLayoutsPage();

    await act(async () => {
      fireEvent.click(await screen.findByRole('button', { name: 'Filters' }));
    });

    // Select Retired = Yes to set a non-default filter value.
    const retiredLabel = screen.getByText('Retired');
    const retiredContainer = retiredLabel.closest('div')!;
    await act(async () => {
      fireEvent.click(within(retiredContainer).getByRole('button'));
    });
    await act(async () => {
      fireEvent.click(within(retiredContainer).getByText('Yes'));
    });

    // Now reset - the filter values should return to the initial empty state.
    await act(async () => {
      fireEvent.click(screen.getByRole('button', { name: 'Reset' }));
    });

    await waitFor(() => {
      expect(useLayoutData).toHaveBeenLastCalledWith(
        expect.objectContaining({
          advancedFilters: expect.objectContaining({ retired: '' }),
        }),
      );
    });
  });
});
