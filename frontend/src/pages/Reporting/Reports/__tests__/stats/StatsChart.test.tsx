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
// Test type: Component — the Summary/Distribution chart
// =============================================================================

import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type { ReactNode } from 'react';
import { describe, test, expect, vi } from 'vitest';

import StatsChart from '../../shared/StatsChart';
import { STATS_RESPONSE } from '../fixtures/reportRows';

vi.mock('recharts', () => {
  const box = (label: string) =>
    function Stub({ children }: { children?: ReactNode }) {
      return (
        <div role="img" aria-label={label}>
          {children}
        </div>
      );
    };
  type Series = { dataKey: string; hide?: boolean };
  const series = (kind: string) =>
    function Stub({ dataKey, hide }: Series) {
      return <span>{`${kind} ${dataKey} ${hide ? 'hidden' : 'shown'}`}</span>;
    };
  return {
    ResponsiveContainer: ({ children }: { children?: ReactNode }) => <div>{children}</div>,
    ComposedChart: box('two-axis chart'),
    PieChart: box('pie chart'),
    CartesianGrid: () => null,
    XAxis: () => null,
    Tooltip: () => null,
    YAxis: ({ yAxisId }: { yAxisId: string }) => <span>{`y-axis ${yAxisId}`}</span>,
    Bar: series('bar'),
    Line: series('line'),
    Legend: ({
      onClick,
      formatter,
    }: {
      onClick?: (entry: { dataKey: string }) => void;
      formatter: (value: string, entry: { dataKey: string }) => ReactNode;
    }) => (
      <>
        {[
          ['Total Duration', 'duration'],
          ['Total Count', 'count'],
        ].map(([value, dataKey]) => (
          <button key={dataKey} type="button" onClick={() => onClick?.({ dataKey: dataKey! })}>
            {formatter(value!, { dataKey: dataKey! })}
          </button>
        ))}
      </>
    ),
    Pie: ({ data }: { data: { name: string; value: number }[] }) => (
      <>
        {data.map((d) => (
          <span key={d.name}>{`slice ${d.name} ${d.value}`}</span>
        ))}
      </>
    ),
  };
});

const rows = STATS_RESPONSE.table;

describe('Summary/Distribution chart', () => {
  test('a bar chart draws duration and count as bars on their own axes', () => {
    render(<StatsChart rows={rows} type="bar" />);

    expect(screen.getByText('y-axis duration')).toBeInTheDocument();
    expect(screen.getByText('y-axis count')).toBeInTheDocument();
    expect(screen.getByText('bar duration shown')).toBeInTheDocument();
    expect(screen.getByText('bar count shown')).toBeInTheDocument();
  });

  test('a line chart draws duration and count as lines', () => {
    render(<StatsChart rows={rows} type="line" />);

    expect(screen.getByText('line duration shown')).toBeInTheDocument();
    expect(screen.getByText('line count shown')).toBeInTheDocument();
  });

  test('clicking a legend entry hides that series, and clicking again shows it', async () => {
    const user = userEvent.setup({ delay: null });
    render(<StatsChart rows={rows} type="line" />);

    await user.click(screen.getByRole('button', { name: 'Total Duration' }));
    expect(screen.getByText('line duration hidden')).toBeInTheDocument();
    expect(screen.getByText('line count shown')).toBeInTheDocument();

    await user.click(screen.getByRole('button', { name: 'Total Duration' }));
    expect(screen.getByText('line duration shown')).toBeInTheDocument();
  });

  test('a pie chart shows one slice per row, sized by its count', () => {
    render(<StatsChart rows={rows} type="pie" />);

    expect(screen.getByRole('img', { name: 'pie chart' })).toBeInTheDocument();
    expect(screen.getByText('slice Mon 4')).toBeInTheDocument();
    expect(screen.getByText('slice Tue 30')).toBeInTheDocument();
  });
});
