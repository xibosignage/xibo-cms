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
import { Activity, HardDrive, Loader2, Package, PlayCircle } from 'lucide-react';
import { useTranslation } from 'react-i18next';

import { PanelField } from './PanelCard';

import { useUserContext } from '@/context/UserContext';
import { getClientTypeLabel } from '@/pages/Displays/Displays/DisplaysConfig';
import { useDisplayUptime } from '@/pages/Displays/Overview/hooks/useOverviewManageModalData';
import type { Display } from '@/types/display';
import { formatFileSize } from '@/utils/formatters';
import { hasFeature } from '@/utils/permissions';

interface QuickStatsRowProps {
  display: Display;
}

function formatStorageUsed(display: Display, t: TFunction): string {
  const { storageAvailableSpace, storageTotalSpace } = display;
  if (storageAvailableSpace == null || storageTotalSpace == null || storageTotalSpace <= 0) {
    return '-';
  }

  const used = Math.max(0, storageTotalSpace - storageAvailableSpace);
  const percentUsed = Math.round((used / storageTotalSpace) * 100);

  return t('{{used}} of {{total}} ({{percent}}% used)', {
    used: formatFileSize(used),
    total: formatFileSize(storageTotalSpace),
    percent: percentUsed,
  });
}

function formatClientVersion(display: Display, t: TFunction): string {
  const typeAndVersion = [getClientTypeLabel(t, display.clientType), display.clientVersion]
    .filter(Boolean)
    .join(' ');

  if (!typeAndVersion) {
    return '-';
  }

  return display.clientCode != null ? `${typeAndVersion}-${display.clientCode}` : typeAndVersion;
}

// The Manage modal's "quick-stats" row from display-management.html — a
// plain 4-box grid sitting directly under the Hero, no card chrome. Now
// Playing joins the Storage/Uptime/Software figures that previously lived in
// their own titled panel (StorageUptimeSoftwarePanel), matching the mock's
// grouping exactly.
export default function QuickStatsRow({ display }: QuickStatsRowProps) {
  const { t } = useTranslation();
  const { user } = useUserContext();

  // The underlying report endpoint (/report/data/timeconnected) is gated by
  // the same feature as Bandwidth in the legacy modal — mirror that gate here
  // rather than firing a request that will fail on permissions.
  const canViewReporting = hasFeature(user, 'displays.reporting');
  const uptimeQuery = useDisplayUptime(display.displayId, canViewReporting);

  const uptimeValue = (() => {
    if (!canViewReporting) {
      return '-';
    }
    if (uptimeQuery.isFetching) {
      return <Loader2 className="w-4 h-4 animate-spin text-gray-400" />;
    }
    return uptimeQuery.data == null ? '-' : `${uptimeQuery.data.toFixed(1)}%`;
  })();

  return (
    <div className="grid grid-cols-2 sm:grid-cols-4 gap-3">
      <div className="rounded-lg border border-gray-200 bg-slate-50 p-3">
        <PanelField
          icon={PlayCircle}
          label={t('Now Playing')}
          value={display.currentLayout || '-'}
        />
      </div>
      <div className="rounded-lg border border-gray-200 bg-slate-50 p-3">
        <PanelField
          icon={HardDrive}
          label={t('Storage Used')}
          value={formatStorageUsed(display, t)}
        />
      </div>
      <div className="rounded-lg border border-gray-200 bg-slate-50 p-3">
        <PanelField icon={Activity} label={t('Uptime')} value={uptimeValue} />
      </div>
      <div className="rounded-lg border border-gray-200 bg-slate-50 p-3">
        <PanelField
          icon={Package}
          label={t('Client Version')}
          value={formatClientVersion(display, t)}
        />
      </div>
    </div>
  );
}
