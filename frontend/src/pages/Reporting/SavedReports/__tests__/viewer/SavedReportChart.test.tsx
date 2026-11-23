/*
 * Xibo Digital Signage
 * Copyright (C) 2006-2026 Xibo Signage Ltd
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published
 * by the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 *
 * This program is also available under a commercial licence from
 * Xibo Signage Ltd. See https://xibosignage.com/terms
 */

// =============================================================================
// Component: which chart a saved Chart.js config becomes (Recharts stubbed)
// =============================================================================

import { render, screen } from '@testing-library/react';
import type { ReactNode } from 'react';
import { describe, test, expect, vi } from 'vitest';

import SavedReportChart, {
  getChartIconType,
  isRenderableChart,
} from '../../SavedReportViewer/SavedReportChart';
import {
  BANDWIDTH_CHART,
  EMPTY_LIBRARY_USAGE_CHART,
  LIBRARY_USAGE_CHART,
  SUMMARY_CHART,
  TDS_CHART,
} from '../fixtures/savedReportData';

vi.mock('recharts', () => {
  const box = (label: string) =>
    function Stub({ children }: { children?: ReactNode }) {
      return (
        <div role="img" aria-label={label}>
          {children}
        </div>
      );
    };
  return {
    ResponsiveContainer: ({ children }: { children?: ReactNode }) => <div>{children}</div>,
    ComposedChart: box('two-axis chart'),
    BarChart: box('bar chart'),
    PieChart: box('pie chart'),
    CartesianGrid: () => null,
    XAxis: () => null,
    Tooltip: () => null,
    Cell: () => null,
    YAxis: ({ yAxisId }: { yAxisId?: string }) => <span>{`y-axis ${yAxisId ?? 'default'}`}</span>,
    Bar: ({ dataKey }: { dataKey: string }) => <span>{`bar ${dataKey}`}</span>,
    Line: ({ dataKey }: { dataKey: string }) => <span>{`line ${dataKey}`}</span>,
    Legend: () => <span>legend</span>,
    Pie: ({ data }: { data: { name: string }[] }) => (
      <>
        {data.map((d) => (
          <span key={d.name}>{`slice ${d.name}`}</span>
        ))}
      </>
    ),
  };
});

const seriesInOrder = () => screen.getAllByText(/^(bar|line) /).map((el) => el.textContent);

describe('Saved report chart', () => {
  test('the helpers recognise drawable charts and their type', () => {
    expect(isRenderableChart(LIBRARY_USAGE_CHART)).toBe(true);
    expect(isRenderableChart(SUMMARY_CHART)).toBe(true);
    expect(isRenderableChart(BANDWIDTH_CHART)).toBe(true);
    expect(isRenderableChart(null)).toBe(false);
    expect(isRenderableChart({ type: 'bar', data: { labels: [] } })).toBe(false);

    expect(getChartIconType(SUMMARY_CHART)).toBe('line');
    expect(getChartIconType(BANDWIDTH_CHART)).toBe('bar');
    expect(getChartIconType(LIBRARY_USAGE_CHART)).toBe('bar');
  });

  test('Library Usage shows two pies with their titles and slices', () => {
    render(<SavedReportChart chart={LIBRARY_USAGE_CHART} />);

    expect(screen.getByText('Library Usage')).toBeInTheDocument();
    expect(screen.getByText('User Percentage Usage')).toBeInTheDocument();
    expect(screen.getAllByRole('img', { name: 'pie chart' })).toHaveLength(2);
    expect(screen.getByText('slice Free')).toBeInTheDocument();
  });

  test('Library Usage with no data says so', () => {
    render(<SavedReportChart chart={EMPTY_LIBRARY_USAGE_CHART} />);

    expect(screen.getByText('No chart data available for this period.')).toBeInTheDocument();
  });

  test('a chart with no labels says so', () => {
    render(<SavedReportChart chart={{ ...BANDWIDTH_CHART, data: { labels: [], datasets: [] } }} />);

    expect(screen.getByText('No chart data available for this period.')).toBeInTheDocument();
  });

  test('separate duration and count axes become a two-axis chart', () => {
    render(<SavedReportChart chart={SUMMARY_CHART} />);

    expect(screen.getByRole('img', { name: 'two-axis chart' })).toBeInTheDocument();
    expect(screen.getByText('y-axis Duration')).toBeInTheDocument();
    expect(screen.getByText('y-axis Count')).toBeInTheDocument();
    expect(seriesInOrder()).toEqual(['bar Total Duration', 'line Total Count']);
  });

  test('a two-axis config that is also stacked still shows two axes', () => {
    render(<SavedReportChart chart={{ ...SUMMARY_CHART, options: TDS_CHART.options }} />);

    expect(screen.getByRole('img', { name: 'two-axis chart' })).toBeInTheDocument();
  });

  // Drawn Uptime-first so the stack matches the live report.
  test('stacked charts draw their series in reverse stored order', () => {
    render(<SavedReportChart chart={TDS_CHART} />);

    expect(screen.getByRole('img', { name: 'bar chart' })).toBeInTheDocument();
    expect(seriesInOrder()).toEqual(['bar Uptime', 'bar Downtime']);
  });

  test('plain bar charts only show a legend with more than one series', () => {
    const { unmount } = render(<SavedReportChart chart={BANDWIDTH_CHART} />);
    expect(screen.queryByText('legend')).not.toBeInTheDocument();
    unmount();

    const twoSeries = {
      ...BANDWIDTH_CHART,
      data: {
        ...BANDWIDTH_CHART.data,
        datasets: [...BANDWIDTH_CHART.data.datasets, { label: 'Peak', data: [6, 4] }],
      },
    };
    render(<SavedReportChart chart={twoSeries} />);
    expect(screen.getByText('legend')).toBeInTheDocument();
  });

  test('an unrecognised chart draws nothing', () => {
    const { container } = render(<SavedReportChart chart={{ foo: 1 }} />);

    expect(container).toBeEmptyDOMElement();
  });
});
