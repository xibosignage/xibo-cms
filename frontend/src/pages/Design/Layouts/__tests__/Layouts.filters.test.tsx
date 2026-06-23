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
import { render, screen, fireEvent, waitFor, within } from '@testing-library/react';
import type { TFunction } from 'i18next';
import { MemoryRouter } from 'react-router-dom';
import { vi, beforeEach, afterEach, describe, test, expect } from 'vitest';

import {
  getBaseFilterKeys,
  LAYOUT_INITIAL_FILTER_STATE,
  type LayoutFilterInput,
} from '../LayoutConfig';
import Layouts from '../Layouts';
import { useLayoutData } from '../hooks/useLayoutData';
import { useLayoutFilterOptions } from '../hooks/useLayoutFilterOptions';

import { EMPTY_LAYOUT_TABLE, mockLayoutData, mockUser } from './layoutTestUtils';

import { UserProvider } from '@/context/UserContext';
import { testQueryClient } from '@/setupTests';

// -----------------------------------------------------------------------------
// Module mocks
// -----------------------------------------------------------------------------

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

vi.mock('../hooks/useLayoutData', () => ({ useLayoutData: vi.fn() }));
vi.mock('../hooks/useLayoutFilterOptions', () => ({ useLayoutFilterOptions: vi.fn() }));

vi.mock('@/hooks/useOwner', () => ({
  useOwner: vi.fn().mockReturnValue({ owner: null, loading: false }),
}));

vi.mock('@/components/ui/FolderActionModals', () => ({ default: () => null }));
vi.mock('@/components/ui/FolderSidebar', () => ({ default: () => null }));
vi.mock('@/components/ui/modals/Modal');
vi.mock('@/components/ui/FolderBreadCrumb', () => ({
  default: () => <div>Breadcrumb</div>,
}));
// DateRangeFilter renders a full calendar with 60+ day buttons. Replace it with
// a lightweight stub that still renders the label and preset option buttons so
// filter-label and option-click tests work without the expensive DatePicker.
vi.mock('@/components/ui/DateRangeFilter', () => ({
  default: ({
    label,
    name,
    options,
    onChange,
  }: {
    label: string;
    name: string;
    options: Array<{ label: string; value: string | number | null }>;
    onChange: (n: string, v: string | number | null) => void;
  }) => (
    <div>
      <span>{label}</span>
      {options.map((opt) => (
        <button key={String(opt.value)} type="button" onClick={() => onChange(name, opt.value)}>
          {opt.label}
        </button>
      ))}
    </div>
  ),
}));

// -----------------------------------------------------------------------------
// Helpers
// -----------------------------------------------------------------------------

// Returns the real static filter options using an identity t-function.
const realFilterOptions = () => getBaseFilterKeys(((k: string) => k) as unknown as TFunction);

// Renders Layouts with saved preferences pre-loaded in React Query cache.
const renderWithSavedFilters = (filterInputs: Partial<LayoutFilterInput>) => {
  testQueryClient.setQueryData(['userPref', 'layout_page'], {
    filterInputs: { ...LAYOUT_INITIAL_FILTER_STATE, ...filterInputs },
  });
  return render(
    <QueryClientProvider client={testQueryClient}>
      <UserProvider initialUser={mockUser}>
        <MemoryRouter>
          <Layouts />
        </MemoryRouter>
      </UserProvider>
    </QueryClientProvider>,
  );
};

// Renders Layouts with router location state (e.g. injected activeDisplayGroupId).
const renderWithRouterState = (state: Record<string, unknown> = {}) => {
  testQueryClient.setQueryData(['userPref', 'layout_page'], null);
  return render(
    <QueryClientProvider client={testQueryClient}>
      <UserProvider initialUser={mockUser}>
        <MemoryRouter initialEntries={[{ pathname: '/design/layouts', state }]}>
          <Layouts />
        </MemoryRouter>
      </UserProvider>
    </QueryClientProvider>,
  );
};

// Clicks the Filters button and waits until the panel is open (Reset accessible).
const openFilterPanel = async () => {
  const btn = await screen.findByRole('button', { name: /Filters/i });
  fireEvent.click(btn);
  await waitFor(() => expect(screen.getByRole('button', { name: 'Reset' })).toBeInTheDocument());
};

// Returns the FilterInputs container (the div with aria-hidden on it).
const getFilterPanel = () =>
  screen.getByRole('button', { name: 'Reset' }).closest('[aria-hidden]') as HTMLElement;

// Restore real timers after every test so fake timers never leak.
afterEach(() => {
  vi.useRealTimers();
});

// =============================================================================
// Group 1 — Filter Panel Toggle
// =============================================================================

describe('Filter Panel Toggle', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    vi.mocked(useLayoutFilterOptions).mockReturnValue({ filterOptions: [], isLoading: false });
    mockLayoutData(EMPTY_LAYOUT_TABLE);
  });

  // S1
  test('Reset button is not accessible before the panel is opened', async () => {
    testQueryClient.setQueryData(['userPref', 'layout_page'], null);
    render(
      <QueryClientProvider client={testQueryClient}>
        <UserProvider initialUser={mockUser}>
          <MemoryRouter>
            <Layouts />
          </MemoryRouter>
        </UserProvider>
      </QueryClientProvider>,
    );

    // Reset lives inside the aria-hidden FilterInputs container; role queries skip it.
    expect(screen.queryByRole('button', { name: 'Reset' })).not.toBeInTheDocument();
  });

  // S2
  test('clicking Filters a second time closes the panel again', async () => {
    testQueryClient.setQueryData(['userPref', 'layout_page'], null);
    render(
      <QueryClientProvider client={testQueryClient}>
        <UserProvider initialUser={mockUser}>
          <MemoryRouter>
            <Layouts />
          </MemoryRouter>
        </UserProvider>
      </QueryClientProvider>,
    );

    const btn = await screen.findByRole('button', { name: /Filters/i });
    fireEvent.click(btn); // open
    await waitFor(() => expect(screen.getByRole('button', { name: 'Reset' })).toBeInTheDocument());

    fireEvent.click(btn); // close
    await waitFor(() => {
      expect(screen.queryByRole('button', { name: 'Reset' })).not.toBeInTheDocument();
    });
  });

  // S3
  test('the FilterInputs container carries aria-hidden="true" on initial render', async () => {
    testQueryClient.setQueryData(['userPref', 'layout_page'], null);
    const { container } = render(
      <QueryClientProvider client={testQueryClient}>
        <UserProvider initialUser={mockUser}>
          <MemoryRouter>
            <Layouts />
          </MemoryRouter>
        </UserProvider>
      </QueryClientProvider>,
    );

    const filterPanel = container.querySelector('[aria-hidden="true"]');
    expect(filterPanel).toBeInTheDocument();
  });
});

// =============================================================================
// Group 2 — Filter Fields Rendered
// =============================================================================

describe('Filter Fields Rendered', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    vi.mocked(useLayoutFilterOptions).mockReturnValue({
      filterOptions: realFilterOptions(),
      isLoading: false,
    });
    mockLayoutData(EMPTY_LAYOUT_TABLE);
  });

  // S4
  test('all 14 filter labels are present in the open panel', async () => {
    testQueryClient.setQueryData(['userPref', 'layout_page'], null);
    render(
      <QueryClientProvider client={testQueryClient}>
        <UserProvider initialUser={mockUser}>
          <MemoryRouter>
            <Layouts />
          </MemoryRouter>
        </UserProvider>
      </QueryClientProvider>,
    );

    await openFilterPanel();
    const panel = getFilterPanel();

    const expectedLabels = [
      'ID',
      'Name',
      'Tags',
      'Code',
      'Display Group',
      'Owner',
      'Owner User Group',
      'Orientation',
      'Retired',
      'Show',
      'Description',
      'Media',
      'Layout ID',
      'Last Modified',
    ];

    for (const label of expectedLabels) {
      expect(within(panel).getAllByText(label).length).toBeGreaterThanOrEqual(1);
    }
  });

  // S5
  test('Name filter shows an AND/OR operator toggle button', async () => {
    testQueryClient.setQueryData(['userPref', 'layout_page'], null);
    render(
      <QueryClientProvider client={testQueryClient}>
        <UserProvider initialUser={mockUser}>
          <MemoryRouter>
            <Layouts />
          </MemoryRouter>
        </UserProvider>
      </QueryClientProvider>,
    );

    await openFilterPanel();
    // Name and Tags both render an AndOrButton; at least one must be present.
    expect(screen.getAllByTitle('Match ANY entered terms')[0]).toBeInTheDocument();
  });

  // S6
  test('Name filter shows a Regex toggle button', async () => {
    testQueryClient.setQueryData(['userPref', 'layout_page'], null);
    render(
      <QueryClientProvider client={testQueryClient}>
        <UserProvider initialUser={mockUser}>
          <MemoryRouter>
            <Layouts />
          </MemoryRouter>
        </UserProvider>
      </QueryClientProvider>,
    );

    await openFilterPanel();
    expect(screen.getByTitle('Use RegEx pattern matching')).toBeInTheDocument();
  });

  // S7
  test('Tags filter shows an AND/OR toggle (two AND/OR buttons total)', async () => {
    testQueryClient.setQueryData(['userPref', 'layout_page'], null);
    render(
      <QueryClientProvider client={testQueryClient}>
        <UserProvider initialUser={mockUser}>
          <MemoryRouter>
            <Layouts />
          </MemoryRouter>
        </UserProvider>
      </QueryClientProvider>,
    );

    await openFilterPanel();
    // Name and Tags both have showAndOr; expect at least two.
    const andOrBtns = screen.getAllByTitle('Match ANY entered terms');
    expect(andOrBtns.length).toBeGreaterThanOrEqual(2);
  });

  // S8
  test('Tags filter shows an Exact Tags toggle button', async () => {
    testQueryClient.setQueryData(['userPref', 'layout_page'], null);
    render(
      <QueryClientProvider client={testQueryClient}>
        <UserProvider initialUser={mockUser}>
          <MemoryRouter>
            <Layouts />
          </MemoryRouter>
        </UserProvider>
      </QueryClientProvider>,
    );

    await openFilterPanel();
    expect(screen.getByTitle('Match exact characters only')).toBeInTheDocument();
  });

  // S9
  test('ID and Layout ID filters render as number inputs (spinbutton role)', async () => {
    testQueryClient.setQueryData(['userPref', 'layout_page'], null);
    render(
      <QueryClientProvider client={testQueryClient}>
        <UserProvider initialUser={mockUser}>
          <MemoryRouter>
            <Layouts />
          </MemoryRouter>
        </UserProvider>
      </QueryClientProvider>,
    );

    await openFilterPanel();
    const spinbuttons = screen.getAllByRole('spinbutton');
    expect(spinbuttons.length).toBeGreaterThanOrEqual(2);
  });

  // S10
  test('typing in the ID (campaignId) filter (after debounce) updates advancedFilters.campaignId', async () => {
    testQueryClient.setQueryData(['userPref', 'layout_page'], null);
    render(
      <QueryClientProvider client={testQueryClient}>
        <UserProvider initialUser={mockUser}>
          <MemoryRouter>
            <Layouts />
          </MemoryRouter>
        </UserProvider>
      </QueryClientProvider>,
    );

    await openFilterPanel(); // complete with real timers

    // Switch to fake timers AFTER the panel is open.
    vi.useFakeTimers();

    const [idInput] = screen.getAllByRole('spinbutton');
    fireEvent.change(idInput!, { target: { value: '42' } });
    vi.runAllTimers(); // advance the 300ms DebouncedInputFilter debounce

    vi.useRealTimers();

    await waitFor(() => {
      const lastArgs = vi.mocked(useLayoutData).mock.calls.slice(-1)[0]?.[0];
      expect(lastArgs?.advancedFilters?.campaignId).toBe(42);
    });
  });

  // S11
  test('typing in the Layout ID filter (after debounce) updates advancedFilters.layoutId', async () => {
    testQueryClient.setQueryData(['userPref', 'layout_page'], null);
    render(
      <QueryClientProvider client={testQueryClient}>
        <UserProvider initialUser={mockUser}>
          <MemoryRouter>
            <Layouts />
          </MemoryRouter>
        </UserProvider>
      </QueryClientProvider>,
    );

    await openFilterPanel();

    vi.useFakeTimers();

    const spinbuttons = screen.getAllByRole('spinbutton');
    const layoutIdInput = spinbuttons[1]; // second spinbutton is Layout ID
    fireEvent.change(layoutIdInput!, { target: { value: '7' } });
    vi.runAllTimers();

    vi.useRealTimers();

    await waitFor(() => {
      const lastArgs = vi.mocked(useLayoutData).mock.calls.slice(-1)[0]?.[0];
      expect(lastArgs?.advancedFilters?.layoutId).toBe(7);
    });
  });
});

// =============================================================================
// Group 3 — Dropdown Filter Interactions
// =============================================================================

describe('Dropdown Filter Interactions', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    vi.mocked(useLayoutFilterOptions).mockReturnValue({
      filterOptions: realFilterOptions(),
      isLoading: false,
    });
    mockLayoutData(EMPTY_LAYOUT_TABLE);
  });

  // S12
  test('selecting Show = Only Used passes layoutStatusId: 2 to useLayoutData', async () => {
    testQueryClient.setQueryData(['userPref', 'layout_page'], null);
    render(
      <QueryClientProvider client={testQueryClient}>
        <UserProvider initialUser={mockUser}>
          <MemoryRouter>
            <Layouts />
          </MemoryRouter>
        </UserProvider>
      </QueryClientProvider>,
    );

    await openFilterPanel();

    const panel = getFilterPanel();
    const showLabel = within(panel).getByText('Show');
    const showContainer = showLabel.closest('div')!;
    fireEvent.click(within(showContainer).getByRole('combobox'));

    fireEvent.click(await screen.findByRole('option', { name: 'Only Used' }));

    await waitFor(() => {
      expect(useLayoutData).toHaveBeenLastCalledWith(
        expect.objectContaining({
          advancedFilters: expect.objectContaining({ layoutStatusId: 2 }),
        }),
      );
    });
  });

  // S13
  test('selecting Description = 1st line passes showDescriptionId: 2 to useLayoutData', async () => {
    testQueryClient.setQueryData(['userPref', 'layout_page'], null);
    render(
      <QueryClientProvider client={testQueryClient}>
        <UserProvider initialUser={mockUser}>
          <MemoryRouter>
            <Layouts />
          </MemoryRouter>
        </UserProvider>
      </QueryClientProvider>,
    );

    await openFilterPanel();

    const panel = getFilterPanel();
    const descLabel = within(panel).getByText('Description');
    const descContainer = descLabel.closest('div')!;
    fireEvent.click(within(descContainer).getByRole('combobox'));

    fireEvent.click(await screen.findByRole('option', { name: '1st line' }));

    await waitFor(() => {
      expect(useLayoutData).toHaveBeenLastCalledWith(
        expect.objectContaining({
          advancedFilters: expect.objectContaining({ showDescriptionId: 2 }),
        }),
      );
    });
  });

  // S14
  test('selecting Orientation = Portrait passes orientation: portrait to useLayoutData', async () => {
    testQueryClient.setQueryData(['userPref', 'layout_page'], null);
    render(
      <QueryClientProvider client={testQueryClient}>
        <UserProvider initialUser={mockUser}>
          <MemoryRouter>
            <Layouts />
          </MemoryRouter>
        </UserProvider>
      </QueryClientProvider>,
    );

    await openFilterPanel();

    const orientationLabel = screen.getByText('Orientation');
    const orientationContainer = orientationLabel.closest('div')!;
    fireEvent.click(within(orientationContainer).getByRole('combobox'));

    fireEvent.click(await screen.findByRole('option', { name: 'Portrait' }));

    await waitFor(() => {
      expect(useLayoutData).toHaveBeenLastCalledWith(
        expect.objectContaining({
          advancedFilters: expect.objectContaining({ orientation: 'portrait' }),
        }),
      );
    });
  });

  // S15
  test('selecting Retired = No passes retired: 0 to useLayoutData', async () => {
    testQueryClient.setQueryData(['userPref', 'layout_page'], null);
    render(
      <QueryClientProvider client={testQueryClient}>
        <UserProvider initialUser={mockUser}>
          <MemoryRouter>
            <Layouts />
          </MemoryRouter>
        </UserProvider>
      </QueryClientProvider>,
    );

    await openFilterPanel();

    const retiredLabel = screen.getByText('Retired');
    const retiredContainer = retiredLabel.closest('div')!;
    fireEvent.click(within(retiredContainer).getByRole('combobox'));

    fireEvent.click(await screen.findByRole('option', { name: 'No' }));

    await waitFor(() => {
      expect(useLayoutData).toHaveBeenLastCalledWith(
        expect.objectContaining({
          advancedFilters: expect.objectContaining({ retired: 0 }),
        }),
      );
    });
  });

  // S16
  test('selecting Last Modified = Last 7 days passes lastModified: 7d to useLayoutData', async () => {
    testQueryClient.setQueryData(['userPref', 'layout_page'], null);
    render(
      <QueryClientProvider client={testQueryClient}>
        <UserProvider initialUser={mockUser}>
          <MemoryRouter>
            <Layouts />
          </MemoryRouter>
        </UserProvider>
      </QueryClientProvider>,
    );

    await openFilterPanel();

    // DateRangeFilter renders a plain button toggle (not a combobox).
    // Its option buttons are always in the DOM (CSS-only visibility),
    // so we can fire a click directly without opening the dropdown first.
    const lastModLabel = screen.getByText('Last Modified');
    const lastModContainer = lastModLabel.closest('div')!;
    fireEvent.click(within(lastModContainer).getByRole('button', { name: 'Last 7 days' }));

    await waitFor(() => {
      expect(useLayoutData).toHaveBeenLastCalledWith(
        expect.objectContaining({
          advancedFilters: expect.objectContaining({ lastModified: '7d' }),
        }),
      );
    });
  });
});

// =============================================================================
// Group 4 — Reset Behavior
// =============================================================================

describe('Reset Behavior', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    vi.mocked(useLayoutFilterOptions).mockReturnValue({
      filterOptions: realFilterOptions(),
      isLoading: false,
    });
    mockLayoutData(EMPTY_LAYOUT_TABLE);
  });

  // S17
  test('Reset button is accessible whenever the filter panel is open', async () => {
    testQueryClient.setQueryData(['userPref', 'layout_page'], null);
    render(
      <QueryClientProvider client={testQueryClient}>
        <UserProvider initialUser={mockUser}>
          <MemoryRouter>
            <Layouts />
          </MemoryRouter>
        </UserProvider>
      </QueryClientProvider>,
    );

    await openFilterPanel();
    expect(screen.getByRole('button', { name: 'Reset' })).toBeInTheDocument();
  });

  // S18
  test('clicking Reset resets pagination to page 0', async () => {
    testQueryClient.setQueryData(['userPref', 'layout_page'], null);
    render(
      <QueryClientProvider client={testQueryClient}>
        <UserProvider initialUser={mockUser}>
          <MemoryRouter>
            <Layouts />
          </MemoryRouter>
        </UserProvider>
      </QueryClientProvider>,
    );

    await openFilterPanel();

    // Set a filter to give the reset something to clear.
    vi.useFakeTimers();
    const [idInput] = screen.getAllByRole('spinbutton');
    fireEvent.change(idInput!, { target: { value: '3' } });
    vi.runAllTimers();
    vi.useRealTimers();

    fireEvent.click(screen.getByRole('button', { name: 'Reset' }));

    await waitFor(() => {
      const lastArgs = vi.mocked(useLayoutData).mock.calls.slice(-1)[0]?.[0];
      expect(lastArgs?.pagination?.pageIndex).toBe(0);
    });
  });

  // S19
  test('the filter panel stays open after clicking Reset', async () => {
    testQueryClient.setQueryData(['userPref', 'layout_page'], null);
    render(
      <QueryClientProvider client={testQueryClient}>
        <UserProvider initialUser={mockUser}>
          <MemoryRouter>
            <Layouts />
          </MemoryRouter>
        </UserProvider>
      </QueryClientProvider>,
    );

    await openFilterPanel();

    fireEvent.click(screen.getByRole('button', { name: 'Reset' }));

    // The Reset button must still be accessible (panel remains open).
    await waitFor(() => {
      expect(screen.getByRole('button', { name: 'Reset' })).toBeInTheDocument();
    });
  });
});

// =============================================================================
// Group 5 — Pre-populated Filter State (saved preferences)
// =============================================================================

describe('Pre-populated Filter State (saved preferences)', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    vi.mocked(useLayoutFilterOptions).mockReturnValue({ filterOptions: [], isLoading: false });
    mockLayoutData(EMPTY_LAYOUT_TABLE);
  });

  // S20
  test('campaignId from saved prefs is passed to useLayoutData after hydration', async () => {
    renderWithSavedFilters({ campaignId: 42 });

    await waitFor(() => {
      const lastArgs = vi.mocked(useLayoutData).mock.calls.slice(-1)[0]?.[0];
      expect(lastArgs?.advancedFilters?.campaignId).toBe(42);
    });
  });

  // S21
  test('orientation from saved prefs is passed to useLayoutData after hydration', async () => {
    renderWithSavedFilters({ orientation: 'portrait' });

    await waitFor(() => {
      const lastArgs = vi.mocked(useLayoutData).mock.calls.slice(-1)[0]?.[0];
      expect(lastArgs?.advancedFilters?.orientation).toBe('portrait');
    });
  });

  // S22
  test('retired from saved prefs is passed to useLayoutData after hydration', async () => {
    renderWithSavedFilters({ retired: '1' });

    await waitFor(() => {
      const lastArgs = vi.mocked(useLayoutData).mock.calls.slice(-1)[0]?.[0];
      expect(lastArgs?.advancedFilters?.retired).toBe('1');
    });
  });
});

// =============================================================================
// Group 6 — Router State Injection
// =============================================================================

describe('Router State Injection', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    vi.mocked(useLayoutFilterOptions).mockReturnValue({ filterOptions: [], isLoading: false });
    mockLayoutData(EMPTY_LAYOUT_TABLE);
  });

  // S23
  test('location.state.activeDisplayGroupId pre-fills advancedFilters.activeDisplayGroupId', async () => {
    renderWithRouterState({ activeDisplayGroupId: 99 });

    await waitFor(() => {
      const lastArgs = vi.mocked(useLayoutData).mock.calls.slice(-1)[0]?.[0];
      expect(lastArgs?.advancedFilters?.activeDisplayGroupId).toBe(99);
    });
  });

  // S24
  test('activeDisplayGroupId injection resets pagination to page 0', async () => {
    renderWithRouterState({ activeDisplayGroupId: 99 });

    await waitFor(() => {
      const lastArgs = vi.mocked(useLayoutData).mock.calls.slice(-1)[0]?.[0];
      expect(lastArgs?.pagination?.pageIndex).toBe(0);
    });
  });
});
