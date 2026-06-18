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

import { QueryClientProvider } from '@tanstack/react-query';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import type { TFunction } from 'i18next';
import { MemoryRouter } from 'react-router-dom';
import { describe, test, expect, vi, beforeEach, afterEach } from 'vitest';

import Media from '../Media';
import { getBaseFilterKeys, INITIAL_FILTER_STATE, type MediaFilterInput } from '../MediaConfig';
import { useMediaData } from '../hooks/useMediaData';
import { useMediaFilterOptions } from '../hooks/useMediaFilterOptions';

import { mockMediaData, renderMediaPage, mockUser, type UseMediaReturn } from './mediaTestUtils';

import { UploadProvider } from '@/context/UploadContext';
import { UserProvider } from '@/context/UserContext';
import { testQueryClient } from '@/setupTests';

// ---------------------------------------------------------------------------
// Module mocks — hoisted before any import resolution
// ---------------------------------------------------------------------------

vi.mock('@/pages/Library/Media/hooks/useMediaFilterOptions', () => ({
  useMediaFilterOptions: vi.fn(),
}));
vi.mock('../hooks/useMediaData');
vi.mock('@/services/mediaApi', () => ({
  uploadMedia: vi.fn(),
  uploadMediaFromUrl: vi.fn(),
  updateMedia: vi.fn(),
  uploadThumbnail: vi.fn(),
  deleteMedia: vi.fn(),
  downloadMedia: vi.fn(),
  downloadMediaAsZip: vi.fn(),
  fetchMediaBlob: vi.fn(),
}));
vi.mock('@/services/folderApi', () => ({
  fetchFolderById: vi.fn().mockResolvedValue({ id: 1, text: 'Root' }),
  fetchFolderTree: vi.fn().mockResolvedValue([]),
  searchFolders: vi.fn().mockResolvedValue([]),
  fetchContextButtons: vi.fn().mockResolvedValue({ create: true }),
  selectFolder: vi.fn(),
}));
vi.mock('@/services/userApi', () => ({
  fetchUserPreference: vi.fn().mockResolvedValue(null),
  saveUserPreference: vi.fn().mockResolvedValue(undefined),
}));

// ---------------------------------------------------------------------------
// Shared helpers
// ---------------------------------------------------------------------------

const EMPTY_DATA: UseMediaReturn = {
  data: { rows: [], totalCount: 0 },
  isFetching: false,
  isError: false,
  error: null,
} as unknown as UseMediaReturn;

// Returns real filter config with an identity t-function (key → key).
const realFilterOptions = () => getBaseFilterKeys(((k: string) => k) as unknown as TFunction);

// Renders Media with an arbitrary router state (e.g. { layoutId: 99 }).
const renderWithRouterState = (state: Record<string, unknown> = {}) => {
  testQueryClient.setQueryData(['userPref', 'media_page'], null);
  return render(
    <QueryClientProvider client={testQueryClient}>
      <UploadProvider>
        <UserProvider initialUser={mockUser}>
          <MemoryRouter initialEntries={[{ pathname: '/library/media', state }]}>
            <Media />
          </MemoryRouter>
        </UserProvider>
      </UploadProvider>
    </QueryClientProvider>,
  );
};

// Renders Media with pre-populated filterInputs (bypasses null userPref override).
const renderWithSavedFilters = (filterInputs: Partial<MediaFilterInput>) => {
  testQueryClient.setQueryData(['userPref', 'media_page'], {
    filterInputs: { ...INITIAL_FILTER_STATE, ...filterInputs },
  });
  return render(
    <QueryClientProvider client={testQueryClient}>
      <UploadProvider>
        <UserProvider initialUser={mockUser}>
          <MemoryRouter>
            <Media />
          </MemoryRouter>
        </UserProvider>
      </UploadProvider>
    </QueryClientProvider>,
  );
};

// Clicks the Filters button and waits until the panel is open (Reset accessible).
const openFilterPanel = async () => {
  const btn = await screen.findByRole('button', { name: /Filters/i });
  fireEvent.click(btn);
  await waitFor(() => expect(screen.getByRole('button', { name: 'Reset' })).toBeInTheDocument());
};

// Ensure fake timers never leak across tests — if any test uses vi.useFakeTimers()
// without cleanup, this afterEach restores real timers before the next test starts.
afterEach(() => {
  vi.useRealTimers();
});

// ---------------------------------------------------------------------------
// Group 1 — Filter Panel Toggle
// ---------------------------------------------------------------------------

describe('Filter Panel Toggle', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    vi.mocked(useMediaFilterOptions).mockReturnValue({ filterOptions: [], isLoading: false });
    mockMediaData(EMPTY_DATA);
  });

  // S1
  test('Reset button is not accessible before the panel is opened', async () => {
    renderMediaPage();
    // Reset lives inside the aria-hidden FilterInputs container; role queries skip it.
    expect(screen.queryByRole('button', { name: 'Reset' })).not.toBeInTheDocument();
  });

  // S2
  test('clicking Filters opens the panel and makes Reset accessible', async () => {
    renderMediaPage();
    const btn = await screen.findByRole('button', { name: /Filters/i });
    fireEvent.click(btn);
    await waitFor(() => {
      expect(screen.getByRole('button', { name: 'Reset' })).toBeInTheDocument();
    });
  });

  // S3
  test('clicking Filters a second time closes the panel again', async () => {
    renderMediaPage();
    const btn = await screen.findByRole('button', { name: /Filters/i });
    fireEvent.click(btn); // open
    await waitFor(() => expect(screen.getByRole('button', { name: 'Reset' })).toBeInTheDocument());
    fireEvent.click(btn); // close
    await waitFor(() => {
      expect(screen.queryByRole('button', { name: 'Reset' })).not.toBeInTheDocument();
    });
  });

  // S4
  test('the FilterInputs container carries aria-hidden="true" on initial render', async () => {
    const { container } = renderMediaPage();
    // FilterInputs renders a single div with aria-hidden={!isOpen}.
    const filterPanel = container.querySelector('[aria-hidden="true"]');
    expect(filterPanel).toBeInTheDocument();
  });
});

// ---------------------------------------------------------------------------
// Group 2 — Filter Fields Rendered
// ---------------------------------------------------------------------------

describe('Filter Fields Rendered', () => {
  beforeEach(async () => {
    testQueryClient.clear();
    vi.clearAllMocks();
    vi.mocked(useMediaFilterOptions).mockReturnValue({
      filterOptions: realFilterOptions(),
      isLoading: false,
    });
    mockMediaData(EMPTY_DATA);
  });

  // S5
  test('all ten filter labels are present in the open panel', async () => {
    renderMediaPage();
    await openFilterPanel();

    const expectedLabels = [
      'ID',
      'Name',
      'Tags',
      'Owner',
      'User Group',
      'Type',
      'Orientation',
      'Retired',
      'Layout ID',
      'Last Modified',
    ];

    for (const label of expectedLabels) {
      expect(screen.getAllByText(label).length).toBeGreaterThanOrEqual(1);
    }
  });

  // S6
  test('Name filter shows an AND/OR operator toggle button', async () => {
    renderMediaPage();
    await openFilterPanel();
    // Name and Tags both render an AndOrButton; use getAllByTitle to avoid "multiple elements" error.
    expect(screen.getAllByTitle('Match ANY entered terms')[0]).toBeInTheDocument();
  });

  // S7
  test('Name filter shows a Regex toggle button', async () => {
    renderMediaPage();
    await openFilterPanel();
    expect(screen.getByTitle('Use RegEx pattern matching')).toBeInTheDocument();
  });

  // S8
  test('Tags filter also shows an AND/OR toggle (two AND/OR buttons total)', async () => {
    renderMediaPage();
    await openFilterPanel();
    // Name and Tags both have showAndOr; expect at least two.
    const andOrBtns = screen.getAllByTitle('Match ANY entered terms');
    expect(andOrBtns.length).toBeGreaterThanOrEqual(2);
  });

  // S9
  test('Tags filter shows an Exact Tags toggle button', async () => {
    renderMediaPage();
    await openFilterPanel();
    expect(screen.getByTitle('Match exact characters only')).toBeInTheDocument();
  });

  // S10
  test('ID filter renders as a number input (spinbutton role)', async () => {
    renderMediaPage();
    await openFilterPanel();
    // Two spinbuttons: ID and Layout ID. Just verify at least one exists.
    const spinbuttons = screen.getAllByRole('spinbutton');
    expect(spinbuttons.length).toBeGreaterThanOrEqual(1);
  });

  // S11 — interaction: typing in the ID filter propagates to useMediaData
  test('typing in the ID filter (after debounce) updates advancedFilters.mediaId', async () => {
    renderMediaPage();
    await openFilterPanel(); // must complete before switching to fake timers

    // Switch to fake timers AFTER the panel is open so openFilterPanel's waitFor still works.
    vi.useFakeTimers();

    // ID is the first number input in the filter panel.
    const [idInput] = screen.getAllByRole('spinbutton');
    fireEvent.change(idInput!, { target: { value: '7' } });
    vi.runAllTimers(); // advance the 300ms debounce

    vi.useRealTimers(); // restore before waitFor (which needs real setInterval internally)

    await waitFor(() => {
      const lastArgs = vi.mocked(useMediaData).mock.calls.slice(-1)[0]?.[0];
      expect(lastArgs?.advancedFilters?.mediaId).toBe(7);
    });
  });
});

// ---------------------------------------------------------------------------
// Group 3 — Reset Behavior
// ---------------------------------------------------------------------------

describe('Reset Behavior', () => {
  beforeEach(async () => {
    testQueryClient.clear();
    vi.clearAllMocks();
    vi.mocked(useMediaFilterOptions).mockReturnValue({
      filterOptions: realFilterOptions(),
      isLoading: false,
    });
    mockMediaData(EMPTY_DATA);
  });

  // S12
  test('Reset button is accessible whenever the filter panel is open', async () => {
    renderMediaPage();
    await openFilterPanel();
    expect(screen.getByRole('button', { name: 'Reset' })).toBeInTheDocument();
  });

  // S13
  test('clicking Reset returns advancedFilters to INITIAL_FILTER_STATE', async () => {
    renderMediaPage();
    await openFilterPanel(); // complete panel open with real timers

    vi.useFakeTimers(); // switch to fake timers for debounce control

    // Type a value in the first spinbutton (ID filter).
    const [idInput] = screen.getAllByRole('spinbutton');
    fireEvent.change(idInput!, { target: { value: '55' } });
    vi.runAllTimers();

    vi.useRealTimers();

    await waitFor(() => {
      const args = vi.mocked(useMediaData).mock.calls.slice(-1)[0]?.[0];
      expect(args?.advancedFilters?.mediaId).toBe(55);
    });

    // Reset
    fireEvent.click(screen.getByRole('button', { name: 'Reset' }));

    await waitFor(() => {
      const args = vi.mocked(useMediaData).mock.calls.slice(-1)[0]?.[0];
      expect(args?.advancedFilters?.mediaId).toBeNull();
    });
  });

  // S14
  test('clicking Reset resets pagination to page 0', async () => {
    renderMediaPage();
    await openFilterPanel(); // complete with real timers

    vi.useFakeTimers(); // fake timers for debounce

    // Set a filter so the component has reason to reset pagination.
    const [idInput] = screen.getAllByRole('spinbutton');
    fireEvent.change(idInput!, { target: { value: '3' } });
    vi.runAllTimers();

    vi.useRealTimers();

    fireEvent.click(screen.getByRole('button', { name: 'Reset' }));

    await waitFor(() => {
      const args = vi.mocked(useMediaData).mock.calls.slice(-1)[0]?.[0];
      expect(args?.pagination?.pageIndex).toBe(0);
    });
  });

  // S15
  test('the filter panel stays open after clicking Reset', async () => {
    renderMediaPage();
    await openFilterPanel();

    fireEvent.click(screen.getByRole('button', { name: 'Reset' }));

    // Reset button should still be accessible (panel is still open).
    await waitFor(() => {
      expect(screen.getByRole('button', { name: 'Reset' })).toBeInTheDocument();
    });
    // Bumped from the 5s default: this test passes in ~3s in isolation but races on
    // JSDOM under the full parallel suite and intermittently exceeds 5s. See the
    // frontend-testing notes on JSDOM contention before removing this.
  }, 20_000);
});

// ---------------------------------------------------------------------------
// Group 4 — Pre-populated Filter State
// ---------------------------------------------------------------------------

describe('Pre-populated Filter State (saved preferences)', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    vi.mocked(useMediaFilterOptions).mockReturnValue({ filterOptions: [], isLoading: false });
    mockMediaData(EMPTY_DATA);
  });

  // S16
  test('mediaId from saved prefs is passed to useMediaData after hydration', async () => {
    renderWithSavedFilters({ mediaId: 42 });

    await waitFor(() => {
      const args = vi.mocked(useMediaData).mock.calls.slice(-1)[0]?.[0];
      expect(args?.advancedFilters?.mediaId).toBe(42);
    });
  });

  // S17
  test('type from saved prefs is passed to useMediaData after hydration', async () => {
    renderWithSavedFilters({ type: 'video' });

    await waitFor(() => {
      const args = vi.mocked(useMediaData).mock.calls.slice(-1)[0]?.[0];
      expect(args?.advancedFilters?.type).toBe('video');
    });
  });

  // S18
  test('layoutId from saved prefs is passed to useMediaData after hydration', async () => {
    renderWithSavedFilters({ layoutId: 99 });

    await waitFor(() => {
      const args = vi.mocked(useMediaData).mock.calls.slice(-1)[0]?.[0];
      expect(args?.advancedFilters?.layoutId).toBe(99);
    });
  });
});

// ---------------------------------------------------------------------------
// Group 5 — layoutId from Router State
// ---------------------------------------------------------------------------

describe('Layout ID from Router State', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    vi.mocked(useMediaFilterOptions).mockReturnValue({ filterOptions: [], isLoading: false });
    mockMediaData(EMPTY_DATA);
  });

  // S19
  test('location.state.layoutId pre-fills advancedFilters.layoutId', async () => {
    renderWithRouterState({ layoutId: 77 });

    await waitFor(() => {
      const args = vi.mocked(useMediaData).mock.calls.slice(-1)[0]?.[0];
      expect(args?.advancedFilters?.layoutId).toBe(77);
    });
  });

  // S20
  test('location.state.layoutId injection resets pagination to page 0', async () => {
    renderWithRouterState({ layoutId: 77 });

    await waitFor(() => {
      const args = vi.mocked(useMediaData).mock.calls.slice(-1)[0]?.[0];
      expect(args?.pagination?.pageIndex).toBe(0);
    });
  });
});
