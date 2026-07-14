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

import { ArrowLeft, Loader2, Plus } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { useNavigate } from 'react-router-dom';

import type { DisplayAlertsFilter } from './DisplayAlertsConfig';
import { ACTIVE_FILTER_KEYS, INITIAL_FILTER_STATE } from './DisplayAlertsConfig';
import AddScheduleModal from './components/AddScheduleModal';
import DisplayAlertsFilters from './components/DisplayAlertsFilters';
import DisplayAlertsResults from './components/DisplayAlertsResults';
import { useDisplayAlertsData } from './hooks/useDisplayAlertsData';

import Button from '@/components/ui/Button';
import FilterButton from '@/components/ui/FilterButton';
import TabNav from '@/components/ui/TabNav';
import { useFilteredTabs } from '@/hooks/useFilteredTabs';
import { useTableState } from '@/hooks/useTableState';
import ReportSelector from '@/pages/Reporting/Reports/shared/ReportSelector';
import { countActiveFilters } from '@/utils/filters';

export default function DisplayAlerts() {
  const { t } = useTranslation();
  const navigate = useNavigate();
  const reportingTabs = useFilteredTabs('reporting');

  const [submittedFilter, setSubmittedFilter] = useState<DisplayAlertsFilter | null>(null);
  const [scheduleModalOpen, setScheduleModalOpen] = useState(false);
  const [filtersOpen, setFiltersOpen] = useState(true);

  const {
    pagination,
    setPagination,
    sorting,
    setSorting,
    filterInputs,
    setFilterInputs,
    isHydrated,
  } = useTableState<Partial<DisplayAlertsFilter>>('display_alerts_page', {
    pagination: { pageIndex: 0, pageSize: 10 },
    sorting: [],
    columnVisibility: {},
    viewMode: 'table',
    globalFilter: '',
    filterInputs: INITIAL_FILTER_STATE,
  });

  const filter: DisplayAlertsFilter = { ...INITIAL_FILTER_STATE, ...filterInputs };

  const { data, isFetching, isError, refetch } = useDisplayAlertsData({
    filter: submittedFilter ?? INITIAL_FILTER_STATE,
    enabled: submittedFilter !== null,
  });

  const handleApply = () => {
    setPagination((prev) => ({ ...prev, pageIndex: 0 }));
    setSubmittedFilter({ ...filter });
    setFiltersOpen(false);
  };

  const handleRefresh = () => {
    if (submittedFilter) {
      void refetch();
    }
  };

  const handleFilterChange = (patch: Partial<DisplayAlertsFilter>) => {
    setFilterInputs((prev) => ({ ...INITIAL_FILTER_STATE, ...prev, ...patch }));
  };

  const activeFilterCount = countActiveFilters(
    filterInputs,
    INITIAL_FILTER_STATE,
    ACTIVE_FILTER_KEYS,
  );

  return (
    <section className="flex h-full w-full min-h-0 relative outline-none overflow-hidden">
      <div className="flex-1 flex flex-col min-h-0 min-w-0 px-5 pb-5">
        <div className="flex flex-row justify-between py-4 items-center gap-4">
          <TabNav activeTab="" navigation={reportingTabs} />
          <div className="flex items-center gap-2">
            <Button variant="primary" leftIcon={Plus} onClick={() => setScheduleModalOpen(true)}>
              {t('Schedule')}
            </Button>
            <FilterButton
              isOpen={filtersOpen}
              onToggle={() => setFiltersOpen((prev) => !prev)}
              activeCount={activeFilterCount}
              disabled={!isHydrated}
            />
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

          <ReportSelector currentReportName="displayalerts" fallbackLabel={t('Display Alerts')} />
        </div>

        {!isHydrated ? (
          <div className="flex-1 flex items-center justify-center mt-4">
            <Loader2 className="w-8 h-8 animate-spin text-gray-400" />
          </div>
        ) : (
          <>
            {filtersOpen && (
              <DisplayAlertsFilters
                filter={filter}
                onFilterChange={handleFilterChange}
                onApply={handleApply}
                isLoading={isFetching}
              />
            )}

            {submittedFilter !== null ? (
              <DisplayAlertsResults
                rows={data?.rows ?? []}
                isFetching={isFetching}
                isError={isError}
                onRefresh={handleRefresh}
                pagination={pagination}
                onPaginationChange={setPagination}
                sorting={sorting}
                onSortingChange={setSorting}
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
