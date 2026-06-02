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
import { Pie, PieChart, ResponsiveContainer } from 'recharts';

const TYPE_COLORS: Record<string, string> = {
  Audio: '#38BDF8',
  'HTML Package': '#FACC15',
  Image: '#0E70F6',
  'Generic File': '#94A3B8',
  Flash: '#FB7185',
  Font: '#F97316',
  Video: '#4ADE80',
  Module: '#818CF8',
  PDF: '#F87171',
  'Player Software': '#FB923C',
  PowerPoint: '#FB923C',
  'Saved Report': '#64748B',
};

const FALLBACK_COLOR = '#94A3B8';

interface LibraryItem {
  name: string;
  value: number;
  displayValue: string;
  fill: string;
}

interface LibraryUsageChartProps {
  libraryWidgetLabels: string;
  libraryWidgetData: string;
  librarySize: string;
  librarySuffix: string;
  libraryLimit?: string;
  libraryLimitSet: boolean;
}

function parseLibraryData(
  labelsJson: string,
  dataJson: string,
  suffix: string,
): Map<string, number> {
  const labels = JSON.parse(labelsJson) as string[];
  const data = JSON.parse(dataJson) as number[];
  const map = new Map<string, number>();

  labels.forEach((label, i) => {
    // Strip suffix from label (e.g. "Image GiB" -> "Image", "Player Software GiB" -> "Player Software")
    const name = label.replace(new RegExp(`\\s+${suffix}$`, 'i'), '');
    // Capitalize first letter of each word (e.g. "playersoftware" -> "Playersoftware")
    const capitalized = name.charAt(0).toUpperCase() + name.slice(1);
    map.set(capitalized, data[i] ?? 0);
  });

  return map;
}

function buildItems(
  dataMap: Map<string, number>,
  suffix: string,
): { items: LibraryItem[]; freeValue: number } {
  const freeValue = dataMap.get('Free') ?? 0;

  const items = Object.keys(TYPE_COLORS).map((name) => {
    // Try exact match first, then case-insensitive match
    let value = dataMap.get(name);
    if (value === undefined) {
      for (const [key, val] of dataMap) {
        if (key.toLowerCase() === name.toLowerCase()) {
          value = val;
          break;
        }
      }
    }

    return {
      name,
      value: value ?? 0,
      displayValue: `${value ?? 0} ${suffix}`,
      fill: TYPE_COLORS[name] ?? FALLBACK_COLOR,
    };
  });

  return { items, freeValue };
}

export default function LibraryUsageChart({
  libraryWidgetLabels,
  libraryWidgetData,
  librarySize,
  librarySuffix,
  libraryLimit,
  libraryLimitSet,
}: LibraryUsageChartProps) {
  const { t } = useTranslation();
  const dataMap = parseLibraryData(libraryWidgetLabels, libraryWidgetData, librarySuffix);
  const { items, freeValue } = buildItems(dataMap, librarySuffix);
  const totalUsed = items.reduce((sum, item) => sum + item.value, 0);

  const donutData = [
    { name: 'Used', value: totalUsed, fill: '#3b82f6' },
    ...(freeValue > 0 ? [{ name: 'Free', value: freeValue, fill: '#E5E7EB' }] : []),
  ];

  return (
    <div className="flex items-center gap-6">
      {/* Donut chart — used vs total */}
      <div className="relative shrink-0">
        <ResponsiveContainer width={200} height={200}>
          <PieChart accessibilityLayer={false}>
            <Pie
              data={donutData}
              cx="50%"
              cy="50%"
              innerRadius={60}
              outerRadius={90}
              dataKey="value"
              paddingAngle={0}
              stroke="none"
              className="outline-none"
            />
            <text
              x="50%"
              y="46%"
              textAnchor="middle"
              dominantBaseline="central"
              className="fill-gray-800 text-lg font-semibold"
            >
              {librarySize}
            </text>
            {libraryLimitSet && libraryLimit && (
              <text
                x="50%"
                y="58%"
                textAnchor="middle"
                dominantBaseline="central"
                className="fill-gray-400 text-[11px]"
              >
                {t('Used of {{limit}}', { limit: libraryLimit })}
              </text>
            )}
          </PieChart>
        </ResponsiveContainer>
      </div>

      {/* Legend grid — file type breakdown */}
      <div className="grid grid-cols-2 gap-x-6 gap-y-2">
        {items.map((item) => (
          <div key={item.name} className="text-sm text-gray-600 whitespace-nowrap">
            {item.name}: {item.displayValue}
          </div>
        ))}
      </div>
    </div>
  );
}
