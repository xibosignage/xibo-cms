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

import { ArrowLeft, Filter, FilterX, Loader2, Plus } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { useNavigate } from 'react-router-dom';

import ReportSelector from './ReportSelector';
import StatsReportFilters from './StatsReportFilters';
import StatsReportResults from './StatsReportResults';
import StatsReportScheduleModal from './StatsReportScheduleModal';
import { useStatsReportData } from './hooks/useStatsReportData';
import type { StatsFilter, StatsReportConfig } from './types';

import Button from '@/components/ui/Button';
import TabNav from '@/components/ui/TabNav';
import { useFilteredTabs } from '@/hooks/useFilteredTabs';
import { useTableState } from '@/hooks/useTableState';

interface StatsReportPageProps {
  config: StatsReportConfig;
}

export default function StatsReportPage({ config }: StatsReportPageProps) {
  const { t } = useTranslation();
  const navigate = useNavigate();
  const reportingTabs = useFilteredTabs('reporting');

  const [submittedFilter, setSubmittedFilter] = useState<StatsFilter | null>(null);
  const [scheduleModalOpen, setScheduleModalOpen] = useState(false);
  const [filtersOpen, setFiltersOpen] = useState(true);

  const {
    pagination,
    setPagination,
    sorting,
    setSorting,
    viewMode,
    setViewMode,
    filterInputs,
    setFilterInputs,
    isHydrated,
  } = useTableState<Partial<StatsFilter>>(config.tableStateKey, {
    pagination: { pageIndex: 0, pageSize: 10 },
    sorting: [],
    columnVisibility: {},
    viewMode: 'table',
    globalFilter: '',
    filterInputs: config.initialFilter,
  });

  const filter: StatsFilter = { ...config.initialFilter, ...filterInputs };

  const { data, isFetching, isError, refetch } = useStatsReportData({
    reportName: config.reportName,
    filter: submittedFilter ?? config.initialFilter,
    enabled: submittedFilter !== null,
  });

  const itemSelected =
    (filter.type === 'layout' && !!filter.layoutId) ||
    (filter.type === 'media' && !!filter.mediaId) ||
    (filter.type === 'event' && filter.eventTag.trim() !== '');

  const handleApply = () => {
    if (!itemSelected) {
      return;
    }
    setPagination((prev) => ({ ...prev, pageIndex: 0 }));
    setSubmittedFilter({ ...filter });
    setFiltersOpen(false);
  };

  const handleRefresh = () => {
    if (submittedFilter) {
      void refetch();
    }
  };

  const handleFilterChange = (patch: Partial<StatsFilter>) => {
    setFilterInputs((prev) => ({ ...config.initialFilter, ...prev, ...patch }));
  };

  return (
    <section className="flex h-full w-full min-h-0 relative outline-none overflow-hidden">
      <div className="flex-1 flex flex-col min-h-0 min-w-0 px-5 pb-5">
        <div className="flex flex-row justify-between py-4 items-center gap-4">
          <TabNav activeTab="" navigation={reportingTabs} />
          <div className="flex items-center gap-2">
            <Button
              variant="primary"
              leftIcon={Plus}
              disabled={!itemSelected}
              onClick={() => setScheduleModalOpen(true)}
            >
              {t('Schedule')}
            </Button>
            <Button
              leftIcon={filtersOpen ? FilterX : Filter}
              variant="secondary"
              disabled={!isHydrated}
              onClick={() => setFiltersOpen((prev) => !prev)}
              removeTextOnMobile
            >
              {t('Filters')}
            </Button>
          </div>
        </div>

        <div className="flex items-center justify-between">
          <Button
            variant="iconLink"
            leftIcon={ArrowLeft}
            onClick={() => navigate('/reporting/all-reports')}
          >
            {t('Back')}
          </Button>

          <ReportSelector currentReportName={config.reportName} fallbackLabel={t(config.title)} />
        </div>

        {!isHydrated ? (
          <div className="flex-1 flex items-center justify-center mt-4">
            <Loader2 className="w-8 h-8 animate-spin text-gray-400" />
          </div>
        ) : (
          <>
            {filtersOpen && (
              <StatsReportFilters
                config={config}
                filter={filter}
                onFilterChange={handleFilterChange}
                onApply={handleApply}
                isLoading={isFetching}
                applyDisabled={!itemSelected}
              />
            )}

            {submittedFilter !== null ? (
              <StatsReportResults
                rows={data?.rows ?? []}
                metadata={data?.metadata}
                typeOptions={config.typeOptions}
                chartType={config.chartType}
                isFetching={isFetching}
                isError={isError}
                onRefresh={handleRefresh}
                viewMode={viewMode}
                onViewModeChange={setViewMode}
                pagination={pagination}
                onPaginationChange={setPagination}
                sorting={sorting}
                onSortingChange={setSorting}
              />
            ) : (
              <div className="flex-1 flex items-center justify-center mt-4 bg-gray-50 rounded-lg border border-gray-200 border-dashed">
                <p className="text-gray-400 text-sm">
                  {t(
                    'Select a type and an item, set your filters, then click Apply to generate the report.',
                  )}
                </p>
              </div>
            )}
          </>
        )}
      </div>

      <StatsReportScheduleModal
        config={config}
        isOpen={scheduleModalOpen}
        currentFilter={filter}
        onClose={() => setScheduleModalOpen(false)}
        onSuccess={() => setScheduleModalOpen(false)}
      />
    </section>
  );
}
