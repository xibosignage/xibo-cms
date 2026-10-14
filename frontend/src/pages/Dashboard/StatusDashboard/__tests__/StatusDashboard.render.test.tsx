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

import { screen, waitFor } from '@testing-library/react';
import type * as RechartsModule from 'recharts';
import { vi, beforeEach, describe, test, expect } from 'vitest';

import {
  buildBandwidthWidget,
  buildStatusDashboardData,
  EMPTY_DISPLAY_TABLE,
} from './fixtures/statusDashboard';
import { getStatCardValue, renderStatusDashboardPage } from './helpers/renderStatusDashboardPage';
import {
  mockDashboardDisplays,
  mockStatusDashboard,
  mockStatusDashboardError,
  mockStatusDashboardPending,
} from './mocks/statusDashboardApi';

import { fetchStatusDashboard } from '@/services/dashboardApi';
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
// Recharts' ResponsiveContainer measures 0×0 in JSDOM and renders nothing; give it a size.
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
// Tests
// =============================================================================

describe('Status Dashboard - summary cards', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    mockDashboardDisplays(EMPTY_DISPLAY_TABLE);
  });

  // Active Displays is online + offline. The two numbers are deliberately different so
  // that a version showing only "online" (7) or only "offline" (2) would fail.
  test('the Active Displays card adds online and offline displays together', async () => {
    mockStatusDashboard(buildStatusDashboardData({ displayStatus: JSON.stringify([7, 2]) }));
    renderStatusDashboardPage();

    await waitFor(() => expect(getStatCardValue('Active Displays')).toHaveTextContent('9'));
  });

  test('the Library Size card shows the size exactly as the server sent it', async () => {
    mockStatusDashboard(buildStatusDashboardData({ librarySize: '12.3 GiB' }));
    renderStatusDashboardPage();

    await waitFor(() => expect(getStatCardValue('Library Size')).toHaveTextContent('12.3 GiB'));
  });

  test('the Active Users card shows the number of users', async () => {
    mockStatusDashboard(buildStatusDashboardData({ countUsers: 4 }));
    renderStatusDashboardPage();

    await waitFor(() => expect(getStatCardValue('Active Users')).toHaveTextContent('4'));
  });

  // `?? '-'` lets 0 through; a refactor to `||` would show a dash for a real zero.
  test('zero users shows 0 rather than a dash', async () => {
    mockStatusDashboard(buildStatusDashboardData({ countUsers: 0 }));
    renderStatusDashboardPage();

    await waitFor(() => expect(getStatCardValue('Active Users')).toHaveTextContent(/^0$/));
  });
});

describe('Status Dashboard - loading', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    mockDashboardDisplays(EMPTY_DISPLAY_TABLE);
  });

  // Four sections (Display Activity, Latest News, Bandwidth, Library) each have a loader;
  // the three cards show an empty placeholder instead of a number.
  test('while the data loads, every section says Loading and the cards show no values', async () => {
    mockStatusDashboardPending();
    renderStatusDashboardPage();

    expect(await screen.findAllByText('Loading...')).toHaveLength(4);
    expect(getStatCardValue('Active Displays')).toHaveTextContent(/^$/);
    expect(getStatCardValue('Library Size')).toHaveTextContent(/^$/);
    expect(getStatCardValue('Active Users')).toHaveTextContent(/^$/);
  });

  test('once the data arrives, the loaders go and the values appear', async () => {
    const resolve = mockStatusDashboardPending();
    renderStatusDashboardPage();
    await screen.findAllByText('Loading...');

    resolve(buildStatusDashboardData({ displayStatus: JSON.stringify([3, 2]) }));

    await waitFor(() => expect(screen.queryByText('Loading...')).not.toBeInTheDocument());
    expect(getStatCardValue('Active Displays')).toHaveTextContent('5');
  });
});

describe('Status Dashboard - error handling', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    mockDashboardDisplays(EMPTY_DISPLAY_TABLE);
  });

  test('a failed load shows the error message in an alert', async () => {
    mockStatusDashboardError('Server unavailable');
    renderStatusDashboardPage();

    expect(await screen.findByRole('alert')).toHaveTextContent('Server unavailable');
    expect(fetchStatusDashboard).toHaveBeenCalled();
  });

  // Pins current behaviour (known issue K8): a failure reads as "0 displays", which is
  // indistinguishable from a real zero. Changing this should be a deliberate decision.
  test('a failed load leaves Active Displays at 0 and the other cards at a dash', async () => {
    mockStatusDashboardError('Server unavailable');
    renderStatusDashboardPage();
    await screen.findByRole('alert');

    expect(getStatCardValue('Active Displays')).toHaveTextContent(/^0$/);
    expect(getStatCardValue('Library Size')).toHaveTextContent(/^-$/);
    expect(getStatCardValue('Active Users')).toHaveTextContent(/^-$/);
  });

  test('a successful load shows no error alert', async () => {
    mockStatusDashboard(buildStatusDashboardData({ displayStatus: JSON.stringify([3, 2]) }));
    renderStatusDashboardPage();

    await waitFor(() => expect(getStatCardValue('Active Displays')).toHaveTextContent('5'));
    expect(screen.queryByRole('alert')).not.toBeInTheDocument();
  });
});

describe('Status Dashboard - navigation links', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    mockDashboardDisplays(EMPTY_DISPLAY_TABLE);
  });

  test('"Displays page" goes to the All Displays page', async () => {
    mockStatusDashboard(buildStatusDashboardData());
    renderStatusDashboardPage();

    expect(await screen.findByRole('link', { name: /displays page/i })).toHaveAttribute(
      'href',
      '/displays/all-displays',
    );
  });

  test('"View Full Report" goes to the Bandwidth report', async () => {
    mockStatusDashboard(
      buildStatusDashboardData({ bandwidthWidget: buildBandwidthWidget(['January'], [1]) }),
    );
    renderStatusDashboardPage();

    expect(await screen.findByRole('link', { name: /view full report/i })).toHaveAttribute(
      'href',
      '/reporting/bandwidth',
    );
  });
});
