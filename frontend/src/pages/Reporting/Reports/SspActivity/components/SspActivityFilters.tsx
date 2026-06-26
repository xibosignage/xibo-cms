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

import type { SspActivityFilter } from '../SspActivityConfig';
import { INITIAL_FILTER_STATE } from '../SspActivityConfig';

import Button from '@/components/ui/Button';
import DatePickerInput from '@/components/ui/forms/DatePickerInput';
import SelectDropdown, { type SelectOption } from '@/components/ui/forms/SelectDropdown';
import { useDisplayOptions } from '@/pages/Reporting/Reports/shared/hooks/useDisplayOptions';

interface SspActivityFiltersProps {
  filter: SspActivityFilter;
  onFilterChange: (patch: Partial<SspActivityFilter>) => void;
  onApply: () => void;
  isLoading: boolean;
  partnerOptions: SelectOption[];
  partnersLoading: boolean;
}

export default function SspActivityFilters({
  filter,
  onFilterChange,
  onApply,
  isLoading,
  partnerOptions,
  partnersLoading,
}: SspActivityFiltersProps) {
  const { t } = useTranslation();

  const displaySelect = useDisplayOptions();

  const handleReset = () => {
    onFilterChange({ ...INITIAL_FILTER_STATE });
  };

  const displayMissing = filter.displayId == null;

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
          <SelectDropdown
            label={t('Display')}
            value={filter.displayId?.toString() ?? ''}
            placeholder={t('Select Display')}
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

          <DatePickerInput
            label={t('From Date')}
            value={filter.activityFromDt}
            onChange={(val) => onFilterChange({ activityFromDt: val })}
          />

          <DatePickerInput
            label={t('To Date')}
            value={filter.activityToDt}
            onChange={(val) => onFilterChange({ activityToDt: val })}
          />

          <SelectDropdown
            label={t('Partner')}
            value={filter.partnerId}
            placeholder={t('All Partners')}
            searchable
            clearable
            optional
            isLoading={partnersLoading}
            options={partnerOptions}
            onSelect={(val) => onFilterChange({ partnerId: val })}
          />
        </div>

        <div className="flex justify-end">
          {displayMissing && (
            <div className="flex items-center gap-1.5 text-sm text-amber-600 mt-2 mr-auto">
              <TriangleAlert className="w-4 h-4 shrink-0" />
              {t('Please select a display')}
            </div>
          )}
          <Button
            variant="secondary"
            className="font-semibold h-11.25"
            disabled={isLoading || displayMissing}
            onClick={onApply}
          >
            {t('Apply')}
          </Button>
        </div>
      </div>
    </div>
  );
}
