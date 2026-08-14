import { screen, waitFor } from '@testing-library/react';
import { describe, expect, it, vi, beforeEach } from 'vitest';

import Logs from '../Logs';
import { INITIAL_FILTER_STATE } from '../LogsConfig';
import { useLogsData } from '../hooks/useLogsData';

import { createMockLogEntry, createMockLogsQuery, renderWithProviders } from './Setup';

import { useUserContext } from '@/context/UserContext';
import { useTableState } from '@/hooks/useTableState';
import { UserType } from '@/types/user';

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
  columnVisibility: {
    logId: true,
    runNo: true,
    logDate: true,
    channel: true,
    function: true,
    type: true,
    display: true,
    page: true,
    message: true,
  },
  setColumnVisibility: vi.fn(),
  globalFilter: '',
  setGlobalFilter: vi.fn(),
  filterInputs: INITIAL_FILTER_STATE,
  setFilterInputs: vi.fn(),
  isHydrated: true,
};

// --- Tests ---

describe('Logs Page - Rendering', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    vi.mocked(useTableState).mockReturnValue(defaultTableState as never);
    vi.mocked(useLogsData).mockReturnValue(createMockLogsQuery() as never);
  });

  it('shows a loading pulse when the page is not yet hydrated', () => {
    vi.mocked(useTableState).mockReturnValue({
      ...defaultTableState,
      isHydrated: false,
    } as never);

    renderWithProviders(<Logs />);

    expect(screen.getByText('Loading logs...')).toBeInTheDocument();
  });

  it('shows a prompt to apply filters when no filter has been submitted yet', () => {
    renderWithProviders(<Logs />);

    expect(
      screen.getByText('Set your filters above and click Apply Filter to view logs.'),
    ).toBeInTheDocument();
  });

  it('renders the DataTable with log rows after Apply Filter is clicked', async () => {
    const { user } = renderWithProviders(<Logs />);

    await user.click(screen.getByRole('button', { name: /apply filter/i }));

    await waitFor(() => {
      expect(screen.getByText('Info log message')).toBeInTheDocument();
    });
    expect(screen.getByText('Error log message')).toBeInTheDocument();
    expect(screen.getByText('Debug log message')).toBeInTheDocument();
  });

  it('shows an error alert banner when the API call fails', async () => {
    vi.mocked(useLogsData).mockReturnValue(
      createMockLogsQuery([], {
        data: undefined,
        isError: true,
        error: new Error('Server Error'),
      }) as never,
    );

    const { user } = renderWithProviders(<Logs />);

    await user.click(screen.getByRole('button', { name: /apply filter/i }));

    await waitFor(() => {
      expect(screen.getByRole('alert')).toHaveTextContent('Server Error');
    });
  });

  it('does not show an error alert when data loads successfully', async () => {
    const { user } = renderWithProviders(<Logs />);

    await user.click(screen.getByRole('button', { name: /apply filter/i }));

    await waitFor(() => {
      expect(screen.getByText('Info log message')).toBeInTheDocument();
    });
    expect(screen.queryByRole('alert')).not.toBeInTheDocument();
  });

  it('does not show the Truncate button for non-SuperAdmin users', () => {
    vi.mocked(useUserContext).mockReturnValue({ user: { userTypeId: 2 } } as never);

    renderWithProviders(<Logs />);

    expect(screen.queryByRole('button', { name: /truncate/i })).not.toBeInTheDocument();
  });

  it('shows the Truncate button for SuperAdmin users', () => {
    vi.mocked(useUserContext).mockReturnValue({
      user: { userTypeId: UserType.SuperAdmin },
    } as never);

    renderWithProviders(<Logs />);

    expect(screen.getByRole('button', { name: /truncate/i })).toBeInTheDocument();
  });

  it('renders the Filters toggle button', () => {
    renderWithProviders(<Logs />);

    expect(screen.getByRole('button', { name: /filters/i })).toBeInTheDocument();
  });

  it('disables the Filters toggle button when the page is not hydrated', () => {
    vi.mocked(useTableState).mockReturnValue({
      ...defaultTableState,
      isHydrated: false,
    } as never);

    renderWithProviders(<Logs />);

    expect(screen.getByRole('button', { name: /filters/i })).toBeDisabled();
  });

  it('decodes HTML entities in the message column after filters are applied', async () => {
    vi.mocked(useLogsData).mockReturnValue(
      createMockLogsQuery([
        {
          rows: [createMockLogEntry({ logId: 99, message: '&amp; &lt;b&gt;bold&lt;/b&gt;' })],
          totalCount: 1,
        },
      ]) as never,
    );

    const { user } = renderWithProviders(<Logs />);

    await user.click(screen.getByRole('button', { name: /apply filter/i }));

    await waitFor(() => {
      expect(screen.getByText('& <b>bold</b>')).toBeInTheDocument();
    });
  });
});
