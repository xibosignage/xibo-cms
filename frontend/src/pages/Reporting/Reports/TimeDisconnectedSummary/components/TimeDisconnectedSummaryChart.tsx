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

import { type ReactElement } from 'react';
import { useTranslation } from 'react-i18next';
import {
  Bar,
  BarChart,
  CartesianGrid,
  Legend,
  ResponsiveContainer,
  Tooltip,
  XAxis,
  YAxis,
  type TooltipContentProps,
} from 'recharts';

import type { GroupBy } from '../TimeDisconnectedSummaryConfig';

import type { TimeDisconnectedSummaryRow } from '@/services/timeDisconnectedSummaryApi';
import { BRAND_ACCENT, BRAND_PRIMARY } from '@/styles/brandColors';

const UPTIME_COLOR = BRAND_PRIMARY;
const DOWNTIME_COLOR = BRAND_ACCENT;

interface TimeDisconnectedSummaryChartProps {
  rows: TimeDisconnectedSummaryRow[];
  groupBy: GroupBy;
}

function ChartTooltip({ active, payload, label }: TooltipContentProps): ReactElement | null {
  if (!active || !payload?.length) {
    return null;
  }
  return (
    <div className="rounded-lg bg-gray-800 px-3 py-2 text-sm text-white shadow-lg">
      {label !== undefined && label !== '' && (
        <div className="mb-1 font-medium">{String(label)}</div>
      )}
      {payload.map((item) => (
        <div key={String(item.dataKey)} className="flex items-center gap-2">
          <span className="h-2.5 w-2.5 rounded-full" style={{ backgroundColor: item.color }} />
          <span>
            {item.name}: {item.value}
          </span>
        </div>
      ))}
    </div>
  );
}

export default function TimeDisconnectedSummaryChart({
  rows,
  groupBy,
}: TimeDisconnectedSummaryChartProps) {
  const { t } = useTranslation();
  const units = rows[0]?.postUnits ?? '';

  const data = rows.map((row) => ({
    label: groupBy === 'displayGroup' ? row.displayGroup : row.display,
    timeConnected: row.timeConnected,
    timeDisconnected: row.timeDisconnected,
  }));

  return (
    <div className="relative flex flex-col flex-1 min-h-0 w-full">
      <h3 className="mb-3 flex-none text-sm font-semibold text-gray-800">
        {t('Availability')} {units && `(${units})`}
      </h3>

      <div className="flex-1 min-h-75 w-full">
        <ResponsiveContainer width="100%" height="100%">
          <BarChart data={data} margin={{ top: 8, right: 16, bottom: 8, left: 0 }}>
            <CartesianGrid strokeDasharray="3 3" vertical={false} stroke="#E5E7EB" />
            <XAxis
              dataKey="label"
              axisLine={false}
              tickLine={false}
              tick={{ fontSize: 12, fill: '#9CA3AF' }}
              interval={0}
              angle={-30}
              textAnchor="end"
              height={70}
            />
            <YAxis
              axisLine={false}
              tickLine={false}
              tick={{ fontSize: 12, fill: '#9CA3AF' }}
              label={{
                value: units,
                position: 'insideLeft',
                angle: -90,
                style: { fontSize: 12, fill: '#9CA3AF', textAnchor: 'middle' },
              }}
            />
            <Tooltip content={ChartTooltip} cursor={{ fill: 'rgba(0,0,0,0.04)' }} />
            <Legend
              verticalAlign="top"
              align="right"
              iconType="circle"
              iconSize={8}
              formatter={(value: string) => <span className="text-xs text-gray-600">{value}</span>}
              wrapperStyle={{ paddingBottom: 8 }}
            />
            <Bar
              dataKey="timeConnected"
              stackId="availability"
              name={t('Uptime')}
              fill={UPTIME_COLOR}
              radius={[0, 0, 0, 0]}
              maxBarSize={48}
            />
            <Bar
              dataKey="timeDisconnected"
              stackId="availability"
              name={t('Downtime')}
              fill={DOWNTIME_COLOR}
              radius={[4, 4, 0, 0]}
              maxBarSize={48}
            />
          </BarChart>
        </ResponsiveContainer>
      </div>
    </div>
  );
}
