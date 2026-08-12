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

import { useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';
import {
  Bar,
  BarChart,
  CartesianGrid,
  Rectangle,
  ResponsiveContainer,
  XAxis,
  YAxis,
} from 'recharts';

import type { BandwidthTableRow } from '@/services/bandwidthReportApi';
import { BRAND_PRIMARY } from '@/styles/brandColors';

const BAR_COLOR = BRAND_PRIMARY;
const DELETED_COLOR = '#e7000b';
const TRACK_COLOR = '#ECECEC';

const ROW_HEIGHT = 34;
const AXIS_HEIGHT = 48;

// Map the backend's raw size suffixes ('bytes', 'k', 'M', 'G', 'T') to display units.
const UNIT_LABELS: Record<string, string> = {
  bytes: 'B',
  k: 'KB',
  M: 'MB',
  G: 'GB',
  T: 'TB',
};

interface BandwidthBarChartProps {
  rows: BandwidthTableRow[];
}

export default function BandwidthBarChart({ rows }: BandwidthBarChartProps) {
  const { t } = useTranslation();
  const rawUnit = rows[0]?.unit ?? '';
  const unit = UNIT_LABELS[rawUnit] ?? rawUnit;
  const containerRef = useRef<HTMLDivElement>(null);
  const [mousePos, setMousePos] = useState({ x: 0, y: 0 });
  const [tooltip, setTooltip] = useState<{ label: string; value: number; color: string } | null>(
    null,
  );

  const handleMouseMove = (e: React.MouseEvent) => {
    const rect = containerRef.current?.getBoundingClientRect();
    if (rect) {
      setMousePos({ x: e.clientX - rect.left, y: e.clientY - rect.top });
    }
  };

  const clearTooltip = () => setTooltip(null);

  const colorFor = (deleted: boolean) => (deleted ? DELETED_COLOR : BAR_COLOR);

  const handleBarEnter = (data: { payload?: BandwidthTableRow }) => {
    const point = data.payload;
    if (point) {
      setTooltip({ label: point.label, value: point.bandwidth, color: colorFor(point.deleted) });
    }
  };

  const chartHeight = rows.length * ROW_HEIGHT + AXIS_HEIGHT;

  return (
    <div
      ref={containerRef}
      onMouseMove={handleMouseMove}
      className="relative flex flex-col flex-1 min-h-0 w-full"
    >
      <h3 className="mb-3 flex-none text-sm font-semibold text-gray-800">
        {t('Displays Bandwidth')} {unit && `(${unit})`}
      </h3>

      {tooltip && (
        <div
          className="pointer-events-none absolute z-10 -translate-x-1/2 -translate-y-full"
          style={{ left: mousePos.x, top: mousePos.y - 12 }}
        >
          <div className="whitespace-nowrap rounded-lg bg-gray-800 px-3 py-2 text-sm text-white shadow-lg">
            <div className="mb-1 font-medium">{tooltip.label}</div>
            <div className="flex items-center gap-2">
              <span
                className="h-2.5 w-2.5 shrink-0 rounded-full"
                style={{ backgroundColor: tooltip.color }}
              />
              <span>
                {t('Bandwidth')}: {tooltip.value} {unit}
              </span>
            </div>
          </div>
          <div className="mx-auto h-0 w-0 border-x-[6px] border-x-transparent border-t-[6px] border-t-gray-800" />
        </div>
      )}

      <div className="flex-1 min-h-0 w-full overflow-y-auto">
        <ResponsiveContainer width="100%" height={chartHeight}>
          <BarChart
            layout="vertical"
            data={rows}
            accessibilityLayer={false}
            margin={{ top: 0, right: 24, bottom: 8, left: 8 }}
            onMouseLeave={clearTooltip}
          >
            <CartesianGrid horizontal={false} stroke="#E5E7EB" />
            <XAxis
              type="number"
              axisLine={false}
              tickLine={false}
              tick={{ fontSize: 12, fill: '#9CA3AF' }}
              tickFormatter={(value: number) => `${value} ${unit}`.trim()}
            />
            <YAxis
              type="category"
              dataKey="label"
              width={150}
              axisLine={false}
              tickLine={false}
              tick={{ fontSize: 12, fill: '#9CA3AF' }}
              interval={0}
            />
            <Bar
              dataKey="bandwidth"
              name={t('Bandwidth')}
              maxBarSize={20}
              background={{ fill: TRACK_COLOR, radius: 4 }}
              onMouseEnter={handleBarEnter}
              onMouseLeave={clearTooltip}
              shape={(props: { payload?: BandwidthTableRow }) => (
                <Rectangle
                  {...props}
                  radius={[0, 4, 4, 0]}
                  fill={colorFor(props.payload?.deleted ?? false)}
                />
              )}
            />
          </BarChart>
        </ResponsiveContainer>
      </div>
    </div>
  );
}
