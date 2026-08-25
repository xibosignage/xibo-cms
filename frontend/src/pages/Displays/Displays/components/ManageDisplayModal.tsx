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

import { Check, Loader2, X } from 'lucide-react';
import { DateTime } from 'luxon';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import {
  Bar,
  BarChart,
  CartesianGrid,
  Legend,
  Line,
  LineChart,
  ReferenceArea,
  ResponsiveContainer,
  Tooltip,
  XAxis,
  YAxis,
} from 'recharts';

import {
  useBandwidthData,
  useDisconnectionEvents,
  useDisplayManageData,
} from '../hooks/useDisplayManageData';

import Modal from '@/components/ui/modals/Modal';
import { useUserContext } from '@/context/UserContext';
import DisplayChart from '@/pages/Dashboard/StatusDashboard/components/DisplayChart';
import { BRAND_PRIMARY, STATUS_DOWN, STATUS_UP } from '@/styles/brandColors';
import type { Display } from '@/types/display';
import type {
  DisconnectionEvent,
  ManageDependency,
  ManageLayout,
  ManageMedia,
  ManageWidget,
  ManageWidgetData,
  PlayerFault,
} from '@/types/displayManage';
import { DISPLAY_EVENT_TYPE } from '@/types/displayManage';
import { hasFeature } from '@/utils/permissions';

interface ManageDisplayModalProps {
  display: Display;
  onClose: () => void;
}

/** How far back the chart looks. Passed down as an argument, never read directly below. */
const CONNECTIVITY_WINDOW_MINUTES = 1440;

/**
 * Roughly how many evenly spaced points to lay across the window. These carry the flat stretches
 * and give the tooltip something to hit; outage edges are added on top exactly.
 */
const BASELINE_SAMPLE_COUNT = 100;

/** The two heights the line sits at. The axis relabels them, so the 0 and 1 never show. */
const LEVEL_UP = 1;
const LEVEL_DOWN = 0;

/** One point on the line. */
interface ConnectivitySample {
  t: number;
  level: number;
}

/**
 * A stretch of time the display was unreachable, clipped to the chart window. `ongoing` means it
 * has no end yet and is drawn up to the present.
 */
interface Outage {
  from: number;
  to: number;
  ongoing: boolean;
}

function CompletionIcon({ complete }: { complete: number }) {
  return complete === 1 ? (
    <Check className="w-4 h-4 text-green-600" />
  ) : (
    <X className="w-4 h-4 text-red-500" />
  );
}

function SectionCard({ title, children }: { title: string; children: React.ReactNode }) {
  return (
    <div className="rounded-lg border border-gray-200 overflow-hidden">
      <div className="bg-gray-50 px-4 py-2.5 border-b border-gray-200">
        <h3 className="text-sm font-semibold text-gray-700">{title}</h3>
      </div>
      <div className="overflow-x-auto">{children}</div>
    </div>
  );
}

function EmptyState({ message }: { message: string }) {
  return <p className="px-4 py-3 text-sm text-gray-400 italic text-center">{message}</p>;
}

function DependenciesTable({ data }: { data: ManageDependency[] }) {
  const { t } = useTranslation();
  if (data.length === 0) return <EmptyState message={t('No dependencies')} />;

  return (
    <table className="w-full text-sm">
      <thead className="bg-gray-50 border-b border-gray-200">
        <tr>
          <th className="text-left px-3 py-2 font-semibold text-gray-600">{t('Path')}</th>
          <th className="text-left px-3 py-2 font-semibold text-gray-600">{t('File Type')}</th>
          <th className="text-right px-3 py-2 font-semibold text-gray-600">{t('Bytes')}</th>
          <th className="text-center px-3 py-2 font-semibold text-gray-600">{t('Complete')}</th>
        </tr>
      </thead>
      <tbody>
        {data.map((dep) => (
          <tr
            key={`${dep.path}-${dep.fileType}`}
            className="border-b border-gray-100 last:border-0 hover:bg-gray-50"
          >
            <td className="px-3 py-2 text-gray-700 break-all">{dep.path}</td>
            <td className="px-3 py-2 text-gray-500">{dep.fileType}</td>
            <td className="px-3 py-2 text-gray-500 text-right">{dep.bytesRequested}</td>
            <td className="px-3 py-2 text-center">
              <CompletionIcon complete={dep.complete} />
            </td>
          </tr>
        ))}
      </tbody>
    </table>
  );
}

function LayoutsTable({ data }: { data: ManageLayout[] }) {
  const { t } = useTranslation();
  if (data.length === 0) return <EmptyState message={t('No layouts')} />;

  return (
    <table className="w-full text-sm">
      <thead className="bg-gray-50 border-b border-gray-200">
        <tr>
          <th className="text-left px-3 py-2 font-semibold text-gray-600">{t('ID')}</th>
          <th className="text-left px-3 py-2 font-semibold text-gray-600">{t('Layout')}</th>
          <th className="text-right px-3 py-2 font-semibold text-gray-600">{t('Size')}</th>
          <th className="text-center px-3 py-2 font-semibold text-gray-600">{t('Complete')}</th>
          <th className="text-right px-3 py-2 font-semibold text-gray-600">{t('Bytes')}</th>
        </tr>
      </thead>
      <tbody>
        {data.map((layout) => (
          <tr
            key={layout.itemId}
            className="border-b border-gray-100 last:border-0 hover:bg-gray-50"
          >
            <td className="px-3 py-2 text-gray-500">{layout.itemId}</td>
            <td className="px-3 py-2 text-gray-700">{layout.layout}</td>
            <td className="px-3 py-2 text-gray-500 text-right">{layout.size}</td>
            <td className="px-3 py-2 text-center">
              <CompletionIcon complete={layout.complete} />
            </td>
            <td className="px-3 py-2 text-gray-500 text-right">{layout.bytesRequested}</td>
          </tr>
        ))}
      </tbody>
    </table>
  );
}

function MediaTable({ data }: { data: ManageMedia[] }) {
  const { t } = useTranslation();
  if (data.length === 0) return <EmptyState message={t('No media')} />;

  return (
    <table className="w-full text-sm">
      <thead className="bg-gray-50 border-b border-gray-200">
        <tr>
          <th className="text-left px-3 py-2 font-semibold text-gray-600">{t('ID')}</th>
          <th className="text-left px-3 py-2 font-semibold text-gray-600">{t('Name')}</th>
          <th className="text-left px-3 py-2 font-semibold text-gray-600">{t('Type')}</th>
          <th className="text-right px-3 py-2 font-semibold text-gray-600">{t('Size')}</th>
          <th className="text-center px-3 py-2 font-semibold text-gray-600">{t('Complete')}</th>
          <th className="text-right px-3 py-2 font-semibold text-gray-600">{t('Bytes')}</th>
          <th className="text-center px-3 py-2 font-semibold text-gray-600">{t('Released')}</th>
        </tr>
      </thead>
      <tbody>
        {data.map((m) => (
          <tr key={m.itemId} className="border-b border-gray-100 last:border-0 hover:bg-gray-50">
            <td className="px-3 py-2 text-gray-500">{m.itemId}</td>
            <td className="px-3 py-2 text-gray-700">{m.name}</td>
            <td className="px-3 py-2 text-gray-500">{m.type}</td>
            <td className="px-3 py-2 text-gray-500 text-right">{m.size}</td>
            <td className="px-3 py-2 text-center">
              <CompletionIcon complete={m.complete} />
            </td>
            <td className="px-3 py-2 text-gray-500 text-right">{m.bytesRequested}</td>
            <td className="px-3 py-2 text-center">
              <CompletionIcon complete={m.released} />
            </td>
          </tr>
        ))}
      </tbody>
    </table>
  );
}

function WidgetsTable({ data }: { data: ManageWidget[] }) {
  const { t } = useTranslation();
  if (data.length === 0) return <EmptyState message={t('No widgets')} />;

  return (
    <table className="w-full text-sm">
      <thead className="bg-gray-50 border-b border-gray-200">
        <tr>
          <th className="text-left px-3 py-2 font-semibold text-gray-600">{t('ID')}</th>
          <th className="text-left px-3 py-2 font-semibold text-gray-600">{t('Name')}</th>
          <th className="text-center px-3 py-2 font-semibold text-gray-600">{t('Complete')}</th>
          <th className="text-right px-3 py-2 font-semibold text-gray-600">{t('Bytes')}</th>
        </tr>
      </thead>
      <tbody>
        {data.map((w) => (
          <tr key={w.itemId} className="border-b border-gray-100 last:border-0 hover:bg-gray-50">
            <td className="px-3 py-2 text-gray-500">{w.itemId}</td>
            <td className="px-3 py-2 text-gray-700">{w.widgetName || w.widgetType}</td>
            <td className="px-3 py-2 text-center">
              <CompletionIcon complete={w.complete} />
            </td>
            <td className="px-3 py-2 text-gray-500 text-right">{w.bytesRequested}</td>
          </tr>
        ))}
      </tbody>
    </table>
  );
}

function WidgetDataTable({ data }: { data: ManageWidgetData[] }) {
  const { t } = useTranslation();
  if (data.length === 0) return <EmptyState message={t('No widget data')} />;

  return (
    <table className="w-full text-sm">
      <thead className="bg-gray-50 border-b border-gray-200">
        <tr>
          <th className="text-left px-3 py-2 font-semibold text-gray-600">{t('Widget ID')}</th>
          <th className="text-left px-3 py-2 font-semibold text-gray-600">{t('Name')}</th>
          <th className="text-right px-3 py-2 font-semibold text-gray-600">{t('Bytes')}</th>
        </tr>
      </thead>
      <tbody>
        {data.map((wd) => (
          <tr
            key={`${wd.widgetId}-${wd.widgetType}`}
            className="border-b border-gray-100 last:border-0 hover:bg-gray-50"
          >
            <td className="px-3 py-2 text-gray-500">{wd.widgetId}</td>
            <td className="px-3 py-2 text-gray-700">{wd.widgetName || wd.widgetType}</td>
            <td className="px-3 py-2 text-gray-500 text-right">{wd.bytesRequested}</td>
          </tr>
        ))}
      </tbody>
    </table>
  );
}

function FaultsTable({ data }: { data: PlayerFault[] }) {
  const { t } = useTranslation();
  if (data.length === 0) return <EmptyState message={t('No reported faults')} />;

  return (
    <table className="w-full text-sm">
      <thead className="bg-gray-50 border-b border-gray-200">
        <tr>
          <th className="text-left px-3 py-2 font-semibold text-gray-600">{t('Code')}</th>
          <th className="text-left px-3 py-2 font-semibold text-gray-600">{t('Reason')}</th>
          <th className="text-left px-3 py-2 font-semibold text-gray-600">{t('Date')}</th>
          <th className="text-left px-3 py-2 font-semibold text-gray-600">{t('Expires')}</th>
          <th className="text-left px-3 py-2 font-semibold text-gray-600">{t('Schedule ID')}</th>
          <th className="text-left px-3 py-2 font-semibold text-gray-600">{t('Layout ID')}</th>
          <th className="text-left px-3 py-2 font-semibold text-gray-600">{t('Media ID')}</th>
        </tr>
      </thead>
      <tbody>
        {data.map((fault) => (
          <tr
            key={fault.playerFaultId}
            className="border-b border-gray-100 last:border-0 hover:bg-gray-50"
          >
            <td className="px-3 py-2 text-gray-700">{fault.code}</td>
            <td className="px-3 py-2 text-gray-500">{fault.reason}</td>
            <td className="px-3 py-2 text-gray-500">{fault.incidentDt}</td>
            <td className="px-3 py-2 text-gray-500">{fault.expires}</td>
            <td className="px-3 py-2 text-gray-500">{fault.scheduleId || '-'}</td>
            <td className="px-3 py-2 text-gray-500">{fault.layoutId || '-'}</td>
            <td className="px-3 py-2 text-gray-500">{fault.mediaId || '-'}</td>
          </tr>
        ))}
      </tbody>
    </table>
  );
}

function BandwidthSection({
  displayId,
  defaults,
}: {
  displayId: number;
  defaults: { fromDate: string; toDate: string };
}) {
  const { t } = useTranslation();
  const [fromDt, setFromDt] = useState(defaults.fromDate);
  const [toDt, setToDt] = useState(defaults.toDate);

  const { data: bandwidthData, isFetching } = useBandwidthData(displayId, fromDt, toDt, true);

  const chartData =
    bandwidthData?.labels?.map((label, i) => ({
      name: label,
      value: bandwidthData.data[i] ?? 0,
    })) ?? [];

  const suffix = bandwidthData?.postUnits ?? '';

  return (
    <SectionCard title={t('Bandwidth')}>
      <div className="p-4">
        <div className="flex items-center gap-4 mb-4">
          <label className="text-sm text-gray-600">
            {t('From')}
            <input
              type="date"
              className="ml-2 rounded border border-gray-300 px-2 py-1 text-sm"
              value={fromDt.split(' ')[0] || fromDt}
              onChange={(e) => setFromDt(e.target.value || defaults.fromDate)}
            />
          </label>
          <label className="text-sm text-gray-600">
            {t('To')}
            <input
              type="date"
              className="ml-2 rounded border border-gray-300 px-2 py-1 text-sm"
              value={toDt.split(' ')[0] || toDt}
              onChange={(e) => setToDt(e.target.value || defaults.toDate)}
            />
          </label>
        </div>
        {isFetching && (
          <div className="flex items-center justify-center py-8">
            <Loader2 className="w-6 h-6 animate-spin text-gray-400" />
          </div>
        )}
        {!isFetching && chartData.length > 0 && (
          <ResponsiveContainer width="100%" height={250}>
            <BarChart data={chartData} accessibilityLayer={false}>
              <CartesianGrid strokeDasharray="3 3" vertical={false} stroke="#E5E7EB" />
              <XAxis
                dataKey="name"
                axisLine={false}
                tickLine={false}
                tick={{ fontSize: 12, fill: '#9CA3AF' }}
              />
              <YAxis
                axisLine={false}
                tickLine={false}
                tick={{ fontSize: 12, fill: '#9CA3AF' }}
                label={{
                  value: suffix,
                  position: 'insideLeft',
                  angle: -90,
                  style: { fontSize: 12, fill: '#9CA3AF' },
                }}
              />
              <Legend
                iconType="circle"
                iconSize={8}
                formatter={(value: string) => (
                  <span className="text-xs text-gray-600">{value}</span>
                )}
                wrapperStyle={{ paddingTop: 8 }}
              />
              <Bar
                dataKey="value"
                name={t('Bandwidth')}
                fill={BRAND_PRIMARY}
                radius={[4, 4, 0, 0]}
              />
            </BarChart>
          </ResponsiveContainer>
        )}
        {!isFetching && chartData.length === 0 && (
          <EmptyState message={t('No bandwidth data for the selected period')} />
        )}
      </div>
    </SectionCard>
  );
}

/** Parses the API's 'YYYY-MM-DD HH:mm:ss'. ISO wants a T separator. */
function parseCmsDate(value: string, timeZone: string): DateTime {
  return DateTime.fromISO(value.replace(' ', 'T'), { zone: timeZone });
}

/**
 * Reduces the event rows to the outages overlapping the chart window.
 *
 * These are inferred from a display going quiet, so the edges are only as sharp as the collect
 * interval, and anything shorter than one interval never appears at all.
 */
function collectOutages(
  events: DisconnectionEvent[],
  windowStart: number,
  windowEnd: number,
  timeZone: string,
): Outage[] {
  const outages: Outage[] = [];

  for (const event of events) {
    const startedAt = parseCmsDate(event.start, timeZone);

    if (!startedAt.isValid) {
      continue;
    }

    // No end yet, so it runs to the present.
    const endedAt = event.end ? parseCmsDate(event.end, timeZone) : null;
    const hasEnd = endedAt !== null && endedAt.isValid;
    const rawEnd = hasEnd ? Number(endedAt) : windowEnd;

    // Clipped, so one that began earlier still draws from the left edge.
    const from = Math.max(Number(startedAt), windowStart);
    const to = Math.min(rawEnd, windowEnd);

    if (to > from) {
      outages.push({ from, to, ongoing: !event.isFinished });
    }
  }

  return outages.sort((a, b) => a.from - b.from);
}

/**
 * Builds the points the line is drawn through. Outage edges are added to the even samples so a
 * dip lands on the second it happened. An outage covers its start up to but not including its
 * end, so the point at the end is already back up.
 */
function buildConnectivitySamples(
  outages: Outage[],
  windowStart: number,
  windowEnd: number,
): ConnectivitySample[] {
  const span = windowEnd - windowStart;
  // Floored at a minute so a narrow window cannot ask for thousands of points.
  const step = Math.max(60 * 1000, Math.floor(span / BASELINE_SAMPLE_COUNT));
  const timestamps = new Set<number>();

  for (let t = windowStart; t < windowEnd; t += step) {
    timestamps.add(t);
  }

  timestamps.add(windowEnd);

  for (const outage of outages) {
    timestamps.add(outage.from);
    timestamps.add(outage.to);
  }

  return [...timestamps]
    .filter((t) => t >= windowStart && t <= windowEnd)
    .sort((a, b) => a - b)
    .map((t) => ({
      t,
      level: outages.some((outage) => t >= outage.from && t < outage.to) ? LEVEL_DOWN : LEVEL_UP,
    }));
}

/**
 * Human label for the window, for example "Last 24 hours". Each case is a whole string so
 * translators get something that declines properly.
 */
function windowLabel(t: (key: string) => string, windowMinutes: number): string {
  if (windowMinutes % 1440 === 0) {
    const days = windowMinutes / 1440;
    return days === 1 ? t('Last 24 hours') : `${t('Last')} ${days} ${t('days')}`;
  }

  if (windowMinutes % 60 === 0) {
    const hours = windowMinutes / 60;
    return hours === 1 ? t('Last hour') : `${t('Last')} ${hours} ${t('hours')}`;
  }

  return `${t('Last')} ${windowMinutes} ${t('minutes')}`;
}

function formatMinutes(minutes: number): string {
  if (minutes < 1) {
    return Math.round(minutes * 60) + 's';
  }

  // Rounded before splitting into hours, otherwise 119.7 minutes renders as "1h 60m".
  const total = Math.round(minutes);

  if (total < 60) {
    return total + 'm';
  }

  return Math.floor(total / 60) + 'h ' + String(total % 60).padStart(2, '0') + 'm';
}

function ConnectivitySection({ display }: { display: Display }) {
  const { t } = useTranslation();
  const windowMinutes = CONNECTIVITY_WINDOW_MINUTES;

  // The CMS-wide timezone, not the viewer's. Everything here is sent, read and labelled in it.
  const { user } = useUserContext();
  const cmsTimeZone = user?.settings?.defaultTimezone ?? 'UTC';

  const {
    data: events,
    isPending,
    error,
  } = useDisconnectionEvents(
    display.displayId,
    windowMinutes,
    cmsTimeZone,
    // Narrowed, because the table also holds command and app start events.
    [DISPLAY_EVENT_TYPE.displayUpDown],
    true,
  );

  // Read once per render so the axis, samples and bands share one instant.
  const windowEnd = Date.now();
  const windowStart = windowEnd - windowMinutes * 60 * 1000;

  const outages = collectOutages(events ?? [], windowStart, windowEnd, cmsTimeZone);
  const samples = buildConnectivitySamples(outages, windowStart, windowEnd);

  // Includes an ongoing outage; its length so far is real downtime.
  const totalDownMs = outages.reduce((sum, outage) => sum + (outage.to - outage.from), 0);
  const longestDownMs = outages.reduce(
    (longest, outage) => Math.max(longest, outage.to - outage.from),
    0,
  );

  const isDownNow = outages.some((outage) => outage.ongoing);

  return (
    <SectionCard title={t('Connectivity')}>
      <div className="p-4">
        <div className="flex items-center gap-4 mb-4">
          <p className="text-sm text-gray-600">{windowLabel(t, windowMinutes)}</p>
          <span
            className={
              'ml-auto inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-medium ' +
              (isDownNow ? 'bg-red-50 text-red-700' : 'bg-green-50 text-green-700')
            }
          >
            <span
              className={'h-1.5 w-1.5 rounded-full ' + (isDownNow ? 'bg-red-500' : 'bg-green-500')}
            />
            {isDownNow ? t('Disconnected now') : t('Connected now')}
          </span>
        </div>

        {isPending && (
          <div className="flex items-center justify-center py-8">
            <Loader2 className="w-6 h-6 animate-spin text-gray-400" />
          </div>
        )}

        {!isPending && error && (
          <p className="px-4 py-3 text-sm text-red-600">
            {error instanceof Error ? error.message : t('Could not load connectivity')}
          </p>
        )}

        {!isPending && !error && (
          <>
            <div className="grid grid-cols-3 gap-4 mb-4">
              <div>
                <p className="text-xs text-gray-500">{t('Outages')}</p>
                <p className="text-lg font-semibold text-gray-800">{outages.length}</p>
              </div>
              <div>
                <p className="text-xs text-gray-500">{t('Total downtime')}</p>
                <p className="text-lg font-semibold text-gray-800">
                  {formatMinutes(totalDownMs / 60000)}
                </p>
              </div>
              <div>
                <p className="text-xs text-gray-500">{t('Longest outage')}</p>
                <p className="text-lg font-semibold text-gray-800">
                  {formatMinutes(longestDownMs / 60000)}
                </p>
              </div>
            </div>

            <ResponsiveContainer width="100%" height={200}>
              <LineChart data={samples} margin={{ top: 8, right: 16, bottom: 8, left: 8 }}>
                <CartesianGrid strokeDasharray="3 3" vertical={false} stroke="#E5E7EB" />
                {/* Shaded, because across a day a short dip is only a few pixels of travel. */}
                {outages.map((outage) => (
                  <ReferenceArea
                    key={outage.from + '-' + outage.ongoing}
                    x1={outage.from}
                    x2={outage.to}
                    y1={LEVEL_DOWN}
                    y2={LEVEL_UP}
                    fill={STATUS_DOWN}
                    fillOpacity={outage.ongoing ? 0.07 : 0.16}
                    stroke={outage.ongoing ? STATUS_DOWN : 'none'}
                    strokeDasharray="3 3"
                    strokeOpacity={0.5}
                    ifOverflow="hidden"
                  />
                ))}
                <XAxis
                  dataKey="t"
                  type="number"
                  scale="time"
                  // Fixed to the window so the line spans the full width and the bands line up.
                  domain={[windowStart, windowEnd]}
                  axisLine={false}
                  tickLine={false}
                  tick={{ fontSize: 12, fill: '#9CA3AF' }}
                  minTickGap={32}
                  tickFormatter={(value: number) =>
                    DateTime.fromMillis(value).setZone(cmsTimeZone).toFormat('HH:mm')
                  }
                />
                <YAxis
                  type="number"
                  domain={[LEVEL_DOWN, LEVEL_UP]}
                  ticks={[LEVEL_DOWN, LEVEL_UP]}
                  axisLine={false}
                  tickLine={false}
                  tick={{ fontSize: 12, fill: '#9CA3AF' }}
                  width={56}
                  tickFormatter={(value: number) => (value === LEVEL_UP ? t('Up') : t('Down'))}
                />
                <Tooltip
                  labelFormatter={(value) =>
                    DateTime.fromMillis(Number(value)).setZone(cmsTimeZone).toFormat('dd LLL HH:mm')
                  }
                  formatter={(value) => [Number(value) === LEVEL_UP ? t('Up') : t('Down'), '']}
                />
                <Line
                  // Held flat until the next reading; the link was either up or down, never between.
                  type="stepAfter"
                  dataKey="level"
                  stroke={STATUS_UP}
                  strokeWidth={2}
                  dot={false}
                  activeDot={{ r: 3 }}
                  isAnimationActive={false}
                />
              </LineChart>
            </ResponsiveContainer>

            <div className="mt-2 flex flex-wrap items-center gap-4 text-xs text-gray-500">
              <span className="inline-flex items-center gap-1.5">
                <span
                  className="h-2.5 w-2.5 rounded-sm"
                  style={{ backgroundColor: STATUS_DOWN, opacity: 0.32 }}
                />
                {t('Outage')}
              </span>
              <span className="inline-flex items-center gap-1.5">
                <span
                  className="h-2.5 w-2.5 rounded-sm border border-dashed"
                  style={{
                    borderColor: STATUS_DOWN,
                    backgroundColor: STATUS_DOWN,
                    opacity: 0.16,
                  }}
                />
                {t('Still down')}
              </span>
            </div>
          </>
        )}
      </div>
    </SectionCard>
  );
}

export default function ManageDisplayModal({ display, onClose }: ManageDisplayModalProps) {
  const { t } = useTranslation();
  const { user } = useUserContext();
  const showBandwidth = hasFeature(user, 'displays.reporting');

  const { manageQuery, faultsQuery } = useDisplayManageData(display.displayId);

  const isLoading = manageQuery.isLoading;
  const error = manageQuery.error instanceof Error ? manageQuery.error.message : null;
  const faultsError = faultsQuery.error instanceof Error ? faultsQuery.error.message : null;
  const data = manageQuery.data;
  const faultsRaw = faultsQuery.data;
  const faults = Array.isArray(faultsRaw) ? faultsRaw : [];

  const status = data?.status;
  const countChartData = status
    ? [
        {
          name: t('Downloaded'),
          value: status.countComplete,
          color: STATUS_UP,
        },
        {
          name: t('Pending'),
          value: status.countRemaining,
          color: STATUS_DOWN,
        },
      ]
    : [];

  const sizeChartData = status
    ? [
        {
          name: `${t('Downloaded')} (${status.units})`,
          value: status.sizeComplete,
          color: STATUS_UP,
        },
        {
          name: `${t('Pending')} (${status.units})`,
          value: status.sizeRemaining,
          color: STATUS_DOWN,
        },
      ]
    : [];

  return (
    <Modal
      isOpen
      onClose={onClose}
      title={`${t('Manage')}: ${display.display}`}
      showCloseButton
      size="xl"
      actions={[{ label: t('Close'), onClick: onClose, variant: 'secondary' }]}
      error={error ?? undefined}
    >
      <div className="p-6 space-y-6">
        {isLoading && (
          <div className="flex items-center justify-center py-16">
            <Loader2 className="w-8 h-8 animate-spin text-gray-400" />
          </div>
        )}

        {data && (
          <>
            {/* File Status Charts */}
            <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
              <SectionCard title={t('File Count')}>
                {(status?.countComplete ?? 0) + (status?.countRemaining ?? 0) > 0 ? (
                  <div className="p-4">
                    <DisplayChart data={countChartData} label={t('Files')} />
                  </div>
                ) : (
                  <EmptyState message={t('No file data available')} />
                )}
              </SectionCard>
              <SectionCard title={t('File Size')}>
                {(status?.sizeComplete ?? 0) + (status?.sizeRemaining ?? 0) > 0 ? (
                  <div className="p-4">
                    <DisplayChart data={sizeChartData} label={status?.units} />
                  </div>
                ) : (
                  <EmptyState message={t('No file data available')} />
                )}
              </SectionCard>
            </div>

            {/* Player Faults */}
            <SectionCard title={t('Reported Player Faults')}>
              {faultsQuery.isLoading ? (
                <div className="flex items-center justify-center py-6">
                  <Loader2 className="w-5 h-5 animate-spin text-gray-400" />
                </div>
              ) : faultsError ? (
                <p className="px-4 py-3 text-sm text-red-600">{faultsError}</p>
              ) : (
                <FaultsTable data={faults} />
              )}
            </SectionCard>

            {/* Dependencies & Layouts */}
            <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
              <SectionCard title={t('Dependencies')}>
                <DependenciesTable data={data.inventory?.dependencies ?? []} />
              </SectionCard>
              <SectionCard title={t('Layouts')}>
                <LayoutsTable data={data.inventory?.layouts ?? []} />
              </SectionCard>
            </div>

            {/* Media & Widgets */}
            <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
              <SectionCard title={t('Media')}>
                <MediaTable data={data.inventory?.media ?? []} />
              </SectionCard>
              <SectionCard title={t('Widgets')}>
                <WidgetsTable data={data.inventory?.widgets ?? []} />
              </SectionCard>
            </div>

            {/* Widget Data */}
            {(data.inventory?.widgetData?.length ?? 0) > 0 && (
              <SectionCard title={t('Widget Data')}>
                <WidgetDataTable data={data.inventory?.widgetData ?? []} />
              </SectionCard>
            )}

            {/* Connectivity */}
            {showBandwidth && <ConnectivitySection display={display} />}

            {/* Bandwidth */}
            {showBandwidth && (
              <BandwidthSection displayId={display.displayId} defaults={data.defaults} />
            )}
          </>
        )}
      </div>
    </Modal>
  );
}
