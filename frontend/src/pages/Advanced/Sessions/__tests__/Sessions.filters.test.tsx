import { screen } from '@testing-library/react';
import { describe, expect, it, vi, beforeEach } from 'vitest';

import Sessions from '../Sessions';

import { renderWithProviders, mockSessionsList } from './Setup';

import { useTableState } from '@/hooks/useTableState';

// FilterInputs imports t directly from i18next (not via useTranslation). Mock it so
// the Reset button renders with accessible text instead of an empty string.
vi.mock('i18next', () => ({ t: (key: string) => key }));

// 1. Mock Table State
vi.mock('@/hooks/useTableState', () => ({
  useTableState: vi.fn(),
}));

// 2. Mock Session Data directly
const { mockUseSessionData } = vi.hoisted(() => ({ mockUseSessionData: vi.fn() }));
vi.mock('../hooks/useSessionData', () => ({ useSessionData: mockUseSessionData }));

// 3. Mock Hooks
vi.mock('@/hooks/useFilteredTabs', () => ({
  useFilteredTabs: vi.fn(() => [{ name: 'Sessions', path: '/advanced/sessions' }]),
}));

const defaultTableState = {
  pagination: { pageIndex: 0, pageSize: 10 },
  setPagination: vi.fn(),
  sorting: [],
  setSorting: vi.fn(),
  columnVisibility: {},
  setColumnVisibility: vi.fn(),
  globalFilter: '',
  setGlobalFilter: vi.fn(),
  filterInputs: { type: '', lastModified: '' },
  setFilterInputs: vi.fn(),
  isHydrated: true,
};

describe('Sessions Page - Filters', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    vi.mocked(useTableState).mockReturnValue(defaultTableState as never);

    mockUseSessionData.mockReturnValue({
      data: { rows: mockSessionsList, totalCount: 50 },
      isFetching: false,
      isError: false,
      error: null,
    } as never);
  });

  it('toggles the filter inputs panel', async () => {
    const { user } = renderWithProviders(<Sessions />);

    const filterBtn = screen.getByRole('button', { name: 'Filters' });
    await user.click(filterBtn);

    expect(screen.getByText('From Date')).toBeInTheDocument();
  });

  it('resets filters and pagination when Reset is clicked', async () => {
    const { user } = renderWithProviders(<Sessions />);

    // Open filters
    await user.click(screen.getByRole('button', { name: 'Filters' }));

    // Click Reset
    const resetBtn = screen.getByRole('button', { name: 'Reset' });
    await user.click(resetBtn);

    // Verify state setters were successfully called to clear things out
    expect(defaultTableState.setFilterInputs).toHaveBeenCalled();
    expect(defaultTableState.setPagination).toHaveBeenCalled();
  });
});
