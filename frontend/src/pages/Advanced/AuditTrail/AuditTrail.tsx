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
import type { RowSelectionState } from '@tanstack/react-table';
import { Download } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';

import type { ModalType } from './AuditTrailConfig';
import {
  getAuditTrailColumns,
  INITIAL_FILTER_STATE,
  type AuditTrailFilterInput,
} from './AuditTrailConfig';
import AuditTrailExportModal from './components/AuditTrailExportModal';
import { useAuditTrailData } from './hooks/useAuditTrailData';
import { useAuditTrailFilterOptions } from './hooks/useAuditTrailFilterOptions';

import Button from '@/components/ui/Button';
import FilterButton from '@/components/ui/FilterButton';
import FilterInputs from '@/components/ui/FilterInputs';
import QueryStatusBanner from '@/components/ui/QueryStatusBanner';
import TabNav from '@/components/ui/TabNav';
import { DataTable } from '@/components/ui/table/DataTable';
import { useDateFormatter } from '@/hooks/useDateFormatter';
import { useFilteredTabs } from '@/hooks/useFilteredTabs';
import { useTableState } from '@/hooks/useTableState';
import { countActiveFilters } from '@/utils/filters';

export default function AuditTrail() {
  const { t } = useTranslation();
  const { formatDateTime } = useDateFormatter();
  const queryClient = useQueryClient();

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
  } = useTableState<AuditTrailFilterInput>('audit_trail_page', {
    pagination: { pageIndex: 0, pageSize: 10 },
    sorting: [{ id: 'logId', desc: true }],
    columnVisibility: {
      logId: true,
      logDate: true,
      userName: true,
      entity: true,
      entityId: true,
      ipAddress: true,
      message: true,
      objectAfter: true,
    },
    viewMode: 'table',
    globalFilter: '',
    filterInputs: INITIAL_FILTER_STATE,
  });

  const [rowSelection, setRowSelection] = useState<RowSelectionState>({});
  const [openFilter, setOpenFilter] = useState(false);
  const [activeModal, setActiveModal] = useState<ModalType | null>(null);

  const openModal = (name: ModalType) => setActiveModal(name);
  const closeModal = () => setActiveModal(null);

  const {
    data: queryData,
    isFetching,
    isError,
    isPaused,
    error: queryError,
  } = useAuditTrailData({
    pagination,
    sorting,
    advancedFilters: filterInputs,
    enabled: isHydrated,
  });

  const auditLogList = queryData?.rows ?? [];
  const pageCount = Math.ceil((queryData?.totalCount || 0) / pagination.pageSize);
  const error = isError && queryError instanceof Error ? queryError.message : '';

  const handleRefresh = () => {
    queryClient.invalidateQueries({ queryKey: ['auditTrail'] });
  };

  const handleResetFilters = () => {
    setFilterInputs(INITIAL_FILTER_STATE);
    setPagination((prev) => ({ ...prev, pageIndex: 0 }));
  };

  const columns = getAuditTrailColumns(t, formatDateTime);
  const { filterOptions } = useAuditTrailFilterOptions(t);
  const advancedTabs = useFilteredTabs('advanced');

  const activeFilterCount = countActiveFilters(filterInputs, INITIAL_FILTER_STATE, filterOptions);

  return (
    <section className="flex h-full w-full min-h-0 relative outline-none overflow-hidden">
      <div className="flex-1 flex flex-col min-h-0 min-w-0 px-5 pb-5">
        <div className="flex flex-row justify-between py-4 items-center gap-4">
          <TabNav activeTab="Audit Trail" navigation={advancedTabs} />
        </div>

        <div className="flex flex-row justify-end gap-2">
          <Button leftIcon={Download} variant="secondary" onClick={() => openModal('export')}>
            {t('Export')}
          </Button>
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
            setPagination((prev) => ({ ...prev, pageIndex: 0 }));
          }}
          isOpen={openFilter}
          values={filterInputs}
          options={filterOptions}
          onReset={handleResetFilters}
        />

        <QueryStatusBanner error={error} isPaused={isPaused} />

        <div className="min-h-0 flex flex-col">
          {!isHydrated ? (
            <div className="flex-1 flex items-center justify-center bg-gray-50 animate-pulse rounded-lg border border-gray-200">
              <span className="text-gray-400 font-medium">{t('Loading audit trail...')}</span>
            </div>
          ) : (
            <DataTable
              columns={columns}
              data={auditLogList}
              pageCount={pageCount}
              rowCount={queryData?.totalCount || 0}
              pagination={pagination}
              onPaginationChange={setPagination}
              sorting={sorting}
              onSortingChange={setSorting}
              globalFilter={globalFilter}
              onGlobalFilterChange={setGlobalFilter}
              loading={isFetching}
              rowSelection={rowSelection}
              onRowSelectionChange={setRowSelection}
              enableSelection={false}
              columnPinning={{ left: [], right: [] }}
              columnVisibility={columnVisibility}
              onColumnVisibilityChange={setColumnVisibility}
              bulkActions={[]}
              viewMode={null}
              onRefresh={handleRefresh}
              exportFileName={t('Audit Trail')}
            />
          )}
        </div>
      </div>

      {activeModal === 'export' && <AuditTrailExportModal onClose={closeModal} />}
    </section>
  );
}
