import { render, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { MemoryRouter } from 'react-router-dom';
import { describe, it, expect, vi, beforeEach } from 'vitest';

import { DataTablePagination } from '../DataTablePagination';

// Mock translation
vi.mock('react-i18next', () => ({
  useTranslation: () => ({ t: (str: string) => str }),
}));

// Mock usePreline so it doesn't try to run DOM animations in our headless test
vi.mock('@/hooks/usePreline', () => ({
  usePreline: vi.fn(),
}));

describe('DataTablePagination', () => {
  const mockPreviousPage = vi.fn();
  const mockNextPage = vi.fn();
  const mockSetPageIndex = vi.fn();
  const mockSetPageSize = vi.fn();

  // Helper to generate a fake TanStack table object along with the pagination
  // state and pageCount that the component now requires as explicit props.
  const createMockTable = (pageIndex = 0, pageCount = 5, canPrev = false, canNext = true) => {
    const pagination = { pageIndex, pageSize: 10 };

    const table = {
      getCanPreviousPage: () => canPrev,
      getCanNextPage: () => canNext,
      previousPage: mockPreviousPage,
      nextPage: mockNextPage,
      setPageIndex: mockSetPageIndex,
      setPageSize: mockSetPageSize,
      getPageCount: () => pageCount,
      getState: () => ({ pagination }),
      // eslint-disable-next-line @typescript-eslint/no-explicit-any
    } as any;
    return { table, pagination, pageCount };
  };

  // A helper function to wrap the component in the MemoryRouter
  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  const renderWithRouter = (ui: any) => {
    return render(<MemoryRouter>{ui}</MemoryRouter>);
  };

  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('The Previous button is greyed out on the first page', () => {
    const { table, pagination, pageCount } = createMockTable(0, 5, false, true);
    renderWithRouter(
      <DataTablePagination table={table} pagination={pagination} pageCount={pageCount} />,
    );

    const prevBtn = screen.getByRole('button', { name: /Previous/i });
    expect(prevBtn).toBeDisabled();
  });

  it('The Next button is greyed out on the last page', () => {
    const { table, pagination, pageCount } = createMockTable(4, 5, true, false);
    renderWithRouter(
      <DataTablePagination table={table} pagination={pagination} pageCount={pageCount} />,
    );

    const nextBtn = screen.getByRole('button', { name: /Next/i });
    expect(nextBtn).toBeDisabled();
  });

  it('Clicking Previous goes to the previous page', async () => {
    const user = userEvent.setup();
    const { table, pagination, pageCount } = createMockTable(2, 5, true, true);
    renderWithRouter(
      <DataTablePagination table={table} pagination={pagination} pageCount={pageCount} />,
    );

    const prevBtn = screen.getByRole('button', { name: /Previous/i });
    await user.click(prevBtn);
    expect(mockPreviousPage).toHaveBeenCalledTimes(1);
  });

  it('Clicking Next goes to the next page', async () => {
    const user = userEvent.setup();
    const { table, pagination, pageCount } = createMockTable(2, 5, true, true);
    renderWithRouter(
      <DataTablePagination table={table} pagination={pagination} pageCount={pageCount} />,
    );

    const nextBtn = screen.getByRole('button', { name: /Next/i });
    await user.click(nextBtn);
    expect(mockNextPage).toHaveBeenCalledTimes(1);
  });

  it('Clicking a page number goes directly to that page', async () => {
    const user = userEvent.setup();
    const { table, pagination, pageCount } = createMockTable(0, 5, false, true);
    renderWithRouter(
      <DataTablePagination table={table} pagination={pagination} pageCount={pageCount} />,
    );

    const nav = screen.getByRole('navigation');
    const page3Btn = within(nav).getByRole('button', { name: '3' });
    await user.click(page3Btn);

    expect(mockSetPageIndex).toHaveBeenCalledWith(2);
  });

  it('The page size selector shows available options and changes items per page', async () => {
    const user = userEvent.setup();
    const { table, pagination, pageCount } = createMockTable(0, 5, false, true);
    renderWithRouter(
      <DataTablePagination table={table} pagination={pagination} pageCount={pageCount} />,
    );

    const dropdownTrigger = screen.getByRole('button', { name: /Select page size/i });
    await user.click(dropdownTrigger);

    // The menu opens, so we click the button that has '20'
    // (We look specifically inside the menu to avoid clicking page 20 if it existed)
    const menu = screen.getByRole('menu');
    const option20 = within(menu).getByRole('button', { name: '20' });

    await user.click(option20);
    expect(mockSetPageSize).toHaveBeenCalledWith(20);
  });

  it('When there are more than 7 pages, dots (...) appear to skip the middle pages', () => {
    const { table, pagination, pageCount } = createMockTable(0, 10, false, true);
    renderWithRouter(
      <DataTablePagination table={table} pagination={pagination} pageCount={pageCount} />,
    );

    expect(screen.getByText('...')).toBeInTheDocument();
  });

  it('When there are 7 or fewer pages, all page numbers are shown', () => {
    const { table, pagination, pageCount } = createMockTable(0, 7, false, true);
    renderWithRouter(
      <DataTablePagination table={table} pagination={pagination} pageCount={pageCount} />,
    );

    expect(screen.queryByText('...')).not.toBeInTheDocument();

    const nav = screen.getByRole('navigation');
    for (let i = 1; i <= 7; i++) {
      expect(within(nav).getByRole('button', { name: i.toString() })).toBeInTheDocument();
    }
  });

  it('All buttons are greyed out while data is loading', () => {
    const { table, pagination, pageCount } = createMockTable(2, 5, true, true);
    renderWithRouter(
      <DataTablePagination
        table={table}
        pagination={pagination}
        pageCount={pageCount}
        loading={true}
      />,
    );

    expect(screen.getByRole('button', { name: /Previous/i })).toBeDisabled();
    expect(screen.getByRole('button', { name: /Next/i })).toBeDisabled();

    const nav = screen.getByRole('navigation');
    const pageButtons = within(nav).getAllByRole('button', { name: /^[0-9]+$/ });
    pageButtons.forEach((btn) => {
      expect(btn).toBeDisabled();
    });
  });
});
