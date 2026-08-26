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

import { Eye, Monitor, Settings } from 'lucide-react';
import { useTranslation } from 'react-i18next';

import { useDisplayNextSchedule } from '../hooks/useDisplayNextSchedule';
import { useDisplayStatusBadge } from '../hooks/useDisplayStatusBadge';

import DisplayStatusBadge from './DisplayStatusBadge';

import Badge from '@/components/ui/Badge';
import { useDateFormatter } from '@/hooks/useDateFormatter';
import type { Display } from '@/types/display';
import type { DisplayNextScheduleStatus } from '@/types/displayOverview';
import type { UIStatus } from '@/types/uiStatus';

interface DisplayCardProps {
  display: Display;
  onManage: (display: Display) => void;
  onLiveView: (display: Display) => void;
}

// "error" deliberately isn't here — the backend never returns it (see
// DisplayNextScheduleStatus), so there's nothing to map.
const NEXT_SCHEDULE_STATUS: Record<DisplayNextScheduleStatus, UIStatus> = {
  ready: 'success',
  downloading: 'warning',
  pending: 'neutral',
};

function getNextScheduleStatusLabel(t: (key: string) => string, status: DisplayNextScheduleStatus) {
  switch (status) {
    case 'ready':
      return t('Ready');
    case 'downloading':
      return t('Downloading');
    case 'pending':
      return t('Pending');
    default:
      return '';
  }
}

// Card face follows display-selection.png: status pill overlaid on the
// screenshot, name + description, a relative "Last seen" line, then Currently
// Playing / Up Next detail, and a two-way Manage / Live view footer.
export default function DisplayCard({ display, onManage, onLiveView }: DisplayCardProps) {
  const { t } = useTranslation();
  const { formatRelative } = useDateFormatter();
  const { data: nextSchedule, isLoading: isNextScheduleLoading } = useDisplayNextSchedule(
    display.displayId,
  );

  const { bucket, colors, badgeLabel, lastSeenLabel, showThumbnail, onThumbnailError } =
    useDisplayStatusBadge(display);

  const subtitle = display.description || display.displayGroups?.[0]?.displayGroup || '';

  return (
    <div className="flex flex-col rounded-xl bg-slate-50 overflow-hidden hover:bg-gray-100 transition-colors">
      <div className="relative aspect-video w-full bg-gradient-to-br from-gray-50 to-gray-100">
        <DisplayStatusBadge
          bucket={bucket}
          colors={colors}
          label={badgeLabel}
          className="absolute left-1.5 top-1.5 z-10 max-w-[80%] py-1 px-2 text-[11px] shadow-sm"
        />

        {showThumbnail ? (
          <img
            src={display.thumbnail}
            alt={t('Screenshot of {{name}}', { name: display.display })}
            className="h-full w-full object-cover"
            onError={onThumbnailError}
          />
        ) : (
          <div className="flex h-full items-center justify-center text-gray-300">
            <Monitor className="size-10" />
          </div>
        )}
      </div>

      <div className="flex flex-col gap-0.5 px-3 pt-2 pb-1.5">
        <h3 className="truncate text-sm font-semibold text-gray-800" title={display.display}>
          {display.display}
        </h3>
        {subtitle && (
          <p className="truncate text-xs text-gray-500" title={subtitle}>
            {subtitle}
          </p>
        )}
        <p className="text-xs text-gray-400">{t('Last seen {{time}}', { time: lastSeenLabel })}</p>
      </div>

      <div className="flex flex-col gap-1.5 px-3 pb-2">
        <div className="flex flex-col gap-0.5">
          <span className="text-[10px] font-semibold uppercase tracking-wide text-gray-400">
            {t('Currently Playing')}
          </span>
          <span
            className="truncate text-xs text-gray-700"
            title={display.currentLayout ?? undefined}
          >
            {display.currentLayout || '-'}
          </span>
        </div>

        <div className="flex flex-col gap-0.5">
          <span className="text-[10px] font-semibold uppercase tracking-wide text-gray-400">
            {t('Up Next')}
          </span>
          {isNextScheduleLoading ? (
            <Badge type="neutral" variation="outline" className="w-fit border-dashed text-gray-400">
              {t('Loading…')}
            </Badge>
          ) : nextSchedule ? (
            <div className="flex flex-col gap-0.5 min-w-0">
              <span className="truncate text-xs text-gray-700" title={nextSchedule.layoutName}>
                {nextSchedule.layoutName}
              </span>
              <div className="flex items-center gap-1.5 min-w-0">
                <Badge
                  type={NEXT_SCHEDULE_STATUS[nextSchedule.status]}
                  className="w-fit shrink-0 py-0.5 px-1.5 text-[10px]"
                >
                  {getNextScheduleStatusLabel(t, nextSchedule.status)}
                </Badge>
                {nextSchedule.status !== 'ready' && (
                  <span className="truncate text-[11px] text-gray-400">
                    {t('starts {{time}}', { time: formatRelative(nextSchedule.startsAt) })}
                  </span>
                )}
              </div>
            </div>
          ) : (
            <span className="text-xs text-gray-700">-</span>
          )}
        </div>
      </div>

      <div className="mt-auto grid grid-cols-2 border-t border-gray-200 text-xs font-semibold">
        <button
          type="button"
          onClick={() => onManage(display)}
          className="flex items-center justify-center gap-1.5 py-2 text-gray-800 border-r border-gray-200 hover:bg-gray-50 cursor-pointer focus:outline-2 focus:-outline-offset-2 focus:outline-xibo-blue-500"
        >
          <Settings className="size-3.5 shrink-0" />
          {t('Manage')}
        </button>
        <button
          type="button"
          onClick={() => onLiveView(display)}
          className="flex items-center justify-center gap-1.5 py-2 text-xibo-blue-600 hover:bg-xibo-blue-50 cursor-pointer focus:outline-2 focus:-outline-offset-2 focus:outline-xibo-blue-500"
        >
          <Eye className="size-3.5 shrink-0" />
          {t('Screenshots')}
        </button>
      </div>
    </div>
  );
}
