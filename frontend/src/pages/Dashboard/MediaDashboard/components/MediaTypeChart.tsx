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

import { Loader2 } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { Pie, PieChart, ResponsiveContainer, Tooltip } from 'recharts';

import { makePieTooltip, usePieTooltip } from '@/components/ui/charts/pieTooltip';
import { formatFileSize } from '@/utils/formatters';

const TYPE_COLORS: Record<string, string> = {
  'Widget cache': '#818CF8',
  PowerPoint: '#FB923C',
  Image: '#0E70F6',
  Flash: '#FB7185',
  PDF: '#F87171',
  'HTML Package': '#FACC15',
  Audio: '#38BDF8',
  'Generic File': '#94A3B8',
  Video: '#4ADE80',
};

const FALLBACK_COLOR = '#94A3B8';

interface ChartItem {
  name: string;
  value: number;
  fill: string;
  displayValue: string;
}

interface MediaTypeChartProps {
  title: string;
  items: ChartItem[];
  centerValue: string;
  centerLabel: string;
  isLoading: boolean;
}

const TooltipContent = makePieTooltip<ChartItem & { total: number }>((entry) => {
  const pct = entry.total > 0 ? ((entry.value / entry.total) * 100).toFixed(1) : '0';
  return { color: entry.fill, text: `${entry.name}: ${pct}%` };
});

export function buildChartItems(
  types: { title: string; count: number; size: number }[],
  mode: 'count' | 'size',
): ChartItem[] {
  const typeMap = new Map(types.map((t) => [t.title, t]));

  return Object.keys(TYPE_COLORS).map((name) => {
    const t = typeMap.get(name);
    const rawValue = t ? (mode === 'count' ? t.count : t.size) : 0;
    const displayValue = mode === 'count' ? String(rawValue) : formatFileSize(rawValue);

    return {
      name,
      value: rawValue,
      fill: TYPE_COLORS[name] ?? FALLBACK_COLOR,
      displayValue,
    };
  });
}

export default function MediaTypeChart({
  title,
  items,
  centerValue,
  centerLabel,
  isLoading,
}: MediaTypeChartProps) {
  const { t } = useTranslation();
  const total = items.reduce((sum, item) => sum + item.value, 0);
  const chartData = items.map((item) => ({ ...item, total }));
  const { active, onMouseMove, onMouseLeave } = usePieTooltip();

  return (
    <div className="rounded-lg border border-gray-200 bg-white p-5">
      <h3 className="mb-4 text-sm font-semibold text-gray-800">{title}</h3>
      {isLoading ? (
        <div className="flex flex-col items-center justify-center min-h-50">
          <Loader2 className="w-8 h-8 animate-spin text-gray-600" />
          <p className="ml-2 text-gray-600">{t('Loading...')}</p>
        </div>
      ) : (
        <div className="flex items-center gap-5">
          <div className="relative shrink-0" onMouseMove={onMouseMove} onMouseLeave={onMouseLeave}>
            <ResponsiveContainer width={200} height={200}>
              <PieChart accessibilityLayer={false}>
                <Pie
                  data={chartData}
                  cx="50%"
                  cy="50%"
                  innerRadius={60}
                  outerRadius={90}
                  dataKey="value"
                  paddingAngle={total > 0 ? 1 : 0}
                  stroke="none"
                  className="outline-none"
                  isAnimationActive={false}
                />
                <text
                  x="50%"
                  y="46%"
                  textAnchor="middle"
                  dominantBaseline="central"
                  className="fill-gray-800 text-[20px] font-semibold"
                >
                  {centerValue}
                </text>
                <text
                  x="50%"
                  y="58%"
                  textAnchor="middle"
                  dominantBaseline="central"
                  className="fill-gray-400 text-[12px]"
                >
                  {centerLabel}
                </text>
                <Tooltip content={TooltipContent} active={active} isAnimationActive={false} />
              </PieChart>
            </ResponsiveContainer>
          </div>

          <div className="grid grid-cols-2 gap-x-5 gap-y-2">
            {items.map((item) => (
              <div key={item.name} className="flex items-center gap-2 text-sm text-gray-600">
                <span
                  className="h-2.5 w-2.5 shrink-0 rounded-full"
                  style={{ backgroundColor: item.fill }}
                />
                <span className="whitespace-break-spaces">
                  {item.name}: {item.displayValue}
                </span>
              </div>
            ))}
          </div>
        </div>
      )}
    </div>
  );
}
