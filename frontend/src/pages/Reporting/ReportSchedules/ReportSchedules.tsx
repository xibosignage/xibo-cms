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
import { isAxiosError } from 'axios';
import { Filter, FilterX, Search } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { useNavigate } from 'react-router-dom';

import { savedReportQueryKeys } from '../SavedReports/hooks/useSavedReportData';

import {
  getBulkActions,
  getReportScheduleColumns,
  INITIAL_FILTER_STATE,
} from './ReportSchedulesConfig';
import type { ModalType, ReportScheduleFilterInput } from './ReportSchedulesConfig';
import { ReportScheduleModals } from './components/ReportScheduleModals';
import { useReportScheduleActions } from './hooks/useReportScheduleActions';
import { reportScheduleQueryKeys, useReportScheduleData } from './hooks/useReportScheduleData';
import { useReportScheduleFilterOptions } from './hooks/useReportScheduleFilterOptions';

import Button from '@/components/ui/Button';
import FilterInputs from '@/components/ui/FilterInputs';
import { notify } from '@/components/ui/Notification';
import TabNav from '@/components/ui/TabNav';
import { DataTable } from '@/components/ui/table/DataTable';
import { REPORT_META } from '@/config/reportRoutes';
import { useFilteredTabs } from '@/hooks/useFilteredTabs';
import { useTableState } from '@/hooks/useTableState';
import { deleteAllSavedReportsForSchedule } from '@/services/reportScheduleApi';
import type { ReportSchedule } from '@/types/reportSchedule';

export default function ReportSchedules() {
  const { t } = useTranslation();
  const navigate = useNavigate();
  const queryClient = useQueryClient();

  const {
    pagination,
    setPagination,
    sorting,
    setSorting,
    columnVisibility,
    setColumnVisibility,
    globalFilter,
    debouncedFilter,
    setGlobalFilter,
    filterInputs,
    setFilterInputs,
    isHydrated,
  } = useTableState<ReportScheduleFilterInput>('report_schedules_page', {
    pagination: { pageIndex: 0, pageSize: 10 },
    sorting: [],
    columnVisibility: {},
    viewMode: 'table',
    globalFilter: '',
    filterInputs: INITIAL_FILTER_STATE,
  });

  const [rowSelection, setRowSelection] = useState<RowSelectionState>({});
  const [selectionCache, setSelectionCache] = useState<Record<string, ReportSchedule>>({});
  const [openFilter, setOpenFilter] = useState(false);

  const [activeModal, setActiveModal] = useState<ModalType | null>(null);
  const [itemsToDelete, setItemsToDelete] = useState<ReportSchedule[]>([]);
  const [selectedSchedule, setSelectedSchedule] = useState<ReportSchedule | null>(null);
  const [scheduleForDeleteAll, setScheduleForDeleteAll] = useState<ReportSchedule | null>(null);
  const [isDeletingAllSaved, setIsDeletingAllSaved] = useState(false);
  const [deleteAllSavedError, setDeleteAllSavedError] = useState<string | null>(null);

  const openModal = (name: ModalType) => setActiveModal(name);
  const closeModal = () => setActiveModal(null);

  const {
    data: queryData,
    isFetching,
    isError,
    error: queryError,
  } = useReportScheduleData({
    pagination,
    sorting,
    filter: debouncedFilter,
    advancedFilters: filterInputs,
    enabled: isHydrated,
  });

  const scheduleList = queryData?.rows ?? [];
  const pageCount = Math.ceil((queryData?.totalCount || 0) / pagination.pageSize);
  const error = isError && queryError instanceof Error ? queryError.message : '';

  const getRowId = (row: ReportSchedule) => String(row.reportScheduleId);

  const handleRowSelectionChange = (
    updaterOrValue: RowSelectionState | ((old: RowSelectionState) => RowSelectionState),
  ) => {
    const newSelection =
      typeof updaterOrValue === 'function' ? updaterOrValue(rowSelection) : updaterOrValue;

    setRowSelection(newSelection);

    setSelectionCache((prev) => {
      const next = { ...prev };
      scheduleList.forEach((item) => {
        const id = getRowId(item);
        if (newSelection[id]) {
          next[id] = item;
        }
      });
      return next;
    });
  };

  const handleRefresh = () => {
    queryClient.invalidateQueries({ queryKey: reportScheduleQueryKeys.all });
  };

  const {
    isDeleting,
    deleteError,
    setDeleteError,
    confirmDelete,
    isTogglingActive,
    toggleActiveError,
    setToggleActiveError,
    confirmToggleActive,
    isResetting,
    resetError,
    setResetError,
    confirmReset,
  } = useReportScheduleActions({ t, handleRefresh, closeModal, setRowSelection });

  const handleDelete = (scheduleId: number) => {
    const schedule = scheduleList.find((s) => s.reportScheduleId === scheduleId);
    if (!schedule) {
      return;
    }
    setItemsToDelete([schedule]);
    setDeleteError(null);
    openModal('delete');
  };

  const openEditModal = (schedule: ReportSchedule) => {
    setSelectedSchedule(schedule);
    openModal('edit');
  };

  const openResetModal = (schedule: ReportSchedule) => {
    setSelectedSchedule(schedule);
    setResetError(null);
    openModal('reset');
  };

  const openToggleActiveModal = (schedule: ReportSchedule) => {
    setSelectedSchedule(schedule);
    setToggleActiveError(null);
    openModal('toggleActive');
  };

  const handleOpenLastSaved = (schedule: ReportSchedule) => {
    navigate(
      `/reporting/saved-reports/${schedule.lastSavedReportId}/${schedule.reportNameId}/view`,
    );
  };

  const handleBackToReports = (schedule: ReportSchedule) => {
    const meta = REPORT_META[schedule.reportNameId];
    if (meta) {
      navigate(meta.route);
    } else {
      window.location.assign(`/report/form/${schedule.reportNameId}`);
    }
  };

  const handleDeleteAllSaved = (schedule: ReportSchedule) => {
    setScheduleForDeleteAll(schedule);
    setDeleteAllSavedError(null);
    openModal('deleteAllSaved');
  };

  const confirmDeleteAllSaved = async () => {
    if (!scheduleForDeleteAll || isDeletingAllSaved) {
      return;
    }
    try {
      setIsDeletingAllSaved(true);
      setDeleteAllSavedError(null);
      await deleteAllSavedReportsForSchedule(scheduleForDeleteAll.reportScheduleId);
      notify.success(t('All saved reports deleted successfully'));
      handleRefresh();
      queryClient.invalidateQueries({ queryKey: savedReportQueryKeys.all });
      closeModal();
    } catch (err: unknown) {
      const message =
        isAxiosError(err) && err.response?.data?.message
          ? err.response.data.message
          : t('An unexpected error occurred.');
      setDeleteAllSavedError(message);
    } finally {
      setIsDeletingAllSaved(false);
    }
  };

  const handleResetFilters = () => {
    setFilterInputs(INITIAL_FILTER_STATE);
    setPagination((prev) => ({ ...prev, pageIndex: 0 }));
  };

  const getAllSelectedItems = (): ReportSchedule[] => {
    return Object.keys(rowSelection)
      .map((id) => selectionCache[id])
      .filter((item): item is ReportSchedule => !!item);
  };

  const { filterOptions, reportDescriptionMap } = useReportScheduleFilterOptions(t);

  const columns = getReportScheduleColumns({
    t,
    reportDescriptionMap,
    onDelete: handleDelete,
    openEditModal,
    openResetModal,
    openToggleActiveModal,
    onOpenLastSaved: handleOpenLastSaved,
    onDeleteAllSaved: handleDeleteAllSaved,
    onBackToReports: handleBackToReports,
  });

  const bulkActions = getBulkActions({
    t,
    onDelete: () => {
      const allItems = getAllSelectedItems();
      setItemsToDelete(allItems);
      setDeleteError(null);
      openModal('delete');
    },
  });

  const tabs = useFilteredTabs('reporting');

  return (
    <section className="flex h-full w-full min-h-0 relative outline-none overflow-hidden">
      <div className="flex-1 flex flex-col min-h-0 min-w-0 px-5 pb-5">
        <div className="flex flex-row justify-between py-4 items-center gap-4">
          <TabNav activeTab={t('Report Schedules')} navigation={tabs} />
        </div>

        <div className="flex flex-col lg:flex-row justify-end items-center gap-4 mb-4">
          <div className="flex items-center gap-2 w-full xl:w-115 lg:w-75 shrink-0">
            <div className="relative flex-1 flex">
              <div className="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                <Search className="w-4 h-4 text-gray-400" />
              </div>
              <input
                name="search"
                value={globalFilter}
                disabled={!isHydrated}
                onChange={(e) => {
                  setGlobalFilter(e.target.value);
                  setPagination((prev) => ({ ...prev, pageIndex: 0 }));
                }}
                placeholder={t('Search')}
                className="py-2 px-3 pl-10 block h-11.25 bg-gray-100 rounded-lg w-full border-gray-200 disabled:opacity-50 disabled:pointer-events-none disabled:bg-gray-200"
              />
            </div>
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

        {error && (
          <div className="bg-red-50 border border-red-200 text-red-800 p-4" role="alert">
            {error}
          </div>
        )}

        <div className="min-h-0 flex flex-col">
          {!isHydrated ? (
            <div className="flex-1 flex items-center justify-center bg-gray-50 animate-pulse rounded-lg border border-gray-200">
              <span className="text-gray-400 font-medium">{t('Loading...')}</span>
            </div>
          ) : (
            <DataTable
              columns={columns}
              data={scheduleList}
              pageCount={pageCount}
              pagination={pagination}
              onPaginationChange={setPagination}
              sorting={sorting}
              onSortingChange={setSorting}
              globalFilter={globalFilter}
              onGlobalFilterChange={setGlobalFilter}
              loading={isFetching}
              rowSelection={rowSelection}
              onRowSelectionChange={handleRowSelectionChange}
              onRefresh={handleRefresh}
              columnPinning={{ left: ['tableSelection'], right: ['tableActions'] }}
              columnVisibility={columnVisibility}
              onColumnVisibilityChange={setColumnVisibility}
              bulkActions={bulkActions}
              viewMode={null}
              getRowId={getRowId}
            />
          )}
        </div>
      </div>

      <ReportScheduleModals
        activeModal={activeModal}
        closeModal={closeModal}
        handleRefresh={handleRefresh}
        selectedSchedule={selectedSchedule}
        itemsToDelete={itemsToDelete}
        deleteError={deleteError}
        isDeleting={isDeleting}
        confirmDelete={confirmDelete}
        isTogglingActive={isTogglingActive}
        toggleActiveError={toggleActiveError}
        confirmToggleActive={confirmToggleActive}
        isResetting={isResetting}
        resetError={resetError}
        confirmReset={confirmReset}
        scheduleForDeleteAll={scheduleForDeleteAll}
        isDeletingAllSaved={isDeletingAllSaved}
        deleteAllSavedError={deleteAllSavedError}
        confirmDeleteAllSaved={confirmDeleteAllSaved}
      />
    </section>
  );
}
