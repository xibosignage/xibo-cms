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
import { twMerge } from 'tailwind-merge';

import type { DisplayFilterInput } from '../DisplaysConfig';

import type { DisplayOverviewSummary } from '@/types/displayOverview';

type ChipField = 'loggedIn' | 'authorised' | 'mediaInventoryStatus' | 'faults';

interface StatusChipRowProps {
  summary: DisplayOverviewSummary | undefined;
  filters: DisplayFilterInput;
  onFilterChange: (name: ChipField, value: string | null) => void;
  onClearAll: () => void;
}

interface ChipDef {
  field: ChipField;
  value: string;
  label: string;
  count: number;
}

// Each chip toggles one concrete list-filter field directly (no re-derived
// "bucket" in between) — Logged In/Logged Out and Authorised/Unauthorised and
// Up-to-date/Out-of-date are pairs sharing one field (so picking one in a
// pair naturally replaces the other), while Faults is independent and can be
// combined with any of them. mediaInventoryStatus's "-1" is the same
// "not fully synced" sentinel DisplayFactory::query() already understands
// for that field (mediaInventoryStatus <> STATUS_DONE) — broader than the
// Filters modal's own "Out of date" option (which targets the specific
// Pending status only), since a quick chip is meant to be the plain
// complement of Up-to-date.
export default function StatusChipRow({
  summary,
  filters,
  onFilterChange,
  onClearAll,
}: StatusChipRowProps) {
  const { t } = useTranslation();
  const total = summary?.total ?? 0;

  const chips: ChipDef[] = [
    { field: 'loggedIn', value: '1', label: t('Logged In'), count: summary?.loggedIn ?? 0 },
    {
      field: 'loggedIn',
      value: '0',
      label: t('Logged Out'),
      count: total - (summary?.loggedIn ?? 0),
    },
    {
      field: 'authorised',
      value: '1',
      label: t('Authorised'),
      count: summary?.authorised ?? 0,
    },
    {
      field: 'authorised',
      value: '0',
      label: t('Unauthorised'),
      count: total - (summary?.authorised ?? 0),
    },
    {
      field: 'mediaInventoryStatus',
      value: '1',
      label: t('Up-to-date'),
      count: summary?.upToDate ?? 0,
    },
    {
      field: 'mediaInventoryStatus',
      value: '-1',
      label: t('Out-of-date'),
      count: total - (summary?.upToDate ?? 0),
    },
    { field: 'faults', value: '1', label: t('Faults'), count: summary?.faults ?? 0 },
  ];

  const isAllActive =
    !filters.loggedIn && !filters.authorised && !filters.mediaInventoryStatus && !filters.faults;

  return (
    <div className="flex flex-wrap gap-2">
      <button
        type="button"
        aria-pressed={isAllActive}
        onClick={onClearAll}
        className={twMerge(
          'inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 text-xs font-semibold transition-colors cursor-pointer',
          isAllActive
            ? 'bg-xibo-blue-600 text-white hover:bg-xibo-blue-700'
            : 'bg-gray-50 text-gray-800 hover:bg-gray-100',
        )}
      >
        {t('All')}
        <span
          className={twMerge(
            'font-mono tabular-nums',
            isAllActive ? 'text-white/80' : 'text-gray-400',
          )}
        >
          {total.toLocaleString()}
        </span>
      </button>
      {chips.map((chip) => {
        const isActive = filters[chip.field] === chip.value;
        return (
          <button
            key={`${chip.field}-${chip.value}`}
            type="button"
            aria-pressed={isActive}
            onClick={() => onFilterChange(chip.field, isActive ? null : chip.value)}
            className={twMerge(
              'inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 text-xs font-semibold transition-colors cursor-pointer',
              isActive
                ? 'bg-xibo-blue-600 text-white hover:bg-xibo-blue-700'
                : 'bg-gray-50 text-gray-800 hover:bg-gray-100',
            )}
          >
            {chip.label}
            <span
              className={twMerge(
                'font-mono tabular-nums',
                isActive ? 'text-white/80' : 'text-gray-400',
              )}
            >
              {chip.count.toLocaleString()}
            </span>
          </button>
        );
      })}
    </div>
  );
}
