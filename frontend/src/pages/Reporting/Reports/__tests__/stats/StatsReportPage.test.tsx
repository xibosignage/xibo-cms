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
// Test type: Page integration — Summary and Distribution
// =============================================================================

import { screen, waitFor } from '@testing-library/react';
import userEvent, { type UserEvent } from '@testing-library/user-event';
import { vi, beforeAll, beforeEach, describe, test, expect } from 'vitest';

import Distribution from '../../Distribution/Distribution';
import Summary from '../../Summary/Summary';
import { STATS_RESPONSE } from '../fixtures/reportRows';
import { stubIntersectionObserver } from '../helpers/domStubs';
import { applyButton, pickOption, renderReportPage } from '../helpers/renderReportPage';
import { setupOptionServiceMocks } from '../mocks/optionServices';

import { fetchStatsReport } from '@/services/statsReportApi';
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
vi.mock('@/services/reportApi', () => ({
  fetchReports: vi.fn().mockResolvedValue({}),
}));
vi.mock('@/services/displaysApi', () => ({ fetchDisplays: vi.fn() }));
vi.mock('@/services/displayGroupApi', () => ({ fetchDisplayGroups: vi.fn() }));
vi.mock('@/services/layoutsApi', () => ({ fetchLayouts: vi.fn() }));
vi.mock('@/services/mediaApi', () => ({ fetchMedia: vi.fn() }));
vi.mock('@/services/campaignApi', () => ({ fetchCampaigns: vi.fn() }));
vi.mock('@/services/userGroupApi', () => ({ fetchUserGroups: vi.fn() }));
vi.mock('@/services/userApi', () => ({
  fetchUserPreference: vi.fn().mockResolvedValue(null),
  saveUserPreference: vi.fn().mockResolvedValue(undefined),
  fetchUsers: vi.fn(),
}));
vi.mock('@/services/reportScheduleApi', () => ({
  createReportSchedule: vi.fn().mockResolvedValue({}),
}));
vi.mock('@/components/ui/Notification', () => ({
  notify: { success: vi.fn(), error: vi.fn(), info: vi.fn(), warning: vi.fn() },
}));
vi.mock('@/components/ui/modals/Modal');
// The chart has its own file (StatsChart.test.tsx).
vi.mock('../../shared/StatsChart', () => ({
  default: ({ type }: { type: string }) => <div role="img" aria-label={`${type} chart`} />,
}));

// =============================================================================
// Helpers
// =============================================================================

const scheduleButton = () => screen.getByRole('button', { name: /^schedule$/i });

// Range is a button showing the current preset; it opens the preset list.
const pickRange = async (user: UserEvent, current: string, next: string) => {
  await user.click(screen.getByRole('button', { name: current }));
  await user.click(await screen.findByRole('button', { name: next }));
};

const CASES = [
  {
    name: 'Summary',
    Page: Summary,
    route: '/reporting/summary',
    settingsKey: 'summary_report_page',
    reportName: 'summaryReport',
    groupBy: 'byday',
  },
  {
    name: 'Distribution',
    Page: Distribution,
    route: '/reporting/distribution',
    settingsKey: 'distribution_report_page',
    reportName: 'distributionReport',
    groupBy: 'byhour',
  },
];

// =============================================================================
// Tests — both configs
// =============================================================================

describe.each(CASES)('$name page', (c) => {
  beforeAll(() => {
    stubIntersectionObserver();
  });

  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    vi.mocked(fetchStatsReport).mockReset().mockResolvedValue(STATS_RESPONSE);
    setupOptionServiceMocks();
  });

  const render = () => renderReportPage(c.Page, { route: c.route, settingsKey: c.settingsKey });

  test('Apply and Schedule wait until an item is chosen', async () => {
    const user = userEvent.setup({ delay: null });
    render();
    await screen.findByRole('combobox', { name: /^layout/i });
    expect(applyButton()).toBeDisabled();
    expect(scheduleButton()).toBeDisabled();

    await pickOption(user, /^layout/i, 'Lobby Layout');

    expect(applyButton()).toBeEnabled();
    expect(scheduleButton()).toBeEnabled();
  });

  test('changing Type clears the chosen item', async () => {
    const user = userEvent.setup({ delay: null });
    render();
    await screen.findByRole('combobox', { name: /^layout/i });
    await pickOption(user, /^layout/i, 'Lobby Layout');

    await pickOption(user, /^type$/i, 'Media');
    expect(applyButton()).toBeDisabled();
    await pickOption(user, /^type$/i, 'Layout');

    expect(screen.getByRole('combobox', { name: /^layout/i })).not.toHaveTextContent(
      'Lobby Layout',
    );
    expect(applyButton()).toBeDisabled();
  });

  test('the Event type needs a tag instead of an item', async () => {
    const user = userEvent.setup({ delay: null });
    render();
    await screen.findByRole('combobox', { name: /^layout/i });

    await pickOption(user, /^type$/i, 'Event');
    expect(applyButton()).toBeDisabled();
    await user.type(screen.getByRole('textbox', { name: /^tag/i }), 'promo');

    expect(applyButton()).toBeEnabled();
  });

  test('a month or year range warns that it may return a lot of data', async () => {
    const user = userEvent.setup({ delay: null });
    render();
    await screen.findByRole('combobox', { name: /^layout/i });
    expect(screen.queryByText(/may return a lot of data/i)).not.toBeInTheDocument();

    await pickRange(user, 'Today', 'This Month');

    expect(screen.getByText(/may return a lot of data/i)).toBeInTheDocument();
  });

  test('Apply sends the report, the chosen item and the range', async () => {
    const user = userEvent.setup({ delay: null });
    render();
    await screen.findByRole('combobox', { name: /^layout/i });
    await pickOption(user, /^layout/i, 'Lobby Layout');
    await pickOption(user, /^display$/i, 'Lobby Screen');

    await user.click(applyButton());

    await waitFor(() => expect(fetchStatsReport).toHaveBeenCalled());
    expect(fetchStatsReport).toHaveBeenCalledWith(
      c.reportName,
      expect.objectContaining({
        type: 'layout',
        layoutId: 301,
        displayId: 1,
        reportFilter: 'today',
        groupByFilter: c.groupBy,
      }),
    );
    // Empty display groups are left out, not sent as an empty list.
    expect(vi.mocked(fetchStatsReport).mock.calls[0]![1].displayGroupId).toBeUndefined();
  });

  test('the results header names the type and the item reported on', async () => {
    const user = userEvent.setup({ delay: null });
    render();
    await screen.findByRole('combobox', { name: /^layout/i });
    await pickOption(user, /^layout/i, 'Lobby Layout');

    await user.click(applyButton());

    await screen.findByText('Tue');
    expect(document.body).toHaveTextContent('Layout:Lobby Layout');
  });

  test('the table and chart views switch', async () => {
    const user = userEvent.setup({ delay: null });
    render();
    await screen.findByRole('combobox', { name: /^layout/i });
    await pickOption(user, /^layout/i, 'Lobby Layout');
    await user.click(applyButton());
    await screen.findByText('Tue');

    await user.click(screen.getByRole('button', { name: 'Chart View' }));
    expect(screen.getByRole('img', { name: 'line chart' })).toBeInTheDocument();
    expect(screen.queryByText('Tue')).not.toBeInTheDocument();

    await user.click(screen.getByRole('button', { name: 'Table View' }));
    await screen.findByText('Tue');
  });
});

// =============================================================================
// Tests — Summary only (its Group By depends on the range)
// =============================================================================

describe('Summary page - Group By follows the range', () => {
  beforeAll(() => {
    stubIntersectionObserver();
  });

  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    vi.mocked(fetchStatsReport).mockReset().mockResolvedValue(STATS_RESPONSE);
    setupOptionServiceMocks();
  });

  // Month is offered for a year but not a month; switching back must not keep it.
  test('a Group By the new range does not offer is replaced by its default', async () => {
    const user = userEvent.setup({ delay: null });
    renderReportPage(Summary, { route: '/reporting/summary', settingsKey: 'summary_report_page' });
    await screen.findByRole('combobox', { name: /^layout/i });
    expect(screen.queryByRole('combobox', { name: /^group by$/i })).not.toBeInTheDocument();

    await pickRange(user, 'Today', 'This Year');
    await pickOption(user, /^group by$/i, 'Month');
    await pickRange(user, 'This Year', 'This Month');

    expect(screen.getByRole('combobox', { name: /^group by$/i })).toHaveTextContent('Day');
  });
});
