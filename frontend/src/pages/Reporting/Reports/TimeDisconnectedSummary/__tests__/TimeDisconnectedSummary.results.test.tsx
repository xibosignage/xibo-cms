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
// Test type: Page integration — Time Connected Summary results
// =============================================================================

import { screen, waitFor } from '@testing-library/react';
import userEvent, { type UserEvent } from '@testing-library/user-event';
import type { ReactNode } from 'react';
import { vi, beforeAll, beforeEach, describe, test, expect } from 'vitest';

import { TDS_RESPONSE } from '../../__tests__/fixtures/reportRows';
import { stubIntersectionObserver } from '../../__tests__/helpers/domStubs';
import {
  applyButton,
  pickOption,
  renderReportPage,
} from '../../__tests__/helpers/renderReportPage';
import { setupOptionServiceMocks } from '../../__tests__/mocks/optionServices';
import TimeDisconnectedSummary from '../TimeDisconnectedSummary';

import { fetchTimeDisconnectedSummary } from '@/services/timeDisconnectedSummaryApi';
import { testQueryClient } from '@/setupTests';

// =============================================================================
// Module mocks
// =============================================================================

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
vi.mock('@/services/tagApi', () => ({
  fetchTags: vi.fn().mockResolvedValue({ rows: [], totalCount: 0 }),
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
vi.mock('@/services/timeDisconnectedSummaryApi', () => ({
  fetchTimeDisconnectedSummary: vi.fn(),
}));
// Recharts measures 0×0 in jsdom, so use named stand-ins.
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
    BarChart: box('bar chart'),
    CartesianGrid: () => null,
    XAxis: () => null,
    YAxis: () => null,
    Tooltip: () => null,
    Legend: () => null,
    Bar: ({ name, dataKey }: { name?: string; dataKey: string }) => (
      <span>{`bar ${name ?? dataKey}`}</span>
    ),
  };
});

// =============================================================================
// Helpers
// =============================================================================

const renderPage = () =>
  renderReportPage(TimeDisconnectedSummary, {
    route: '/reporting/time-connected-summary',
    settingsKey: 'time_disconnected_summary_page',
  });

const runReport = async (user: UserEvent) => {
  await screen.findByRole('button', { name: /^apply$/i });
  await user.click(applyButton());
  // Both fixture rows share a group, so wait for any matching cell.
  await screen.findAllByRole('cell', { name: /^(Back Office|Ground Floor)$/ });
};

const headings = () => screen.getAllByRole('columnheader').map((th) => th.textContent);

// =============================================================================
// Tests
// =============================================================================

describe('Time Connected Summary - results', () => {
  beforeAll(() => {
    stubIntersectionObserver();
  });

  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    vi.mocked(fetchTimeDisconnectedSummary).mockReset().mockResolvedValue(TDS_RESPONSE);
    setupOptionServiceMocks();
  });

  test('Display grouping shows one row per display with display columns', async () => {
    const user = userEvent.setup({ delay: null });
    renderPage();

    await runReport(user);

    expect(headings()).toEqual([
      'Display ID',
      'Display',
      'Time Disconnected',
      'Time Connected',
      'Availability',
      'Units',
    ]);
    expect(screen.getByRole('cell', { name: 'Lobby Screen' })).toBeInTheDocument();
  });

  test('Display Group grouping shows group columns, including the averages', async () => {
    const user = userEvent.setup({ delay: null });
    renderPage();
    await screen.findByRole('button', { name: /^apply$/i });

    await pickOption(user, /^group by$/i, 'Display Group');
    await runReport(user);

    expect(headings()).toEqual([
      'Display Group ID',
      'Display Group',
      'Time Disconnected',
      'Time Connected',
      'Average Time Disconnected',
      'Average Time Connected',
      'Availability',
      'Units',
    ]);
  });

  // Changing Group By without Apply must not relabel already-fetched rows.
  test('the columns follow the Group By the report was run with', async () => {
    const user = userEvent.setup({ delay: null });
    renderPage();
    await runReport(user);

    await user.click(screen.getByRole('button', { name: /filters/i }));
    await pickOption(user, /^group by$/i, 'Display Group');

    expect(headings()).toContain('Display ID');
    expect(headings()).not.toContain('Display Group ID');
  });

  test('the chart shows Uptime and Downtime, titled with the unit', async () => {
    const user = userEvent.setup({ delay: null });
    renderPage();
    await runReport(user);

    await user.click(screen.getByRole('button', { name: 'Chart View' }));

    await screen.findByRole('heading', { name: 'Availability (hrs)' });
    expect(screen.getByRole('img', { name: 'bar chart' })).toBeInTheDocument();
    expect(screen.getByText('bar Uptime')).toBeInTheDocument();
    expect(screen.getByText('bar Downtime')).toBeInTheDocument();
  });

  test('switching back to Table View shows the rows again', async () => {
    const user = userEvent.setup({ delay: null });
    renderPage();
    await runReport(user);
    await user.click(screen.getByRole('button', { name: 'Chart View' }));
    await screen.findByRole('img', { name: 'bar chart' });

    await user.click(screen.getByRole('button', { name: 'Table View' }));

    await waitFor(() =>
      expect(screen.getByRole('cell', { name: 'Lobby Screen' })).toBeInTheDocument(),
    );
    expect(screen.queryByRole('img', { name: 'bar chart' })).not.toBeInTheDocument();
  });
});
