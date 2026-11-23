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
// Contract: each report's Schedule form, run once per report that has one
// Seeding: pick a filter without Apply; the form must start from it.
// =============================================================================

import { screen, waitFor, within } from '@testing-library/react';
import userEvent, { type UserEvent } from '@testing-library/user-event';
import { cloneElement, type ReactElement } from 'react';
import { vi, beforeAll, beforeEach, describe, test, expect } from 'vitest';

import { apiError } from '../../../__tests__/fixtures/apiError';
import { SSP_CONNECTOR_ID, SSP_PARTNERS } from '../fixtures/reportRows';
import { stubIntersectionObserver } from '../helpers/domStubs';
import { pickOption, renderReportPage } from '../helpers/renderReportPage';
import { REPORT_CASES } from '../helpers/reportCases';
import { setupOptionServiceMocks } from '../mocks/optionServices';

import { notify } from '@/components/ui/Notification';
import { createReportSchedule } from '@/services/reportScheduleApi';
import { fetchSspPartners, resolveSspConnectorId } from '@/services/sspActivityApi';
import { testQueryClient } from '@/setupTests';

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
// Per-report expectations
// =============================================================================

// Time Connected seeding is tested in its own folder.
const SCHEDULES: Record<
  string,
  { reportName: string; seed?: { filter: RegExp; option: string; sent: Record<string, unknown> } }
> = {
  Summary: {
    reportName: 'summaryReport',
    seed: { filter: /^display$/i, option: 'Lobby Screen', sent: { displayId: 1 } },
  },
  Distribution: {
    reportName: 'distributionReport',
    seed: { filter: /^display$/i, option: 'Lobby Screen', sent: { displayId: 1 } },
  },
  'Proof of Play': {
    reportName: 'proofofplayReport',
    seed: { filter: /^display$/i, option: 'Lobby Screen', sent: { displayId: 1 } },
  },
  Bandwidth: {
    reportName: 'bandwidth',
    seed: { filter: /^display$/i, option: 'Lobby Screen', sent: { displayId: 1 } },
  },
  'Time Connected': { reportName: 'timeconnected' },
  'Time Connected Summary': {
    reportName: 'timedisconnectedsummary',
    seed: { filter: /^display$/i, option: 'Lobby Screen', sent: { displayId: 1 } },
  },
  'Display Alerts': {
    reportName: 'displayalerts',
    seed: { filter: /^display$/i, option: 'Lobby Screen', sent: { displayId: 1 } },
  },
  'Library Usage': {
    reportName: 'libraryusage',
    seed: { filter: /^user$/i, option: 'alice', sent: { userId: 1 } },
  },
  'Session History': {
    reportName: 'sessionhistory',
    seed: { filter: /^user$/i, option: 'alice', sent: { userId: 1 } },
  },
  'API Request History': {
    reportName: 'apirequests',
    seed: { filter: /^user$/i, option: 'alice', sent: { userId: 1 } },
  },
};

const SCHEDULABLE = REPORT_CASES.filter((c) => c.schedule);

beforeAll(() => {
  stubIntersectionObserver();
});

describe.each(SCHEDULABLE)('$name schedule form', (c) => {
  const expected = SCHEDULES[c.name]!;

  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    // clearAllMocks keeps earlier behaviour; reset what this file overrides.
    vi.mocked(createReportSchedule)
      .mockReset()
      .mockResolvedValue({} as never);
    c.fetch().mockReset().mockResolvedValue(c.rows);
    setupOptionServiceMocks();
    vi.mocked(resolveSspConnectorId).mockResolvedValue(SSP_CONNECTOR_ID);
    vi.mocked(fetchSspPartners).mockResolvedValue(SSP_PARTNERS);
  });

  // Summary/Distribution need an item chosen before Schedule is enabled.
  const openSchedule = async (user: UserEvent) => {
    renderReportPage(c.Page, { route: c.route, settingsKey: c.settingsKey });
    await screen.findByText(c.placeholder);
    if (c.prepare) await c.prepare(user);
    await user.click(screen.getByRole('button', { name: /^schedule$/i }));
    return screen.findByRole('dialog');
  };

  const nameInput = (dialog: HTMLElement) =>
    within(dialog).getByRole('textbox', { name: /^name$/i });
  const saveButton = (dialog: HTMLElement) =>
    within(dialog).getByRole('button', { name: /^save$/i });

  test('Save needs a name', async () => {
    const user = userEvent.setup({ delay: null });
    const dialog = await openSchedule(user);

    expect(saveButton(dialog)).toBeDisabled();
    await user.type(nameInput(dialog), 'Weekly');
    expect(saveButton(dialog)).toBeEnabled();
  });

  test("saving sends the report's own name, confirms and closes", async () => {
    const user = userEvent.setup({ delay: null });
    const dialog = await openSchedule(user);

    await user.type(nameInput(dialog), 'Weekly');
    await user.click(saveButton(dialog));

    await waitFor(() =>
      expect(createReportSchedule).toHaveBeenCalledWith(
        expect.objectContaining({ name: 'Weekly', reportName: expected.reportName }),
      ),
    );
    expect(notify.success).toHaveBeenCalledWith('Report schedule created.');
    await waitFor(() => expect(screen.queryByRole('dialog')).not.toBeInTheDocument());
  });

  test.runIf(expected.seed !== undefined)(
    "the form starts from the page's current filters, applied or not",
    async () => {
      const user = userEvent.setup({ delay: null });
      renderReportPage(c.Page, { route: c.route, settingsKey: c.settingsKey });
      await screen.findByText(c.placeholder);
      if (c.prepare) await c.prepare(user);
      await pickOption(user, expected.seed!.filter, expected.seed!.option);

      await user.click(screen.getByRole('button', { name: /^schedule$/i }));
      const dialog = await screen.findByRole('dialog');
      await user.type(nameInput(dialog), 'Weekly');
      await user.click(saveButton(dialog));

      await waitFor(() =>
        expect(createReportSchedule).toHaveBeenCalledWith(
          expect.objectContaining(expected.seed!.sent),
        ),
      );
    },
  );

  test("a failed save shows the server's message and keeps the form open", async () => {
    const user = userEvent.setup({ delay: null });
    vi.mocked(createReportSchedule).mockRejectedValueOnce(apiError('Name already in use'));
    const dialog = await openSchedule(user);

    await user.type(nameInput(dialog), 'Weekly');
    await user.click(saveButton(dialog));

    expect(await within(dialog).findByRole('alert')).toHaveTextContent('Name already in use');
    expect(createReportSchedule).toHaveBeenCalledTimes(1);
    expect(screen.getByRole('dialog')).toBeInTheDocument();
  });
});
