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

import { getBucketLabel } from '../DisplayStatusConfig';

import type { DisplayOverviewSummary, DisplayOverviewBucket } from '@/types/displayOverview';

interface StatusChipRowProps {
  summary: DisplayOverviewSummary | undefined;
  status: string | null;
  onStatusChange: (value: string | null) => void;
}

interface ChipDef {
  value: Extract<DisplayOverviewBucket, 'online' | 'needsAttention'>;
  label: string;
  count: number;
}

// Two mutually-exclusive quick-filter chips driving the single `status`
// field — matches DisplayFactory::getSummary()'s Online (logged in,
// authorised, fully synced, fault-free) vs. Needs Attention (everything
// else) split. The advanced Filters panel keeps its own, more granular
// loggedIn/authorised/mediaInventoryStatus/faults fields untouched.
export default function StatusChipRow({ summary, status, onStatusChange }: StatusChipRowProps) {
  const { t } = useTranslation();
  const total = summary?.total ?? 0;

  const chips: ChipDef[] = [
    { value: 'online', label: getBucketLabel(t, 'online'), count: summary?.online ?? 0 },
    {
      value: 'needsAttention',
      label: getBucketLabel(t, 'needsAttention'),
      count: summary?.needsAttention ?? 0,
    },
  ];

  const isAllActive = !status;

  return (
    <div className="flex flex-wrap gap-2">
      <button
        type="button"
        aria-pressed={isAllActive}
        onClick={() => onStatusChange(null)}
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
        const isActive = status === chip.value;
        return (
          <button
            key={chip.value}
            type="button"
            aria-pressed={isActive}
            onClick={() => onStatusChange(isActive ? null : chip.value)}
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
