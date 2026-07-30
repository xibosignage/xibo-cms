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

import { Equal } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { twMerge } from 'tailwind-merge';

import type { ProofOfPlayFilter } from '../ProofOfPlayConfig';
import {
  DATE_RANGE_OPTIONS,
  GROUP_BY_OPTIONS,
  INITIAL_FILTER_STATE,
  TAGS_TYPE_OPTIONS,
  TYPE_OPTIONS,
} from '../ProofOfPlayConfig';

import PaginatedMultiSelectDropdown from './PaginatedMultiSelectDropdown';

import Button from '@/components/ui/Button';
import DateRangeFilter from '@/components/ui/DateRangeFilter';
import AndOrButton from '@/components/ui/forms/AndOrButton';
import MultiSelectDropdown from '@/components/ui/forms/MultiSelectDropdown';
import SelectDropdown from '@/components/ui/forms/SelectDropdown';
import TagInput from '@/components/ui/forms/TagInput';
import { useCampaignOptions } from '@/pages/Reporting/Reports/shared/hooks/useCampaignOptions';
import { useDisplayGroupOptions } from '@/pages/Reporting/Reports/shared/hooks/useDisplayGroupOptions';
import { useDisplayOptions } from '@/pages/Reporting/Reports/shared/hooks/useDisplayOptions';
import { useLayoutOptions } from '@/pages/Reporting/Reports/shared/hooks/useLayoutOptions';
import { useMediaOptions } from '@/pages/Reporting/Reports/shared/hooks/useMediaOptions';

interface ProofOfPlayFiltersProps {
  filter: ProofOfPlayFilter;
  onFilterChange: (patch: Partial<ProofOfPlayFilter>) => void;
  onApply: () => void;
  isLoading: boolean;
}

export default function ProofOfPlayFilters({
  filter,
  onFilterChange,
  onApply,
  isLoading,
}: ProofOfPlayFiltersProps) {
  const { t } = useTranslation();
  const displaySelect = useDisplayOptions();
  const displayGroupOptions = useDisplayGroupOptions();
  const layoutOpts = useLayoutOptions();
  const campaignOpts = useCampaignOptions();
  const mediaOpts = useMediaOptions();

  const handleReset = () => {
    onFilterChange({ ...INITIAL_FILTER_STATE });
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
            value={filter.reportFilter || ''}
            options={DATE_RANGE_OPTIONS.map((o) => ({ ...o, label: t(o.label) }))}
            onChange={(_name, value) => onFilterChange({ reportFilter: String(value ?? '') })}
          />

          <SelectDropdown
            label={t('Group By')}
            value={filter.groupBy}
            options={GROUP_BY_OPTIONS.map((o) => ({ value: o.value, label: t(o.label) }))}
            onSelect={(val) => onFilterChange({ groupBy: val })}
          />

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

          <MultiSelectDropdown
            label={t('Display Group')}
            value={filter.displayGroupId.map(String)}
            options={displayGroupOptions}
            placeholder={t('All Display Groups')}
            showTags
            onChange={(vals) => onFilterChange({ displayGroupId: vals.map(Number) })}
          />

          <PaginatedMultiSelectDropdown
            label={t('Layout')}
            value={filter.layoutId.map(String)}
            options={layoutOpts.options}
            isLoading={layoutOpts.isLoading}
            isLoadingMore={layoutOpts.isLoadingMore}
            hasMore={layoutOpts.hasMore}
            onSearch={layoutOpts.onSearch}
            onLoadMore={layoutOpts.onLoadMore}
            placeholder={t('All Layouts')}
            showTags
            onChange={(vals) => onFilterChange({ layoutId: vals.map(Number) })}
          />

          <SelectDropdown
            label={t('Campaign')}
            value={filter.parentCampaignId ? String(filter.parentCampaignId) : ''}
            options={campaignOpts.options}
            searchable
            isLoading={campaignOpts.isLoading}
            isLoadingMore={campaignOpts.isLoadingMore}
            hasMore={campaignOpts.hasMore}
            onSearch={campaignOpts.onSearch}
            onLoadMore={campaignOpts.onLoadMore}
            placeholder={t('All Campaigns')}
            clearable
            onSelect={(val) => onFilterChange({ parentCampaignId: val ? Number(val) : null })}
          />

          <PaginatedMultiSelectDropdown
            label={t('Media')}
            value={filter.mediaId.map(String)}
            options={mediaOpts.options}
            isLoading={mediaOpts.isLoading}
            isLoadingMore={mediaOpts.isLoadingMore}
            hasMore={mediaOpts.hasMore}
            onSearch={mediaOpts.onSearch}
            onLoadMore={mediaOpts.onLoadMore}
            placeholder={t('All Media')}
            showTags
            onChange={(vals) => onFilterChange({ mediaId: vals.map(Number) })}
          />

          <SelectDropdown
            label={t('Type')}
            value={filter.type}
            options={TYPE_OPTIONS.map((o) => ({ value: o.value, label: t(o.label) }))}
            onSelect={(val) => onFilterChange({ type: val })}
          />

          <SelectDropdown
            label={t('Tags From')}
            value={filter.tagsType}
            options={TAGS_TYPE_OPTIONS.map((o) => ({ value: o.value, label: t(o.label) }))}
            onSelect={(val) => onFilterChange({ tagsType: val })}
          />

          <TagInput
            label={t('Tags')}
            value={filter.tags}
            onChange={(tags) => onFilterChange({ tags })}
            placeholder={t('Add tags')}
            allowValues={false}
            prefix={
              <AndOrButton
                value={(filter.logicalOperator as 'AND' | 'OR') || 'OR'}
                onChange={(val) => onFilterChange({ logicalOperator: val })}
              />
            }
            suffix={
              <Button
                variant="tertiary"
                leftIcon={Equal}
                ariaLabel={t('Match exact characters only')}
                aria-pressed={filter.exactTags}
                onClick={() => onFilterChange({ exactTags: !filter.exactTags })}
                className={twMerge(
                  'p-1.5',
                  filter.exactTags
                    ? 'bg-xibo-blue-600 text-white hover:bg-xibo-blue-700 hover:text-white'
                    : 'text-xibo-blue-600 hover:text-xibo-blue-800',
                )}
              />
            }
          />
        </div>

        <div className="flex justify-end">
          <Button
            variant="secondary"
            className="font-semibold h-11.25"
            disabled={isLoading}
            onClick={onApply}
          >
            {t('Apply')}
          </Button>
        </div>
      </div>
    </div>
  );
}
