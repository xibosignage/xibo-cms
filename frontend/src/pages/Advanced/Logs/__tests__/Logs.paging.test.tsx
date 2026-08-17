import { screen, waitFor, within } from '@testing-library/react';
import { describe, expect, it, vi, beforeEach } from 'vitest';

import Logs from '../Logs';
import { INITIAL_FILTER_STATE } from '../LogsConfig';
import { useLogsData } from '../hooks/useLogsData';

import { createMockLogEntry, createMockLogsQuery, renderWithProviders } from './Setup';

import { useTableState } from '@/hooks/useTableState';

// --- Module Mocks ---

vi.mock('@/context/UserContext', () => ({
  useUserContext: vi.fn(() => ({ user: { userTypeId: 2 } })),
}));

vi.mock('@/hooks/useFilteredTabs', () => ({
  useFilteredTabs: vi.fn(() => [{ name: 'Log', path: '/advanced/log' }]),
}));

vi.mock('@/hooks/useTableState', () => ({
  useTableState: vi.fn(),
}));

vi.mock('../hooks/useLogsData', () => ({
  useLogsData: vi.fn(),
}));

// --- Default Table State ---

const defaultTableState = {
  pagination: { pageIndex: 0, pageSize: 10 },
  setPagination: vi.fn(),
  sorting: [{ id: 'logId', desc: true }],
  setSorting: vi.fn(),
  columnVisibility: {},
  setColumnVisibility: vi.fn(),
  globalFilter: '',
  setGlobalFilter: vi.fn(),
  filterInputs: INITIAL_FILTER_STATE,
  setFilterInputs: vi.fn(),
  isHydrated: true,
};

// --- Mock Data ---

// 12 entries split across two fetched pages, so the client-side slice (pageSize 10) has to both
// flatten the pages and leave something over for page 2 of the table.
const makeEntries = (from: number, to: number) =>
  Array.from({ length: to - from + 1 }, (_, i) =>
    createMockLogEntry({ logId: from + i, message: `Log message ${from + i}` }),
  );

const firstFetchedPage = { rows: makeEntries(1, 5), totalCount: 12 };
const secondFetchedPage = { rows: makeEntries(6, 12), totalCount: 12 };

// The table only renders once a filter has been applied.
const applyFilter = async (user: ReturnType<typeof renderWithProviders>['user']) => {
  await user.click(screen.getByRole('button', { name: /apply filter/i }));
};

const getBodyRows = () =>
  screen
    .getAllByRole('row')
    .filter((row) => within(row).queryAllByRole('columnheader').length === 0);

// --- Tests ---

describe('Logs Page - Paging', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    vi.mocked(useTableState).mockReturnValue(defaultTableState as never);
    vi.mocked(useLogsData).mockReturnValue(createMockLogsQuery() as never);
  });

  it('does not offer Load More when every matching row has been fetched', async () => {
    const { user } = renderWithProviders(<Logs />);

    await applyFilter(user);

    expect(screen.queryByRole('button', { name: /load more/i })).not.toBeInTheDocument();
  });

  it('reports how many of the matching results have been loaded so far', async () => {
    vi.mocked(useLogsData).mockReturnValue(
      createMockLogsQuery([{ rows: makeEntries(1, 3), totalCount: 10 }], {
        hasNextPage: true,
      }) as never,
    );

    const { user } = renderWithProviders(<Logs />);

    await applyFilter(user);

    expect(screen.getByText('Showing 3 of 10 matching results.')).toBeInTheDocument();
  });

  it('fetches the next batch when Load More is clicked', async () => {
    const fetchNextPage = vi.fn();
    vi.mocked(useLogsData).mockReturnValue(
      createMockLogsQuery([firstFetchedPage], { hasNextPage: true, fetchNextPage }) as never,
    );

    const { user } = renderWithProviders(<Logs />);

    await applyFilter(user);
    await user.click(screen.getByRole('button', { name: /load more/i }));

    expect(fetchNextPage).toHaveBeenCalledTimes(1);
  });

  it('disables the Load More button while the next batch is in flight', async () => {
    vi.mocked(useLogsData).mockReturnValue(
      createMockLogsQuery([firstFetchedPage], {
        hasNextPage: true,
        isFetchingNextPage: true,
      }) as never,
    );

    const { user } = renderWithProviders(<Logs />);

    await applyFilter(user);

    expect(screen.getByRole('button', { name: /loading\.\.\./i })).toBeDisabled();
  });

  it('shows rows from every fetched batch, not just the first', async () => {
    vi.mocked(useLogsData).mockReturnValue(
      createMockLogsQuery([firstFetchedPage, secondFetchedPage]) as never,
    );

    const { user } = renderWithProviders(<Logs />);

    await applyFilter(user);

    // logId 12 only exists in the second fetched batch
    await waitFor(() => {
      expect(screen.getByText('Log message 12')).toBeInTheDocument();
    });
  });

  it('shows only one page worth of the fetched rows at a time', async () => {
    vi.mocked(useLogsData).mockReturnValue(
      createMockLogsQuery([firstFetchedPage, secondFetchedPage]) as never,
    );

    const { user } = renderWithProviders(<Logs />);

    await applyFilter(user);

    await waitFor(() => {
      expect(getBodyRows()).toHaveLength(10);
    });
    // Sorted by logId descending, so the two lowest ids fall onto the table's second page
    expect(screen.queryByText('Log message 1')).not.toBeInTheDocument();
    expect(screen.queryByText('Log message 2')).not.toBeInTheDocument();
  });
});
