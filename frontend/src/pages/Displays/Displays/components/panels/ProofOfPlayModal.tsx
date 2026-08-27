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

// What played inside one layout — same query as the layout list, narrowed by layoutId.
// Rendered inside the existing modal (not a second one) to avoid stacking backdrops/focus traps.
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

// Real aggregated report data, computed client-side (no dedicated KPI endpoint). Defaults to a
// rolling 7 days to match the Displays grid's by-group panel, so the two stay comparable.
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

  // Scoped so only this panel's table prints; the displays grid behind marks itself
  // printable too. Saving as PDF is one of the options the dialog offers.
  const handlePrint = () => printPanelTable();

  // The modal stays mounted while closed, so the drilled-into layout has to be
  // cleared on the way out. Every close path goes through here, otherwise
  // reopening lands back on a layout's widgets instead of the layout list.
  const handleClose = () => {
    setSelectedLayout(null);
    onClose();
  };

  const handleViewFullReport = () => {
    handleClose();
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
        { label: t('Close'), onClick: handleClose, variant: 'secondary' as const },
      ]
    : [
        {
          label: t('View full report'),
          onClick: handleViewFullReport,
          variant: 'secondary' as const,
          rightIcon: ExternalLink,
        },
        { label: t('Close'), onClick: handleClose, variant: 'secondary' as const },
      ];

  return (
    <Modal
      isOpen={isOpen}
      onClose={handleClose}
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
