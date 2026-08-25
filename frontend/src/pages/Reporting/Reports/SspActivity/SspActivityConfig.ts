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

import type { SspActivityRow } from '@/services/sspActivityApi';
import type { DateLike } from '@/utils/date';

export interface SspActivityFilter {
  displayId: number | null;
  partnerId: string;
  activityFromDt: string;
  activityToDt: string;
}

export type SspSummaryGroupBy = 'hour' | 'errorCode' | 'hourerrorcode';

export const SUMMARY_GROUP_OPTIONS: { value: SspSummaryGroupBy; label: string }[] = [
  { value: 'hour', label: 'Hour' },
  { value: 'errorCode', label: 'Error Code' },
  { value: 'hourerrorcode', label: 'Hour and Error Code' },
];

function startOfTodayIso(): string {
  const now = new Date();
  return new Date(now.getFullYear(), now.getMonth(), now.getDate(), 0, 0, 0, 0).toISOString();
}

function endOfTodayIso(): string {
  const now = new Date();
  return new Date(now.getFullYear(), now.getMonth(), now.getDate() + 1, 0, -1, 0, 0).toISOString();
}

export const INITIAL_FILTER_STATE: SspActivityFilter = {
  displayId: null,
  partnerId: '',
  activityFromDt: startOfTodayIso(),
  activityToDt: endOfTodayIso(),
};

export const ACTIVE_FILTER_KEYS: (keyof SspActivityFilter)[] = [
  'displayId',
  'partnerId',
  'activityFromDt',
  'activityToDt',
];

export interface SspSummaryRow {
  key: string;
  date: string;
  time: string;
  campaignId: string | null;
  playCount: number;
  errorCount: number;
  missesCount: number;
  impressions: number;
  impressionActual: number;
  errorCode: string | null;
}

export interface SspSummaryStats {
  totalErrorCount: number;
  totalPlayCount: number;
  totalMissCount: number;
  totalImpressions: number;
  impressionActual: number;
}

export interface SspSummary {
  rows: SspSummaryRow[];
  stats: SspSummaryStats;
}

function pad(n: number): string {
  return String(n).padStart(2, '0');
}

function hourKeyOf(scheduledAt: string | null): string {
  if (!scheduledAt) {
    return '';
  }
  const d = new Date(scheduledAt);
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())} ${pad(d.getHours())}:00`;
}

export function aggregateSummary(
  rows: SspActivityRow[],
  groupBy: SspSummaryGroupBy,
  formatDate: (value: DateLike) => string,
): SspSummary {
  const groups = new Map<string, SspSummaryRow>();

  for (const row of rows) {
    const hourKey = hourKeyOf(row.scheduledAt);
    const errorKey = row.errorCode ?? '';

    let key: string;
    if (groupBy === 'hour') {
      key = hourKey;
    } else if (groupBy === 'errorCode') {
      key = errorKey;
    } else {
      key = `${hourKey} - ${errorKey}`;
    }

    const acc =
      groups.get(key) ??
      ({
        key,
        date: '',
        time: '',
        campaignId: null,
        playCount: 0,
        errorCount: 0,
        missesCount: 0,
        impressions: 0,
        impressionActual: 0,
        errorCode: null,
      } satisfies SspSummaryRow);

    acc.errorCount += row.errors ?? 0;
    acc.playCount += row.isPlayed ? 1 : 0;
    acc.missesCount += !row.isPlayed && !row.isErrored ? 1 : 0;
    acc.impressions += row.impressions ?? 0;
    acc.impressionActual += row.impressionActual ?? 0;
    acc.campaignId = row.campaignId;
    acc.date = row.scheduledAt ? formatDate(row.scheduledAt) : '';
    acc.time = row.scheduledAt ? `${pad(new Date(row.scheduledAt).getHours())}:00` : '';
    acc.errorCode = row.errorCode;

    groups.set(key, acc);
  }

  const aggregated = Array.from(groups.values()).sort((a, b) => a.key.localeCompare(b.key));

  const stats: SspSummaryStats = {
    totalErrorCount: 0,
    totalPlayCount: 0,
    totalMissCount: 0,
    totalImpressions: 0,
    impressionActual: 0,
  };
  for (const row of aggregated) {
    stats.totalErrorCount += row.errorCount;
    stats.totalPlayCount += row.playCount;
    stats.totalMissCount += row.missesCount;
    stats.totalImpressions += row.impressions;
    stats.impressionActual += row.impressionActual;
  }

  return { rows: aggregated, stats };
}
