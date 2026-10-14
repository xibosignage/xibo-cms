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

import { screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type * as RechartsModule from 'recharts';
import { vi, beforeEach, describe, test, expect } from 'vitest';

import {
  buildBandwidthWidget,
  buildLibraryWidget,
  buildStatusDashboardData,
  EMPTY_DISPLAY_TABLE,
} from './fixtures/statusDashboard';
import { getSection, renderStatusDashboardPage } from './helpers/renderStatusDashboardPage';
import { mockDashboardDisplays, mockStatusDashboard } from './mocks/statusDashboardApi';

import { testQueryClient } from '@/setupTests';

// =============================================================================
// Module mocks
// =============================================================================

vi.mock('@/services/dashboardApi', () => ({
  fetchStatusDashboard: vi.fn(),
}));
vi.mock('@/services/displaysApi', () => ({
  fetchDisplays: vi.fn().mockResolvedValue({ rows: [], totalCount: 0 }),
}));
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

// The Display Activity chart card (index 0; index 1 is the table card).
const activityCard = () => within(getSection('Display Activity', 0));

// True when `a` comes before `b` in the document.
const isBefore = (a: Element, b: Element) =>
  Boolean(a.compareDocumentPosition(b) & Node.DOCUMENT_POSITION_FOLLOWING);

// Pie slices are SVG paths with no role or name. Recharts marks each one with this class,
// and it is also the class usePieTooltip hit-tests, so it is the only way to hover a slice.
const pieSlices = (container: HTMLElement) =>
  Array.from(container.querySelectorAll('.recharts-pie-sector'));

// =============================================================================
// Tests
// =============================================================================

describe('Status Dashboard - Display Activity charts', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    mockDashboardDisplays(EMPTY_DISPLAY_TABLE);
  });

  test('the Status chart lists Online before Offline', async () => {
    mockStatusDashboard(buildStatusDashboardData());
    renderStatusDashboardPage();

    const online = await activityCard().findByText('Online');
    expect(isBefore(online, activityCard().getByText('Offline'))).toBe(true);
  });

  test('the Content Status chart lists Up-to-date before Not up-to-date', async () => {
    mockStatusDashboard(buildStatusDashboardData());
    renderStatusDashboardPage();

    const upToDate = await activityCard().findByText('Up-to-date');
    expect(isBefore(upToDate, activityCard().getByText('Not up-to-date'))).toBe(true);
  });

  test('each chart shows its own label in the middle', async () => {
    mockStatusDashboard(buildStatusDashboardData());
    renderStatusDashboardPage();

    await activityCard().findByText('Status');
    expect(activityCard().getByText('Content Status')).toBeInTheDocument();
  });

  // 7 online and 0 offline: hovering the first slice must report Online: 7. If the pair
  // were read the wrong way round the tooltip would say Online: 0.
  test('the online count is drawn as the Online slice, not swapped with offline', async () => {
    const user = userEvent.setup();
    mockStatusDashboard(buildStatusDashboardData({ displayStatus: JSON.stringify([7, 0]) }));
    renderStatusDashboardPage();
    await activityCard().findByText('Online');

    const card = getSection('Display Activity', 0);
    await user.hover(pieSlices(card)[0]!);

    await within(card).findByText('Online: 7');
  });

  test('when every count is zero, both charts still draw', async () => {
    mockStatusDashboard(
      buildStatusDashboardData({
        displayStatus: JSON.stringify([0, 0]),
        displayMediaStatus: JSON.stringify([0, 0]),
      }),
    );
    renderStatusDashboardPage();

    await activityCard().findByText('Online');
    expect(activityCard().getByText('Up-to-date')).toBeInTheDocument();
  });
});

describe('Status Dashboard - bandwidth and library sections', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    mockDashboardDisplays(EMPTY_DISPLAY_TABLE);
  });

  test('with no bandwidth data, the section says so', async () => {
    mockStatusDashboard(buildStatusDashboardData({ bandwidthWidget: '' }));
    renderStatusDashboardPage();

    await screen.findByText('No bandwidth data available.');
  });

  test('with no library data, the section says so', async () => {
    mockStatusDashboard(buildStatusDashboardData({ libraryWidgetLabels: '' }));
    renderStatusDashboardPage();

    await screen.findByText('No library data available.');
  });

  test('when the data is present, the charts are shown instead of the empty messages', async () => {
    mockStatusDashboard(
      buildStatusDashboardData({
        bandwidthWidget: buildBandwidthWidget(['January', 'February', 'March'], [1, 2, 3]),
        ...buildLibraryWidget([['Image GiB', 1]]),
      }),
    );
    renderStatusDashboardPage();

    await within(getSection('Library Usage')).findByText('Image: 1 GiB');
    expect(within(getSection('Bandwidth Usage')).getByText('Jan')).toBeInTheDocument();
    expect(screen.queryByText('No bandwidth data available.')).not.toBeInTheDocument();
    expect(screen.queryByText('No library data available.')).not.toBeInTheDocument();
  });
});
