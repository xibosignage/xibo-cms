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
// Test type: Page integration — Display Alerts results
// =============================================================================

import { screen, within } from '@testing-library/react';
import userEvent, { type UserEvent } from '@testing-library/user-event';
import { vi, afterAll, beforeAll, beforeEach, describe, test, expect } from 'vitest';

import { ALERTS_RESPONSE, buildAlertRow } from '../../__tests__/fixtures/reportRows';
import { stubIntersectionObserver } from '../../__tests__/helpers/domStubs';
import { applyButton, renderReportPage } from '../../__tests__/helpers/renderReportPage';
import { setupOptionServiceMocks } from '../../__tests__/mocks/optionServices';
import DisplayAlerts from '../DisplayAlerts';

import { fetchDisplayAlerts } from '@/services/displayAlertsApi';
import type { DisplayAlertsResponse } from '@/services/displayAlertsApi';
import { testQueryClient } from '@/setupTests';
import { knownFailure } from '@/testUtils/knownFailure';
import { getRow } from '@/testUtils/rowActions';
import { setTimezone } from '@/testUtils/timezone';

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
vi.mock('@/services/displayAlertsApi', () => ({ fetchDisplayAlerts: vi.fn() }));

// =============================================================================
// Helpers
// =============================================================================

const renderPage = () =>
  renderReportPage(DisplayAlerts, {
    route: '/reporting/display-alerts',
    settingsKey: 'display_alerts_page',
  });

const runReport = async (user: UserEvent) => {
  await screen.findByRole('button', { name: /^apply$/i });
  await user.click(applyButton());
  await screen.findByRole('cell', { name: 'Back Office' });
};

const cellFor = (display: string, heading: string) => {
  const headers = screen.getAllByRole('columnheader').map((th) => th.textContent);
  const row = getRow(display);
  return within(row).getAllByRole('cell')[headers.indexOf(heading)]!;
};

const withRows = (rows: DisplayAlertsResponse['table']): DisplayAlertsResponse => ({
  ...ALERTS_RESPONSE,
  table: rows,
});

const originalTz = process.env.TZ;

// =============================================================================
// Tests
// =============================================================================

describe('Display Alerts - results (browser 13 hours ahead of the CMS)', () => {
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
    vi.mocked(fetchDisplayAlerts).mockReset().mockResolvedValue(ALERTS_RESPONSE);
    setupOptionServiceMocks();
  });

  // Guard: if the TZ switch ever stops working, the date checks below pass vacuously.
  test('the environment really is running 13 hours ahead of the CMS', () => {
    expect(new Date('2027-01-01T00:00:00Z').getTimezoneOffset()).toBe(-13 * 60);
  });

  test('Display Alerts lists its columns in order', async () => {
    const user = userEvent.setup({ delay: null });
    renderPage();
    await runReport(user);

    expect(screen.getAllByRole('columnheader').map((th) => th.textContent)).toEqual([
      'Display ID',
      'Display',
      'Event Type',
      'Start',
      'End',
      'Duration',
      'Reference',
      'Detail',
    ]);
  });

  // 1798804800 is 2027-01-01 12:00 UTC: 1 Jan for the CMS, 2 Jan in the browser.
  test('Start and End show in the CMS timezone; an empty End stays empty', async () => {
    const user = userEvent.setup({ delay: null });
    vi.mocked(fetchDisplayAlerts).mockResolvedValueOnce(
      withRows([
        buildAlertRow({ start: '1798804800', end: '1798810200' }),
        buildAlertRow({ displayId: 2, display: 'Back Office', end: '' }),
      ]),
    );
    renderPage();
    await runReport(user);

    expect(cellFor('Lobby Screen', 'Start')).toHaveTextContent(/^2027-01-01$/);
    expect(cellFor('Lobby Screen', 'End')).toHaveTextContent(/^2027-01-01$/);
    expect(cellFor('Back Office', 'End')).toHaveTextContent(/^$/);
  });

  test('Duration is worked out from Start and End', async () => {
    const user = userEvent.setup({ delay: null });
    renderPage();
    await runReport(user);

    expect(cellFor('Lobby Screen', 'Duration')).toHaveTextContent('0day 1hr 30min 0sec');
    expect(cellFor('Back Office', 'Duration')).toHaveTextContent(/^$/);
  });

  test('an End before its Start gives a zero duration, never a negative one', async () => {
    const user = userEvent.setup({ delay: null });
    vi.mocked(fetchDisplayAlerts).mockResolvedValueOnce(
      withRows([buildAlertRow({ display: 'Back Office', start: '1798767000', end: '1798761600' })]),
    );
    renderPage();
    await runReport(user);

    expect(cellFor('Back Office', 'Duration')).toHaveTextContent('0day 0hr 0min 0sec');
  });

  // Known issue 3: xibosignage/xibo#4095 (Duration column has no sort control)
  describe('Pinned until https://github.com/xibosignage/xibo/issues/4095 is fixed', () => {
    knownFailure(
      'the Duration column can be sorted',
      async () => {
        const user = userEvent.setup({ delay: null });
        renderPage();
        await runReport(user);
        // Control: other columns do sort.
        expect(
          within(screen.getByRole('columnheader', { name: 'Display' })).getByRole('button'),
        ).toBeInTheDocument();
      },
      () =>
        expect(
          within(screen.getByRole('columnheader', { name: 'Duration' })).getByRole('button'),
        ).toBeInTheDocument(),
    );
  });
});
