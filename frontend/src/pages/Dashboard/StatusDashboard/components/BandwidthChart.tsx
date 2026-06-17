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
import { Bar, BarChart, CartesianGrid, Legend, ResponsiveContainer, XAxis, YAxis } from 'recharts';

interface BandwidthDataset {
  label: string;
  backgroundColor: string;
  data: number[];
}

interface BandwidthWidgetData {
  labels: string[];
  datasets: BandwidthDataset[];
}

interface ChartDataPoint {
  month: string;
  used: number;
  available?: number;
}

interface BandwidthChartProps {
  bandwidthWidget: string;
  bandwidthSuffix: string;
}

function shortenMonth(month: string): string {
  return month.slice(0, 3);
}

function parseBandwidthData(widgetJson: string): ChartDataPoint[] {
  const parsed = JSON.parse(widgetJson) as BandwidthWidgetData;
  return parsed.labels.map((label, i) => {
    const point: ChartDataPoint = {
      month: shortenMonth(label),
      used: parsed.datasets[0]?.data[i] ?? 0,
    };
    if (parsed.datasets[1]) {
      point.available = parsed.datasets[1].data[i] ?? 0;
    }
    return point;
  });
}

export default function BandwidthChart({ bandwidthWidget, bandwidthSuffix }: BandwidthChartProps) {
  const { t } = useTranslation();
  const chartData = parseBandwidthData(bandwidthWidget);
  const hasLimit = chartData.length > 0 && chartData[0]?.available !== undefined;
  const containerRef = useRef<HTMLDivElement>(null);
  const [mousePos, setMousePos] = useState({ x: 0, y: 0 });
  const [tooltip, setTooltip] = useState<{
    label: string;
    name: string;
    value: number;
    color: string;
  } | null>(null);

  const handleMouseMove = (e: React.MouseEvent) => {
    const rect = containerRef.current?.getBoundingClientRect();
    if (rect) {
      setMousePos({ x: e.clientX - rect.left, y: e.clientY - rect.top });
    }
  };

  const handleBarEnter = (dataKey: 'used' | 'available', name: string, color: string) => {
    return (data: { payload?: ChartDataPoint }) => {
      const point = data.payload;
      if (point) {
        setTooltip({ label: point.month, name, value: point[dataKey] ?? 0, color });
      }
    };
  };

  const clearTooltip = () => setTooltip(null);

  return (
    <div ref={containerRef} onMouseMove={handleMouseMove} className="relative">
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
                {tooltip.name}: {tooltip.value} {bandwidthSuffix}
              </span>
            </div>
          </div>
          <div className="mx-auto h-0 w-0 border-x-[6px] border-x-transparent border-t-[6px] border-t-gray-800" />
        </div>
      )}
      <ResponsiveContainer width="100%" height={250}>
        <BarChart data={chartData} accessibilityLayer={false} onMouseLeave={clearTooltip}>
          <CartesianGrid strokeDasharray="3 3" vertical={false} stroke="#E5E7EB" />
          <XAxis
            dataKey="month"
            axisLine={false}
            tickLine={false}
            tick={{ fontSize: 12, fill: '#9CA3AF' }}
          />
          <YAxis
            axisLine={false}
            tickLine={false}
            tick={{ fontSize: 12, fill: '#9CA3AF' }}
            label={{
              value: bandwidthSuffix,
              position: 'insideLeft',
              angle: -90,
              style: { fontSize: 12, fill: '#9CA3AF' },
            }}
          />
          <Legend
            iconType="circle"
            iconSize={8}
            formatter={(value: string) => <span className="text-xs text-gray-600">{value}</span>}
            wrapperStyle={{ paddingTop: 8 }}
          />
          <Bar
            dataKey="used"
            name={t('Used')}
            stackId="bandwidth"
            fill="#0E70F6"
            radius={hasLimit ? [0, 0, 0, 0] : [4, 4, 0, 0]}
            onMouseEnter={handleBarEnter('used', t('Used'), '#0E70F6')}
            onMouseLeave={clearTooltip}
          />
          {hasLimit && (
            <Bar
              dataKey="available"
              name={t('Available')}
              stackId="bandwidth"
              fill="rgba(0, 0, 0, 0.05)"
              radius={[4, 4, 0, 0]}
              onMouseEnter={handleBarEnter('available', t('Available'), '#E5E7EB')}
              onMouseLeave={clearTooltip}
            />
          )}
        </BarChart>
      </ResponsiveContainer>
    </div>
  );
}
