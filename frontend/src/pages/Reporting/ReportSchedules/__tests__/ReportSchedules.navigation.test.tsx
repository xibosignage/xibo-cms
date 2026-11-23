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
// Links use reportNameId (internal name), never reportName (the description).
// =============================================================================

import userEvent from '@testing-library/user-event';
import { vi, beforeEach, afterEach, describe, test, expect } from 'vitest';

import {
  SCHEDULE_WITH_SAVED,
  SINGLE_REPORT_SCHEDULE,
  UNKNOWN_REPORT_SCHEDULE,
  mockReportSchedule,
} from './fixtures/reportSchedule';
import { renderReportSchedulesPage } from './helpers/renderReportSchedulesPage';
import { mockFetchReportSchedules } from './mocks/reportScheduleApi';

import { testQueryClient } from '@/setupTests';
import { clickMenuAction } from '@/testUtils/rowActions';

// =============================================================================
// Module mocks
// =============================================================================

const mockNavigate = vi.fn();
vi.mock('react-router-dom', async (importOriginal) => {
  const actual = await importOriginal();
  return { ...(actual as object), useNavigate: () => mockNavigate };
});

// The install folder is read once at import, so pretend to be a /cms/ install.
vi.mock('@/config/publicPath', async (importOriginal) => ({
  ...((await importOriginal()) as object),
  withPublicPath: (path: string) => '/cms/' + path.replace(/^\//, ''),
}));

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

describe('Report Schedules page - navigation', () => {
  const realLocation = window.location;
  const assign = vi.fn();

  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    Object.defineProperty(window, 'location', {
      configurable: true,
      value: { ...realLocation, assign },
    });
  });

  afterEach(() => {
    Object.defineProperty(window, 'location', { configurable: true, value: realLocation });
  });

  test("Open Last Report goes to the schedule's latest saved report", async () => {
    const user = userEvent.setup({ delay: null });
    const { lastSavedReportId, reportNameId, reportName } = SCHEDULE_WITH_SAVED;
    mockFetchReportSchedules({ rows: [SCHEDULE_WITH_SAVED], totalCount: 1 });
    renderReportSchedulesPage();

    await clickMenuAction(user, SCHEDULE_WITH_SAVED.name, /^open last report$/i);

    expect(mockNavigate).toHaveBeenCalledWith(
      `/reporting/saved-reports/${lastSavedReportId}/${reportNameId}/view`,
    );
    expect(mockNavigate.mock.calls[0]![0]).not.toContain(reportName);
  });

  test('Back to Reports opens the matching report page in the app', async () => {
    const user = userEvent.setup({ delay: null });
    mockFetchReportSchedules(SINGLE_REPORT_SCHEDULE);
    renderReportSchedulesPage();

    await clickMenuAction(user, mockReportSchedule.name, /^back to reports$/i);

    expect(mockNavigate).toHaveBeenCalledWith('/reporting/proof-of-play');
    expect(assign).not.toHaveBeenCalled();
  });

  // Classic page URL must include the install folder, not the site root.
  test('Back to Reports for an unknown report falls back to the classic page', async () => {
    const user = userEvent.setup({ delay: null });
    mockFetchReportSchedules({ rows: [UNKNOWN_REPORT_SCHEDULE], totalCount: 1 });
    renderReportSchedulesPage();

    await clickMenuAction(user, UNKNOWN_REPORT_SCHEDULE.name, /^back to reports$/i);

    expect(assign).toHaveBeenCalledWith('/cms/report/form/customReport');
    expect(mockNavigate).not.toHaveBeenCalled();
  });
});
