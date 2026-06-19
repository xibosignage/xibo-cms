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

export interface PieChartItem {
  name: string;
  value: number;
  color: string;
  display?: string;
}

interface LibraryUsagePieChartProps {
  title: string;
  data: PieChartItem[];
  emptyLabel: string;
}

const TooltipContent = makePieTooltip<PieChartItem>((entry) => ({
  color: entry.color,
  text: `${entry.name}: ${entry.display ?? entry.value}`,
}));

export default function LibraryUsagePieChart({
  title,
  data,
  emptyLabel,
}: LibraryUsagePieChartProps) {
  const total = data.reduce((sum, item) => sum + item.value, 0);
  const chartData = data.map((item) => ({ ...item, fill: item.color }));
  const { active, onMouseMove, onMouseLeave } = usePieTooltip();

  return (
    <div className="rounded-lg flex flex-col border border-gray-200 p-5">
      <h4 className="text-sm font-semibold text-gray-800 mb-4">{title}</h4>
      {total > 0 ? (
        <div
          onMouseMove={onMouseMove}
          onMouseLeave={onMouseLeave}
          className="w-full max-w-95 mx-auto"
        >
          <ResponsiveContainer width="100%" height={380} className="outline-none">
            <PieChart accessibilityLayer={false} className="outline-none">
              <Pie
                data={chartData}
                cx="50%"
                cy="50%"
                innerRadius="45%"
                outerRadius="75%"
                dataKey="value"
                paddingAngle={2}
                stroke="none"
                className="outline-none"
                isAnimationActive={false}
              />
              <Tooltip content={TooltipContent} active={active} isAnimationActive={false} />
              <Legend
                iconType="circle"
                iconSize={8}
                formatter={(value: string) => (
                  <span className="text-xs text-gray-600">{value}</span>
                )}
                wrapperStyle={{ paddingTop: 12 }}
              />
            </PieChart>
          </ResponsiveContainer>
        </div>
      ) : (
        <div className="flex h-95 items-center justify-center text-gray-400 text-sm">
          {emptyLabel}
        </div>
      )}
    </div>
  );
}
