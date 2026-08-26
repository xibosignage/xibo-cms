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

import { ArrowRight, BarChart3 } from 'lucide-react';
import { useTranslation } from 'react-i18next';

interface ProofOfPlayPlaceholderPanelProps {
  /** e.g. "Proof of Play — Today" (list page) or "Proof of Play — Last 7 Days" (Manage page). */
  title: string;
  canViewProofOfPlay: boolean;
  onViewReport: () => void;
  /** Only the Manage page's variant shows the "not seeing any plays?" troubleshooting hint. */
  onViewTroubleshooting?: () => void;
}

// Shared placeholder for the "Proof of Play" panel from the Display
// Management reference mock (display-management.html), used by both the
// Overview list page and the per-display Manage page. Wiring this up to the
// real Proof of Play report data is separate follow-up work — this just
// reserves the panel's place in the page layout, with a link through to the
// full report in the meantime.
export default function ProofOfPlayPlaceholderPanel({
  title,
  canViewProofOfPlay,
  onViewReport,
  onViewTroubleshooting,
}: ProofOfPlayPlaceholderPanelProps) {
  const { t } = useTranslation();

  return (
    <div className="rounded-lg border border-blue-100 bg-blue-50/60 p-4">
      <div className="flex items-center justify-between gap-3">
        <h2 className="flex items-center gap-1.5 text-sm font-semibold text-gray-700">
          <BarChart3 className="size-3.5 shrink-0 text-xibo-blue-600" aria-hidden="true" />
          {title}
        </h2>
        {canViewProofOfPlay && (
          <button
            type="button"
            onClick={onViewReport}
            className="flex items-center gap-1 rounded-lg bg-white px-3 py-1.5 text-xs font-semibold text-xibo-blue-600 shadow-sm hover:bg-xibo-blue-50 cursor-pointer"
          >
            {t('View full report')}
            <ArrowRight className="size-3.5 shrink-0" />
          </button>
        )}
      </div>
      <div className="mt-3 flex flex-col items-center justify-center gap-2 rounded-lg border border-dashed border-blue-200 bg-white/70 py-14 text-center">
        <BarChart3 className="size-6 text-blue-200" />
        <p className="text-sm text-gray-400">{t('Coming soon')}</p>
      </div>
      {onViewTroubleshooting && (
        <p className="mt-3 text-xs text-gray-500">
          {t('Not seeing any plays for this display?')}{' '}
          <button
            type="button"
            onClick={onViewTroubleshooting}
            className="font-semibold text-xibo-blue-600 hover:underline cursor-pointer"
          >
            {t('Check the Troubleshooting Guide below')}
          </button>
        </p>
      )}
    </div>
  );
}
