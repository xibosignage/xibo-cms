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

import type { LibraryUsageFilter } from '../LibraryUsageConfig';
import { INITIAL_FILTER_STATE } from '../LibraryUsageConfig';
import { useUserGroupOptions } from '../hooks/useUserGroupOptions';

import Button from '@/components/ui/Button';
import SelectDropdown from '@/components/ui/forms/SelectDropdown';

interface LibraryUsageFiltersProps {
  filter: LibraryUsageFilter;
  onFilterChange: (patch: Partial<LibraryUsageFilter>) => void;
  onApply: () => void;
  isLoading: boolean;
}

export default function LibraryUsageFilters({
  filter,
  onFilterChange,
  onApply,
  isLoading,
}: LibraryUsageFiltersProps) {
  const { t } = useTranslation();
  const { users, groups } = useUserGroupOptions();

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
          <SelectDropdown
            label={t('User')}
            value={filter.userId ? String(filter.userId) : ''}
            placeholder={t('All Users')}
            options={users.options}
            searchable
            clearable
            isLoading={users.isLoading}
            onSearch={users.onSearch}
            onLoadMore={users.onLoadMore}
            hasMore={users.hasMore}
            isLoadingMore={users.isLoadingMore}
            resolveLabel={users.resolveLabel}
            onSelect={(val) => onFilterChange({ userId: val ? Number(val) : null })}
          />

          <SelectDropdown
            label={t('User Group')}
            value={filter.groupId ? String(filter.groupId) : ''}
            placeholder={t('All User Groups')}
            options={groups.options}
            searchable
            clearable
            isLoading={groups.isLoading}
            onSearch={groups.onSearch}
            onLoadMore={groups.onLoadMore}
            hasMore={groups.hasMore}
            isLoadingMore={groups.isLoadingMore}
            resolveLabel={groups.resolveLabel}
            onSelect={(val) => onFilterChange({ groupId: val ? Number(val) : null })}
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
