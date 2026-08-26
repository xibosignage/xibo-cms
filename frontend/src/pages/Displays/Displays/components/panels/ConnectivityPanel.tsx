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

import { Activity, Loader2 } from 'lucide-react';
import { DateTime } from 'luxon';
import { useTranslation } from 'react-i18next';
import {
  CartesianGrid,
  Line,
  LineChart,
  ReferenceArea,
  ResponsiveContainer,
  Tooltip,
  XAxis,
  YAxis,
} from 'recharts';

import { PanelCard } from './PanelCard';

import { useUserContext } from '@/context/UserContext';
import { useDisconnectionEvents } from '@/pages/Displays/Displays/hooks/useDisplayManageData';
import { STATUS_DOWN, STATUS_UP } from '@/styles/brandColors';
import type { DisconnectionEvent } from '@/types/displayManage';
import { DISPLAY_EVENT_TYPE } from '@/types/displayManage';

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
 * Merges outages that overlap or touch into single stretches.
 *
 * The CMS can write more than one row for the same outage: its status task starts a row from the
 * display's last contact, and if that runs twice against the same state the rows come out
 * identical. Left alone they inflate the count and the total, and the bands stack into a darker
 * shade than a single outage draws.
 */
function mergeOutages(outages: Outage[]): Outage[] {
  const merged: Outage[] = [];

  // collectOutages already sorted these by start.
  for (const outage of outages) {
    const last = merged[merged.length - 1];

    if (last && outage.from <= last.to) {
      last.to = Math.max(last.to, outage.to);
      last.ongoing = last.ongoing || outage.ongoing;
    } else {
      merged.push({ ...outage });
    }
  }

  return merged;
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

interface ConnectivityPanelProps {
  displayId: number;
}

/**
 * Uptime for one display, as a step line that drops for each outage.
 *
 * Reads the CMS's own Display Up/Down events, which it raises when a display stops checking in, so
 * this needs nothing from the player.
 */
export default function ConnectivityPanel({ displayId }: ConnectivityPanelProps) {
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
    displayId,
    windowMinutes,
    cmsTimeZone,
    // Narrowed, because the table also holds command and app start events.
    [DISPLAY_EVENT_TYPE.displayUpDown],
    true,
  );

  // Read once per render so the axis, samples and bands share one instant.
  const windowEnd = Date.now();
  const windowStart = windowEnd - windowMinutes * 60 * 1000;

  const outages = mergeOutages(collectOutages(events ?? [], windowStart, windowEnd, cmsTimeZone));
  const samples = buildConnectivitySamples(outages, windowStart, windowEnd);

  // Includes an ongoing outage; its length so far is real downtime.
  const totalDownMs = outages.reduce((sum, outage) => sum + (outage.to - outage.from), 0);
  const longestDownMs = outages.reduce(
    (longest, outage) => Math.max(longest, outage.to - outage.from),
    0,
  );

  return (
    <PanelCard title={t('Uptime')} icon={Activity}>
      <div className="p-4">
        <p className="mb-4 text-sm text-gray-600">{windowLabel(t, windowMinutes)}</p>

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
                  style={{ backgroundColor: STATUS_DOWN, opacity: 0.16 }}
                />
                {t('Outage')}
              </span>
              <span className="inline-flex items-center gap-1.5">
                <span
                  className="h-2.5 w-2.5 rounded-sm border border-dashed"
                  style={{
                    borderColor: STATUS_DOWN,
                    backgroundColor: STATUS_DOWN,
                    opacity: 0.07,
                  }}
                />
                {t('Still down')}
              </span>
            </div>
          </>
        )}
      </div>
    </PanelCard>
  );
}
