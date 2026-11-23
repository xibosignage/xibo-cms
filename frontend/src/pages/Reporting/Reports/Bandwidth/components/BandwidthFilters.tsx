/*
 * Xibo Digital Signage
 * Copyright (C) 2006-2026 Xibo Signage Ltd
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published
 * by the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 *
 * This program is also available under a commercial licence from
 * Xibo Signage Ltd. See https://xibosignage.com/terms
 */

import { useTranslation } from 'react-i18next';

import type { BandwidthFilter } from '../BandwidthConfig';
import { INITIAL_FILTER_STATE } from '../BandwidthConfig';

import Button from '@/components/ui/Button';
import MonthPickerInput from '@/components/ui/forms/MonthPickerInput';
import SelectDropdown from '@/components/ui/forms/SelectDropdown';
import { useDisplayOptions } from '@/pages/Reporting/Reports/shared/hooks/useDisplayOptions';

interface BandwidthFiltersProps {
  filter: BandwidthFilter;
  onFilterChange: (patch: Partial<BandwidthFilter>) => void;
  onApply: () => void;
  isLoading: boolean;
}

export default function BandwidthFilters({
  filter,
  onFilterChange,
  onApply,
  isLoading,
}: BandwidthFiltersProps) {
  const { t } = useTranslation();

  const displaySelect = useDisplayOptions();

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
          <MonthPickerInput
            label={t('From Date')}
            value={filter.fromDt}
            onChange={(val) => onFilterChange({ fromDt: val })}
          />

          <MonthPickerInput
            label={t('To Date')}
            value={filter.toDt}
            onChange={(val) => onFilterChange({ toDt: val })}
          />

          <SelectDropdown
            label={t('Display')}
            value={filter.displayId?.toString() ?? ''}
            placeholder={t('Select Display')}
            searchable
            clearable
            translateLabels={false}
            resolveLabel={displaySelect.resolveLabel}
            options={displaySelect.options}
            isLoading={displaySelect.isLoading}
            isLoadingMore={displaySelect.isLoadingMore}
            hasMore={displaySelect.hasMore}
            onSearch={displaySelect.onSearch}
            onLoadMore={displaySelect.onLoadMore}
            onSelect={(val) => onFilterChange({ displayId: val ? Number(val) : null })}
          />
        </div>

        <div className="flex justify-end">
          <Button
            variant="secondary"
            className="font-semibold h-11.25"
            disabled={isLoading || !filter.fromDt || !filter.toDt}
            onClick={onApply}
          >
            {t('Apply')}
          </Button>
        </div>
      </div>
    </div>
  );
}
