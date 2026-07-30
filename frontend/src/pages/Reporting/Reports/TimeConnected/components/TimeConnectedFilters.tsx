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

import { useTranslation } from 'react-i18next';

import type { TimeConnectedFilter } from '../TimeConnectedConfig';
import {
  DATE_RANGE_OPTIONS,
  GROUP_BY_OPTIONS,
  INITIAL_FILTER_STATE,
  SORT_BY_OPTIONS,
} from '../TimeConnectedConfig';

import Button from '@/components/ui/Button';
import DateRangeFilter from '@/components/ui/DateRangeFilter';
import SelectDropdown from '@/components/ui/forms/SelectDropdown';
import { DisplayGroupMultiSelect } from '@/pages/Schedule/Schedule/components/DisplayGroupMultiSelect';
import type { DisplayGroupMultiSelectValue } from '@/pages/Schedule/Schedule/components/DisplayGroupMultiSelect';

interface TimeConnectedFiltersProps {
  filter: TimeConnectedFilter;
  onFilterChange: (patch: Partial<TimeConnectedFilter>) => void;
  onApply: () => void;
  isLoading: boolean;
}

export default function TimeConnectedFilters({
  filter,
  onFilterChange,
  onApply,
  isLoading,
}: TimeConnectedFiltersProps) {
  const { t } = useTranslation();

  const displayValue: DisplayGroupMultiSelectValue = {
    displaySpecificGroupIds: filter.displaySpecificGroupIds,
    displayGroupIds: filter.displayGroupIds,
  };

  const handleDisplayChange = (value: DisplayGroupMultiSelectValue) => {
    onFilterChange({
      displaySpecificGroupIds: value.displaySpecificGroupIds,
      displayGroupIds: value.displayGroupIds,
    });
  };

  const handleDateRangeChange = (_name: string, value: string | number | null) => {
    onFilterChange({ reportFilter: String(value ?? ''), fromDt: null, toDt: null });
  };

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
            onChange={handleDateRangeChange}
          />

          <SelectDropdown
            label={t('Group By')}
            value={filter.groupByFilter}
            options={GROUP_BY_OPTIONS.map((o) => ({ value: o.value, label: t(o.label) }))}
            onSelect={(val) =>
              onFilterChange({ groupByFilter: val as TimeConnectedFilter['groupByFilter'] })
            }
          />

          <div className="flex flex-col gap-1">
            <label className="text-sm font-semibold text-gray-500 leading-5">
              {t('Display/Display Groups')}
            </label>
            <DisplayGroupMultiSelect
              value={displayValue}
              onChange={handleDisplayChange}
              triggerClassName="bg-white"
            />
          </div>

          <SelectDropdown
            label={t('Sort By')}
            value={filter.sortBy}
            options={SORT_BY_OPTIONS.map((o) => ({ value: o.value, label: t(o.label) }))}
            onSelect={(val) => onFilterChange({ sortBy: val as TimeConnectedFilter['sortBy'] })}
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
