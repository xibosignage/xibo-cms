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

import { Download, ExternalLink, Loader2 } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { useNavigate } from 'react-router-dom';

import Button from '@/components/ui/Button';
import DateRangeFilter from '@/components/ui/DateRangeFilter';
import Modal from '@/components/ui/modals/Modal';
import {
  DATE_RANGE_OPTIONS,
  INITIAL_FILTER_STATE,
} from '@/pages/Reporting/Reports/ProofOfPlay/ProofOfPlayConfig';
import ExportStatisticsModal from '@/pages/Reporting/Reports/ProofOfPlay/components/ExportStatisticsModal';
import { useProofOfPlayData } from '@/pages/Reporting/Reports/ProofOfPlay/hooks/useProofOfPlayData';
import type { Display } from '@/types/display';

interface ProofOfPlayModalProps {
  display: Display;
  isOpen: boolean;
  onClose: () => void;
}

function formatDurationShort(totalSeconds: number): string {
  const hours = Math.floor(totalSeconds / 3600);
  const minutes = Math.round((totalSeconds % 3600) / 60);
  if (hours === 0) {
    return `${minutes}m`;
  }
  return `${hours}h ${minutes}m`;
}

// Real report data (useProofOfPlayData, already filterable by displayId + date
// range server-side) rather than the fabricated demo numbers in
// xibo-displays.html's popModal. Total plays/duration and the per-layout
// breakdown are a real aggregation over the real rows, computed client-side
// since there's no dedicated backend KPI endpoint for this compact view. The
// date-range picker and "View full report" link are deliberate additions
// beyond the mockup (justified since the real backend already supports
// arbitrary ranges) — see wiggly-doodling-wren.md.
export default function ProofOfPlayModal({ display, isOpen, onClose }: ProofOfPlayModalProps) {
  const { t } = useTranslation();
  const navigate = useNavigate();
  const [reportFilter, setReportFilter] = useState('lastweek');
  const [isExportOpen, setIsExportOpen] = useState(false);

  const { data, isPending, isError } = useProofOfPlayData({
    filter: { ...INITIAL_FILTER_STATE, reportFilter, displayId: display.displayId },
    enabled: isOpen,
  });

  const rows = data?.rows ?? [];

  const totalPlays = rows.reduce((sum, row) => sum + row.numberPlays, 0);
  const totalDurationSec = rows.reduce((sum, row) => sum + row.duration, 0);
  const campaignCount = new Set(
    rows.filter((row) => row.parentCampaignId).map((row) => row.parentCampaignId),
  ).size;

  const byLayout = new Map<number, { name: string; plays: number }>();
  rows.forEach((row) => {
    const existing = byLayout.get(row.layoutId);
    if (existing) {
      existing.plays += row.numberPlays;
    } else {
      byLayout.set(row.layoutId, { name: row.layout, plays: row.numberPlays });
    }
  });

  const breakdown = Array.from(byLayout.values())
    .sort((a, b) => b.plays - a.plays)
    .map((item) => ({
      ...item,
      percent: totalPlays > 0 ? Math.round((item.plays / totalPlays) * 100) : 0,
    }));

  const summary = { totalPlays, totalDurationSec, campaignCount, breakdown };

  const handleViewFullReport = () => {
    onClose();
    navigate('/reporting/proof-of-play', { state: { displayId: display.displayId } });
  };

  return (
    <>
      <Modal
        isOpen={isOpen}
        onClose={onClose}
        title={t('Proof of play — {{name}}', { name: display.display })}
        size="lg"
        showCloseButton
        actions={[
          {
            label: t('View full report'),
            onClick: handleViewFullReport,
            variant: 'secondary',
            rightIcon: ExternalLink,
          },
          { label: t('Close'), onClick: onClose, variant: 'secondary' },
        ]}
      >
        <div className="flex flex-col gap-4 p-6">
          <div className="flex flex-wrap items-end justify-between gap-3">
            <DateRangeFilter
              label={t('Range')}
              name="reportFilter"
              value={reportFilter}
              options={DATE_RANGE_OPTIONS.map((o) => ({ ...o, label: t(o.label) }))}
              onChange={(_name, value) => setReportFilter(String(value ?? ''))}
            />
            <Button variant="secondary" leftIcon={Download} onClick={() => setIsExportOpen(true)}>
              {t('CSV')}
            </Button>
          </div>

          {isPending && (
            <div className="flex items-center justify-center py-10">
              <Loader2 className="size-6 animate-spin text-gray-400" />
            </div>
          )}

          {isError && <p className="text-sm text-red-600">{t('Failed to load report data.')}</p>}

          {!isPending && !isError && (
            <>
              <div className="grid grid-cols-3 gap-3">
                <div className="rounded-lg bg-gray-50 p-3">
                  <p className="text-xs text-gray-500">{t('Total plays')}</p>
                  <p className="text-lg font-semibold text-gray-800">
                    {summary.totalPlays.toLocaleString()}
                  </p>
                </div>
                <div className="rounded-lg bg-gray-50 p-3">
                  <p className="text-xs text-gray-500">{t('Total duration')}</p>
                  <p className="text-lg font-semibold text-gray-800">
                    {formatDurationShort(summary.totalDurationSec)}
                  </p>
                </div>
                <div className="rounded-lg bg-gray-50 p-3">
                  <p className="text-xs text-gray-500">{t('Campaigns')}</p>
                  <p className="text-lg font-semibold text-gray-800">{summary.campaignCount}</p>
                </div>
              </div>

              <div className="flex flex-col">
                {summary.breakdown.length === 0 ? (
                  <p className="py-4 text-center text-sm text-gray-400">
                    {t('No plays recorded for this period.')}
                  </p>
                ) : (
                  summary.breakdown.map((item) => (
                    <div
                      key={item.name}
                      className="flex items-center justify-between border-t border-gray-100 py-2.5 text-sm first:border-t-0"
                    >
                      <span className="text-gray-800">{item.name}</span>
                      <span className="text-gray-500">
                        {t('{{plays}} plays · {{percent}}%', {
                          plays: item.plays.toLocaleString(),
                          percent: item.percent,
                        })}
                      </span>
                    </div>
                  ))
                )}
              </div>
            </>
          )}
        </div>
      </Modal>

      <ExportStatisticsModal
        isOpen={isExportOpen}
        onClose={() => setIsExportOpen(false)}
        initialDisplayId={display.displayId}
      />
    </>
  );
}
