import {
  useReactTable,
  getCoreRowModel,
  flexRender,
  type ColumnDef,
  type SortingState,
  type PaginationState,
  type OnChangeFn,
  type RowSelectionState,
  type ColumnPinningState,
  type Column,
} from '@tanstack/react-table';
import {
  ArrowUp,
  ArrowDown,
  ChevronLeft,
  ChevronRight,
  ChevronsLeft,
  ChevronsRight,
  Search,
  Loader2,
  MoreHorizontal,
} from 'lucide-react';
import { type CSSProperties, useRef, useEffect, useCallback, useState } from 'react';
import { useTranslation } from 'react-i18next';

const getCommonPinningStyles = <TData, TValue>(
  column: Column<TData, TValue>,
  isScrolledLeft: boolean,
  isScrolledRight: boolean,
): CSSProperties => {
  const isPinned = column.getIsPinned();
  const isLastLeft = isPinned === 'left' && column.getIsLastColumn('left');
  const isFirstRight = isPinned === 'right' && column.getIsFirstColumn('right');

  const shadowLeft = 'inset -10px 0 8px -8px rgba(0, 0, 0, 0.15)';
  const shadowRight = 'inset 10px 0 8px -8px rgba(0, 0, 0, 0.15)';

  return {
    boxShadow:
      isLastLeft && isScrolledLeft
        ? shadowLeft
        : isFirstRight && isScrolledRight
          ? shadowRight
          : undefined,
    left: isPinned === 'left' ? `${column.getStart('left')}px` : undefined,
    right: isPinned === 'right' ? `${column.getAfter('right')}px` : undefined,
    opacity: isPinned ? 0.97 : 1,
    position: isPinned ? 'sticky' : 'relative',
    width: column.getSize(),
    zIndex: isPinned ? 1 : 0,
  };
};

function getPaginationItems(pageIndex: number, pageCount: number) {
  const current = pageIndex + 1;
  const total = pageCount;
  const delta = 1;

  if (total <= 7) {
    return Array.from({ length: total }, (_, i) => i + 1);
  }

  const range = [];
  const rangeWithDots = [];
  let l;

  range.push(1);
  for (let i = current - delta; i <= current + delta; i++) {
    if (i < total && i > 1) {
      range.push(i);
    }
  }
  range.push(total);

  for (const i of range) {
    if (l) {
      if (i - l === 2) {
        rangeWithDots.push(l + 1);
      } else if (i - l !== 1) {
        rangeWithDots.push('...');
      }
    }
    rangeWithDots.push(i);
    l = i;
  }

  return rangeWithDots;
}

interface DataTableProps<TData, TValue> {
  columns: ColumnDef<TData, TValue>[];
  data: TData[];
  pageCount: number;
  pagination: PaginationState;
  onPaginationChange: OnChangeFn<PaginationState>;
  sorting: SortingState;
  onSortingChange: OnChangeFn<SortingState>;
  globalFilter: string;
  onGlobalFilterChange: (value: string) => void;
  rowSelection: RowSelectionState;
  onRowSelectionChange: OnChangeFn<RowSelectionState>;
  pageSizeOptions?: number[];
  loading?: boolean;
  selectionActions?: React.ReactNode;
  columnPinning?: ColumnPinningState;
}

export function DataTable<TData, TValue>({
  columns,
  data,
  pageCount,
  pagination,
  onPaginationChange,
  sorting,
  onSortingChange,
  globalFilter,
  onGlobalFilterChange,
  rowSelection,
  onRowSelectionChange,
  pageSizeOptions = [5, 10, 20, 50],
  loading = false,
  selectionActions,
  columnPinning = { left: [], right: [] },
}: DataTableProps<TData, TValue>) {
  const { t } = useTranslation();

  const [scrollState, setScrollState] = useState({ left: false, right: false });
  const scrollContainerRef = useRef<HTMLDivElement>(null);
  const rafRef = useRef<number | null>(null);

  const table = useReactTable({
    data,
    columns,
    pageCount,
    state: {
      sorting,
      globalFilter,
      pagination,
      rowSelection,
      columnPinning,
    },
    enableRowSelection: true,
    enableColumnPinning: true,
    onRowSelectionChange,
    onPaginationChange,
    onSortingChange,
    manualPagination: true,
    manualSorting: true,
    manualFiltering: true,
    getCoreRowModel: getCoreRowModel(),
  });

  const selectedCount = Object.keys(rowSelection).length;

  const checkForScroll = useCallback(() => {
    const el = scrollContainerRef.current;
    if (!el) return;

    if (rafRef.current) return;

    rafRef.current = requestAnimationFrame(() => {
      const { scrollLeft, scrollWidth, clientWidth } = el;
      const canScroll = scrollWidth > clientWidth;

      let newLeft = false;
      let newRight = false;

      if (canScroll) {
        newLeft = scrollLeft > 0;
        const isAtEnd = Math.abs(scrollWidth - clientWidth - scrollLeft) < 2;
        newRight = !isAtEnd;
      }

      setScrollState((prev) => {
        if (prev.left === newLeft && prev.right === newRight) return prev;
        return { left: newLeft, right: newRight };
      });

      rafRef.current = null;
    });
  }, []);

  useEffect(() => {
    const el = scrollContainerRef.current;
    if (!el) return;

    const observer = new ResizeObserver(checkForScroll);
    observer.observe(el);

    checkForScroll();

    return () => {
      observer.disconnect();
      if (rafRef.current) cancelAnimationFrame(rafRef.current);
    };
  }, [checkForScroll, data, columns]);

  return (
    <div className="space-y-4">
      {selectedCount > 0 && (
        <div className="bg-blue-50 border border-blue-200 text-blue-700 px-4 py-3 rounded-md flex justify-between items-center animate-in fade-in slide-in-from-top-2">
          <span className="font-medium text-sm">
            {selectedCount} {selectedCount === 1 ? t('item') : t('items')} {t('selected')}
          </span>
          <div className="flex gap-2">{selectionActions}</div>
        </div>
      )}

      <div className="relative w-full max-w-sm">
        <div className="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
          <Search className="w-4 h-4 text-gray-400" />
        </div>
        <input
          value={globalFilter ?? ''}
          onChange={(e) => onGlobalFilterChange(e.target.value)}
          placeholder={t('Filter...')}
          className="pl-10 p-2 border border-gray-300 rounded-md w-full shadow-sm focus:ring-2 focus:ring-blue-500 focus:outline-none"
        />
      </div>

      <div className="relative rounded-md border border-gray-200 overflow-hidden">
        {loading && data.length > 0 && (
          <div className="absolute inset-0 bg-white/50 dark:bg-black/50 z-10 flex items-center justify-center">
            <Loader2 className="w-8 h-8 animate-spin text-blue-600" />
          </div>
        )}

        <div className="overflow-x-auto" ref={scrollContainerRef} onScroll={checkForScroll}>
          <table
            className="min-w-full divide-y divide-gray-200 dark:divide-neutral-700"
            style={{ width: table.getTotalSize() }}
          >
            <thead className="bg-gray-50 dark:bg-neutral-800">
              {table.getHeaderGroups().map((headerGroup) => (
                <tr key={headerGroup.id}>
                  {headerGroup.headers.map((header) => (
                    <th
                      key={header.id}
                      className="px-6 py-3 text-start text-xs font-medium text-gray-500 uppercase dark:text-neutral-500 cursor-pointer hover:bg-gray-100 dark:hover:bg-neutral-700 bg-gray-50 dark:bg-neutral-800 transition-shadow duration-200"
                      style={getCommonPinningStyles(
                        header.column,
                        scrollState.left,
                        scrollState.right,
                      )}
                      onClick={header.column.getToggleSortingHandler()}
                    >
                      <div className="flex items-center gap-2">
                        {flexRender(header.column.columnDef.header, header.getContext())}
                        {{
                          asc: <ArrowUp className="h-4 w-4" />,
                          desc: <ArrowDown className="h-4 w-4" />,
                        }[header.column.getIsSorted() as string] ?? null}
                      </div>
                    </th>
                  ))}
                </tr>
              ))}
            </thead>
            <tbody className="divide-y divide-gray-200 bg-white dark:divide-neutral-700 dark:bg-neutral-900">
              {table.getRowModel().rows.length > 0 ? (
                table.getRowModel().rows.map((row) => (
                  <tr
                    key={row.id}
                    className="hover:bg-gray-50 dark:hover:bg-neutral-800 transition-colors group"
                  >
                    {row.getVisibleCells().map((cell) => (
                      <td
                        key={cell.id}
                        className="px-6 py-4 whitespace-nowrap text-sm text-gray-800 dark:text-neutral-200 bg-white dark:bg-neutral-900 group-hover:bg-gray-50 dark:group-hover:bg-neutral-800 transition-colors transition-shadow duration-200"
                        style={getCommonPinningStyles(
                          cell.column,
                          scrollState.left,
                          scrollState.right,
                        )}
                      >
                        {flexRender(cell.column.columnDef.cell, cell.getContext())}
                      </td>
                    ))}
                  </tr>
                ))
              ) : (
                <tr>
                  <td colSpan={columns.length} className="h-24 text-center text-gray-500">
                    {loading ? (
                      <div className="flex items-center justify-center gap-2">
                        <Loader2 className="w-4 h-4 animate-spin" />
                        {t('Loading data...')}
                      </div>
                    ) : (
                      t('No results.')
                    )}
                  </td>
                </tr>
              )}
            </tbody>
          </table>
        </div>
      </div>

      <div className="flex flex-col md:flex-row items-center justify-between gap-4 py-2">
        <div className="flex items-center gap-2 text-sm text-gray-600">
          <span>{t('Rows per page')}:</span>
          <select
            value={table.getState().pagination.pageSize}
            onChange={(e) => table.setPageSize(Number(e.target.value))}
            className="h-8 w-16 rounded border border-gray-300 bg-white px-2 py-1 text-xs focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
          >
            {pageSizeOptions.map((pageSize) => (
              <option key={pageSize} value={pageSize}>
                {pageSize}
              </option>
            ))}
          </select>
        </div>

        <div className="flex items-center gap-1">
          <button
            className="hidden p-2 border rounded hover:bg-gray-100 disabled:opacity-50 sm:block"
            onClick={() => table.setPageIndex(0)}
            disabled={!table.getCanPreviousPage() || loading}
            aria-label={t('First Page')}
          >
            <ChevronsLeft className="h-4 w-4" />
          </button>
          <button
            className="p-2 border rounded hover:bg-gray-100 disabled:opacity-50"
            onClick={() => table.previousPage()}
            disabled={!table.getCanPreviousPage() || loading}
            aria-label={t('Previous Page')}
          >
            <ChevronLeft className="h-4 w-4" />
          </button>

          {getPaginationItems(pagination.pageIndex, table.getPageCount()).map((page, index) => {
            if (page === '...') {
              return (
                <span key={`dots-${index}`} className="px-2 text-gray-400">
                  <MoreHorizontal className="h-4 w-4" />
                </span>
              );
            }

            const isCurrent = (page as number) === pagination.pageIndex + 1;

            return (
              <button
                key={page}
                onClick={() => table.setPageIndex((page as number) - 1)}
                disabled={loading}
                className={`
                   h-8 w-8 rounded border text-sm font-medium transition-colors
                   ${
                     isCurrent
                       ? 'bg-blue-600 text-white border-blue-600 hover:bg-blue-700'
                       : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50'
                   }
                 `}
              >
                {page}
              </button>
            );
          })}

          <button
            className="p-2 border rounded hover:bg-gray-100 disabled:opacity-50"
            onClick={() => table.nextPage()}
            disabled={!table.getCanNextPage() || loading}
            aria-label={t('Next Page')}
          >
            <ChevronRight className="h-4 w-4" />
          </button>
          <button
            className="hidden p-2 border rounded hover:bg-gray-100 disabled:opacity-50 sm:block"
            onClick={() => table.setPageIndex(table.getPageCount() - 1)}
            disabled={!table.getCanNextPage() || loading}
            aria-label={t('Last Page')}
          >
            <ChevronsRight className="h-4 w-4" />
          </button>
        </div>
      </div>
    </div>
  );
}
