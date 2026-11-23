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
// No Add button here; schedules are created from report pages.
// =============================================================================

import { screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { vi, beforeEach, describe, test, expect } from 'vitest';

import {
  EMPTY_REPORT_SCHEDULE_TABLE,
  mockReportSchedule,
  PAGED_SCHEDULES_P1,
  PAGED_SCHEDULES_P2,
  SINGLE_REPORT_SCHEDULE,
  buildReportSchedule,
} from './fixtures/reportSchedule';
import { renderReportSchedulesPage } from './helpers/renderReportSchedulesPage';
import {
  lastScheduleRequest,
  mockFetchReportSchedulePages,
  mockFetchReportSchedules,
} from './mocks/reportScheduleApi';

import { fetchReportSchedules } from '@/services/reportScheduleApi';
import { fetchUserPreference } from '@/services/userApi';
import { testQueryClient } from '@/setupTests';
import { deferred } from '@/testUtils/deferred';
import { findRow } from '@/testUtils/rowActions';

// =============================================================================
// Module mocks
// =============================================================================

const mockNavigate = vi.fn();
vi.mock('react-router-dom', async (importOriginal) => {
  const actual = await importOriginal();
  return { ...(actual as object), useNavigate: () => mockNavigate };
});

vi.mock('@/services/reportScheduleApi', () => ({
  fetchReportSchedules: vi.fn().mockResolvedValue({ rows: [], totalCount: 0 }),
  updateReportSchedule: vi.fn().mockResolvedValue({}),
  deleteReportSchedule: vi.fn().mockResolvedValue(undefined),
  toggleActiveReportSchedule: vi.fn().mockResolvedValue({}),
  resetReportSchedule: vi.fn().mockResolvedValue({}),
  deleteAllSavedReportsForSchedule: vi.fn().mockResolvedValue(undefined),
}));
vi.mock('@/services/reportApi', () => ({
  fetchReports: vi.fn().mockResolvedValue({}),
}));
vi.mock('@/services/userApi', () => ({
  fetchUserPreference: vi.fn().mockResolvedValue(null),
  saveUserPreference: vi.fn().mockResolvedValue(undefined),
}));
vi.mock('@/components/ui/Notification', () => ({
  notify: { success: vi.fn(), error: vi.fn(), info: vi.fn(), warning: vi.fn() },
}));
vi.mock('@/components/ui/modals/Modal');
vi.mock('../hooks/useReportScheduleFilterOptions', () => ({
  useReportScheduleFilterOptions: vi.fn(() => ({
    filterOptions: [],
    reportDescriptionMap: { proofofplayReport: 'Proof of Play' },
    isLoading: false,
  })),
}));

// =============================================================================
// Tests
// =============================================================================

describe('Report Schedules page - render', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    // clearAllMocks keeps earlier implementations, so reset these.
    vi.mocked(fetchUserPreference).mockReset().mockResolvedValue(null);
    vi.mocked(fetchReportSchedules).mockReset();
  });

  test('the page shows a loading placeholder until the saved page settings arrive', async () => {
    const pref = deferred<null>();
    vi.mocked(fetchUserPreference).mockReturnValue(pref.promise);
    mockFetchReportSchedules(SINGLE_REPORT_SCHEDULE);
    renderReportSchedulesPage({ hydrated: false });

    await screen.findByText('Loading...');
    expect(screen.getByRole('textbox', { name: /^search$/i })).toBeDisabled();
    expect(screen.getByRole('button', { name: /filters/i })).toBeDisabled();

    pref.resolve(null);

    await screen.findByText(mockReportSchedule.name);
    expect(screen.getByRole('textbox', { name: /^search$/i })).toBeEnabled();
    expect(screen.getByRole('button', { name: /filters/i })).toBeEnabled();
  });

  test('each schedule is listed with its name, report and owner', { tags: ['smoke'] }, async () => {
    mockFetchReportSchedules(SINGLE_REPORT_SCHEDULE);
    renderReportSchedulesPage();

    const row = await findRow('Weekly PoP');
    expect(row).toHaveTextContent('Weekly PoP');
    expect(row).toHaveTextContent('Proof of Play');
    expect(row).toHaveTextContent('TestUser');
  });

  test('an empty list shows the table empty state', async () => {
    mockFetchReportSchedules(EMPTY_REPORT_SCHEDULE_TABLE);
    renderReportSchedulesPage();

    await screen.findByText('No results found.');
    expect(screen.queryByText(mockReportSchedule.name)).not.toBeInTheDocument();
  });

  test('a failed load shows the server error message', async () => {
    vi.mocked(fetchReportSchedules).mockRejectedValue(new Error('Database unavailable'));
    renderReportSchedulesPage();

    await screen.findByText(/database unavailable/i);
  });

  test('moving to page 2 asks the server for the second page', async () => {
    const user = userEvent.setup({ delay: null });
    mockFetchReportSchedulePages(PAGED_SCHEDULES_P1, PAGED_SCHEDULES_P2);
    renderReportSchedulesPage();

    await screen.findByText('Schedule 1');
    await user.click(screen.getByRole('button', { name: /next/i }));

    await screen.findByText('Schedule 11');
    expect(lastScheduleRequest()).toEqual(expect.objectContaining({ start: 10, length: 10 }));
  });

  // Checked by outcome, not request count, since React Query may repeat requests.
  test('Refresh asks the server for the list again', async () => {
    const user = userEvent.setup({ delay: null });
    mockFetchReportSchedules(SINGLE_REPORT_SCHEDULE);
    renderReportSchedulesPage();

    await screen.findByText(mockReportSchedule.name);
    mockFetchReportSchedules({
      rows: [buildReportSchedule({ name: 'Weekly PoP (refreshed)' })],
      totalCount: 1,
    });
    await user.click(screen.getByRole('button', { name: /refresh/i }));

    await waitFor(() => expect(screen.getByText('Weekly PoP (refreshed)')).toBeInTheDocument());
  });
});
