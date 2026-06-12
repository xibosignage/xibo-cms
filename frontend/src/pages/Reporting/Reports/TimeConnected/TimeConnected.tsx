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

import {
  getCoreRowModel,
  getPaginationRowModel,
  useReactTable,
  type ColumnDef,
} from '@tanstack/react-table';
import { ArrowLeft, ChevronDown, Loader2, Plus } from 'lucide-react';
import { useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { useNavigate } from 'react-router-dom';

import type { DisplayReportRow, TimeConnectedFilter } from './TimeConnectedConfig';
import { DISPLAY_CATEGORY_REPORTS, INITIAL_FILTER_STATE } from './TimeConnectedConfig';
import AddScheduleModal from './components/AddScheduleModal';
import TimeConnectedFilters from './components/TimeConnectedFilters';
import TimeConnectedResults from './components/TimeConnectedResults';
import { useTimeConnectedData } from './hooks/useTimeConnectedData';

import Button from '@/components/ui/Button';
import TabNav from '@/components/ui/TabNav';
import { useClickOutside } from '@/hooks/useClickOutside';
import { useFilteredTabs } from '@/hooks/useFilteredTabs';
import { useTableState } from '@/hooks/useTableState';

const EMPTY_COLUMNS: ColumnDef<DisplayReportRow>[] = [];

export default function TimeConnected() {
  const { t } = useTranslation();
  const navigate = useNavigate();
  const reportingTabs = useFilteredTabs('reporting');

  const [submittedFilter, setSubmittedFilter] = useState<TimeConnectedFilter | null>(null);
  const [scheduleModalOpen, setScheduleModalOpen] = useState(false);
  const [reportSelectorOpen, setReportSelectorOpen] = useState(false);
  const reportSelectorRef = useRef<HTMLDivElement>(null);

  useClickOutside(reportSelectorRef, () => setReportSelectorOpen(false));

  const { filterInputs, setFilterInputs, isHydrated, pagination, setPagination } = useTableState<
    Partial<TimeConnectedFilter>
  >('timeconnected_report_page', {
    pagination: { pageIndex: 0, pageSize: 10 },
    sorting: [],
    columnVisibility: {},
    viewMode: 'table',
    globalFilter: '',
    filterInputs: INITIAL_FILTER_STATE,
  });

  const filter: TimeConnectedFilter = { ...INITIAL_FILTER_STATE, ...filterInputs };

  const { data, isFetching, isError, refetch } = useTimeConnectedData({
    filter: submittedFilter ?? INITIAL_FILTER_STATE,
    enabled: submittedFilter !== null,
  });

  const isLoading = isFetching || (submittedFilter !== null && data === undefined && !isError);

  const handleApply = () => {
    setSubmittedFilter({ ...filter });
    setPagination((prev) => ({ ...prev, pageIndex: 0 }));
  };

  const handleRefresh = () => {
    if (submittedFilter) {
      void refetch();
    }
  };

  const handleFilterChange = (patch: Partial<TimeConnectedFilter>) => {
    setFilterInputs((prev) => ({ ...INITIAL_FILTER_STATE, ...prev, ...patch }));
  };

  const sortedRows = (data?.rows ?? []).slice().sort((a, b) => {
    if (filter.sortBy === 'uptime_asc') {
      return a.uptimePercent - b.uptimePercent;
    }
    return b.uptimePercent - a.uptimePercent;
  });

  const table = useReactTable({
    data: sortedRows,
    columns: EMPTY_COLUMNS,
    state: { pagination },
    onPaginationChange: setPagination,
    getCoreRowModel: getCoreRowModel(),
    getPaginationRowModel: getPaginationRowModel(),
    autoResetPageIndex: false,
  });

  return (
    <section className="flex h-full w-full min-h-0 relative outline-none overflow-hidden">
      <div className="flex-1 flex flex-col min-h-0 min-w-0 px-5 pb-5 overflow-y-auto">
        <div className="flex flex-row justify-between py-4 items-center gap-4">
          <TabNav activeTab="" navigation={reportingTabs} />
          <Button variant="primary" leftIcon={Plus} onClick={() => setScheduleModalOpen(true)}>
            {t('Schedule')}
          </Button>
        </div>

        <div className="flex items-center justify-between">
          <Button
            variant="iconLink"
            leftIcon={ArrowLeft}
            onClick={() => navigate('/reporting/all-reports')}
          >
            {t('Back')}
          </Button>

          <div className="relative" ref={reportSelectorRef}>
            <Button
              variant="link"
              rightIcon={ChevronDown}
              onClick={() => setReportSelectorOpen((prev) => !prev)}
            >
              {t('Time Connected')}
            </Button>

            {reportSelectorOpen && (
              <div className="absolute right-0 top-full mt-1 z-50 bg-white border border-gray-200 rounded-lg shadow-lg min-w-72 py-1">
                {DISPLAY_CATEGORY_REPORTS.map((report) => (
                  <button
                    key={report.label}
                    type="button"
                    className={`w-full text-left px-4 py-2 text-sm hover:bg-gray-100 ${
                      report.url === null ? 'font-semibold text-gray-900' : 'text-gray-700'
                    }`}
                    onClick={() => {
                      if (report.url) {
                        window.location.href = report.url;
                      }
                      setReportSelectorOpen(false);
                    }}
                  >
                    {t(report.label)}
                  </button>
                ))}
              </div>
            )}
          </div>
        </div>

        {!isHydrated ? (
          <div className="flex-1 flex items-center justify-center mt-4">
            <Loader2 className="w-8 h-8 animate-spin text-gray-400" />
          </div>
        ) : (
          <>
            <TimeConnectedFilters
              filter={filter}
              onFilterChange={handleFilterChange}
              onApply={handleApply}
              isLoading={isLoading}
            />

            {submittedFilter !== null ? (
              <TimeConnectedResults
                table={table}
                totalRowCount={sortedRows.length}
                metadata={data?.metadata}
                isFetching={isLoading}
                isError={isError}
                onRefresh={handleRefresh}
              />
            ) : (
              <div className="flex-1 flex items-center justify-center mt-4 bg-gray-50 rounded-lg border border-gray-200 border-dashed">
                <p className="text-gray-400 text-sm">
                  {t('Set your filters above and click Apply to generate the report.')}
                </p>
              </div>
            )}
          </>
        )}
      </div>

      <AddScheduleModal
        isOpen={scheduleModalOpen}
        currentFilter={filter}
        onClose={() => setScheduleModalOpen(false)}
        onSuccess={() => setScheduleModalOpen(false)}
      />
    </section>
  );
}
