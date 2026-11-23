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
// Test type: Page integration — SSP Activity results
// =============================================================================

import { screen, waitFor, within } from '@testing-library/react';
import userEvent, { type UserEvent } from '@testing-library/user-event';
import type { ReactNode } from 'react';
import { vi, afterAll, beforeAll, beforeEach, describe, test, expect } from 'vitest';

import {
  buildSspRow,
  SSP_CONNECTOR_ID,
  SSP_PARTNERS,
  SSP_RESPONSE,
} from '../../__tests__/fixtures/reportRows';
import { stubIntersectionObserver } from '../../__tests__/helpers/domStubs';
import {
  applyButton,
  pickOption,
  renderReportPage,
} from '../../__tests__/helpers/renderReportPage';
import { setupOptionServiceMocks } from '../../__tests__/mocks/optionServices';
import SspActivity from '../SspActivity';

import { fetchConnectors } from '@/services/connectorApi';
import { fetchSspActivity, fetchSspPartners } from '@/services/sspActivityApi';
import type { SspActivityResponse } from '@/services/sspActivityApi';
import { testQueryClient } from '@/setupTests';
import { knownFailure } from '@/testUtils/knownFailure';
import { setTimezone } from '@/testUtils/timezone';
import type { Connector } from '@/types/connector';

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
vi.mock('@/services/reportApi', () => ({
  fetchReports: vi.fn().mockResolvedValue({}),
}));
vi.mock('@/components/ui/Notification', () => ({
  notify: { success: vi.fn(), error: vi.fn(), info: vi.fn(), warning: vi.fn() },
}));
vi.mock('@/components/ui/modals/Modal');
vi.mock('@/services/connectorApi', async (o) => ({
  ...((await o()) as object),
  fetchConnectors: vi.fn(),
}));
vi.mock('@/services/sspActivityApi', async (o) => ({
  ...((await o()) as object),
  fetchSspActivity: vi.fn(),
  fetchSspPartners: vi.fn(),
}));
// Plain inputs so a test can type the ISO string the real date picker emits.
vi.mock('@/components/ui/forms/DatePickerInput', () => ({
  default: ({
    label,
    value,
    onChange,
  }: {
    label: string;
    value: string;
    onChange: (v: string) => void;
  }) => <input aria-label={label} value={value} onChange={(e) => onChange(e.target.value)} />,
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
    PieChart: box('pie chart'),
    Tooltip: () => null,
    Legend: () => null,
    Pie: ({ data }: { data: { name: string; value: number }[] }) => (
      <>
        {data.map((d) => (
          <span key={d.name}>{`slice ${d.name} ${d.value}`}</span>
        ))}
      </>
    ),
  };
});

// =============================================================================
// Helpers
// =============================================================================

const SSP_CONNECTOR = {
  connectorId: SSP_CONNECTOR_ID,
  className: 'Xibo\\\\Connector\\\\XiboSspConnector',
  isEnabled: 1,
  isVisible: 1,
  isSystem: false,
  title: 'Xibo SSP',
  description: '',
  thumbnail: '',
} as unknown as Connector;

const serveSsp = (activity: SspActivityResponse) => {
  vi.mocked(fetchSspPartners).mockResolvedValue(SSP_PARTNERS);
  vi.mocked(fetchSspActivity).mockResolvedValue(activity);
};

const renderPage = () =>
  renderReportPage(SspActivity, {
    route: '/reporting/ssp-activity',
    settingsKey: 'ssp_activity_report_page',
  });

const runReport = async (user: UserEvent) => {
  await screen.findByRole('button', { name: /^apply$/i });
  await pickOption(user, /^display$/i, 'Lobby Screen');
  await user.click(applyButton());
  await screen.findByRole('button', { name: 'Detailed' });
};

const headings = () => screen.getAllByRole('columnheader').map((th) => th.textContent);

// Twelve rows, one per hour, so the grouped summary has two pages.
const TWELVE_HOURS: SspActivityResponse = {
  success: true,
  data: Array.from({ length: 12 }, (_, i) =>
    buildSspRow({
      scheduledAt: `2027-01-01T${String(i).padStart(2, '0')}:15:00Z`,
      errorCode: 'E1',
    }),
  ),
};

const originalTz = process.env.TZ;

// =============================================================================
// Tests
// =============================================================================

describe('SSP Activity - results', () => {
  beforeAll(() => {
    stubIntersectionObserver();
    process.env.TZ = 'UTC';
  });
  afterAll(() => {
    setTimezone(originalTz);
  });

  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    vi.mocked(fetchConnectors).mockReset().mockResolvedValue([SSP_CONNECTOR]);
    vi.mocked(fetchSspPartners).mockReset();
    vi.mocked(fetchSspActivity).mockReset();
    serveSsp(SSP_RESPONSE);
    setupOptionServiceMocks();
  });

  test('Summary is the default view, grouped by hour', async () => {
    const user = userEvent.setup({ delay: null });
    renderPage();
    await runReport(user);

    expect(screen.getByRole('combobox', { name: /^filter options$/i })).toHaveTextContent('Hour');
    expect(headings()).toEqual([
      'Date',
      'Hour',
      'Campaign',
      'Play Count',
      'Error Count',
      'Misses Count',
      'Impressions',
      'Impression Actual',
    ]);
    expect(screen.getByRole('cell', { name: '09:00' })).toBeInTheDocument();
  });

  test('Detailed lists every row with Yes/No for played and errored', async () => {
    const user = userEvent.setup({ delay: null });
    renderPage();
    await runReport(user);

    await user.click(screen.getByRole('button', { name: 'Detailed' }));

    expect(headings()).toEqual(
      expect.arrayContaining(['Scheduled At', 'Played?', 'Errored?', 'Error Code']),
    );
    const rows = screen.getAllByRole('row').slice(1);
    expect(rows).toHaveLength(2);
    expect(within(rows[1]!).getByRole('cell', { name: 'E42' })).toBeInTheDocument();
    expect(within(rows[0]!).getAllByRole('cell', { name: 'Yes' })).toHaveLength(1);
  });

  test('grouping by error code swaps the date columns for the error code', async () => {
    const user = userEvent.setup({ delay: null });
    renderPage();
    await runReport(user);

    await pickOption(user, /^filter options$/i, 'Error Code');

    await waitFor(() => expect(headings()).not.toContain('Hour'));
    expect(headings()).toContain('Error Code');
    expect(screen.getByRole('cell', { name: 'E42' })).toBeInTheDocument();
  });

  // The summary table is rebuilt per grouping, so it starts again from the first page.
  test('changing the grouping returns the summary to page 1', async () => {
    const user = userEvent.setup({ delay: null });
    serveSsp(TWELVE_HOURS);
    renderPage();
    await runReport(user);
    await user.click(screen.getByRole('button', { name: /next/i }));
    await screen.findByRole('cell', { name: '10:00' });

    await pickOption(user, /^filter options$/i, 'Hour and Error Code');

    await screen.findByRole('cell', { name: '00:00' });
    expect(screen.queryByRole('cell', { name: '10:00' })).not.toBeInTheDocument();
  }, 30_000); // ~8.1 s locally under load; 15 s is too tight on a 2-vCPU runner

  test('the chart shows plays, errors and misses as percentages', async () => {
    const user = userEvent.setup({ delay: null });
    renderPage();
    await runReport(user);

    expect(screen.getByRole('heading', { name: 'Summary Chart' })).toBeInTheDocument();
    expect(screen.getByText('slice Plays 50')).toBeInTheDocument();
    expect(screen.getByText('slice Errors 50')).toBeInTheDocument();
    expect(screen.getByText('slice Misses 0')).toBeInTheDocument();
  });

  test('with no plays, errors or misses every share is 0', async () => {
    const user = userEvent.setup({ delay: null });
    serveSsp({
      success: true,
      data: [buildSspRow({ isPlayed: false, isErrored: true, errors: 0 })],
    });
    renderPage();
    await runReport(user);

    expect(screen.getByText('slice Plays 0')).toBeInTheDocument();
    expect(screen.getByText('slice Errors 0')).toBeInTheDocument();
    expect(screen.getByText('slice Misses 0')).toBeInTheDocument();
  });
});

describe('SSP Activity - results (browser 13 hours ahead of the CMS)', () => {
  beforeAll(() => {
    stubIntersectionObserver();
    process.env.TZ = 'Pacific/Auckland'; // UTC+13 in January; the CMS (superAdmin) is UTC
  });
  afterAll(() => {
    setTimezone(originalTz);
  });

  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    vi.mocked(fetchConnectors).mockReset().mockResolvedValue([SSP_CONNECTOR]);
    vi.mocked(fetchSspPartners).mockReset();
    vi.mocked(fetchSspActivity).mockReset();
    serveSsp(SSP_RESPONSE);
    setupOptionServiceMocks();
  });

  // Guard: if the TZ switch ever stops working, the pin below passes vacuously.
  test('the environment really is running 13 hours ahead of the CMS', () => {
    expect(new Date('2027-01-01T00:00:00Z').getTimezoneOffset()).toBe(-13 * 60);
  });

  // Known issue 10: xibosignage/xibo#4102 (hours bucketed on the browser's clock)
  describe('Pinned until https://github.com/xibosignage/xibo/issues/4102 is fixed', () => {
    knownFailure(
      'an activity at 09:15 in the CMS is summarised under 09:00',
      async () => {
        const user = userEvent.setup({ delay: null });
        renderPage();
        await runReport(user);
        // Control: the summary rendered its hour column.
        expect(headings()).toContain('Hour');
      },
      () => expect(screen.getByRole('cell', { name: '09:00' })).toBeInTheDocument(),
    );
  });
});
