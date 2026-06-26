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

import type { DisplayAlertsFilter } from '../DisplayAlertsConfig';
import {
  DATE_RANGE_OPTIONS,
  EVENT_TYPE_OPTIONS,
  INITIAL_FILTER_STATE,
} from '../DisplayAlertsConfig';

import Button from '@/components/ui/Button';
import DateRangeFilter from '@/components/ui/DateRangeFilter';
import Checkbox from '@/components/ui/forms/Checkbox';
import MultiSelectDropdown from '@/components/ui/forms/MultiSelectDropdown';
import SelectDropdown from '@/components/ui/forms/SelectDropdown';
import TagInput from '@/components/ui/forms/TagInput';
import { useDisplayGroupOptions } from '@/pages/Reporting/Reports/shared/hooks/useDisplayGroupOptions';
import { useDisplayOptions } from '@/pages/Reporting/Reports/shared/hooks/useDisplayOptions';

interface DisplayAlertsFiltersProps {
  filter: DisplayAlertsFilter;
  onFilterChange: (patch: Partial<DisplayAlertsFilter>) => void;
  onApply: () => void;
  isLoading: boolean;
}

export default function DisplayAlertsFilters({
  filter,
  onFilterChange,
  onApply,
  isLoading,
}: DisplayAlertsFiltersProps) {
  const { t } = useTranslation();
  const displaySelect = useDisplayOptions();
  const displayGroupOptions = useDisplayGroupOptions();

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
            label={t('Event Type')}
            value={filter.eventType}
            placeholder={t('All Event Types')}
            clearable
            options={EVENT_TYPE_OPTIONS.map((o) => ({ value: o.value, label: t(o.label) }))}
            onSelect={(val) => onFilterChange({ eventType: val ?? '' })}
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

          <TagInput
            label={t('Tags')}
            value={filter.tags}
            onChange={(tags) => onFilterChange({ tags })}
            placeholder={t('Add tags')}
            suffix={
              <button
                type="button"
                onClick={() => onFilterChange({ exactTags: !filter.exactTags })}
                title={t('Match exact characters only')}
                className={twMerge(
                  'flex items-center justify-center px-3 h-full cursor-pointer',
                  filter.exactTags
                    ? 'bg-xibo-blue-600 text-white'
                    : 'text-xibo-blue-600 hover:text-xibo-blue-800',
                )}
              >
                <Equal size={18} />
              </button>
            }
          />

          <div className="flex items-center h-11.25">
            <Checkbox
              id="onlyLoggedIn"
              label={t('Only show currently logged in?')}
              checked={filter.onlyLoggedIn}
              onChange={(e) => onFilterChange({ onlyLoggedIn: e.target.checked })}
            />
          </div>
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
