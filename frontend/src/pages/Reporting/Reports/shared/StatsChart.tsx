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

import { Eye, EyeOff } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import {
  Bar,
  CartesianGrid,
  ComposedChart,
  Legend,
  Line,
  Pie,
  PieChart,
  ResponsiveContainer,
  Tooltip,
  XAxis,
  YAxis,
} from 'recharts';

import type { StatsChartType } from './types';

import type { StatsReportTableRow } from '@/services/statsReportApi';

const DURATION_COLOR = '#14b8a6';
const COUNT_COLOR = '#0e70f6';

const SLICE_COLORS = [
  '#0e70f6',
  '#14b8a6',
  '#f59e0b',
  '#ef4444',
  '#8b5cf6',
  '#ec4899',
  '#10b981',
  '#6366f1',
  '#f97316',
  '#06b6d4',
];

const TOOLTIP_STYLE = {
  borderRadius: 4,
  border: 'none',
  backgroundColor: '#1f2937',
  color: '#fff',
  fontSize: 12,
} as const;

interface StatsChartProps {
  rows: StatsReportTableRow[];
  type: StatsChartType;
}

export default function StatsChart({ rows, type }: StatsChartProps) {
  const { t } = useTranslation();
  const [hidden, setHidden] = useState<Record<string, boolean>>({});

  const toggleSeries = (dataKey: string) => {
    setHidden((prev) => ({ ...prev, [dataKey]: !prev[dataKey] }));
  };

  if (type === 'pie') {
    const pieData = rows.map((row, index) => ({
      name: row.label,
      value: row.count,
      fill: SLICE_COLORS[index % SLICE_COLORS.length],
    }));
    return (
      <div className="flex-1 min-h-75 w-full">
        <ResponsiveContainer width="100%" height="100%">
          <PieChart margin={{ top: 16, right: 16, bottom: 8, left: 0 }}>
            <Tooltip
              contentStyle={TOOLTIP_STYLE}
              labelStyle={{ color: '#fff', fontWeight: 500 }}
              itemStyle={{ color: '#fff' }}
            />
            <Legend
              verticalAlign="bottom"
              iconType="circle"
              iconSize={8}
              formatter={(value: string) => (
                <span className="text-xs text-gray-600">{t(value)}</span>
              )}
              wrapperStyle={{ paddingTop: 8 }}
            />
            <Pie
              data={pieData}
              dataKey="value"
              nameKey="name"
              cx="50%"
              cy="50%"
              outerRadius="70%"
              innerRadius="45%"
              paddingAngle={2}
            />
          </PieChart>
        </ResponsiveContainer>
      </div>
    );
  }

  return (
    <div className="flex-1 min-h-75 w-full">
      <ResponsiveContainer width="100%" height="100%">
        <ComposedChart data={rows} margin={{ top: 16, right: 16, bottom: 8, left: 0 }}>
          <CartesianGrid
            yAxisId="duration"
            strokeDasharray="3 3"
            horizontal
            vertical={false}
            stroke="#D1D5DB"
          />
          <XAxis
            dataKey="label"
            axisLine={false}
            tickLine={false}
            tick={{ fontSize: 12, fill: '#9CA3AF' }}
          />
          <YAxis
            yAxisId="duration"
            axisLine={false}
            tickLine={false}
            tick={{ fontSize: 12, fill: '#9CA3AF' }}
            label={{
              value: t('Duration (seconds)'),
              position: 'insideLeft',
              angle: -90,
              style: { fontSize: 12, fill: '#9CA3AF', textAnchor: 'middle' },
            }}
          />
          <YAxis
            yAxisId="count"
            orientation="right"
            axisLine={false}
            tickLine={false}
            tick={{ fontSize: 12, fill: '#9CA3AF' }}
            label={{
              value: t('Count'),
              position: 'insideRight',
              angle: 90,
              style: { fontSize: 12, fill: '#9CA3AF', textAnchor: 'middle' },
            }}
          />
          <Tooltip
            contentStyle={TOOLTIP_STYLE}
            labelStyle={{ color: '#fff', fontWeight: 500 }}
            itemStyle={{ color: '#fff' }}
          />
          <Legend
            verticalAlign="top"
            align="right"
            iconType="circle"
            iconSize={8}
            onClick={(entry) => toggleSeries(String(entry.dataKey))}
            formatter={(value: string, entry) => {
              const isHidden = hidden[String(entry.dataKey)];
              return (
                <span
                  className={`inline-flex cursor-pointer items-center gap-1 text-xs ${
                    isHidden ? 'text-gray-400 line-through' : 'text-gray-600'
                  }`}
                >
                  {value}
                  {isHidden ? <EyeOff size={14} /> : <Eye size={14} />}
                </span>
              );
            }}
            wrapperStyle={{ paddingBottom: 8 }}
          />
          {type === 'bar' ? (
            <Bar
              yAxisId="duration"
              dataKey="duration"
              name={t('Total Duration')}
              fill={DURATION_COLOR}
              radius={[4, 4, 0, 0]}
              hide={hidden.duration}
            />
          ) : (
            <Line
              yAxisId="duration"
              type="monotone"
              dataKey="duration"
              name={t('Total Duration')}
              stroke={DURATION_COLOR}
              strokeWidth={2}
              dot={false}
              activeDot={{ r: 4 }}
              hide={hidden.duration}
            />
          )}
          {type === 'bar' ? (
            <Bar
              yAxisId="count"
              dataKey="count"
              name={t('Total Count')}
              fill={COUNT_COLOR}
              radius={[4, 4, 0, 0]}
              hide={hidden.count}
            />
          ) : (
            <Line
              yAxisId="count"
              type="monotone"
              dataKey="count"
              name={t('Total Count')}
              stroke={COUNT_COLOR}
              strokeWidth={2}
              dot={false}
              activeDot={{ r: 4 }}
              hide={hidden.count}
            />
          )}
        </ComposedChart>
      </ResponsiveContainer>
    </div>
  );
}
