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
  activeBucket: DisplayOverviewBucket | null;
  onSelectBucket: (bucket: DisplayOverviewBucket | null) => void;
}

const BUCKETS: DisplayOverviewBucket[] = ['online', 'needsAttention', 'offline', 'faults'];

// The quick status chips from the Display Management reference mock
// (display-management.html's .chip-row) — a faster shortcut onto the same
// bucket the Health Status filter field drives, not a separate filter.
export default function StatusChipRow({
  summary,
  activeBucket,
  onSelectBucket,
}: StatusChipRowProps) {
  const { t } = useTranslation();

  const chips: { key: DisplayOverviewBucket | 'all'; label: string; count: number }[] = [
    { key: 'all', label: t('All'), count: summary?.total ?? 0 },
    ...BUCKETS.map((bucket) => ({
      key: bucket,
      label: getBucketLabel(t, bucket),
      count: summary?.[bucket] ?? 0,
    })),
  ];

  return (
    <div className="flex flex-wrap gap-2">
      {chips.map((chip) => {
        const isActive = chip.key === 'all' ? activeBucket === null : activeBucket === chip.key;
        return (
          <button
            key={chip.key}
            type="button"
            aria-pressed={isActive}
            onClick={() => onSelectBucket(chip.key === 'all' ? null : chip.key)}
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
