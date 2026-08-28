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

import {
  Activity,
  HardDrive,
  HeartPulse,
  ShieldCheck,
  Smartphone,
  Wifi,
  type LucideIcon,
} from 'lucide-react';
import { useTranslation } from 'react-i18next';

import { BUCKET_COLORS } from '../../DisplayStatusConfig';
import { useDisplayUptime } from '../../hooks/useManagePageData';

import { formatStorageUsed, getStorageUsedPercent } from './ManagePageFormatters';
import { PanelCard } from './PanelCard';

import Switch from '@/components/ui/forms/Switch';
import { useUserContext } from '@/context/UserContext';
import { getClientTypeLabel } from '@/pages/Displays/Displays/DisplaysConfig';
import type { Display } from '@/types/display';
import { hasFeature } from '@/utils/permissions';

interface HealthCheckCardProps {
  display: Display;
  onClearCache: () => void;
  isClearingCache: boolean;
  canClearCache: boolean;
  onToggleAuthorise: () => void;
  isTogglingAuthorise: boolean;
  canAuthorise: boolean;
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

// A left label (with its topic icon) / right value row — every field below
// shares this shape, so the card reads as a consistent two-column list.
function HealthRow({
  icon: Icon,
  label,
  value,
  bordered = true,
}: {
  icon: LucideIcon;
  label: string;
  value: React.ReactNode;
  bordered?: boolean;
}) {
  return (
    <div
      className={`flex items-center justify-between gap-3 px-4 py-3 ${bordered ? 'border-b border-gray-100' : ''}`}
    >
      <span className="flex items-center gap-1.5 text-sm text-gray-600">
        <Icon className="size-4 shrink-0 text-gray-400" aria-hidden="true" />
        {label}
      </span>
      <span className="text-sm font-medium text-gray-700">{value}</span>
    </div>
  );
}

export default function HealthCheckCard({
  display,
  onClearCache,
  isClearingCache,
  canClearCache,
  onToggleAuthorise,
  isTogglingAuthorise,
  canAuthorise,
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
            <span className="flex items-center gap-1.5 text-gray-600">
              <HardDrive className="size-4 shrink-0 text-gray-400" aria-hidden="true" />
              {t('Storage')}
            </span>
            <div className="flex flex-col items-end gap-1">
              <span className="font-medium text-gray-700">{formatStorageUsed(display, t)}</span>
              {canClearCache && (
                <button
                  type="button"
                  onClick={onClearCache}
                  disabled={isClearingCache}
                  className="text-xs font-semibold text-xibo-blue-600 hover:underline disabled:cursor-not-allowed disabled:opacity-50 cursor-pointer"
                >
                  {t('Clear Cache')}
                </button>
              )}
            </div>
          </div>
          {storagePercent !== null && (
            <div className="h-2 w-full overflow-hidden rounded-full bg-gray-100">
              <div
                className={`h-full rounded-full ${getStorageBarColor(storagePercent)}`}
                style={{ width: `${storagePercent}%` }}
              />
            </div>
          )}
        </div>

        <div className="flex items-center justify-between gap-3 border-b border-gray-100 px-4 py-3">
          <span className="flex items-center gap-1.5 text-sm text-gray-600">
            <ShieldCheck className="size-4 shrink-0 text-gray-400" aria-hidden="true" />
            {t('Authorised')}
          </span>
          {/* Switch's own root element is w-full (it's built for a form column, not an
              inline trailing control) — wrapped in a shrink-0/w-fit box so that doesn't
              stretch it across the row and pull it off the right edge. */}
          <div className="w-fit shrink-0">
            <Switch
              ariaLabel={t('Authorised')}
              checked={isAuthorised}
              onChange={onToggleAuthorise}
              disabled={!canAuthorise || isTogglingAuthorise}
              size="sm"
              hideOnOff
            />
          </div>
        </div>

        <HealthRow icon={Wifi} label={t('Network')} value={display.clientAddress || '-'} />
        <HealthRow icon={Activity} label={t('Uptime')} value={uptimeValue} />
        <HealthRow icon={Smartphone} label={t('Player Type')} value={playerType} bordered={false} />
      </div>
    </PanelCard>
  );
}
