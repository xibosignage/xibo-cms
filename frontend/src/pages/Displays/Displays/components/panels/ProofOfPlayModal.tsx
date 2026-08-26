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

import { ArrowLeft, Download, ExternalLink, Loader2, Printer } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { useNavigate } from 'react-router-dom';

import {
  PROOF_OF_PLAY_WINDOW_HOURS,
  type PlayListItem,
  PlayList,
  PrintableTable,
  StatTile,
  VISIBLE_ROWS,
  downloadCsv,
  exportColumns,
  formatHoursMinutes,
  formatRange,
  rollingWindow,
  totalPlaysBy,
} from './ProofOfPlayShared';

import Button from '@/components/ui/Button';
import DateRangeFilter from '@/components/ui/DateRangeFilter';
import Modal from '@/components/ui/modals/Modal';
import {
  DATE_RANGE_OPTIONS,
  INITIAL_FILTER_STATE,
} from '@/pages/Reporting/Reports/ProofOfPlay/ProofOfPlayConfig';
import { useProofOfPlayData } from '@/pages/Reporting/Reports/ProofOfPlay/hooks/useProofOfPlayData';
import type { Display } from '@/types/display';

interface ProofOfPlayModalProps {
  display: Display;
  isOpen: boolean;
  onClose: () => void;
}

/**
 * What played inside one layout, shown in place of the layout list.
 *
 * Widget rows carry the layout they belong to, so this is the same query narrowed by layoutId
 * rather than anything new.
 *
 * Rendered inside the existing modal rather than as a second one on top: stacking two dialogs
 * means two backdrops and two focus traps, and there is nothing on the layout list worth keeping
 * on screen behind it.
 */
function LayoutWidgets({
  displayId,
  layout,
  reportFilter,
  period,
}: {
  displayId: number;
  layout: PlayListItem;
  reportFilter: string;
  period: string;
}) {
  const { t } = useTranslation();

  const { data, isFetching, isError } = useProofOfPlayData({
    filter: {
      ...INITIAL_FILTER_STATE,
      reportFilter,
      displayId,
      layoutId: [layout.id],
      type: 'widget',
    },
    enabled: true,
  });

  const widgets = totalPlaysBy(
    data?.rows ?? [],
    (row) => row.widgetId,
    (row) => row.media || t('Widget {{id}}', { id: row.widgetId }),
  );

  const totalPlays = widgets.reduce((sum, item) => sum + item.plays, 0);

  return (
    <>
      <div className="mb-4 flex items-baseline justify-between gap-4">
        <p className="text-xs text-gray-500">
          {t('Played inside this layout')}, {period}
        </p>
        <p className="text-xs text-gray-500">
          {t('Total')}{' '}
          <span className="font-semibold text-gray-700">{totalPlays.toLocaleString()}</span>{' '}
          {t('plays')}
        </p>
      </div>

      {isFetching && (
        <div className="flex items-center justify-center py-10">
          <Loader2 className="size-6 animate-spin text-gray-400" />
        </div>
      )}

      {!isFetching && isError && (
        <p className="py-3 text-sm text-red-600">{t('Could not load proof of play')}</p>
      )}

      {!isFetching && !isError && widgets.length === 0 && (
        <p className="py-8 text-center text-sm italic text-gray-400">
          {t('Nothing recorded inside this layout')}
        </p>
      )}

      {!isFetching && !isError && widgets.length > 0 && (
        <PlayList
          items={widgets}
          totalPlays={totalPlays}
          labels={{
            showingTop: t('Showing top {{shown}} of {{total}} by plays', {
              shown: VISIBLE_ROWS,
              total: widgets.length,
            }),
            showingAll: t('Showing all {{count}}', { count: widgets.length }),
            showAll: t('Show all'),
            showFewer: t('Show fewer'),
          }}
        />
      )}
    </>
  );
}

// Real report data (useProofOfPlayData, already filterable by displayId + date
// range server-side) rather than the fabricated demo numbers in
// xibo-displays.html's popModal. Total plays/duration and the per-layout
// breakdown are a real aggregation over the real rows, computed client-side
// since there's no dedicated backend KPI endpoint for this compact view. The
// date-range picker and "View full report" link are deliberate additions
// beyond the mockup (justified since the real backend already supports
// arbitrary ranges) — see wiggly-doodling-wren.md.
//
// The range defaults to a rolling 7 days rather than one of the report's own presets, because
// the by-group panel on the Displays grid reports over that same window. Landing on "Last Week"
// (the previous calendar week) would mean the two panels disagreed by default and could not be
// read against each other.
export default function ProofOfPlayModal({ display, isOpen, onClose }: ProofOfPlayModalProps) {
  const { t } = useTranslation();
  const navigate = useNavigate();

  // Held in state so the window is fixed for as long as the modal is mounted. Recomputing it
  // per render would change the query key every time and refetch in a loop.
  const [defaultRange] = useState(() => rollingWindow(PROOF_OF_PLAY_WINDOW_HOURS));
  const [reportFilter, setReportFilter] = useState(defaultRange.filter);
  const [selectedLayout, setSelectedLayout] = useState<PlayListItem | null>(null);

  const rangeOptions = [
    { value: defaultRange.filter, label: t('Last 7 Days') },
    ...DATE_RANGE_OPTIONS.map((option) => ({ ...option, label: t(option.label) })),
  ];

  const { data, isPending, isError } = useProofOfPlayData({
    filter: {
      ...INITIAL_FILTER_STATE,
      reportFilter,
      displayId: display.displayId,
      // Every type, not just layouts. The summary below only wants layout rows, but the exports
      // carry the same full detail the report itself would, so both come from one request.
    },
    enabled: isOpen,
  });

  const allRows = data?.rows ?? [];

  // Layout rows only for the summary. Media and widget rows are child detail of the same play
  // event, so counting those as well would report the same play several times over.
  const layouts = totalPlaysBy(
    allRows.filter((row) => row.type === 'layout'),
    (row) => row.layoutId,
    (row) => row.layout || t('Deleted layout'),
  );

  const totalPlays = layouts.reduce((sum, item) => sum + item.plays, 0);
  const totalDuration = formatHoursMinutes(layouts.reduce((sum, item) => sum + item.duration, 0));

  // Counted from parentCampaign, the campaign a layout was scheduled as part of. A layout
  // scheduled directly has none, so this reads zero on a display with no campaign scheduling.
  const campaigns = new Set(
    allRows.map((row) => row.parentCampaign).filter((name) => Boolean(name)),
  ).size;

  // Only the default window has explicit ends to state. A preset is named rather than dated,
  // so fall back to its own label.
  const period =
    reportFilter === defaultRange.filter
      ? formatRange(defaultRange.from, defaultRange.to)
      : (rangeOptions.find((option) => option.value === reportFilter)?.label ?? '');

  const columns = exportColumns(t);

  const handleCsv = () => {
    // Slugged so the file name is safe on every platform, and stamped so two exports of the
    // same display do not overwrite each other.
    const slug =
      display.display.replace(/[^a-zA-Z0-9-_]+/g, '_').replace(/^_+|_+$/g, '') || 'display';

    // Named for the range actually on screen, not the default one. Only that default window
    // has explicit ends to stamp; a preset is resolved by the CMS, so it names the file itself.
    const stamp =
      reportFilter === defaultRange.filter
        ? `${defaultRange.from.toFormat('yyyy-MM-dd')}_${defaultRange.to.toFormat('yyyy-MM-dd')}`
        : reportFilter;

    downloadCsv(
      `proof-of-play_${slug}_${stamp}.csv`,
      columns.map((column) => column.header),
      allRows.map((row) => columns.map((column) => column.value(row))),
    );
  };

  // print.css hides everything outside .printable-table-container, so only the detail table
  // below reaches the page. Saving as PDF is one of the options the dialog offers.
  const handlePrint = () => window.print();

  const handleViewFullReport = () => {
    onClose();
    navigate('/reporting/proof-of-play', { state: { displayId: display.displayId } });
  };

  const actions = selectedLayout
    ? [
        {
          label: t('Back'),
          onClick: () => setSelectedLayout(null),
          variant: 'secondary' as const,
          leftIcon: ArrowLeft,
        },
        { label: t('Close'), onClick: onClose, variant: 'secondary' as const },
      ]
    : [
        {
          label: t('View full report'),
          onClick: handleViewFullReport,
          variant: 'secondary' as const,
          rightIcon: ExternalLink,
        },
        { label: t('Close'), onClick: onClose, variant: 'secondary' as const },
      ];

  return (
    <Modal
      isOpen={isOpen}
      onClose={onClose}
      title={
        selectedLayout
          ? selectedLayout.label
          : t('Proof of play — {{name}}', { name: display.display })
      }
      size="lg"
      showCloseButton
      actions={actions}
    >
      <div className="flex flex-col gap-4 p-6">
        {selectedLayout ? (
          <LayoutWidgets
            displayId={display.displayId}
            layout={selectedLayout}
            reportFilter={reportFilter}
            period={period}
          />
        ) : (
          <>
            <div className="no-print flex flex-wrap items-end justify-between gap-3">
              <DateRangeFilter
                label={t('Range')}
                name="reportFilter"
                value={reportFilter}
                options={rangeOptions}
                onChange={(_name, value) => setReportFilter(String(value ?? ''))}
              />
              {!isPending && !isError && layouts.length > 0 && (
                <div className="flex items-center gap-2">
                  <Button variant="secondary" leftIcon={Download} onClick={handleCsv}>
                    {t('CSV')}
                  </Button>
                  <Button variant="secondary" leftIcon={Printer} onClick={handlePrint}>
                    {t('Print')}
                  </Button>
                </div>
              )}
            </div>

            {isPending && (
              <div className="flex items-center justify-center py-10">
                <Loader2 className="size-6 animate-spin text-gray-400" />
              </div>
            )}

            {isError && <p className="text-sm text-red-600">{t('Failed to load report data.')}</p>}

            {!isPending && !isError && (
              <>
                <p className="text-sm text-gray-400">{period}</p>

                <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                  <StatTile label={t('Total plays')}>{totalPlays.toLocaleString()}</StatTile>
                  <StatTile label={t('Total duration')}>
                    {totalDuration.hours}
                    <span className="text-base font-medium text-gray-500">{t('h')}</span>{' '}
                    {totalDuration.minutes}
                    <span className="text-base font-medium text-gray-500">{t('m')}</span>
                  </StatTile>
                  <StatTile label={t('Campaigns')}>{campaigns.toLocaleString()}</StatTile>
                </div>

                {layouts.length === 0 ? (
                  <p className="py-4 text-center text-sm text-gray-400">
                    {t('No plays recorded for this period.')}
                  </p>
                ) : (
                  /* The printed sheet carries the full detail below instead. */
                  <div className="no-print">
                    <PlayList
                      items={layouts}
                      totalPlays={totalPlays}
                      onSelect={setSelectedLayout}
                      labels={{
                        showingTop: t('Showing top {{shown}} of {{total}} layouts by plays', {
                          shown: VISIBLE_ROWS,
                          total: layouts.length,
                        }),
                        showingAll: t('Showing all {{count}} layouts', { count: layouts.length }),
                        showAll: t('Show all layouts'),
                        showFewer: t('Show fewer'),
                      }}
                    />
                  </div>
                )}

                <PrintableTable
                  heading={display.display}
                  period={period}
                  rows={allRows}
                  columns={columns}
                />
              </>
            )}
          </>
        )}
      </div>
    </Modal>
  );
}
