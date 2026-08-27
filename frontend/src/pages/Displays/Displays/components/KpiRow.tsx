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

import Badge from '@/components/ui/Badge';
import type { UIStatus } from '@/types/uiStatus';

interface KpiRowProps {
  total: number | undefined;
  loggedIn: number | undefined;
  authorised: number | undefined;
  upToDate: number | undefined;
  faults: number | undefined;
  faultsTrend: number | undefined;
  isLoading: boolean;
}

interface KpiTileProps {
  label: string;
  count: number | undefined;
  total: number | undefined;
  /** Renders the label as a coloured Badge instead of plain text (Up-to-date/Faults). */
  badgeType?: UIStatus;
  // 24h trend count — omitted (only Faults has real history to show) rather
  // than passed as 0.
  trend?: number;
  trendLabel?: string;
  isLoading: boolean;
}

function KpiTile({ label, count, total, badgeType, trend, trendLabel, isLoading }: KpiTileProps) {
  const { t } = useTranslation();

  return (
    <div className="relative flex flex-col gap-2 rounded-lg border border-gray-200 bg-slate-50 p-4">
      {!isLoading && !!trend && (
        <p className="absolute right-3 top-3 text-right text-[11px] font-medium text-gray-400">
          {t('{{count}} {{label}} in last 24h', { count: trend, label: trendLabel })}
        </p>
      )}

      <div className="flex h-6 items-center">
        {badgeType ? (
          <Badge type={badgeType} className="w-fit">
            {label}
          </Badge>
        ) : (
          <span className="text-xs font-semibold uppercase tracking-wide text-gray-500">
            {label}
          </span>
        )}
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
  loggedIn,
  authorised,
  upToDate,
  faults,
  faultsTrend,
  isLoading,
}: KpiRowProps) {
  const { t } = useTranslation();

  return (
    <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-4">
      <KpiTile label={t('Total Displays')} count={total} total={undefined} isLoading={isLoading} />
      <KpiTile label={t('Logged In')} count={loggedIn} total={total} isLoading={isLoading} />
      <KpiTile label={t('Authorised')} count={authorised} total={total} isLoading={isLoading} />
      <KpiTile
        label={t('Up-to-date')}
        count={upToDate}
        total={total}
        badgeType="success"
        isLoading={isLoading}
      />
      <KpiTile
        label={t('Faults')}
        count={faults}
        total={total}
        badgeType="danger"
        trend={faultsTrend}
        trendLabel={t('new')}
        isLoading={isLoading}
      />
    </div>
  );
}
