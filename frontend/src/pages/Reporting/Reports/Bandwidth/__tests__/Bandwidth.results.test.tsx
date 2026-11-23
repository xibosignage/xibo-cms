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
// Test type: Page integration + component — Bandwidth results
// =============================================================================

import { render, screen, within } from '@testing-library/react';
import userEvent, { type UserEvent } from '@testing-library/user-event';
import { Children, cloneElement, isValidElement, type ReactNode } from 'react';
import { vi, beforeAll, beforeEach, describe, test, expect } from 'vitest';

import { BANDWIDTH_RESPONSE, buildBandwidthRow } from '../../__tests__/fixtures/reportRows';
import { stubIntersectionObserver } from '../../__tests__/helpers/domStubs';
import { applyButton, renderReportPage } from '../../__tests__/helpers/renderReportPage';
import { setupOptionServiceMocks } from '../../__tests__/mocks/optionServices';
import Bandwidth from '../Bandwidth';
import BandwidthBarChart from '../components/BandwidthBarChart';

import { fetchBandwidthReport } from '@/services/bandwidthReportApi';
import type { BandwidthTableRow } from '@/services/bandwidthReportApi';
import { testQueryClient } from '@/setupTests';

// =============================================================================
// Module mocks
// =============================================================================

vi.mock('@/services/bandwidthReportApi', () => ({ fetchBandwidthReport: vi.fn() }));
const mockNavigate = vi.fn();
vi.mock('react-router-dom', async (importOriginal) => ({
  ...((await importOriginal()) as object),
  useNavigate: () => mockNavigate,
}));

vi.mock('@/services/displaysApi', async (o) => ({
  ...((await o()) as object),
  fetchDisplays: vi.fn(),
}));
vi.mock('@/services/displayGroupApi', async (o) => ({
  ...((await o()) as object),
  fetchDisplayGroups: vi.fn(),
}));
vi.mock('@/services/layoutsApi', async (o) => ({
  ...((await o()) as object),
  fetchLayouts: vi.fn(),
}));
vi.mock('@/services/mediaApi', async (o) => ({ ...((await o()) as object), fetchMedia: vi.fn() }));
vi.mock('@/services/campaignApi', async (o) => ({
  ...((await o()) as object),
  fetchCampaigns: vi.fn(),
}));
vi.mock('@/services/userGroupApi', async (o) => ({
  ...((await o()) as object),
  fetchUserGroups: vi.fn(),
}));
vi.mock('@/services/userApi', () => ({
  fetchUserPreference: vi.fn().mockResolvedValue(null),
  saveUserPreference: vi.fn().mockResolvedValue(undefined),
  fetchUsers: vi.fn(),
}));
vi.mock('@/services/reportApi', () => ({
  fetchReports: vi.fn().mockResolvedValue({}),
}));
vi.mock('@/services/reportScheduleApi', () => ({
  createReportSchedule: vi.fn().mockResolvedValue({}),
}));
vi.mock('@/components/ui/Notification', () => ({
  notify: { success: vi.fn(), error: vi.fn(), info: vi.fn(), warning: vi.fn() },
}));
vi.mock('@/components/ui/modals/Modal');
// Stub charts but keep the real bar shape, so each row's fill can be read as text
vi.mock('recharts', () => {
  type ShapeFn = (props: { payload?: BandwidthTableRow }) => ReactNode;
  return {
    ResponsiveContainer: ({ children }: { children?: ReactNode }) => <div>{children}</div>,
    BarChart: ({ data, children }: { data: BandwidthTableRow[]; children?: ReactNode }) => (
      <div role="img" aria-label="bar chart">
        {Children.map(children, (child) =>
          isValidElement(child) ? cloneElement(child as never, { rows: data }) : child,
        )}
      </div>
    ),
    Bar: ({ rows = [], shape }: { rows?: BandwidthTableRow[]; shape: ShapeFn }) => (
      <>
        {rows.map((row) => (
          <span key={row.label}>{shape({ payload: row })}</span>
        ))}
      </>
    ),
    Rectangle: ({ payload, fill }: { payload?: BandwidthTableRow; fill?: string }) => (
      <span>{`bar ${payload?.label} fill ${fill}`}</span>
    ),
    CartesianGrid: () => null,
    XAxis: () => null,
    YAxis: () => null,
  };
});

// =============================================================================
// Helpers
// =============================================================================

const runReport = async (user: UserEvent) => {
  renderReportPage(Bandwidth, {
    route: '/reporting/bandwidth',
    settingsKey: 'bandwidth_report_page',
  });
  await screen.findByRole('button', { name: /^apply$/i });
  await user.click(applyButton());
};

const fillOf = (label: string) =>
  screen.getByText(new RegExp(`^bar ${label} fill `)).textContent!.split(' fill ')[1];

// =============================================================================
// Tests
// =============================================================================

describe('Bandwidth page - results', () => {
  beforeAll(() => {
    stubIntersectionObserver();
  });

  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    vi.mocked(fetchBandwidthReport).mockReset().mockResolvedValue(BANDWIDTH_RESPONSE);
    setupOptionServiceMocks();
  });

  test('the chart is the default view, titled with the unit', async () => {
    const user = userEvent.setup({ delay: null });
    await runReport(user);

    expect(
      await screen.findByRole('heading', { name: 'Displays Bandwidth (MB)' }),
    ).toBeInTheDocument();
    expect(screen.getByRole('img', { name: 'bar chart' })).toBeInTheDocument();
    expect(screen.queryByRole('table')).not.toBeInTheDocument();
  });

  test('a deleted display is drawn differently from a live one', async () => {
    const user = userEvent.setup({ delay: null });
    await runReport(user);
    await screen.findByText(/^bar Lobby Screen fill /);

    expect(fillOf('Old Screen')).not.toBe(fillOf('Lobby Screen'));
  });

  test('the table view lists Display, Bandwidth and Unit', async () => {
    const user = userEvent.setup({ delay: null });
    await runReport(user);
    await screen.findByRole('img', { name: 'bar chart' });

    await user.click(screen.getByRole('button', { name: 'Table View' }));

    await screen.findByRole('table');
    expect(screen.getAllByRole('columnheader').map((th) => th.textContent)).toEqual([
      'Display',
      'Bandwidth',
      'Unit',
    ]);
    const lobby = screen.getByRole('row', { name: /lobby screen/i });
    expect(within(lobby).getByRole('cell', { name: '5' })).toBeInTheDocument();
  });
});

describe('Bandwidth chart - unit label', () => {
  test.each([
    ['bytes', 'Displays Bandwidth (B)'],
    ['k', 'Displays Bandwidth (KB)'],
    ['M', 'Displays Bandwidth (MB)'],
    ['G', 'Displays Bandwidth (GB)'],
    ['T', 'Displays Bandwidth (TB)'],
    ['PB', 'Displays Bandwidth (PB)'],
  ])('the raw unit %s is titled %s', (unit, title) => {
    render(<BandwidthBarChart rows={[buildBandwidthRow({ unit })]} />);

    expect(screen.getByRole('heading')).toHaveTextContent(title);
  });

  test('with no unit the title has no brackets', () => {
    render(<BandwidthBarChart rows={[buildBandwidthRow({ unit: '' })]} />);

    expect(screen.getByRole('heading').textContent?.trim()).toBe('Displays Bandwidth');
  });
});
