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

import { useQueryClient } from '@tanstack/react-query';
import { Filter, FilterX, Scissors } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';

import type { ModalType } from './LogsConfig';
import { getLogsColumns, INITIAL_FILTER_STATE, type LogsFilterInput } from './LogsConfig';
import TruncateLogsModal from './components/TruncateLogsModal';
import { useLogsData } from './hooks/useLogsData';
import { useLogsFilterOptions } from './hooks/useLogsFilterOptions';

import Button from '@/components/ui/Button';
import FilterInputs from '@/components/ui/FilterInputs';
import TabNav from '@/components/ui/TabNav';
import { DataTable } from '@/components/ui/table/DataTable';
import { useUserContext } from '@/context/UserContext';
import { useFilteredTabs } from '@/hooks/useFilteredTabs';
import { useTableState } from '@/hooks/useTableState';
import { UserType } from '@/types/user';

export default function Logs() {
  const { t } = useTranslation();
  const queryClient = useQueryClient();
  const { user } = useUserContext();

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
  const [openFilter, setOpenFilter] = useState(true);
  const [activeModal, setActiveModal] = useState<ModalType>(null);

  const closeModal = () => setActiveModal(null);

  const {
    data: queryData,
    isFetching,
    isError,
    error: queryError,
  } = useLogsData({
    pagination,
    sorting,
    advancedFilters: submittedFilter ?? INITIAL_FILTER_STATE,
    enabled: isHydrated && submittedFilter !== null,
  });

  const logList = queryData?.rows ?? [];
  const pageCount = Math.ceil((queryData?.totalCount || 0) / pagination.pageSize);
  const error = isError && queryError instanceof Error ? queryError.message : '';

  const handleApply = () => {
    setPagination((prev) => ({ ...prev, pageIndex: 0 }));
    setSubmittedFilter({ ...filterInputs });
    setOpenFilter(false);
  };

  const handleRefresh = () => {
    if (submittedFilter !== null) {
      queryClient.invalidateQueries({ queryKey: ['logs'] });
    }
  };

  const handleResetFilters = () => {
    setFilterInputs(INITIAL_FILTER_STATE);
    setSubmittedFilter(null);
    setPagination((prev) => ({ ...prev, pageIndex: 0 }));
  };

  const columns = getLogsColumns(t);
  const { filterOptions } = useLogsFilterOptions(t);
  const advancedTabs = useFilteredTabs('advanced');
  const isSuperAdmin = user?.userTypeId === UserType.SuperAdmin;

  return (
    <section className="flex h-full w-full min-h-0 relative outline-none overflow-hidden">
      <div className="flex-1 flex flex-col min-h-0 min-w-0 px-5 pb-5">
        <div className="flex flex-row justify-between py-4 items-center gap-4">
          <TabNav activeTab="Log" navigation={advancedTabs} />
        </div>

        <div className="flex flex-row justify-end gap-2">
          {isSuperAdmin && (
            <Button
              leftIcon={Scissors}
              variant="primary"
              onClick={() => setActiveModal('truncate')}
            >
              {t('Truncate')}
            </Button>
          )}
          <Button
            leftIcon={!openFilter ? Filter : FilterX}
            variant="secondary"
            disabled={!isHydrated}
            onClick={() => setOpenFilter((prev) => !prev)}
            removeTextOnMobile
          >
            {t('Filters')}
          </Button>
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

        {error && (
          <div className="bg-red-50 border border-red-200 text-red-800 p-4" role="alert">
            {error}
          </div>
        )}

        <div className="min-h-0 flex flex-col">
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
              pageCount={pageCount}
              rowCount={queryData?.totalCount || 0}
              pagination={pagination}
              onPaginationChange={setPagination}
              sorting={sorting}
              onSortingChange={setSorting}
              globalFilter={globalFilter}
              onGlobalFilterChange={setGlobalFilter}
              loading={isFetching}
              rowSelection={{}}
              onRowSelectionChange={() => {}}
              enableSelection={false}
              columnPinning={{ left: [], right: [] }}
              columnVisibility={columnVisibility}
              onColumnVisibilityChange={setColumnVisibility}
              bulkActions={[]}
              viewMode={null}
              onRefresh={handleRefresh}
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
