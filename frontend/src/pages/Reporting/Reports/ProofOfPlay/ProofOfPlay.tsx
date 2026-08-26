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

import { ArrowLeft, Download, Loader2, Plus } from 'lucide-react';
import { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { useLocation, useNavigate } from 'react-router-dom';

import type { ProofOfPlayFilter } from './ProofOfPlayConfig';
import { ACTIVE_FILTER_KEYS, INITIAL_FILTER_STATE } from './ProofOfPlayConfig';
import AddScheduleModal from './components/AddScheduleModal';
import ExportStatisticsModal from './components/ExportStatisticsModal';
import ProofOfPlayFilters from './components/ProofOfPlayFilters';
import ProofOfPlayResults from './components/ProofOfPlayResults';
import { useProofOfPlayData } from './hooks/useProofOfPlayData';

import Button from '@/components/ui/Button';
import FilterButton from '@/components/ui/FilterButton';
import TabNav from '@/components/ui/TabNav';
import { useFilteredTabs } from '@/hooks/useFilteredTabs';
import { useTableState } from '@/hooks/useTableState';
import ReportSelector from '@/pages/Reporting/Reports/shared/ReportSelector';
import { countActiveFilters } from '@/utils/filters';

export default function ProofOfPlay() {
  const { t } = useTranslation();
  const navigate = useNavigate();
  const location = useLocation();
  const reportingTabs = useFilteredTabs('reporting');

  // Deep-link support: the Display Overview Manage modal's "Proof of Play"
  // button navigates here with `{ state: { displayId } }` so the report opens
  // pre-filtered to that display, rather than requiring the user to filter
  // manually.
  const locationDisplayId = (location.state as { displayId?: number } | null)?.displayId;

  const [submittedFilter, setSubmittedFilter] = useState<ProofOfPlayFilter | null>(null);
  const [scheduleModalOpen, setScheduleModalOpen] = useState(false);
  const [exportModalOpen, setExportModalOpen] = useState(false);
  const [filtersOpen, setFiltersOpen] = useState(true);

  const {
    pagination,
    setPagination,
    sorting,
    setSorting,
    columnVisibility,
    setColumnVisibility,
    filterInputs,
    setFilterInputs,
    isHydrated,
  } = useTableState<Partial<ProofOfPlayFilter>>('proof_of_play_page', {
    pagination: { pageIndex: 0, pageSize: 10 },
    sorting: [],
    columnVisibility: {
      displayId: false,
      displayGroupId: false,
      displayGroup: false,
      tagId: false,
      tagName: false,
      layoutId: false,
      widgetId: false,
    },
    viewMode: 'table',
    globalFilter: '',
    filterInputs: INITIAL_FILTER_STATE,
  });

  const filter: ProofOfPlayFilter = { ...INITIAL_FILTER_STATE, ...filterInputs };

  // Once persisted table state has hydrated, a displayId carried in via
  // navigation state overrides it and the report is run immediately so the
  // deep link lands on filtered results rather than an empty "set your
  // filters" placeholder.
  useEffect(() => {
    if (!isHydrated || locationDisplayId == null) {
      return;
    }

    setFilterInputs((prev) => ({ ...prev, displayId: locationDisplayId }));
    setPagination((prev) => ({ ...prev, pageIndex: 0 }));
    setSubmittedFilter((prev) => ({
      ...(prev ?? INITIAL_FILTER_STATE),
      displayId: locationDisplayId,
    }));
    setFiltersOpen(false);
  }, [isHydrated, locationDisplayId, setFilterInputs, setPagination]);

  const { data, isFetching, isError, refetch } = useProofOfPlayData({
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

  const handleFilterChange = (patch: Partial<ProofOfPlayFilter>) => {
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
            <Button
              variant="secondary"
              leftIcon={Download}
              onClick={() => setExportModalOpen(true)}
            >
              {t('Export')}
            </Button>
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

          <ReportSelector
            currentReportName="proofofplayReport"
            fallbackLabel={t('Proof of Play')}
          />
        </div>

        {!isHydrated ? (
          <div className="flex-1 flex items-center justify-center mt-4">
            <Loader2 className="w-8 h-8 animate-spin text-gray-400" />
          </div>
        ) : (
          <>
            {filtersOpen && (
              <ProofOfPlayFilters
                filter={filter}
                onFilterChange={handleFilterChange}
                onApply={handleApply}
                isLoading={isFetching}
              />
            )}

            {submittedFilter !== null ? (
              <ProofOfPlayResults
                rows={data?.rows ?? []}
                isFetching={isFetching}
                isError={isError}
                onRefresh={handleRefresh}
                pagination={pagination}
                onPaginationChange={setPagination}
                sorting={sorting}
                onSortingChange={setSorting}
                columnVisibility={columnVisibility}
                onColumnVisibilityChange={setColumnVisibility}
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

      <ExportStatisticsModal isOpen={exportModalOpen} onClose={() => setExportModalOpen(false)} />
    </section>
  );
}
