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

import type { TFunction } from 'i18next';
import { DateTime } from 'luxon';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';

import Button from '@/components/ui/Button';
import type { ProofOfPlayRow } from '@/services/proofOfPlayApi';
import { formatDurationText } from '@/utils/formatters';

/** One column of the report's export, so CSV and print stay in step. */
interface ExportColumn {
  header: string;
  value: (row: ProofOfPlayRow) => string | number;
}

/**
 * How far back both proof of play panels look.
 *
 * Defined once because the per-display and the by-group panel sit on different pages: if
 * each carried its own figure they would eventually disagree, and the two would quietly
 * stop being comparable.
 */
export const PROOF_OF_PLAY_WINDOW_HOURS = 7 * 24;

/** How many rows to list before the rest are folded away. */
export const VISIBLE_ROWS = 8;

export interface PlayListItem {
  id: number;
  label: string;
  plays: number;
  /** Seconds on screen across every play. */
  duration: number;
}

/**
 * A window ending now, expressed the way the report filters do. Only named presets like "today"
 * and calendar ranges exist, so a rolling window has to be built as an explicit range with times.
 *
 * Returns the ends alongside the filter so the header can state the period it is showing.
 */
export function rollingWindow(hours: number): { filter: string; from: DateTime; to: DateTime } {
  const to = DateTime.now();
  const from = to.minus({ hours });
  const key = (value: DateTime) => value.toFormat("yyyy-MM-dd'T'HH:mm");

  return { filter: `range:${key(from)}|${key(to)}`, from, to };
}

/** Hours and minutes. Seconds are noise at this scale. */
export function formatHoursMinutes(totalSeconds: number): { hours: number; minutes: number } {
  const whole = Math.max(0, Math.round(totalSeconds));

  return { hours: Math.floor(whole / 3600), minutes: Math.floor((whole % 3600) / 60) };
}

/** "Aug 1 - Aug 21, 2026", stating the year once when both ends share it. */
export function formatRange(from: DateTime, to: DateTime): string {
  const dash = '\u2013';

  if (from.year === to.year) {
    return `${from.toFormat('LLL d')} ${dash} ${to.toFormat('LLL d, yyyy')}`;
  }

  return `${from.toFormat('LLL d, yyyy')} ${dash} ${to.toFormat('LLL d, yyyy')}`;
}

/** Quotes a CSV field the same way the shared data table does. */
function csvField(value: string | number): string {
  return `"${String(value).replace(/"/g, '""')}"`;
}

/**
 * Saves the rows on screen as CSV, for this display only.
 *
 * Built from what is already loaded rather than calling the CMS export, which returns raw stat
 * rows for the whole network. This is the aggregated per-layout view the panel is showing.
 */
export function downloadCsv(
  fileName: string,
  headers: string[],
  rows: (string | number)[][],
): void {
  const content = [headers, ...rows].map((row) => row.map(csvField).join(',')).join('\n');

  const blob = new Blob([content], { type: 'text/csv;charset=utf-8;' });
  const url = URL.createObjectURL(blob);
  const link = document.createElement('a');

  link.href = url;
  link.setAttribute('download', fileName);
  document.body.appendChild(link);
  link.click();
  document.body.removeChild(link);
  URL.revokeObjectURL(url);
}

/**
 * Compact duration for a row, e.g. "1h 12m", "12m" or "45s". Units that would read as zero are
 * left off, so a short play does not render as "0h 0m 45s".
 */
function formatDurationShort(totalSeconds: number): string {
  const whole = Math.max(0, Math.round(totalSeconds));
  const { hours, minutes } = formatHoursMinutes(whole);

  if (hours > 0) {
    return `${hours}h ${minutes}m`;
  }

  if (minutes > 0) {
    return `${minutes}m`;
  }

  return `${whole}s`;
}

function capitalize(value: string): string {
  return value ? value.charAt(0).toUpperCase() + value.slice(1) : '';
}

/**
 * The columns the Proof of Play report exports, in its order.
 *
 * Kept identical on purpose: an export from this panel should be the same file you would get from
 * the full report, just already narrowed to one display.
 */
export function exportColumns(
  t: TFunction,
): { header: string; value: (row: ProofOfPlayRow) => string | number }[] {
  return [
    { header: t('Type'), value: (row) => capitalize(row.type) },
    { header: t('Display ID'), value: (row) => row.displayId },
    { header: t('Display'), value: (row) => row.display },
    { header: t('Display Group ID'), value: (row) => row.displayGroupId ?? '' },
    { header: t('Display Group'), value: (row) => row.displayGroup ?? '' },
    { header: t('Tag ID'), value: (row) => row.tagId ?? '' },
    { header: t('Tag Name'), value: (row) => row.tagName ?? '' },
    { header: t('Campaign'), value: (row) => row.parentCampaign ?? '' },
    { header: t('Layout ID'), value: (row) => row.layoutId },
    { header: t('Layout'), value: (row) => row.layout },
    { header: t('Widget ID'), value: (row) => row.widgetId },
    { header: t('Media'), value: (row) => row.media },
    { header: t('Tag'), value: (row) => row.tag },
    { header: t('Number of Plays'), value: (row) => row.numberPlays },
    { header: t('Total Duration'), value: (row) => formatDurationText(row.duration, t) },
    { header: t('Total Duration (s)'), value: (row) => row.duration },
    { header: t('First Period Shown'), value: (row) => row.minStart },
    { header: t('Last Period Shown'), value: (row) => row.maxEnd },
  ];
}

export function StatTile({ label, children }: { label: string; children: React.ReactNode }) {
  return (
    <div className="rounded-lg border border-gray-200 bg-gray-50 px-4 py-3">
      <p className="text-[11px] font-semibold uppercase tracking-wide text-gray-400">{label}</p>
      <p className="mt-1 text-2xl font-semibold tabular-nums text-gray-800">{children}</p>
    </div>
  );
}

/** Totals the report rows into one entry per key, since a thing can span several rows. */
export function totalPlaysBy(
  rows: ProofOfPlayRow[],
  keyOf: (row: ProofOfPlayRow) => number,
  labelOf: (row: ProofOfPlayRow) => string,
): PlayListItem[] {
  const totals = new Map<number, PlayListItem>();

  for (const row of rows) {
    const id = keyOf(row);
    const existing = totals.get(id);

    if (existing) {
      existing.plays += row.numberPlays;
      existing.duration += row.duration;
    } else {
      totals.set(id, {
        id,
        label: labelOf(row),
        plays: row.numberPlays,
        duration: row.duration,
      });
    }
  }

  return [...totals.values()].sort((a, b) => b.plays - a.plays);
}

/**
 * One row per item: name on the left, plays and share of the total on the right.
 *
 * Rows become buttons when onSelect is given, which is what makes the layout list drill down.
 */
export function PlayList({
  items,
  totalPlays,
  labels,
  onSelect,
}: {
  items: PlayListItem[];
  totalPlays: number;
  labels: { showingTop: string; showingAll: string; showAll: string; showFewer: string };
  onSelect?: (item: PlayListItem) => void;
}) {
  const { t } = useTranslation();
  const [showAll, setShowAll] = useState(false);

  const visible = showAll ? items : items.slice(0, VISIBLE_ROWS);

  return (
    <>
      <div className="divide-y divide-gray-100">
        {visible.map((item) => {
          const share = totalPlays > 0 ? Math.round((item.plays / totalPlays) * 100) : 0;

          const row = (
            <>
              <span className="min-w-0 flex-1 truncate text-left text-sm font-medium text-gray-800">
                {item.label}
              </span>
              <span className="w-28 shrink-0 text-right text-sm tabular-nums text-gray-500">
                {item.plays.toLocaleString()} {t('plays')}
              </span>
              <span className="w-20 shrink-0 text-right text-sm tabular-nums text-gray-500">
                {formatDurationShort(item.duration)}
              </span>
              <span className="w-12 shrink-0 text-right text-sm tabular-nums text-gray-400">
                {share}%
              </span>
            </>
          );

          return onSelect ? (
            <button
              key={item.id}
              type="button"
              onClick={() => onSelect(item)}
              title={t('Show what played inside this layout')}
              className="flex w-full cursor-pointer items-center gap-4 px-1 py-3 text-left transition-colors hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-xibo-blue-500"
            >
              {row}
            </button>
          ) : (
            <div key={item.id} className="flex items-center gap-4 px-1 py-3">
              {row}
            </div>
          );
        })}
      </div>

      {items.length > VISIBLE_ROWS && (
        <div className="mt-3 flex items-center justify-between gap-4">
          <p className="text-xs text-gray-500">{showAll ? labels.showingAll : labels.showingTop}</p>
          <Button variant="link" onClick={() => setShowAll((previous) => !previous)}>
            {showAll ? labels.showFewer : labels.showAll}
          </Button>
        </div>
      )}
    </>
  );
}

/**
 * The detail table, hidden on screen and the only thing print.css lets through.
 *
 * Marked up and styled the way the shared data table is, so a sheet printed from a panel looks
 * like one printed from the full report.
 */
export function PrintableTable({
  heading,
  period,
  rows,
  columns,
}: {
  heading: string;
  period: string;
  rows: ProofOfPlayRow[];
  columns: ExportColumn[];
}) {
  return (
    <div className="printable-table-container hidden w-full print:block">
      <p className="mb-2 text-base font-semibold">{heading}</p>
      <p className="mb-3 text-sm text-gray-500">{period}</p>

      <table className="w-full min-w-full border-separate border-spacing-0 bg-white">
        <thead>
          <tr>
            {columns.map((column) => (
              <th key={column.header} scope="col">
                <div className="flex h-8 items-center border-b border-gray-200 bg-gray-50 px-3 py-2 text-left text-sm font-semibold uppercase text-gray-500">
                  {column.header}
                </div>
              </th>
            ))}
          </tr>
        </thead>
        <tbody>
          {rows.map((row, index) => (
            <tr key={`${row.type}-${row.layoutId}-${row.widgetId}-${index}`} className="bg-white">
              {columns.map((column) => (
                <td
                  key={column.header}
                  className="border-b border-gray-200 bg-white px-3 py-2 align-top"
                >
                  {column.value(row)}
                </td>
              ))}
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}
