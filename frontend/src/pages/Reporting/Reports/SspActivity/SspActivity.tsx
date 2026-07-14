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

import { ArrowLeft, Loader2 } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { useNavigate } from 'react-router-dom';

import {
  ACTIVE_FILTER_KEYS,
  INITIAL_FILTER_STATE,
  type SspActivityFilter,
} from './SspActivityConfig';
import SspActivityFilters from './components/SspActivityFilters';
import SspActivityResults from './components/SspActivityResults';
import { useSspActivityData, useSspConnectorId, useSspPartners } from './hooks/useSspActivityData';

import Button from '@/components/ui/Button';
import FilterButton from '@/components/ui/FilterButton';
import TabNav from '@/components/ui/TabNav';
import { useFilteredTabs } from '@/hooks/useFilteredTabs';
import { useTableState } from '@/hooks/useTableState';
import ReportSelector from '@/pages/Reporting/Reports/shared/ReportSelector';
import { countActiveFilters } from '@/utils/filters';

export default function SspActivity() {
  const { t } = useTranslation();
  const navigate = useNavigate();
  const reportingTabs = useFilteredTabs('reporting');

  const [submittedFilter, setSubmittedFilter] = useState<SspActivityFilter | null>(null);
  const [filtersOpen, setFiltersOpen] = useState(true);

  const { filterInputs, setFilterInputs, isHydrated } = useTableState<Partial<SspActivityFilter>>(
    'ssp_activity_report_page',
    {
      pagination: { pageIndex: 0, pageSize: 10 },
      sorting: [],
      columnVisibility: {},
      viewMode: 'table',
      globalFilter: '',
      filterInputs: INITIAL_FILTER_STATE,
    },
  );

  const filter: SspActivityFilter = { ...INITIAL_FILTER_STATE, ...filterInputs };

  const { data: connectorId, isLoading: connectorLoading } = useSspConnectorId();
  const { data: partners, isLoading: partnersLoading } = useSspPartners(connectorId ?? null);

  const partnerOptions = Object.entries(partners ?? {}).map(([value, partner]) => ({
    value,
    label: partner.name,
  }));

  const { data, isFetching, isError, error, refetch } = useSspActivityData({
    connectorId: connectorId ?? null,
    filter: submittedFilter ?? INITIAL_FILTER_STATE,
    enabled: submittedFilter !== null,
  });

  const handleApply = () => {
    if (filter.displayId == null) {
      return;
    }
    setSubmittedFilter({ ...filter });
    setFiltersOpen(false);
  };

  const handleRefresh = () => {
    if (submittedFilter) {
      void refetch();
    }
  };

  const handleFilterChange = (patch: Partial<SspActivityFilter>) => {
    setFilterInputs((prev) => ({ ...INITIAL_FILTER_STATE, ...prev, ...patch }));
  };

  const connectorMissing = !connectorLoading && connectorId == null;

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
          <FilterButton
            isOpen={filtersOpen}
            onToggle={() => setFiltersOpen((prev) => !prev)}
            activeCount={activeFilterCount}
            disabled={!isHydrated || connectorMissing}
          />
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
            currentReportName="ssp-activity"
            fallbackLabel={t('SSP Activity Report')}
          />
        </div>

        {!isHydrated || connectorLoading ? (
          <div className="flex-1 flex items-center justify-center mt-4">
            <Loader2 className="w-8 h-8 animate-spin text-gray-400" />
          </div>
        ) : connectorMissing ? (
          <div className="flex-1 flex items-center justify-center mt-4 bg-gray-50 rounded-lg border border-gray-200 border-dashed">
            <p className="text-gray-400 text-sm">
              {t('The Xibo SSP Connector is not installed or enabled.')}
            </p>
          </div>
        ) : (
          <>
            {filtersOpen && (
              <SspActivityFilters
                filter={filter}
                onFilterChange={handleFilterChange}
                onApply={handleApply}
                isLoading={isFetching}
                partnerOptions={partnerOptions}
                partnersLoading={partnersLoading}
              />
            )}

            {submittedFilter !== null ? (
              <SspActivityResults
                rows={data?.rows ?? []}
                isFetching={isFetching}
                isError={isError}
                errorMessage={error instanceof Error ? error.message : null}
                onRefresh={handleRefresh}
              />
            ) : (
              <div className="flex-1 flex items-center justify-center mt-4 bg-gray-50 rounded-lg border border-gray-200 border-dashed">
                <p className="text-gray-400 text-sm">
                  {t(
                    'Select a display, set your date range, then click Apply to generate the report.',
                  )}
                </p>
              </div>
            )}
          </>
        )}
      </div>
    </section>
  );
}
