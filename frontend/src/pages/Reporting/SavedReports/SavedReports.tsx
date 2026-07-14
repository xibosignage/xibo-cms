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
import { Filter, FilterX, Search } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { useNavigate } from 'react-router-dom';

import { INITIAL_FILTER_STATE as SCHEDULE_INITIAL_FILTER_STATE } from '../ReportSchedules/ReportSchedulesConfig';

import type { ModalType, SavedReportFilterInput } from './SavedReportsConfig';
import { getBulkActions, getSavedReportColumns, INITIAL_FILTER_STATE } from './SavedReportsConfig';
import { SavedReportModals } from './components/SavedReportsModals';
import { useSavedReportActions } from './hooks/useSavedReportActions';
import { savedReportQueryKeys, useSavedReportData } from './hooks/useSavedReportData';
import { useSavedReportFilterOptions } from './hooks/useSavedReportFilterOptions';

import Button from '@/components/ui/Button';
import FilterInputs from '@/components/ui/FilterInputs';
import TabNav from '@/components/ui/TabNav';
import { DataTable } from '@/components/ui/table/DataTable';
import { withPublicPath } from '@/config/publicPath';
import { REPORT_META } from '@/config/reportRoutes';
import { useDateFormatter } from '@/hooks/useDateFormatter';
import { useFilteredTabs } from '@/hooks/useFilteredTabs';
import { useTableState } from '@/hooks/useTableState';
import { saveUserPreference } from '@/services/userApi';
import type { SavedReport } from '@/types/savedReport';

export default function SavedReports() {
  const { t } = useTranslation();
  const { formatDateTime } = useDateFormatter();
  const queryClient = useQueryClient();
  const navigate = useNavigate();

  const savedPageIndex = (() => {
    try {
      const v = sessionStorage.getItem('savedReports_pageIndex');
      return v !== null ? parseInt(v, 10) : 0;
    } catch {
      return 0;
    }
  })();

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
  } = useTableState<SavedReportFilterInput>('saved_reports_page', {
    pagination: { pageIndex: savedPageIndex, pageSize: 10 },
    sorting: [],
    columnVisibility: {},
    viewMode: 'table',
    globalFilter: '',
    filterInputs: INITIAL_FILTER_STATE,
  });

  const [rowSelection, setRowSelection] = useState<RowSelectionState>({});
  const [selectionCache, setSelectionCache] = useState<Record<string, SavedReport>>({});
  const [openFilter, setOpenFilter] = useState(false);

  const [activeModal, setActiveModal] = useState<ModalType | null>(null);
  const [itemsToDelete, setItemsToDelete] = useState<SavedReport[]>([]);

  const openModal = (name: ModalType) => setActiveModal(name);
  const closeModal = () => setActiveModal(null);

  const {
    data: queryData,
    isFetching,
    isError,
    error: queryError,
  } = useSavedReportData({
    pagination,
    sorting,
    filter: debouncedFilter,
    advancedFilters: filterInputs,
    enabled: isHydrated,
  });

  const reportList = queryData?.rows ?? [];
  const pageCount = Math.ceil((queryData?.totalCount || 0) / pagination.pageSize);
  const error = isError && queryError instanceof Error ? queryError.message : '';

  const getRowId = (row: SavedReport) => String(row.savedReportId);

  const handleRowSelectionChange = (
    updaterOrValue: RowSelectionState | ((old: RowSelectionState) => RowSelectionState),
  ) => {
    const newSelection =
      typeof updaterOrValue === 'function' ? updaterOrValue(rowSelection) : updaterOrValue;

    setRowSelection(newSelection);

    setSelectionCache((prev) => {
      const next = { ...prev };
      reportList.forEach((item) => {
        const id = getRowId(item);
        if (newSelection[id]) {
          next[id] = item;
        }
      });
      return next;
    });
  };

  const handleRefresh = () => {
    queryClient.invalidateQueries({ queryKey: savedReportQueryKeys.all });
  };

  const { isDeleting, deleteError, setDeleteError, confirmDelete } = useSavedReportActions({
    t,
    handleRefresh,
    closeModal,
    setRowSelection,
  });

  const handleDelete = (reportId: number) => {
    const report = reportList.find((r) => r.savedReportId === reportId);
    if (!report) {
      return;
    }
    setItemsToDelete([report]);
    setDeleteError(null);
    openModal('delete');
  };

  const handleOpen = (report: SavedReport) => {
    try {
      sessionStorage.setItem('savedReports_pageIndex', String(pagination.pageIndex));
    } catch {
      /* ignore */
    }
    navigate(`/reporting/saved-reports/${report.savedReportId}/${report.reportName}/view`);
  };

  const handleBackToReports = (report: SavedReport) => {
    const meta = REPORT_META[report.reportName];
    if (meta) {
      navigate(meta.route);
    } else {
      window.location.assign(withPublicPath(`report/form/${report.reportName}`));
    }
  };

  const handleGoToSchedule = (report: SavedReport) => {
    void saveUserPreference({
      option: 'report_schedules_page',
      value: {
        filterInputs: {
          ...SCHEDULE_INITIAL_FILTER_STATE,
          reportScheduleId: report.reportScheduleId,
        },
      },
    })
      .then(() =>
        queryClient.invalidateQueries({ queryKey: ['userPref', 'report_schedules_page'] }),
      )
      .then(() => navigate('/reporting/report-schedules'));
  };

  const handleResetFilters = () => {
    setFilterInputs(INITIAL_FILTER_STATE);
    setPagination((prev) => ({ ...prev, pageIndex: 0 }));
  };

  const getAllSelectedItems = (): SavedReport[] => {
    return Object.keys(rowSelection)
      .map((id) => selectionCache[id])
      .filter((item): item is SavedReport => !!item);
  };

  const { filterOptions, reportDescriptionMap } = useSavedReportFilterOptions(t);

  const columns = getSavedReportColumns({
    t,
    formatDateTime,
    reportDescriptionMap,
    onDelete: handleDelete,
    onGoToSchedule: handleGoToSchedule,
    onOpen: handleOpen,
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
          <TabNav activeTab={t('Saved Reports')} navigation={tabs} />
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
              data={reportList}
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

      <SavedReportModals
        activeModal={activeModal}
        closeModal={closeModal}
        itemsToDelete={itemsToDelete}
        deleteError={deleteError}
        isDeleting={isDeleting}
        confirmDelete={confirmDelete}
      />
    </section>
  );
}
