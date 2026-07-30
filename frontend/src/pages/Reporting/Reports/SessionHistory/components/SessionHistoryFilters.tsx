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

import type { SessionHistoryFilter, SessionHistoryLogType } from '../SessionHistoryConfig';
import { DATE_RANGE_OPTIONS, INITIAL_FILTER_STATE, TYPE_OPTIONS } from '../SessionHistoryConfig';

import Button from '@/components/ui/Button';
import DateRangeFilter from '@/components/ui/DateRangeFilter';
import SelectDropdown from '@/components/ui/forms/SelectDropdown';
import TextInput from '@/components/ui/forms/TextInput';
import { useUserOptions } from '@/pages/Reporting/Reports/shared/hooks/useUserOptions';

interface SessionHistoryFiltersProps {
  filter: SessionHistoryFilter;
  onFilterChange: (patch: Partial<SessionHistoryFilter>) => void;
  onApply: () => void;
  isLoading: boolean;
}

export default function SessionHistoryFilters({
  filter,
  onFilterChange,
  onApply,
  isLoading,
}: SessionHistoryFiltersProps) {
  const { t } = useTranslation();
  const userSelect = useUserOptions();

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
            label={t('User')}
            value={filter.userId?.toString() ?? ''}
            placeholder={t('All Users')}
            searchable
            clearable
            resolveLabel={userSelect.resolveLabel}
            options={userSelect.options}
            isLoading={userSelect.isLoading}
            isLoadingMore={userSelect.isLoadingMore}
            hasMore={userSelect.hasMore}
            onSearch={userSelect.onSearch}
            onLoadMore={userSelect.onLoadMore}
            onSelect={(val) => onFilterChange({ userId: val ? Number(val) : null })}
          />

          <TextInput
            name="sessionHistoryId"
            label={t('Session History ID')}
            placeholder=" "
            value={filter.sessionHistoryId}
            onChange={(val) => onFilterChange({ sessionHistoryId: val })}
          />

          <SelectDropdown
            label={t('Report Type')}
            value={filter.type}
            options={TYPE_OPTIONS.map((o) => ({ value: o.value, label: t(o.label) }))}
            onSelect={(val) => onFilterChange({ type: val as SessionHistoryLogType })}
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
