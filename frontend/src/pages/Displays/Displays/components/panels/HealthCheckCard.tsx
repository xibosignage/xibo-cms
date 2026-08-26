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

import { Activity, HeartPulse, ShieldCheck, Smartphone, Wifi } from 'lucide-react';
import { useTranslation } from 'react-i18next';

import { BUCKET_COLORS } from '../../DisplayStatusConfig';
import { useDisplayUptime } from '../../hooks/useManagePageData';

import { formatStorageUsed, getStorageUsedPercent } from './ManagePageFormatters';
import { PanelCard, PanelField } from './PanelCard';

import Badge from '@/components/ui/Badge';
import { useUserContext } from '@/context/UserContext';
import { getClientTypeLabel } from '@/pages/Displays/Displays/DisplaysConfig';
import type { Display } from '@/types/display';
import { hasFeature } from '@/utils/permissions';

interface HealthCheckCardProps {
  display: Display;
  onClearCache: () => void;
  isClearingCache: boolean;
  canClearCache: boolean;
}

// Storage bar fill colour by how full it is — same thresholds/shades as the
// bucket classifier's own needsAttention/faults dots, so "storage is a
// problem" reads with the same colour vocabulary as the rest of the page.
function getStorageBarColor(percent: number): string {
  if (percent > 95) {
    return BUCKET_COLORS.faults.dot;
  }
  if (percent >= 80) {
    return BUCKET_COLORS.needsAttention.dot;
  }
  return 'bg-xibo-blue-600';
}

export default function HealthCheckCard({
  display,
  onClearCache,
  isClearingCache,
  canClearCache,
}: HealthCheckCardProps) {
  const { t } = useTranslation();
  const { user } = useUserContext();

  const canViewReporting = hasFeature(user, 'displays.reporting');
  const uptimeQuery = useDisplayUptime(display.displayId, canViewReporting);

  const uptimeValue = (() => {
    if (!canViewReporting) {
      return '-';
    }
    if (uptimeQuery.isFetching) {
      return t('Loading…');
    }
    return uptimeQuery.data == null ? '-' : `${uptimeQuery.data.toFixed(1)}%`;
  })();

  const isAuthorised = display.licensed === 1;
  const storagePercent = getStorageUsedPercent(display);
  const playerType =
    [getClientTypeLabel(t, display.clientType), display.model].filter(Boolean).join(' / ') || '-';

  return (
    <PanelCard title={t('Health Check')} icon={HeartPulse}>
      <div className="flex flex-col">
        <div className="flex flex-col gap-2 border-b border-gray-100 px-4 py-3">
          <div className="flex items-center justify-between text-sm">
            <span className="text-gray-600">{t('Storage')}</span>
            <span className="font-medium text-gray-700">{formatStorageUsed(display, t)}</span>
          </div>
          {storagePercent !== null && (
            <div className="h-2 w-full overflow-hidden rounded-full bg-gray-100">
              <div
                className={`h-full rounded-full ${getStorageBarColor(storagePercent)}`}
                style={{ width: `${storagePercent}%` }}
              />
            </div>
          )}
          {canClearCache && (
            <button
              type="button"
              onClick={onClearCache}
              disabled={isClearingCache}
              className="self-start text-xs font-semibold text-xibo-blue-600 hover:underline disabled:cursor-not-allowed disabled:opacity-50 cursor-pointer"
            >
              {t('Clear cache')}
            </button>
          )}
        </div>

        <div className="flex items-center justify-between gap-3 border-b border-gray-100 px-4 py-3">
          <span className="flex items-center gap-1.5 text-sm text-gray-600">
            <ShieldCheck className="size-4 shrink-0 text-gray-400" aria-hidden="true" />
            {t('Licence')}
          </span>
          <Badge type={isAuthorised ? 'success' : 'warning'} className="w-fit shrink-0">
            {isAuthorised ? t('Authorised') : t('Unauthorised')}
          </Badge>
        </div>

        <div className="border-b border-gray-100 px-4 py-3">
          <PanelField icon={Wifi} label={t('Network')} value={display.clientAddress || '-'} />
        </div>

        <div className="border-b border-gray-100 px-4 py-3">
          <PanelField icon={Activity} label={t('Uptime')} value={uptimeValue} />
        </div>

        <div className="px-4 py-3">
          <PanelField icon={Smartphone} label={t('Player Type')} value={playerType} />
        </div>
      </div>
    </PanelCard>
  );
}
