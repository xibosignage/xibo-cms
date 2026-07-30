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

import { useTranslation } from 'react-i18next';
import { Legend, Pie, PieChart, ResponsiveContainer, Tooltip } from 'recharts';

import type { SspSummaryStats } from '../SspActivityConfig';

import { makePieTooltip, usePieTooltip } from '@/components/ui/charts/pieTooltip';

const ERRORS_COLOR = '#e7000b';
const PLAYS_COLOR = '#00a63e';
const MISSES_COLOR = '#0e70f6';

const PieTooltipContent = makePieTooltip<{ name: string; value: number; fill: string }>(
  (entry) => ({
    color: entry.fill,
    text: `${entry.name}: ${entry.value}%`,
  }),
);

interface SspActivityChartProps {
  stats: SspSummaryStats;
}

export default function SspActivityChart({ stats }: SspActivityChartProps) {
  const { t } = useTranslation();
  const { active, onMouseMove, onMouseLeave } = usePieTooltip();

  const total = stats.totalErrorCount + stats.totalPlayCount + stats.totalMissCount;
  const pct = (value: number) => (total > 0 ? Number(((value / total) * 100).toFixed(2)) : 0);

  const pieData = [
    { name: t('Errors'), value: pct(stats.totalErrorCount), fill: ERRORS_COLOR },
    { name: t('Plays'), value: pct(stats.totalPlayCount), fill: PLAYS_COLOR },
    { name: t('Misses'), value: pct(stats.totalMissCount), fill: MISSES_COLOR },
  ];

  return (
    <div className="w-full flex justify-center">
      <div onMouseMove={onMouseMove} onMouseLeave={onMouseLeave} className="h-64 w-full max-w-80">
        <ResponsiveContainer width="100%" height="100%">
          <PieChart margin={{ top: 16, right: 16, bottom: 8, left: 0 }}>
            <Tooltip content={PieTooltipContent} active={active} isAnimationActive={false} />
            <Legend
              verticalAlign="bottom"
              iconType="circle"
              iconSize={8}
              formatter={(value: string) => <span className="text-xs text-gray-600">{value}</span>}
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
              isAnimationActive={false}
            />
          </PieChart>
        </ResponsiveContainer>
      </div>
    </div>
  );
}
