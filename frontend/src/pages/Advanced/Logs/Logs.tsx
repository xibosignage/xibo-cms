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

import { isAxiosError } from 'axios';
import { Scissors } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';

import type { ModalType } from './LogsConfig';
import { getLogsColumns, INITIAL_FILTER_STATE, type LogsFilterInput } from './LogsConfig';
import TruncateLogsModal from './components/TruncateLogsModal';
import { useLogsData } from './hooks/useLogsData';
import { useLogsFilterOptions } from './hooks/useLogsFilterOptions';

import Button from '@/components/ui/Button';
import FilterButton from '@/components/ui/FilterButton';
import FilterInputs from '@/components/ui/FilterInputs';
import InfoBanner from '@/components/ui/InfoBanner';
import { notify } from '@/components/ui/Notification';
import QueryStatusBanner from '@/components/ui/QueryStatusBanner';
import TabNav from '@/components/ui/TabNav';
import { DataTable } from '@/components/ui/table/DataTable';
import { AUTO_SUBMIT_FORMS } from '@/constants/autoSubmitForms';
import { useUserContext } from '@/context/UserContext';
import { useAutoSubmit } from '@/hooks/useAutoSubmit';
import { useFilteredTabs } from '@/hooks/useFilteredTabs';
import { useTableState } from '@/hooks/useTableState';
import { sortRows } from '@/pages/Reporting/Reports/shared/utils/sortRows';
import { truncateLogs } from '@/services/logApi';
import { UserType } from '@/types/user';
import { formatDateTime } from '@/utils/date';
import { countActiveFilters } from '@/utils/filters';

export default function Logs() {
  const { t } = useTranslation();
  const { user } = useUserContext();
  const { guard } = useAutoSubmit();

  const {
    pagination,
    setPagination,
    sorting,
    setSorting,
    columnVisibility,
    setColumnVisibility,
    globalFilter,
    setGlobalFilter,
    filterInputs,
    setFilterInputs,
    isHydrated,
  } = useTableState<LogsFilterInput>('logs_page', {
    pagination: { pageIndex: 0, pageSize: 10 },
    sorting: [{ id: 'logId', desc: true }],
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
    viewMode: 'table',
    globalFilter: '',
    filterInputs: INITIAL_FILTER_STATE,
  });

  const [submittedFilter, setSubmittedFilter] = useState<LogsFilterInput | null>(null);
  // Pinned once per Apply/Refresh so a Load More call extends the same time window instead of
  // re-anchoring to "now" — see useLogsData for why that matters.
  const [anchorTime, setAnchorTime] = useState<string | null>(null);
  const [openFilter, setOpenFilter] = useState(true);
  const [activeModal, setActiveModal] = useState<ModalType>(null);

  const closeModal = () => setActiveModal(null);

  const {
    data,
    isFetching,
    isFetchingNextPage,
    hasNextPage,
    fetchNextPage,
    isError,
    isPaused,
    error: queryError,
  } = useLogsData({
    advancedFilters: submittedFilter ?? INITIAL_FILTER_STATE,
    anchorTime,
    enabled: isHydrated && submittedFilter !== null,
  });

  const fetchedRows = data?.pages.flatMap((page) => page.rows) ?? [];
  const totalCount = data?.pages[0]?.totalCount ?? 0;
  const sortedRows = sortRows(fetchedRows, sorting);
  const pageStart = pagination.pageIndex * pagination.pageSize;
  const logList = sortedRows.slice(pageStart, pageStart + pagination.pageSize);
  const pageCount = Math.max(1, Math.ceil(fetchedRows.length / pagination.pageSize));
  const error = isError && queryError instanceof Error ? queryError.message : '';

  const handleApply = () => {
    setPagination((prev) => ({ ...prev, pageIndex: 0 }));
    setAnchorTime(formatDateTime(new Date()));
    setSubmittedFilter({ ...filterInputs });
    setOpenFilter(false);
  };

  const handleRefresh = () => {
    if (submittedFilter === null) {
      return;
    }
    setPagination((prev) => ({ ...prev, pageIndex: 0 }));
    setAnchorTime(formatDateTime(new Date()));
  };

  const handleAutoSubmitTruncate = async () => {
    try {
      await truncateLogs();
      handleRefresh();
      notify.success(t('Logs truncated'));
    } catch (err) {
      const message =
        isAxiosError(err) && err.response?.data?.message
          ? err.response.data.message
          : t('Failed to truncate logs. Please try again.');
      notify.error(message);
    }
  };

  const handleTruncateClick = () => {
    guard(AUTO_SUBMIT_FORMS.logTruncate, handleAutoSubmitTruncate, () =>
      setActiveModal('truncate'),
    );
  };

  const handleResetFilters = () => {
    setFilterInputs(INITIAL_FILTER_STATE);
    setSubmittedFilter(null);
    setAnchorTime(null);
    setPagination((prev) => ({ ...prev, pageIndex: 0 }));
  };

  const columns = getLogsColumns(t);
  const { filterOptions } = useLogsFilterOptions(t);
  const advancedTabs = useFilteredTabs('advanced');
  const isSuperAdmin = user?.userTypeId === UserType.SuperAdmin;

  const activeFilterCount = countActiveFilters(filterInputs, INITIAL_FILTER_STATE, filterOptions);

  return (
    <section className="flex h-full w-full min-h-0 relative outline-none overflow-hidden">
      <div className="flex-1 flex flex-col min-h-0 min-w-0 px-5 pb-5">
        <div className="flex flex-row justify-between py-4 items-center gap-4">
          <TabNav activeTab="Log" navigation={advancedTabs} />
        </div>

        <div className="flex flex-row justify-end gap-2">
          {isSuperAdmin && (
            <Button leftIcon={Scissors} variant="primary" onClick={handleTruncateClick}>
              {t('Truncate')}
            </Button>
          )}
          <FilterButton
            isOpen={openFilter}
            onToggle={() => setOpenFilter((prev) => !prev)}
            activeCount={activeFilterCount}
            disabled={!isHydrated}
          />
        </div>

        <FilterInputs
          onChange={(name, value) => {
            setFilterInputs((prev) => ({ ...prev, [name]: value }));
          }}
          isOpen={openFilter}
          values={filterInputs}
          options={filterOptions}
          onReset={handleResetFilters}
          onApply={handleApply}
        />

        <QueryStatusBanner error={error} isPaused={isPaused} />

        {hasNextPage && (
          <InfoBanner type="warning" className="w-full! mt-4 items-center">
            <div className="flex flex-wrap items-center justify-between gap-3">
              <span>
                {t('Showing {{loaded}} of {{total}} matching results.', {
                  loaded: fetchedRows.length,
                  total: totalCount,
                })}
              </span>
              <Button
                variant="tertiary"
                onClick={() => fetchNextPage()}
                disabled={isFetchingNextPage}
              >
                {isFetchingNextPage ? t('Loading...') : t('Load More')}
              </Button>
            </div>
          </InfoBanner>
        )}

        <div className="flex-1 min-h-0 flex flex-col">
          {!isHydrated ? (
            <div className="flex-1 flex items-center justify-center bg-gray-50 animate-pulse rounded-lg border border-gray-200">
              <span className="text-gray-400 font-medium">{t('Loading logs...')}</span>
            </div>
          ) : submittedFilter === null ? (
            <div className="flex-1 flex items-center justify-center mt-4 bg-gray-50 rounded-lg border border-gray-200 border-dashed">
              <p className="text-gray-400 text-sm">
                {t('Set your filters above and click Apply Filter to view logs.')}
              </p>
            </div>
          ) : (
            <DataTable
              columns={columns}
              data={logList}
              exportRows={sortedRows}
              pageCount={pageCount}
              rowCount={fetchedRows.length}
              pagination={pagination}
              pageSizeOptions={[5, 10, 20, 50, 100]}
              onPaginationChange={setPagination}
              sorting={sorting}
              onSortingChange={setSorting}
              globalFilter={globalFilter}
              onGlobalFilterChange={setGlobalFilter}
              loading={isFetching && !isFetchingNextPage}
              rowSelection={{}}
              onRowSelectionChange={() => {}}
              enableSelection={false}
              columnPinning={{ left: [], right: [] }}
              columnVisibility={columnVisibility}
              onColumnVisibilityChange={setColumnVisibility}
              bulkActions={[]}
              viewMode={null}
              onRefresh={handleRefresh}
              exportFileName={t('Log')}
            />
          )}
        </div>
      </div>

      {activeModal === 'truncate' && (
        <TruncateLogsModal onClose={closeModal} onSuccess={handleRefresh} />
      )}
    </section>
  );
}
