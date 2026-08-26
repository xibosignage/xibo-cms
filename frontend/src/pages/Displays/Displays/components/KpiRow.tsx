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

import { Info } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { twMerge } from 'tailwind-merge';

import { BUCKET_COLORS, BUCKET_ICON, getBucketLabel } from '../DisplayStatusConfig';

import type { DisplayOverviewBucket } from '@/types/displayOverview';

interface KpiRowProps {
  total: number | undefined;
  online: number | undefined;
  offline: number | undefined;
  needsAttention: number | undefined;
  faults: number | undefined;
  offlineTrend: number | undefined;
  onlineTrend: number | undefined;
  faultsTrend: number | undefined;
  isLoading: boolean;
}

interface KpiTileProps {
  bucket: DisplayOverviewBucket;
  count: number | undefined;
  total: number | undefined;
  hint: string;
  // 24h trend count — omitted (no bucket has a fabricated trend) rather than
  // passed as 0, since needsAttention genuinely has no history to show.
  trend?: number;
  trendLabel?: string;
  isLoading: boolean;
}

function KpiTile({ bucket, count, total, hint, trend, trendLabel, isLoading }: KpiTileProps) {
  const { t } = useTranslation();
  const Icon = BUCKET_ICON[bucket];
  const colors = BUCKET_COLORS[bucket];

  return (
    <div className="relative flex flex-col gap-2 rounded-lg border border-gray-200 bg-slate-50 p-4">
      {!isLoading && !!trend && (
        <p className="absolute right-3 top-3 text-right text-[11px] font-medium text-gray-400">
          {t('{{count}} {{label}} in last 24h', { count: trend, label: trendLabel })}
        </p>
      )}

      <div className="flex items-center gap-2 text-xs font-semibold uppercase tracking-wide text-gray-500">
        <span className={twMerge('flex items-center justify-center rounded-md p-1', colors.dot)}>
          <Icon className="size-3.5 shrink-0 text-white" />
        </span>
        <span>{getBucketLabel(t, bucket)}</span>
        <Info className="size-3.5 shrink-0 text-gray-300" aria-label={hint} role="img">
          <title>{hint}</title>
        </Info>
      </div>

      {isLoading ? (
        <div className="h-8 w-20 animate-pulse rounded bg-gray-200" />
      ) : (
        <div className="text-3xl font-bold text-gray-800 leading-none">
          {(count ?? 0).toLocaleString()}
          {total !== undefined && (
            <span className="ml-1 text-base font-medium text-gray-400">
              / {total.toLocaleString()}
            </span>
          )}
        </div>
      )}
    </div>
  );
}

export default function KpiRow({
  total,
  online,
  offline,
  needsAttention,
  faults,
  offlineTrend,
  onlineTrend,
  faultsTrend,
  isLoading,
}: KpiRowProps) {
  const { t } = useTranslation();

  return (
    <div className="grid grid-cols-2 sm:grid-cols-4 gap-4">
      <KpiTile
        bucket="online"
        count={online}
        total={total}
        hint={t('Displays that are logged in, fully synced, authorised and licensed')}
        trend={onlineTrend}
        trendLabel={t('back online')}
        isLoading={isLoading}
      />
      <KpiTile
        bucket="needsAttention"
        count={needsAttention}
        total={total}
        hint={t('Out of sync, unauthorised, or a licence issue')}
        isLoading={isLoading}
      />
      <KpiTile
        bucket="offline"
        count={offline}
        total={total}
        hint={t('Not currently logged in to the CMS')}
        trend={offlineTrend}
        trendLabel={t('went offline')}
        isLoading={isLoading}
      />
      <KpiTile
        bucket="faults"
        count={faults}
        total={total}
        hint={t('Display errors')}
        trend={faultsTrend}
        trendLabel={t('new')}
        isLoading={isLoading}
      />
    </div>
  );
}
