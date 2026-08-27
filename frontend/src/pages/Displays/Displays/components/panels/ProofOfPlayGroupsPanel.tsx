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

import { ArrowRight, BarChart3, Download, Loader2, Printer } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';

import {
  PROOF_OF_PLAY_WINDOW_HOURS,
  PlayList,
  PrintableTable,
  printPanelTable,
  StatTile,
  VISIBLE_ROWS,
  downloadCsv,
  exportColumns,
  formatHoursMinutes,
  formatRange,
  rollingWindow,
  totalPlaysBy,
} from './ProofOfPlayShared';

import { INITIAL_FILTER_STATE as PROOF_OF_PLAY_INITIAL_FILTER } from '@/pages/Reporting/Reports/ProofOfPlay/ProofOfPlayConfig';
import { useProofOfPlayData } from '@/pages/Reporting/Reports/ProofOfPlay/hooks/useProofOfPlayData';

interface ProofOfPlayGroupsPanelProps {
  /** e.g. "Proof of Play — Last 7 Days". */
  title: string;
  canViewProofOfPlay: boolean;
  onViewReport: () => void;
  /** Should agree with whatever the title says. */
  windowHours?: number;
}

/**
 * Plays across every display, grouped by display group.
 *
 * A stat row names the display it came from, and a display belongs to display groups, so the
 * report can roll plays up to the group without anything extra being recorded. Note a display in
 * several groups counts toward each of them, so the group figures can add up to more than the
 * network total. The report leaves out the per-display groups the CMS creates for scheduling.
 */
export default function ProofOfPlayGroupsPanel({
  title,
  canViewProofOfPlay,
  onViewReport,
  windowHours = PROOF_OF_PLAY_WINDOW_HOURS,
}: ProofOfPlayGroupsPanelProps) {
  const { t } = useTranslation();

  // Held in state so the range is fixed for the life of the page; recomputing it every render
  // would change the query key each time and refetch in a loop.
  const [range] = useState(() => rollingWindow(windowHours));

  const { data, isFetching, isError } = useProofOfPlayData({
    filter: {
      ...PROOF_OF_PLAY_INITIAL_FILTER,
      reportFilter: range.filter,
      // No displayId: every display the viewer can see.
      groupBy: 'displayGroup',
    },
    enabled: canViewProofOfPlay,
  });

  const allRows = data?.rows ?? [];

  // Layout rows only. Media and widget rows are child detail of the same play event, so counting
  // those as well would report the same play several times over.
  const layoutRows = allRows.filter((row) => row.type === 'layout');

  const groups = totalPlaysBy(
    layoutRows,
    (row) => row.displayGroupId ?? 0,
    (row) => row.displayGroup ?? '',
  );

  const totalPlays = groups.reduce((sum, item) => sum + item.plays, 0);
  const totalDuration = formatHoursMinutes(groups.reduce((sum, item) => sum + item.duration, 0));
  const campaigns = new Set(
    layoutRows.map((row) => row.parentCampaign).filter((name) => Boolean(name)),
  ).size;

  const period = formatRange(range.from, range.to);
  const hasRows = groups.length > 0;
  const columns = exportColumns(t);

  const handleCsv = () => {
    downloadCsv(
      `proof-of-play_all-displays_${range.from.toFormat('yyyy-MM-dd')}_${range.to.toFormat('yyyy-MM-dd')}.csv`,
      columns.map((column) => column.header),
      allRows.map((row) => columns.map((column) => column.value(row))),
    );
  };

  const handlePrint = () => printPanelTable();

  return (
    <div className="rounded-lg border border-blue-100 bg-blue-50/60 p-4">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <h2 className="flex items-center gap-1.5 text-sm font-semibold text-gray-700">
          <BarChart3 className="size-3.5 shrink-0 text-xibo-blue-600" aria-hidden="true" />
          {title}
        </h2>
        <div className="no-print flex items-center gap-2">
          {canViewProofOfPlay && hasRows && (
            <>
              <button
                type="button"
                onClick={handleCsv}
                className="flex cursor-pointer items-center gap-1 rounded-lg bg-white px-3 py-1.5 text-xs font-semibold text-gray-600 shadow-sm hover:bg-gray-50"
              >
                <Download className="size-3.5 shrink-0" />
                {t('CSV')}
              </button>
              <button
                type="button"
                onClick={handlePrint}
                className="flex cursor-pointer items-center gap-1 rounded-lg bg-white px-3 py-1.5 text-xs font-semibold text-gray-600 shadow-sm hover:bg-gray-50"
              >
                <Printer className="size-3.5 shrink-0" />
                {t('Print')}
              </button>
            </>
          )}
          {canViewProofOfPlay && (
            <button
              type="button"
              onClick={onViewReport}
              className="flex cursor-pointer items-center gap-1 rounded-lg bg-white px-3 py-1.5 text-xs font-semibold text-xibo-blue-600 shadow-sm hover:bg-xibo-blue-50"
            >
              {t('View full report')}
              <ArrowRight className="size-3.5 shrink-0" />
            </button>
          )}
        </div>
      </div>

      <div className="mt-3 rounded-lg border border-blue-100 bg-white/70 p-4 print:border-0 print:bg-white print:p-0">
        {!canViewProofOfPlay && (
          <p className="py-8 text-center text-sm italic text-gray-400">
            {t('You do not have permission to view proof of play')}
          </p>
        )}

        {canViewProofOfPlay && isFetching && (
          <div className="flex items-center justify-center py-10">
            <Loader2 className="w-6 h-6 animate-spin text-gray-400" />
          </div>
        )}

        {canViewProofOfPlay && !isFetching && isError && (
          <p className="py-3 text-sm text-red-600">{t('Could not load proof of play')}</p>
        )}

        {canViewProofOfPlay && !isFetching && !isError && groups.length === 0 && (
          <p className="py-8 text-center text-sm italic text-gray-400">
            {t('No layouts played in this period')}
          </p>
        )}

        {canViewProofOfPlay && !isFetching && !isError && groups.length > 0 && (
          <>
            <p className="text-sm text-gray-400">{period}</p>

            <div className="mt-4 mb-6 grid grid-cols-1 gap-4 sm:grid-cols-3">
              <StatTile label={t('Total plays')}>{totalPlays.toLocaleString()}</StatTile>
              <StatTile label={t('Total duration')}>
                {totalDuration.hours}
                <span className="text-base font-medium text-gray-500">{t('h')}</span>{' '}
                {totalDuration.minutes}
                <span className="text-base font-medium text-gray-500">{t('m')}</span>
              </StatTile>
              <StatTile label={t('Campaigns')}>{campaigns.toLocaleString()}</StatTile>
            </div>

            {/* The printed sheet carries the full detail below instead. */}
            <div className="no-print">
              <PlayList
                items={groups}
                totalPlays={totalPlays}
                labels={{
                  showingTop: t('Showing top {{shown}} of {{total}} groups by plays', {
                    shown: VISIBLE_ROWS,
                    total: groups.length,
                  }),
                  showingAll: t('Showing all {{count}} groups', { count: groups.length }),
                  showAll: t('Show all groups'),
                  showFewer: t('Show fewer'),
                }}
              />
            </div>

            <PrintableTable
              heading={t('All displays')}
              period={period}
              rows={allRows}
              columns={columns}
            />
          </>
        )}
      </div>
    </div>
  );
}
