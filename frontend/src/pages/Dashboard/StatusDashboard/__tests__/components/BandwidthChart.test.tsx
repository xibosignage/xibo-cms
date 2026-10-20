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

import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type * as RechartsModule from 'recharts';
import { vi, describe, test, expect } from 'vitest';

import BandwidthChart from '../../components/BandwidthChart';
import { buildBandwidthWidget } from '../fixtures/statusDashboard';

// =============================================================================
// Module mocks
// =============================================================================

vi.mock('recharts', async (importOriginal) => {
  const actual = await importOriginal<typeof RechartsModule>();
  const { cloneElement } = await import('react');
  return {
    ...actual,
    ResponsiveContainer: ({ children }: { children: React.ReactElement }) =>
      cloneElement(children, { width: 500, height: 300 } as Record<string, unknown>),
  };
});

// =============================================================================
// Helpers local to this file
// =============================================================================

const MONTHS = [
  'January',
  'February',
  'March',
  'April',
  'May',
  'June',
  'July',
  'August',
  'September',
  'October',
  'November',
  'December',
];

// Axis tick labels are rendered as <tspan>. Recharts also writes text into a hidden
// measuring <span>, so the selector keeps the query to what is actually drawn.
const tick = (label: string) => screen.getByText(label, { selector: 'tspan' });
const queryTick = (label: string) => screen.queryByText(label, { selector: 'tspan' });

const renderChart = (bandwidthWidget: string, bandwidthSuffix = 'GiB') => {
  const view = render(
    <BandwidthChart bandwidthWidget={bandwidthWidget} bandwidthSuffix={bandwidthSuffix} />,
  );
  // Bars have no role or name. Recharts wraps each one in a <g> with this class, and that
  // <g> carries the bar's mouse handlers, so it is the element to hover.
  const bars = () => Array.from(view.container.querySelectorAll('.recharts-bar-rectangle'));
  return { ...view, bars };
};

// =============================================================================
// Tests
// =============================================================================

describe('BandwidthChart', () => {
  test('month names are shortened to their first three letters', () => {
    renderChart(buildBandwidthWidget(['January', 'February', 'March'], [1, 2, 3]));

    expect(tick('Jan')).toBeInTheDocument();
    expect(tick('Feb')).toBeInTheDocument();
    expect(tick('Mar')).toBeInTheDocument();
    expect(queryTick('January')).not.toBeInTheDocument();
  });

  test('without a bandwidth limit, only "Used" is in the legend', () => {
    renderChart(buildBandwidthWidget(['January'], [1]));

    expect(screen.getByText('Used')).toBeInTheDocument();
    expect(screen.queryByText('Available')).not.toBeInTheDocument();
  });

  test('with a bandwidth limit, both "Used" and "Available" are in the legend', () => {
    renderChart(buildBandwidthWidget(['January'], [1], [9]));

    expect(screen.getByText('Used')).toBeInTheDocument();
    expect(screen.getByText('Available')).toBeInTheDocument();
  });

  test('the vertical axis is labelled with the bandwidth unit', () => {
    renderChart(buildBandwidthWidget(['January'], [1]), 'MiB');

    expect(screen.getByText('MiB', { selector: 'tspan' })).toBeInTheDocument();
  });

  test('a full year of data shows all twelve months', () => {
    renderChart(
      buildBandwidthWidget(
        MONTHS,
        MONTHS.map((_, i) => i + 1),
      ),
    );

    for (const month of MONTHS) {
      expect(tick(month.slice(0, 3))).toBeInTheDocument();
    }
  });

  // Low priority: the tooltip is the only place a bar's value appears as text.
  test('hovering a bar shows its month, series, value and unit; leaving hides it', async () => {
    const user = userEvent.setup();
    const { bars } = renderChart(buildBandwidthWidget(['January'], [4.2]));

    await user.hover(bars()[0]!);
    await screen.findByText('Used: 4.2 GiB');

    await user.unhover(bars()[0]!);
    expect(screen.queryByText('Used: 4.2 GiB')).not.toBeInTheDocument();
  });

  // The server can send fewer values than labels. That month must still appear on the axis
  // (drawn as 0) rather than being dropped. A zero-height bar isn't drawn at all, so there is
  // no bar to hover; the axis tick is the visible evidence that the month was kept.
  test('a month with no value is still shown on the chart', () => {
    renderChart(buildBandwidthWidget(['January', 'February', 'March'], [5, 6]));

    expect(tick('Jan')).toBeInTheDocument();
    expect(tick('Feb')).toBeInTheDocument();
    expect(tick('Mar')).toBeInTheDocument();
  });
});
