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
  FloatingPortal,
  autoUpdate,
  flip,
  offset,
  shift,
  useFloating,
  useHover,
  useInteractions,
} from '@floating-ui/react';
import { ChevronDown, ChevronUp, Wifi, WifiOff } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { twMerge } from 'tailwind-merge';

import type { DisplayReportRow } from '../TimeConnectedConfig';

interface PeriodBarProps {
  percent: number;
  label: string;
  isFirst: boolean;
  isLast: boolean;
}

function PeriodBar({ percent, label, isFirst, isLast }: PeriodBarProps) {
  const { t } = useTranslation();
  const [open, setOpen] = useState(false);

  const { refs, floatingStyles, context } = useFloating({
    open,
    onOpenChange: setOpen,
    placement: 'top',
    whileElementsMounted: autoUpdate,
    middleware: [offset(6), flip(), shift({ padding: 8 })],
  });

  const hover = useHover(context);
  const { getReferenceProps, getFloatingProps } = useInteractions([hover]);

  const connectedPercent = percent > 0 ? Math.round(percent * 100) / 100 : 0;
  const disconnectedPercent = Math.round((100 - connectedPercent) * 100) / 100;

  return (
    <div
      ref={refs.setReference}
      {...getReferenceProps()}
      className="relative flex-1 h-full min-w-0"
    >
      <div
        className={twMerge(
          'h-full w-full relative overflow-hidden bg-gray-200',
          isFirst && 'rounded-l-sm',
          isLast && 'rounded-r-sm',
        )}
      >
        <div
          className="h-full flex items-center justify-center bg-teal-500"
          style={{
            width: `${connectedPercent}%`,
          }}
        />
      </div>
      {open && (
        <FloatingPortal>
          <div
            ref={refs.setFloating}
            style={floatingStyles}
            {...getFloatingProps()}
            className="z-9999 bg-gray-900 text-white text-xs rounded-md px-2.5 py-2 whitespace-nowrap pointer-events-none shadow-lg"
          >
            <div className="font-semibold mb-1.5">{label}</div>
            <div className="flex items-center gap-1.5">
              <div className="w-2 h-2 rounded-sm bg-teal-500" />
              <span className="text-gray-300">{t('Connected')}</span>
              <span className="ml-auto font-medium">{connectedPercent}%</span>
            </div>
            <div className="flex items-center gap-1.5 mt-0.5">
              <div className="w-2 h-2 rounded-sm bg-gray-400" />
              <span className="text-gray-300">{t('Disconnected')}</span>
              <span className="ml-auto font-medium">{disconnectedPercent}%</span>
            </div>
          </div>
        </FloatingPortal>
      )}
    </div>
  );
}

interface TimelineProps {
  periods: DisplayReportRow['periods'];
}

function Timeline({ periods }: TimelineProps) {
  if (periods.length === 0) {
    return <div className="flex-1 h-6 bg-gray-100 rounded" />;
  }

  return (
    <div className="flex-1 flex gap-px h-6">
      {periods.map((period, i) => (
        <PeriodBar
          key={i}
          percent={period.percent}
          label={period.label}
          isFirst={i === 0}
          isLast={i === periods.length - 1}
        />
      ))}
    </div>
  );
}

interface TimeConnectedRowProps {
  row: DisplayReportRow;
}

export default function TimeConnectedRow({ row }: TimeConnectedRowProps) {
  const { t } = useTranslation();
  const [expanded, setExpanded] = useState(false);

  const uptimePill =
    row.uptimePercent <= 0
      ? 'bg-gray-100 text-gray-500'
      : row.uptimePercent >= 90
        ? 'bg-green-50 text-green-700'
        : row.uptimePercent >= 70
          ? 'bg-yellow-50 text-yellow-700'
          : 'bg-red-50 text-red-700';

  return (
    <div className="bg-white">
      <div
        className="flex items-center gap-3 px-4 py-3 hover:bg-gray-50 cursor-pointer"
        onClick={() => setExpanded((prev) => !prev)}
      >
        <div className="flex-none w-6 flex items-center justify-center">
          {row.uptimePercent > 0 ? (
            <div className="w-2 h-2 bg-teal-500 rounded-full" />
          ) : (
            <div className="w-2 h-2 bg-gray-200 rounded-full" />
          )}
        </div>

        <div className="flex-none w-45 min-w-0">
          <div className="font-semibold text-sm text-gray-800 truncate">{row.displayName}</div>
        </div>

        <div className="flex-none w-16 flex justify-end">
          <span
            className={twMerge(
              'inline-block rounded-full px-2 py-0.5 text-xs font-semibold',
              uptimePill,
            )}
          >
            {row.uptimePercent.toFixed(1)}%
          </span>
        </div>

        <Timeline periods={row.periods} />

        <div className="flex-none w-50 text-left text-xs text-gray-400 truncate">
          {row.lastAccessed ? t('Last seen {{date}}', { date: row.lastAccessed }) : ''}
        </div>

        <div className="flex-none w-5 text-gray-400">
          {expanded ? <ChevronUp className="w-4 h-4" /> : <ChevronDown className="w-4 h-4" />}
        </div>
      </div>

      {expanded && (
        <div className="bg-gray-50 px-4 py-4">
          <div className="flex items-center gap-3 mb-4">
            <div className="inline-flex items-center gap-2 bg-white border border-gray-200 rounded-lg px-3 py-2">
              <div className="w-7 h-7 rounded-full bg-green-50 flex items-center justify-center">
                <Wifi className="w-4 h-4 text-green-500" />
              </div>
              <span className="text-sm text-gray-500">
                {t('Online')}:{' '}
                <span className="font-semibold text-gray-900">{row.uptimePercent.toFixed(1)}%</span>
              </span>
            </div>
            <div className="inline-flex items-center gap-2 bg-white border border-gray-200 rounded-lg px-3 py-2">
              <div className="w-7 h-7 rounded-full bg-gray-100 flex items-center justify-center">
                <WifiOff className="w-4 h-4 text-gray-400" />
              </div>
              <span className="text-sm text-gray-500">
                {t('Offline')}:{' '}
                <span className="font-semibold text-gray-900">
                  {row.offlinePercent.toFixed(1)}%
                </span>
              </span>
            </div>
          </div>

          {row.periods.length > 0 && (
            <div>
              <div className="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">
                {t('Connectivity by Period')}
              </div>
              <div className="flex flex-col">
                {row.periods.map((period, i) => (
                  <div key={i} className="flex items-center gap-3 py-1.5 text-xs">
                    <div className="flex-none w-1.5 h-1.5 rounded-full bg-gray-300" />
                    <div className="flex-none w-40 text-gray-600 whitespace-nowrap">
                      {period.label}
                    </div>
                    <div className="flex-1 h-4 rounded-sm overflow-hidden bg-gray-200">
                      <div
                        className="h-full bg-teal-500"
                        style={{
                          width: `${period.percent}%`,
                        }}
                      />
                    </div>
                    <div className="flex-none w-16 text-right text-gray-700 font-medium">
                      {period.percent.toFixed(1)}%
                    </div>
                  </div>
                ))}
              </div>
            </div>
          )}
        </div>
      )}
    </div>
  );
}
