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
// Contract: shared report-page behaviour, run once per report (reportCases)
// =============================================================================

import { screen, waitFor } from '@testing-library/react';
import userEvent, { type UserEvent } from '@testing-library/user-event';
import { cloneElement, type ReactElement } from 'react';
import { vi, beforeAll, beforeEach, describe, test, expect } from 'vitest';

import { SSP_CONNECTOR_ID, SSP_PARTNERS } from '../fixtures/reportRows';
import { stubIntersectionObserver } from '../helpers/domStubs';
import { applyButton, renderReportPage } from '../helpers/renderReportPage';
import { REPORT_CASES } from '../helpers/reportCases';
import { setupOptionServiceMocks } from '../mocks/optionServices';

import { fetchSspPartners, resolveSspConnectorId } from '@/services/sspActivityApi';
import { testQueryClient } from '@/setupTests';
import { deferred } from '@/testUtils/deferred';

// =============================================================================
// Module mocks
// =============================================================================

const mockNavigate = vi.fn();
vi.mock('react-router-dom', async (importOriginal) => ({
  ...((await importOriginal()) as object),
  useNavigate: () => mockNavigate,
}));

vi.mock('@/services/statsReportApi', () => ({ fetchStatsReport: vi.fn() }));
vi.mock('@/services/proofOfPlayApi', () => ({ fetchProofOfPlay: vi.fn() }));
vi.mock('@/services/bandwidthReportApi', () => ({ fetchBandwidthReport: vi.fn() }));
vi.mock('@/services/timeConnectedApi', () => ({ fetchTimeConnected: vi.fn() }));
vi.mock('@/services/timeDisconnectedSummaryApi', () => ({ fetchTimeDisconnectedSummary: vi.fn() }));
vi.mock('@/services/displayAlertsApi', () => ({ fetchDisplayAlerts: vi.fn() }));
vi.mock('@/services/libraryUsageApi', () => ({ fetchLibraryUsage: vi.fn() }));
vi.mock('@/services/sessionHistoryApi', () => ({ fetchSessionHistory: vi.fn() }));
vi.mock('@/services/apiRequestsApi', () => ({ fetchApiRequests: vi.fn() }));
vi.mock('@/services/sspActivityApi', () => ({
  resolveSspConnectorId: vi.fn(),
  fetchSspPartners: vi.fn(),
  fetchSspActivity: vi.fn(),
}));

// Option services, so the real dropdowns and paging run.
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

// Report switcher (ReportSelector → useAllReportsData) and schedule forms.
vi.mock('@/services/reportApi', () => ({
  fetchReports: vi.fn().mockResolvedValue({}),
  fetchExportStatsCount: vi.fn().mockResolvedValue(0),
}));
vi.mock('@/services/reportScheduleApi', () => ({
  createReportSchedule: vi.fn().mockResolvedValue({}),
}));
vi.mock('@/components/ui/Notification', () => ({
  notify: { success: vi.fn(), error: vi.fn(), info: vi.fn(), warning: vi.fn() },
}));
vi.mock('@/components/ui/modals/Modal');

// Recharts' ResponsiveContainer measures 0×0 in jsdom; give its chart a fixed size.
vi.mock('recharts', async (importOriginal) => {
  const actual = (await importOriginal()) as object;
  return {
    ...actual,
    ResponsiveContainer: ({ children }: { children: ReactElement }) => (
      <div>{cloneElement(children, { width: 800, height: 300 } as object)}</div>
    ),
  };
});

// =============================================================================
// Tests
// =============================================================================

// No message, so every page shows its friendly text.
const SERVER_ERROR = new Error('');

beforeAll(() => {
  stubIntersectionObserver();
});

describe.each(REPORT_CASES)('$name page', (c) => {
  const render = () => renderReportPage(c.Page, { route: c.route, settingsKey: c.settingsKey });

  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    // clearAllMocks keeps earlier behaviour; reset the data service.
    c.fetch().mockReset().mockResolvedValue(c.rows);
    setupOptionServiceMocks();
    vi.mocked(resolveSspConnectorId).mockResolvedValue(SSP_CONNECTOR_ID);
    vi.mocked(fetchSspPartners).mockResolvedValue(SSP_PARTNERS);
  });

  const apply = async (user: UserEvent) => {
    await screen.findByText(c.placeholder);
    if (c.prepare) await c.prepare(user);
    await user.click(applyButton());
  };

  test('before Apply the page explains what to do and requests nothing', async () => {
    render();

    await screen.findByText(c.placeholder);
    expect(c.fetch()).not.toHaveBeenCalled();
  });

  test('Apply runs the report once and closes the filters', { tags: ['smoke'] }, async () => {
    const user = userEvent.setup({ delay: null });
    render();
    await screen.findByRole('button', { name: /^reset$/i });

    await apply(user);

    await screen.findByText(c.resultText);
    expect(c.fetch()).toHaveBeenCalledTimes(1);
    expect(screen.queryByRole('button', { name: /^reset$/i })).not.toBeInTheDocument();
  });

  test("Reset keeps the panel open and doesn't run the report", async () => {
    const user = userEvent.setup({ delay: null });
    render();
    await screen.findByText(c.placeholder);
    if (c.prepare) await c.prepare(user);

    await user.click(screen.getByRole('button', { name: /^reset$/i }));

    expect(screen.getByRole('button', { name: /^reset$/i })).toBeInTheDocument();
    expect(c.fetch()).not.toHaveBeenCalled();
    // Reset clears the required choice, so Apply is disabled again.
    if (c.prepare) await waitFor(() => expect(applyButton()).toBeDisabled());
  });

  test('a failed run says so, and Retry runs it again', async () => {
    const user = userEvent.setup({ delay: null });
    c.fetch().mockRejectedValueOnce(SERVER_ERROR);
    render();

    await apply(user);

    await screen.findByText('Something went wrong generating this report. Please try again.');
    await user.click(screen.getByRole('button', { name: /^retry$/i }));
    await screen.findByText(c.resultText);
  });

  test('an empty run says so', async () => {
    const user = userEvent.setup({ delay: null });
    c.fetch().mockResolvedValueOnce(c.empty);
    render();

    await apply(user);

    await screen.findByText('No results found. Adjust your filters and click Apply.');
  });

  test('while the report runs, Loading shows', async () => {
    const user = userEvent.setup({ delay: null });
    const pending = deferred<unknown>();
    c.fetch().mockReturnValueOnce(pending.promise);
    render();

    await apply(user);

    await screen.findAllByText('Loading...');
    pending.resolve(c.rows);
    await screen.findByText(c.resultText);
    await waitFor(() => expect(screen.queryAllByText('Loading...')).toHaveLength(0));
  });

  test('Refresh is not offered before the report has run', async () => {
    render();
    await screen.findByText(c.placeholder);

    expect(screen.queryByRole('button', { name: /^refresh$/i })).not.toBeInTheDocument();
    expect(c.fetch()).not.toHaveBeenCalled();
  });

  test('Back returns to All Reports', async () => {
    const user = userEvent.setup({ delay: null });
    render();
    await screen.findByText(c.placeholder);

    await user.click(screen.getByRole('button', { name: /^back$/i }));

    expect(mockNavigate).toHaveBeenCalledWith('/reporting/all-reports');
  });

  test(c.schedule ? 'offers Schedule' : 'offers no Schedule', async () => {
    render();
    await screen.findByText(c.placeholder);

    const schedule = screen.queryByRole('button', { name: /^schedule$/i });
    if (c.schedule) expect(schedule).toBeInTheDocument();
    else expect(schedule).not.toBeInTheDocument();
  });
});
