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

import type { PaginationState } from '@tanstack/react-table';
import { Search } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { useNavigate } from 'react-router-dom';

import { CARD_PAGE_SIZE } from './OverviewConfig';
import { INITIAL_OVERVIEW_FILTER_STATE } from './OverviewFilterConfig';
import DisplayCardGrid, { type OverviewViewMode } from './components/DisplayCardGrid';
import KpiRow from './components/KpiRow';
import ProofOfPlayPlaceholderPanel from './components/ProofOfPlayPlaceholderPanel';
import StatusChipRow from './components/StatusChipRow';
import { useDisplayOverviewSummary } from './hooks/useDisplayOverviewSummary';
import { useOverviewDisplays } from './hooks/useOverviewDisplays';
import { useOverviewFilterOptions } from './hooks/useOverviewFilterOptions';

import FilterButton from '@/components/ui/FilterButton';
import FilterInputs from '@/components/ui/FilterInputs';
import QueryStatusBanner from '@/components/ui/QueryStatusBanner';
import { useUserContext } from '@/context/UserContext';
import ScreenshotGalleryModal from '@/pages/Displays/Displays/components/ScreenshotGalleryModal';
import type { Display } from '@/types/display';
import type { DisplayOverviewBucket } from '@/types/displayOverview';
import { countActiveFilters } from '@/utils/filters';
import { hasFeature } from '@/utils/permissions';

export default function Overview() {
  const { t } = useTranslation();
  const navigate = useNavigate();
  const { user } = useUserContext();
  const canViewProofOfPlay = hasFeature(user, 'proof-of-play');

  const [openFilter, setOpenFilter] = useState(false);
  const [filters, setFilters] = useState(INITIAL_OVERVIEW_FILTER_STATE);
  const [viewMode, setViewMode] = useState<OverviewViewMode>('cards');
  const [pagination, setPagination] = useState<PaginationState>({
    pageIndex: 0,
    pageSize: CARD_PAGE_SIZE,
  });

  const { filterOptions } = useOverviewFilterOptions(t);
  const activeFilterCount = countActiveFilters(
    filters,
    INITIAL_OVERVIEW_FILTER_STATE,
    filterOptions,
  );

  const {
    data: summary,
    isLoading: isSummaryLoading,
    isError: isSummaryError,
    error: summaryError,
  } = useDisplayOverviewSummary();

  const {
    data: displaysData,
    isFetching: isDisplaysFetching,
    isLoading: isDisplaysLoading,
    isError: isDisplaysError,
    isPaused: isDisplaysPaused,
    error: displaysError,
  } = useOverviewDisplays({ pagination, filters });

  const [liveViewDisplay, setLiveViewDisplay] = useState<Display | null>(null);

  const activeBucket = (filters.healthStatus as DisplayOverviewBucket | null) || null;

  const handleFilterChange = (name: keyof typeof filters, value: string | null) => {
    setFilters((prev) => ({
      ...prev,
      [name]: value === undefined || value === '' ? null : value,
    }));
    setPagination((prev) => ({ ...prev, pageIndex: 0 }));
  };

  const handleSelectBucket = (bucket: DisplayOverviewBucket | null) => {
    handleFilterChange('healthStatus', bucket);
  };

  // Quick search box — same debounce technique as FilterInputs' own
  // DebouncedInputFilter, writing into the same filters.display value the
  // Filter panel's "Display Name" field used to own (removed from there to
  // avoid two controls for the same one field).
  const [searchTerm, setSearchTerm] = useState(filters.display ?? '');
  const searchDebounceRef = useRef<ReturnType<typeof setTimeout> | null>(null);

  useEffect(() => {
    setSearchTerm(filters.display ?? '');
  }, [filters.display]);

  const handleSearchChange = (value: string) => {
    setSearchTerm(value);
    if (searchDebounceRef.current) {
      clearTimeout(searchDebounceRef.current);
    }
    searchDebounceRef.current = setTimeout(() => {
      handleFilterChange('display', value || null);
    }, 300);
  };

  const handleManage = (display: Display) => {
    navigate(`/displays/overview/${display.displayId}`);
  };

  const summaryErrorMessage =
    isSummaryError && summaryError instanceof Error ? summaryError.message : '';
  const displaysErrorMessage =
    isDisplaysError && displaysError instanceof Error ? displaysError.message : '';

  return (
    <section className="flex h-full w-full min-h-0 relative outline-none overflow-hidden">
      <div className="flex-1 flex flex-col min-h-0 min-w-0 gap-5 px-5 pt-4 pb-5 overflow-y-auto">
        <div className="flex flex-col lg:flex-row items-center justify-between gap-4">
          <h1 className="w-full lg:w-auto text-xl font-semibold text-gray-800">
            {t('Display Overview')}
          </h1>

          <div className="flex items-center gap-2 w-full lg:w-100 shrink-0">
            <div className="relative flex-1 flex">
              <div className="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                <Search className="w-4 h-4 text-gray-400" />
              </div>
              <input
                name="search"
                aria-label={t('Search displays')}
                value={searchTerm}
                onChange={(e) => handleSearchChange(e.target.value)}
                placeholder={t('Search displays...')}
                className="py-2 px-3 pl-10 block h-11.25 bg-gray-100 rounded-lg w-full border-gray-200"
              />
            </div>
            <FilterButton
              isOpen={openFilter}
              onToggle={() => setOpenFilter((prev) => !prev)}
              activeCount={activeFilterCount}
            />
          </div>
        </div>

        <FilterInputs
          isOpen={openFilter}
          values={filters}
          options={filterOptions}
          onChange={(name, value) => handleFilterChange(name, value as string | null)}
          onReset={() => {
            setFilters(INITIAL_OVERVIEW_FILTER_STATE);
            setPagination((prev) => ({ ...prev, pageIndex: 0 }));
          }}
        />

        <QueryStatusBanner
          error={summaryErrorMessage || displaysErrorMessage}
          isPaused={isDisplaysPaused}
        />

        <KpiRow
          total={summary?.total}
          online={summary?.online}
          offline={summary?.offline}
          needsAttention={summary?.needsAttention}
          faults={summary?.faults}
          offlineTrend={summary?.offlineTrend}
          onlineTrend={summary?.onlineTrend}
          faultsTrend={summary?.faultsTrend}
          isLoading={isSummaryLoading}
        />

        <ProofOfPlayPlaceholderPanel
          title={t('Proof of Play — Today')}
          canViewProofOfPlay={canViewProofOfPlay}
          onViewReport={() => navigate('/reporting/proof-of-play')}
        />

        <StatusChipRow
          summary={summary}
          activeBucket={activeBucket}
          onSelectBucket={handleSelectBucket}
        />

        <DisplayCardGrid
          displays={displaysData?.rows ?? []}
          totalCount={displaysData?.totalCount ?? 0}
          isLoading={isDisplaysLoading}
          isFetching={isDisplaysFetching}
          activeBucket={activeBucket}
          pagination={pagination}
          onPaginationChange={setPagination}
          onManage={handleManage}
          onLiveView={setLiveViewDisplay}
          viewMode={viewMode}
          onViewModeChange={setViewMode}
        />
      </div>

      <ScreenshotGalleryModal display={liveViewDisplay} onClose={() => setLiveViewDisplay(null)} />
    </section>
  );
}
