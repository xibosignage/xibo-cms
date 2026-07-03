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
import {
  Bar,
  BarChart,
  CartesianGrid,
  Cell,
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

// ── Chart.js v2 type definitions ─────────────────────────────────────────────
// These reflect the structures produced by the PHP report classes and stored
// in the saved report ZIP archive (reportschedule.json).

interface ChartJsDataset {
  label?: string;
  data: number[];
  type?: string;
  yAxisID?: string;
  backgroundColor?: string | string[];
  borderColor?: string | string[];
  fill?: boolean;
}

interface ChartJsConfig {
  type: string;
  data: {
    labels: string[];
    datasets: ChartJsDataset[];
  };
  options?: {
    scales?: {
      xAxes?: Array<{ stacked?: boolean }>;
      yAxes?: Array<{
        id?: string;
        stacked?: boolean;
        scaleLabel?: { labelString?: string };
      }>;
    };
    legend?: { display?: boolean };
  };
}

// LibraryUsage produces two named charts rather than a single config.
interface LibraryUsageConfig {
  User_Percentage_Usage: ChartJsConfig;
  Library_Usage: ChartJsConfig;
}

// ── Type guards ───────────────────────────────────────────────────────────────

function isChartJsConfig(v: unknown): v is ChartJsConfig {
  if (typeof v !== 'object' || v === null) return false;
  const c = v as Record<string, unknown>;
  if (typeof c.type !== 'string') return false;
  if (typeof c.data !== 'object' || c.data === null) return false;
  const d = c.data as Record<string, unknown>;
  return Array.isArray(d.labels) && Array.isArray(d.datasets);
}

function isLibraryUsageConfig(v: unknown): v is LibraryUsageConfig {
  if (typeof v !== 'object' || v === null) return false;
  const c = v as Record<string, unknown>;
  return 'User_Percentage_Usage' in c && 'Library_Usage' in c;
}

// ── Public helpers ────────────────────────────────────────────────────────────

export function isRenderableChart(chart: unknown): boolean {
  return isLibraryUsageConfig(chart) || isChartJsConfig(chart);
}

export function getChartIconType(chart: unknown): 'line' | 'bar' {
  if (!isChartJsConfig(chart)) return 'bar';
  if (chart.data.datasets.some((ds) => ds.type === 'line')) return 'line';
  return 'bar';
}

// ── Color palette ─────────────────────────────────────────────────────────────

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

const SERIES_COLORS = ['#14b8a6', '#0e70f6', '#f59e0b', '#ef4444', '#8b5cf6'];

// Stacked bar charts always use this palette (bottom bar = index 0) so the
// viewer renders consistent colors regardless of whatever backgroundColor
// strings are stored in the saved report archive.
const STACKED_BAR_COLORS = ['#0e70f6', '#eb7857', '#f59e0b', '#8b5cf6', '#14b8a6'];

function resolveBarColor(bg: string | string[] | undefined, index: number): string {
  if (Array.isArray(bg))
    return bg[index] ?? SERIES_COLORS[index % SERIES_COLORS.length] ?? '#0e70f6';
  if (typeof bg === 'string') return bg;
  return SERIES_COLORS[index % SERIES_COLORS.length] ?? '#0e70f6';
}

// ── Shared axis / grid styles ─────────────────────────────────────────────────

const AXIS_TICK = { fontSize: 12, fill: '#9CA3AF' };
const AXIS_PROPS = { axisLine: false, tickLine: false, tick: AXIS_TICK };
const GRID_PROPS = {
  strokeDasharray: '3 3' as const,
  horizontal: true,
  vertical: false,
  stroke: '#D1D5DB',
};
const CHART_MARGIN = { top: 16, right: 16, bottom: 8, left: 0 };
const LEGEND_PROPS = {
  verticalAlign: 'top' as const,
  align: 'right' as const,
  iconType: 'circle' as const,
  iconSize: 8,
};

// ── Sub-components ────────────────────────────────────────────────────────────

interface PieSeriesProps {
  config: ChartJsConfig;
  title?: string;
}

function PieSeries({ config, title }: PieSeriesProps) {
  const { labels, datasets } = config.data;
  const ds = datasets[0];
  if (!ds) return null;

  const pieData = labels.map((label, i) => ({
    name: label,
    value: ds.data[i] ?? 0,
    fill: SLICE_COLORS[i % SLICE_COLORS.length],
  }));

  return (
    <div className="flex flex-1 flex-col">
      {title && <p className="mb-2 text-center text-xs font-medium text-gray-500">{title}</p>}
      <ResponsiveContainer width="100%" height={280}>
        <PieChart>
          <Pie
            data={pieData}
            dataKey="value"
            nameKey="name"
            cx="50%"
            cy="50%"
            outerRadius="65%"
            innerRadius="40%"
            paddingAngle={2}
            isAnimationActive={false}
          />
          <Tooltip />
          <Legend
            verticalAlign="bottom"
            iconType="circle"
            iconSize={8}
            formatter={(v: string) => <span className="text-xs text-gray-600">{v}</span>}
            wrapperStyle={{ paddingTop: 8 }}
          />
        </PieChart>
      </ResponsiveContainer>
    </div>
  );
}

// Dual-axis mixed bar+line (SummaryReport, DistributionReport).
// Datasets carry yAxisID = 'Duration' | 'Count', with the count series
// marked type: 'line'. Renders as a ComposedChart with left/right Y-axes.
function DualAxisChart({ config }: { config: ChartJsConfig }) {
  const { labels, datasets } = config.data;

  const rows = labels.map((label, i) => {
    const row: Record<string, unknown> = { label };
    datasets.forEach((ds, di) => {
      row[ds.label ?? `dataset_${di}`] = ds.data[i] ?? 0;
    });
    return row;
  });

  return (
    <div className="w-full">
      <ResponsiveContainer width="100%" height={300}>
        <ComposedChart data={rows} margin={CHART_MARGIN}>
          <CartesianGrid {...GRID_PROPS} />
          <XAxis dataKey="label" {...AXIS_PROPS} />
          <YAxis yAxisId="Duration" {...AXIS_PROPS} domain={[0, 'dataMax']} />
          <YAxis yAxisId="Count" orientation="right" {...AXIS_PROPS} domain={[0, 'dataMax']} />
          <Tooltip />
          <Legend {...LEGEND_PROPS} />
          {datasets.map((ds, i) => {
            const dataKey = ds.label ?? `dataset_${i}`;
            const color = SERIES_COLORS[i % SERIES_COLORS.length];
            const axisId = ds.yAxisID ?? 'Duration';
            if (ds.type === 'line') {
              return (
                <Line
                  key={dataKey}
                  yAxisId={axisId}
                  type="monotone"
                  dataKey={dataKey}
                  name={ds.label}
                  stroke={color}
                  strokeWidth={2}
                  dot={false}
                  isAnimationActive={false}
                />
              );
            }
            return (
              <Bar
                key={dataKey}
                yAxisId={axisId}
                dataKey={dataKey}
                name={ds.label}
                fill={color}
                radius={[4, 4, 0, 0]}
                isAnimationActive={false}
              />
            );
          })}
        </ComposedChart>
      </ResponsiveContainer>
    </div>
  );
}

// Stacked bar chart (TimeDisconnectedSummary: Downtime + Uptime per display).
// Datasets are reversed before rendering so the last entry in the stored config
// appears at the bottom of the stack, matching the live page layout.
// STACKED_BAR_COLORS is used instead of the stored backgroundColor values so
// the viewer is consistent regardless of what colour strings were saved in the
// report archive (legacy reports used raw rgb() literals).
function StackedBarChart({ config }: { config: ChartJsConfig }) {
  const { labels, datasets } = config.data;
  const yAxisLabel = config.options?.scales?.yAxes?.[0]?.scaleLabel?.labelString;

  const rows = labels.map((label, i) => {
    const row: Record<string, unknown> = { label };
    datasets.forEach((ds, di) => {
      row[ds.label ?? `dataset_${di}`] = ds.data[i] ?? 0;
    });
    return row;
  });

  const renderDatasets = [...datasets].reverse();

  return (
    <div className="w-full">
      <ResponsiveContainer width="100%" height={300}>
        <BarChart data={rows} margin={CHART_MARGIN}>
          <CartesianGrid {...GRID_PROPS} />
          <XAxis dataKey="label" {...AXIS_PROPS} />
          <YAxis
            {...AXIS_PROPS}
            domain={[0, 'dataMax']}
            label={
              yAxisLabel
                ? {
                    value: yAxisLabel,
                    angle: -90,
                    position: 'insideLeft',
                    style: { fontSize: 12, fill: '#9CA3AF', textAnchor: 'middle' },
                  }
                : undefined
            }
          />
          <Tooltip />
          <Legend {...LEGEND_PROPS} />
          {renderDatasets.map((ds, i) => {
            const dataKey = ds.label ?? `dataset_${i}`;
            const color = STACKED_BAR_COLORS[i % STACKED_BAR_COLORS.length] ?? '#0e70f6';
            return (
              <Bar
                key={dataKey}
                dataKey={dataKey}
                name={ds.label}
                stackId="stack"
                fill={color}
                isAnimationActive={false}
              />
            );
          })}
        </BarChart>
      </ResponsiveContainer>
    </div>
  );
}

// Simple bar chart (Bandwidth and any other single/multi-dataset bar report).
// Supports per-bar colors from the Chart.js backgroundColor array — used by
// Bandwidth where deleted displays are highlighted in red.
function SimpleBarChart({ config }: { config: ChartJsConfig }) {
  const { labels, datasets } = config.data;
  const yAxisLabel = config.options?.scales?.yAxes?.[0]?.scaleLabel?.labelString;

  const rows = labels.map((label, i) => {
    const row: Record<string, unknown> = { label };
    datasets.forEach((ds, di) => {
      row[ds.label ?? `dataset_${di}`] = ds.data[i] ?? 0;
    });
    return row;
  });

  return (
    <div className="w-full">
      <ResponsiveContainer width="100%" height={300}>
        <BarChart data={rows} margin={CHART_MARGIN}>
          <CartesianGrid {...GRID_PROPS} />
          <XAxis dataKey="label" {...AXIS_PROPS} />
          <YAxis
            {...AXIS_PROPS}
            domain={[0, 'dataMax']}
            label={
              yAxisLabel
                ? {
                    value: yAxisLabel,
                    angle: -90,
                    position: 'insideLeft',
                    style: { fontSize: 12, fill: '#9CA3AF', textAnchor: 'middle' },
                  }
                : undefined
            }
          />
          <Tooltip />
          {datasets.length > 1 && <Legend {...LEGEND_PROPS} />}
          {datasets.map((ds, i) => {
            const dataKey = ds.label ?? `dataset_${i}`;
            const bg = ds.backgroundColor;
            const hasPerBarColors = Array.isArray(bg) && bg.length > 1;
            const defaultColor = resolveBarColor(bg, 0);

            return (
              <Bar
                key={dataKey}
                dataKey={dataKey}
                name={ds.label}
                fill={defaultColor}
                radius={[4, 4, 0, 0]}
                isAnimationActive={false}
              >
                {hasPerBarColors &&
                  rows.map((_, idx) => <Cell key={idx} fill={resolveBarColor(bg, idx)} />)}
              </Bar>
            );
          })}
        </BarChart>
      </ResponsiveContainer>
    </div>
  );
}

// ── Main component ────────────────────────────────────────────────────────────

interface SavedReportChartProps {
  chart: unknown;
}

export default function SavedReportChart({ chart }: SavedReportChartProps) {
  const { t } = useTranslation();

  if (isLibraryUsageConfig(chart)) {
    const hasData =
      chart.User_Percentage_Usage.data.labels.length > 0 ||
      chart.Library_Usage.data.labels.length > 0;
    if (!hasData)
      return (
        <div className="flex flex-1 items-center justify-center min-h-40">
          <p className="text-sm text-gray-400">{t('No chart data available for this period.')}</p>
        </div>
      );
    return (
      <div className="flex flex-col gap-6 flex-1 min-h-75 sm:flex-row">
        <PieSeries config={chart.Library_Usage} title={t('Library Usage')} />
        <PieSeries config={chart.User_Percentage_Usage} title={t('User Percentage Usage')} />
      </div>
    );
  }

  if (!isChartJsConfig(chart)) return null;

  if (chart.data.labels.length === 0 || chart.data.datasets.length === 0) {
    return (
      <div className="flex flex-1 items-center justify-center min-h-40">
        <p className="text-sm text-gray-400">{t('No chart data available for this period.')}</p>
      </div>
    );
  }

  const hasDualAxis = chart.data.datasets.some((ds) => ds.yAxisID);
  const isStacked = !!(
    chart.options?.scales?.xAxes?.[0]?.stacked || chart.options?.scales?.yAxes?.[0]?.stacked
  );

  if (hasDualAxis) return <DualAxisChart config={chart} />;
  if (isStacked) return <StackedBarChart config={chart} />;
  return <SimpleBarChart config={chart} />;
}
