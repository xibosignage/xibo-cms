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
import type { TooltipContentProps } from 'recharts';
import { Legend, Pie, PieChart, ResponsiveContainer, Tooltip } from 'recharts';

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

function CustomTooltip({ active, payload }: TooltipContentProps) {
  if (!active || !payload?.length) return null;
  const entry = payload[0]?.payload as DisplayChartItem | undefined;
  if (!entry) return null;

  return (
    <div className="-translate-x-1/2">
      <div className="flex items-center gap-2 rounded-lg bg-gray-800 px-3 py-2 text-sm text-white shadow-lg">
        <span className="h-2.5 w-2.5 rounded-full" style={{ backgroundColor: entry.color }} />
        <span>
          {entry.name}: {entry.value}
        </span>
      </div>
      <div className="mx-auto h-0 w-0 border-x-[6px] border-x-transparent border-t-[6px] border-t-gray-800" />
    </div>
  );
}

export default function DisplayChart({ data, label }: DisplayChartProps) {
  const total = data.reduce((sum, item) => sum + item.value, 0);
  const chartData = data.map((item) => ({ ...item, fill: item.color }));
  const containerRef = useRef<HTMLDivElement>(null);
  const [mousePos, setMousePos] = useState({ x: 0, y: 0 });

  const handleMouseMove = (e: React.MouseEvent) => {
    const rect = containerRef.current?.getBoundingClientRect();
    if (rect) {
      setMousePos({ x: e.clientX - rect.left, y: e.clientY - rect.top - 40 });
    }
  };

  return (
    <div className="flex flex-col items-center">
      <div ref={containerRef} onMouseMove={handleMouseMove} className="w-full">
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
            <Tooltip content={CustomTooltip} position={mousePos} />
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
