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

import { Legend, Pie, PieChart, ResponsiveContainer, Tooltip } from 'recharts';

import { makePieTooltip, usePieTooltip } from '@/components/ui/charts/pieTooltip';

export interface DisplayChartItem {
  name: string;
  value: number;
  color: string;
  fill?: string;
}

interface DisplayChartProps {
  data: DisplayChartItem[];
  label?: string;
}

const TooltipContent = makePieTooltip<DisplayChartItem>((entry) => ({
  color: entry.color,
  text: `${entry.name}: ${entry.value}`,
}));

export default function DisplayChart({ data, label }: DisplayChartProps) {
  const total = data.reduce((sum, item) => sum + item.value, 0);
  const chartData = data.map((item) => ({ ...item, fill: item.color }));
  const { active, onMouseMove, onMouseLeave } = usePieTooltip();

  return (
    <div className="flex flex-col items-center">
      <div onMouseMove={onMouseMove} onMouseLeave={onMouseLeave} className="w-full">
        <ResponsiveContainer width="100%" height={250} className="outline-none">
          <PieChart accessibilityLayer={false} className="outline-none">
            <Pie
              data={chartData}
              cx="50%"
              cy="50%"
              innerRadius={70}
              outerRadius={100}
              dataKey="value"
              paddingAngle={total > 0 ? 2 : 0}
              stroke="none"
              className="outline-none"
              isAnimationActive={false}
            />
            {label && (
              <text
                x="50%"
                y="45%"
                textAnchor="middle"
                dominantBaseline="central"
                className="fill-gray-400 text-[12px] font-medium uppercase tracking-wide"
              >
                {label}
              </text>
            )}
            <Tooltip content={TooltipContent} active={active} isAnimationActive={false} />
            <Legend
              iconType="circle"
              iconSize={8}
              formatter={(value: string) => <span className="text-xs text-gray-600">{value}</span>}
              wrapperStyle={{ paddingTop: 12 }}
              itemSorter={(a) => (a.value === data[0]?.name ? -1 : 1)}
            />
          </PieChart>
        </ResponsiveContainer>
      </div>
    </div>
  );
}
