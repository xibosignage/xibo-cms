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

import type { LucideIcon } from 'lucide-react';
import { useTranslation } from 'react-i18next';

import { getManagePillCopy } from '../../DisplayStatusConfig';
import { useDisplayStatusBadge } from '../../hooks/useDisplayStatusBadge';
import DisplayStatusBadge from '../DisplayStatusBadge';

import type { Display } from '@/types/display';

interface ManagePageHeaderProps {
  display: Display;
  onClose: () => void;
  closeIcon: LucideIcon;
  closeLabel: string;
  /** Right-aligned action buttons (Proof of Play / Diagnostics gear). */
  actions?: React.ReactNode;
}

// The Manage page's header — a back link, the display's name + big status
// pill + "Last seen", and a right-aligned actions slot. Light/white, matching
// every other full-page detail header in the CMS (see e.g. CampaignEditor.tsx,
// SavedReportViewer.tsx) rather than xibo-displays.html's dark navy band —
// `--sidebar-bg`/`--sidebar-fg` are the left-nav sidebar's own tokens, not a
// general "dark surface" for page content, so reusing them here read as a
// stray chunk of the nav bleeding into the page.
export default function ManagePageHeader({
  display,
  onClose,
  closeIcon: CloseIcon,
  closeLabel,
  actions,
}: ManagePageHeaderProps) {
  const { t } = useTranslation();
  const group = display.displayGroups?.[0]?.displayGroup;
  const { bucket, colors, badgeLabel, lastSeenLabel } = useDisplayStatusBadge(display);
  const pillLabel = getManagePillCopy(
    { bucket, colors, badgeLabel, lastSeenLabel },
    display.countFaults,
    t,
  );

  return (
    <div className="shrink-0 border-b border-gray-200 bg-white px-6 py-5">
      <button
        type="button"
        onClick={onClose}
        className="mb-3 flex items-center gap-1.5 text-sm font-semibold text-gray-500 hover:text-gray-800 cursor-pointer"
      >
        <CloseIcon className="size-4 shrink-0" aria-hidden="true" />
        {closeLabel}
      </button>

      <div className="flex flex-wrap items-start justify-between gap-4">
        <div className="min-w-0">
          {group && (
            <p className="truncate text-xs text-gray-500">
              {t('All Displays')} / {group}
            </p>
          )}
          <div className="flex flex-wrap items-center gap-3">
            <h2 className="truncate text-lg font-semibold text-gray-900">{display.display}</h2>
            <DisplayStatusBadge
              bucket={bucket}
              colors={colors}
              label={pillLabel}
              className="w-fit"
            />
          </div>
          <p className="mt-1 text-xs text-gray-500">
            {t('Last seen {{time}}', { time: lastSeenLabel })}
          </p>
        </div>

        {actions && <div className="flex shrink-0 flex-wrap items-center gap-2">{actions}</div>}
      </div>
    </div>
  );
}
