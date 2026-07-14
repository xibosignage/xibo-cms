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

import { TriangleAlert } from 'lucide-react';
import { useTranslation } from 'react-i18next';

import { useDisplayGroupSelect } from './hooks/useDisplayGroupSelect';
import { useDisplayOptions } from './hooks/useDisplayOptions';
import type { OptionsLoader } from './hooks/usePaginatedOptions';
import { usePaginatedOptions } from './hooks/usePaginatedOptions';
import type { StatsFilter, StatsReportConfig, StatsReportType } from './types';

import Button from '@/components/ui/Button';
import DateRangeFilter from '@/components/ui/DateRangeFilter';
import SelectDropdown from '@/components/ui/forms/SelectDropdown';
import TextInput from '@/components/ui/forms/TextInput';
import { fetchLayouts } from '@/services/layoutsApi';
import { fetchMedia } from '@/services/mediaApi';

const PAGE_SIZE = 20;

interface StatsReportFiltersProps {
  config: StatsReportConfig;
  filter: StatsFilter;
  onFilterChange: (patch: Partial<StatsFilter>) => void;
  onApply: () => void;
  isLoading: boolean;
  applyDisabled: boolean;
}

export default function StatsReportFilters({
  config,
  filter,
  onFilterChange,
  onApply,
  isLoading,
  applyDisabled,
}: StatsReportFiltersProps) {
  const { t } = useTranslation();

  const layoutLoader: OptionsLoader = async (search, start, signal) => {
    const { rows, totalCount } = await fetchLayouts({
      start,
      length: PAGE_SIZE,
      ...(search ? { layout: search } : {}),
      signal,
    });
    return {
      options: rows.map((l) => ({ value: String(l.layoutId), label: l.layout })),
      totalCount,
    };
  };

  const mediaLoader: OptionsLoader = async (search, start, signal) => {
    const { rows, totalCount } = await fetchMedia({
      start,
      length: PAGE_SIZE,
      ...(search ? { keyword: search } : {}),
      signal,
    });
    return {
      options: rows.map((m) => ({ value: String(m.mediaId), label: m.name })),
      totalCount,
    };
  };

  const resolveItemLabel = async (val: string): Promise<string> => {
    if (filter.type === 'layout') {
      const { rows } = await fetchLayouts({ start: 0, length: 1, layoutId: Number(val) });
      return rows[0]?.layout ?? '';
    }
    if (filter.type === 'media') {
      const { rows } = await fetchMedia({ start: 0, length: 1, mediaId: Number(val) });
      return rows[0]?.name ?? '';
    }
    return '';
  };

  const itemSelect = usePaginatedOptions({
    loader: filter.type === 'media' ? mediaLoader : layoutLoader,
    enabled: filter.type !== 'event',
    resetKey: filter.type,
  });

  const displaySelect = useDisplayOptions();
  const displayGroupSelect = useDisplayGroupSelect();

  const handleTypeChange = (value: string) => {
    onFilterChange({
      type: value as StatsReportType,
      layoutId: null,
      mediaId: null,
      eventTag: '',
    });
  };

  const itemValue =
    filter.type === 'layout'
      ? (filter.layoutId?.toString() ?? '')
      : filter.type === 'media'
        ? (filter.mediaId?.toString() ?? '')
        : '';

  const handleItemSelect = (value: string) => {
    if (filter.type === 'layout') {
      onFilterChange({ layoutId: value ? Number(value) : null });
    } else if (filter.type === 'media') {
      onFilterChange({ mediaId: value ? Number(value) : null });
    }
  };

  const displayGroupValue = filter.displayGroupId[0]?.toString() ?? '';

  const groupByOptions = config.getGroupByOptions(filter.reportFilter);
  const showDataWarning = config.shouldShowDataWarning(filter.reportFilter);

  const handleReportFilterChange = (value: string) => {
    const patch: Partial<StatsFilter> = { reportFilter: value };
    const nextOptions = config.getGroupByOptions(value);
    if (nextOptions.length > 0 && !nextOptions.some((o) => o.value === filter.groupByFilter)) {
      patch.groupByFilter = config.defaultGroupBy(value);
    }
    onFilterChange(patch);
  };

  const handleReset = () => {
    onFilterChange({ ...config.initialFilter });
  };

  return (
    <div className="mt-4">
      <div className="relative bg-slate-50 p-5 pt-7 flex flex-col gap-4">
        <Button
          variant="tertiary"
          className="absolute right-1 top-1 focus:outline-0"
          onClick={handleReset}
        >
          {t('Reset')}
        </Button>

        <div className="grid grid-cols-[repeat(auto-fit,minmax(15rem,1fr))] gap-4 items-end">
          <DateRangeFilter
            label={t('Range')}
            name="reportFilter"
            value={filter.reportFilter}
            options={config.dateRangeOptions.map((o) => ({ ...o, label: t(o.label) }))}
            onChange={(_name, value) => handleReportFilterChange(String(value ?? ''))}
          />

          {groupByOptions.length > 0 && (
            <SelectDropdown
              label={t('Group By')}
              value={filter.groupByFilter}
              options={groupByOptions.map((o) => ({ value: o.value, label: t(o.label) }))}
              onSelect={(val) => onFilterChange({ groupByFilter: val })}
            />
          )}

          <SelectDropdown
            label={t('Type')}
            value={filter.type}
            options={config.typeOptions.map((o) => ({ value: o.value, label: t(o.label) }))}
            onSelect={handleTypeChange}
          />

          {filter.type === 'event' ? (
            <TextInput
              name="eventTag"
              label={`${t('Tag')} *`}
              placeholder=" "
              value={filter.eventTag}
              onChange={(val) => onFilterChange({ eventTag: val })}
            />
          ) : (
            <SelectDropdown
              label={`${filter.type === 'media' ? t('Media') : t('Layout')} *`}
              value={itemValue}
              placeholder={t('Select')}
              searchable
              resolveLabel={resolveItemLabel}
              options={itemSelect.options}
              isLoading={itemSelect.isLoading}
              isLoadingMore={itemSelect.isLoadingMore}
              hasMore={itemSelect.hasMore}
              onSearch={itemSelect.onSearch}
              onLoadMore={itemSelect.onLoadMore}
              onSelect={handleItemSelect}
            />
          )}

          <SelectDropdown
            label={t('Display')}
            value={filter.displayId?.toString() ?? ''}
            placeholder={t('All Displays')}
            searchable
            clearable
            resolveLabel={displaySelect.resolveLabel}
            options={displaySelect.options}
            isLoading={displaySelect.isLoading}
            isLoadingMore={displaySelect.isLoadingMore}
            hasMore={displaySelect.hasMore}
            onSearch={displaySelect.onSearch}
            onLoadMore={displaySelect.onLoadMore}
            onSelect={(val) => onFilterChange({ displayId: val ? Number(val) : null })}
          />

          <SelectDropdown
            label={t('Display Group')}
            value={displayGroupValue}
            placeholder={t('All Groups')}
            searchable
            clearable
            resolveLabel={displayGroupSelect.resolveLabel}
            options={displayGroupSelect.options}
            isLoading={displayGroupSelect.isLoading}
            isLoadingMore={displayGroupSelect.isLoadingMore}
            hasMore={displayGroupSelect.hasMore}
            onSearch={displayGroupSelect.onSearch}
            onLoadMore={displayGroupSelect.onLoadMore}
            onSelect={(val) => onFilterChange({ displayGroupId: val ? [Number(val)] : [] })}
          />
        </div>

        <div className="flex justify-end">
          {showDataWarning && (
            <div className="flex items-center gap-1.5 text-sm text-amber-600 mt-2 mr-auto">
              <TriangleAlert className="w-4 h-4 shrink-0" />
              {t('Warning: This may return a lot of data and may take several minutes to process.')}
            </div>
          )}

          <Button
            variant="secondary"
            className="font-semibold h-11.25"
            disabled={isLoading || applyDisabled}
            onClick={onApply}
          >
            {t('Apply')}
          </Button>
        </div>
      </div>
    </div>
  );
}
