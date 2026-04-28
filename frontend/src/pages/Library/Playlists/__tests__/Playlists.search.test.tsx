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

// =============================================================================
// Test type: Page integration test
// Tests search/filter and pagination on the Playlists page.
//
// TDD contracts:
//   - Search placeholder:   'Search playlist...'
//   - usePlaylistData called with: { filter: <typed value> }
//   - Pagination Next button increments pageIndex to 1
//   - usePlaylistData called with: { pagination: { pageIndex: 1 } }
//   - Folder navigation passes folderId to usePlaylistData
//   - Filters button opens the filter panel
//   - Filter selection passes advancedFilters to usePlaylistData
//   - Reset clears all advanced filter values
// =============================================================================

import { QueryClientProvider } from '@tanstack/react-query';
import { render, screen, fireEvent, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type React from 'react';
import { MemoryRouter } from 'react-router-dom';
import { vi, beforeEach, describe, test, expect } from 'vitest';

import Playlists from '../Playlists';
import { usePlaylistData } from '../hooks/usePlaylistData';

import { UserProvider } from '@/context/UserContext';
import { testQueryClient } from '@/setupTests';
import type { User } from '@/types/user';

// ─── Mocks ────────────────────────────────────────────────────────────────────

vi.mock('react-i18next', () => ({
  useTranslation: () => ({ t: (key: string) => key, i18n: { changeLanguage: vi.fn() } }),
  Trans: ({ children }: { children: React.ReactNode }) => children,
}));

vi.mock('i18next', () => {
  const t = (key: string) => key;
  return { default: { t, language: 'en', isInitialized: true }, t };
});

vi.mock('@/services/userApi', () => ({
  fetchUserPreference: vi.fn().mockResolvedValue(null),
  saveUserPreference: vi.fn().mockResolvedValue(undefined),
}));

vi.mock('@/services/folderApi');

vi.mock('../hooks/usePlaylistData', () => ({
  usePlaylistData: vi.fn(),
}));

// Bypass the 500ms debounce delay so tests don't wait on real setTimeout.
vi.mock('@/hooks/useDebounce');

vi.mock('../hooks/usePlaylistFilterOptions', () => ({
  usePlaylistFilterOptions: vi.fn(() => ({
    filterOptions: [
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

// Stub FolderBreadcrumb with a single clickable folder link.
vi.mock('@/components/ui/FolderBreadCrumb', () => ({
  default: ({ onNavigate }: { onNavigate?: (folder: { id: number; text: string }) => void }) => (
    <button onClick={() => onNavigate?.({ id: 5, text: 'Sub Folder' })}>Sub Folder</button>
  ),
}));

vi.mock('@/components/ui/modals/Modal');

vi.mock('../components/AddAndEditPlaylistModal', () => ({
  default: () => null,
}));

vi.mock('../components/CopyPlaylistModal', () => ({
  default: () => null,
}));

vi.mock('@/components/ui/modals/ShareModal', () => ({
  default: () => null,
}));

vi.mock('@/components/ui/modals/MoveModal', () => ({
  default: () => null,
}));

vi.mock('@/components/ui/FolderActionModals', () => ({
  default: () => null,
}));

// ─── Fixtures ─────────────────────────────────────────────────────────────────

const mockUser: User = {
  userId: 1,
  userName: 'TestUser',
  userTypeId: 1,
  groupId: 1,
  features: {},
  settings: {
    defaultTimezone: 'UTC',
    defaultLanguage: 'en',
    DATE_FORMAT_JS: 'DD/MM/YYYY',
    TIME_FORMAT_JS: 'HH:mm',
  },
};

// 10 rows with totalCount: 25 so pagination controls render.
const PAGINATED_PLAYLISTS = {
  data: {
    rows: Array.from({ length: 10 }).map((_, i) => ({
      playlistId: i + 1,
      name: `Playlist ${i + 1}`,
      folderId: 1,
      duration: 30,
      isDynamic: false,
      tags: [],
      ownerId: '1',
      createdDt: '2026-01-01',
      modifiedDt: '2026-03-01',
      valid: true,
      enableStat: 'Off',
      retired: false,
      expires: 0,
      updateInLayouts: false,
      filterMediaName: '',
      logicalOperatorName: 'OR' as const,
      filterMediaTag: [],
      exactTags: false,
      logicalOperator: 'OR' as const,
      filterFolderId: null,
      maxNumberOfItems: 0,
    })),
    totalCount: 25,
  },
  isFetching: false,
  isError: false,
  error: null,
};

// ─── Render helper ────────────────────────────────────────────────────────────

const renderPage = () => {
  testQueryClient.setQueryData(['userPref', 'playlist_page'], null);
  return render(
    <QueryClientProvider client={testQueryClient}>
      <UserProvider initialUser={mockUser}>
        <MemoryRouter>
          <Playlists />
        </MemoryRouter>
      </UserProvider>
    </QueryClientProvider>,
  );
};

// ─── Tests ────────────────────────────────────────────────────────────────────

describe('Playlists page - search and pagination', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    vi.mocked(usePlaylistData).mockReturnValue({
      data: { rows: [], totalCount: 0 },
      isFetching: false,
      isError: false,
      error: null,
    } as unknown as ReturnType<typeof usePlaylistData>);
  });

  // -------------------------------------------------------------------------
  // The search input is present and accepts text.
  // -------------------------------------------------------------------------
  test('search input accepts typed text', async () => {
    const user = userEvent.setup({ delay: null });
    renderPage();

    const searchInput = await screen.findByPlaceholderText('Search playlist...');
    await user.type(searchInput, 'morning');

    expect(searchInput).toHaveValue('morning');
  });

  // -------------------------------------------------------------------------
  // Clearing the search resets the input to empty.
  // -------------------------------------------------------------------------
  test('clearing search resets the input to empty', async () => {
    const user = userEvent.setup({ delay: null });
    renderPage();

    const searchInput = await screen.findByPlaceholderText('Search playlist...');
    await user.type(searchInput, 'morning');
    expect(searchInput).toHaveValue('morning');

    await user.clear(searchInput);
    expect(searchInput).toHaveValue('');
  });

  // -------------------------------------------------------------------------
  // The typed value is wired through to the data hook's filter param.
  // The useDebounce mock returns the value immediately so there is no delay.
  // -------------------------------------------------------------------------
  test('typing in search passes filter param to usePlaylistData', async () => {
    const user = userEvent.setup({ delay: null });
    renderPage();

    await user.type(await screen.findByPlaceholderText('Search playlist...'), 'morning');

    await waitFor(() => {
      expect(usePlaylistData).toHaveBeenLastCalledWith(
        expect.objectContaining({ filter: 'morning' }),
      );
    });
  });

  // -------------------------------------------------------------------------
  // Pagination: clicking Next increments pageIndex.
  // totalCount: 25 with default page size of 10 means two more pages exist.
  // -------------------------------------------------------------------------
  test('clicking Next passes pageIndex 1 to usePlaylistData', async () => {
    vi.mocked(usePlaylistData).mockReturnValue(
      PAGINATED_PLAYLISTS as unknown as ReturnType<typeof usePlaylistData>,
    );

    renderPage();

    fireEvent.click(await screen.findByRole('button', { name: /Next/i }));

    await waitFor(() => {
      expect(usePlaylistData).toHaveBeenLastCalledWith(
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
    vi.mocked(usePlaylistData).mockReturnValue(
      PAGINATED_PLAYLISTS as unknown as ReturnType<typeof usePlaylistData>,
    );

    renderPage();

    expect(await screen.findByRole('button', { name: /Next/i })).toBeInTheDocument();
  });

  // -------------------------------------------------------------------------
  // Clicking a folder in the breadcrumb passes its folderId to usePlaylistData.
  // -------------------------------------------------------------------------
  test('navigating into a subfolder via breadcrumb filters the table', async () => {
    // The stub breadcrumb renders a "Sub Folder" button that fires onNavigate
    // with id: 5. After the click, the page must pass folderId: 5 into
    // usePlaylistData so only playlists inside that folder are fetched.
    renderPage();

    fireEvent.click(await screen.findByRole('button', { name: 'Sub Folder' }));

    await waitFor(() => {
      expect(usePlaylistData).toHaveBeenLastCalledWith(expect.objectContaining({ folderId: 5 }));
    });
  });

  // -------------------------------------------------------------------------
  // Clicking the Filters button opens the filter panel (aria-hidden toggle).
  // The panel is hidden by default; clicking Filters makes it accessible.
  // -------------------------------------------------------------------------
  test('clicking the Filters button opens the filter panel', async () => {
    // On first render the filter panel exists in the DOM but is hidden.
    // After clicking Filters the Reset button is reachable.
    renderPage();

    // The Reset button lives inside the filter panel. When the panel is hidden
    // (aria-hidden="true") RTL excludes it from the accessibility tree.
    expect(screen.queryByRole('button', { name: 'Reset' })).not.toBeInTheDocument();

    fireEvent.click(await screen.findByRole('button', { name: 'Filters' }));

    expect(screen.getByRole('button', { name: 'Reset' })).toBeInTheDocument();
  });

  // -------------------------------------------------------------------------
  // Selecting Last Modified = Today passes lastModified: 'today'.
  // -------------------------------------------------------------------------
  test('selecting Last Modified = Today passes lastModified to usePlaylistData', async () => {
    // Open the filter panel, then pick "Today" from the Last Modified dropdown.
    // The Playlists page must pass the chosen value into usePlaylistData via
    // advancedFilters so the API call uses the right filter parameter.
    renderPage();

    fireEvent.click(await screen.findByRole('button', { name: 'Filters' }));

    const lastModLabel = screen.getByText('Last Modified');
    const lastModContainer = lastModLabel.closest('div')!;
    fireEvent.click(within(lastModContainer).getByRole('button'));
    fireEvent.click(within(lastModContainer).getByText('Today'));

    await waitFor(() => {
      expect(usePlaylistData).toHaveBeenLastCalledWith(
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
    // usePlaylistData should be called again with the initial empty filter state
    // so the table shows all playlists with no filter applied.
    renderPage();

    fireEvent.click(await screen.findByRole('button', { name: 'Filters' }));

    // Select Last Modified = Today to set a non-default filter value.
    const lastModLabel = screen.getByText('Last Modified');
    const lastModContainer = lastModLabel.closest('div')!;
    fireEvent.click(within(lastModContainer).getByRole('button'));
    fireEvent.click(within(lastModContainer).getByText('Today'));

    // Now reset - the filter values should return to the initial empty state.
    fireEvent.click(screen.getByRole('button', { name: 'Reset' }));

    await waitFor(() => {
      expect(usePlaylistData).toHaveBeenLastCalledWith(
        expect.objectContaining({
          advancedFilters: expect.objectContaining({ lastModified: '' }),
        }),
      );
    });
  });
});
