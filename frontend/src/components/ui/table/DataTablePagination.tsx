import { type Table } from '@tanstack/react-table';
import { ChevronLeft, ChevronRight, ChevronUp, Check } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { twMerge } from 'tailwind-merge';

import { usePreline } from '@/hooks/usePreline';

interface DataTablePaginationProps<TData> {
  table: Table<TData>;
  pageSizeOptions?: number[];
  loading?: boolean;
}

const BUTTON_BASE_STYLE =
  'cursor-pointer w-full flex justify-center p-2 min-w-5 items-center gap-x-2 text-sm font-medium rounded-lg border border-transparent text-gray-800 hover:bg-gray-100 focus:outline-hidden focus:bg-gray-100 disabled:opacity-50 disabled:pointer-events-none dark:text-white dark:hover:bg-neutral-700 dark:focus:bg-neutral-700';

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
    if (i < total && i > 1) range.push(i);
  }
  range.push(total);

  for (const i of range) {
    if (l) {
      if (i - l === 2) rangeWithDots.push(l + 1);
      else if (i - l !== 1) rangeWithDots.push('...');
    }
    rangeWithDots.push(i);
    l = i;
  }

  return rangeWithDots;
}

export function DataTablePagination<TData>({
  table,
  pageSizeOptions = [5, 10, 20, 50],
  loading = false,
}: DataTablePaginationProps<TData>) {
  const { t } = useTranslation();

  const pagination = table.getState().pagination;
  const pageCount = table.getPageCount();

  usePreline();

  return (
    <div className="flex gap-3 items-center data-table-pagination">
      {/* Page size selector */}
      <div className="flex items-center gap-2 data-table-pagination-picker">
        <div className="hs-dropdown relative inline-flex [--placement:top-left]">
          <button
            id="hs-pagination-dropup"
            type="button"
            className={BUTTON_BASE_STYLE}
            aria-haspopup="menu"
            aria-expanded="false"
            aria-label={t('Select page size')}
          >
            {pagination.pageSize} / {t('Page')}
            <ChevronUp className="hs-dropdown-open:rotate-180 size-4 text-gray-500" />
          </button>

          <div
            className="hs-dropdown-menu transition-[opacity,margin] duration hs-dropdown-open:opacity-100 opacity-0 w-32 hidden z-60 bg-white shadow-md rounded-lg p-1 space-y-0.5 border border-gray-200 mb-2"
            role="menu"
            aria-orientation="vertical"
            aria-labelledby="hs-pagination-dropup"
          >
            {pageSizeOptions.map((pageSize) => (
              <button
                key={pageSize}
                type="button"
                onClick={() => table.setPageSize(pageSize)}
                className={twMerge(
                  BUTTON_BASE_STYLE,
                  pagination.pageSize === pageSize
                    ? 'bg-gray-100 text-gray-800 font-medium'
                    : 'text-gray-800 hover:bg-gray-100',
                )}
              >
                {pageSize}
                {pagination.pageSize === pageSize && <Check className="size-3.5 text-blue-600" />}
              </button>
            ))}
          </div>
        </div>
      </div>

      <div className="border-t sm:border-t-0 h-5 sm:border-s border-gray-200 dark:border-neutral-700"></div>

      {/* Page navigation */}
      <nav className="flex items-center gap-x-1 data-table-pagination-navigator">
        <button
          type="button"
          className={BUTTON_BASE_STYLE}
          onClick={() => table.previousPage()}
          disabled={!table.getCanPreviousPage() || loading}
          aria-label={t('Previous')}
        >
          <ChevronLeft className="h-5 w-5" />
        </button>

        <div className="flex items-center gap-x-1">
          {getPaginationItems(pagination.pageIndex, pageCount).map((page, index) => {
            if (page === '...') {
              return (
                <span
                  key={`dots-${index}`}
                  className="p-2 min-h-8 min-w-8 flex justify-center items-end text-gray-800"
                >
                  ...
                </span>
              );
            }
            const isCurrent = (page as number) === pagination.pageIndex + 1;
            return (
              <div key={page} className="flex w-[37px] justify-center">
                <button
                  type="button"
                  onClick={() => table.setPageIndex((page as number) - 1)}
                  disabled={loading}
                  className={twMerge(
                    BUTTON_BASE_STYLE,
                    isCurrent
                      ? 'bg-gray-200 text-gray-800 focus:bg-gray-300'
                      : 'text-gray-800 hover:bg-gray-100',
                  )}
                >
                  {page}
                </button>
              </div>
            );
          })}
        </div>

        <button
          type="button"
          className={BUTTON_BASE_STYLE}
          onClick={() => table.nextPage()}
          disabled={!table.getCanNextPage() || loading}
          aria-label={t('Next')}
        >
          <ChevronRight className="h-5 w-5" />
        </button>
      </nav>
    </div>
  );
}
