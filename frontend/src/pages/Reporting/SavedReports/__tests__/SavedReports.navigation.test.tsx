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
// Page integration: row actions that leave the page
// =============================================================================

import { screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { vi, afterEach, beforeEach, describe, test, expect } from 'vitest';

import { INITIAL_FILTER_STATE as SCHEDULE_INITIAL_FILTER_STATE } from '../../ReportSchedules/ReportSchedulesConfig';

import {
  mockSavedReport,
  PAGE_INDEX_STORAGE_KEY,
  PAGED_SAVED_REPORTS_P1,
  PAGED_SAVED_REPORTS_P2,
  queryKeys,
  SINGLE_SAVED_REPORT,
  UNKNOWN_TYPE_SAVED_REPORT,
} from './fixtures/savedReport';
import { renderSavedReportsPage } from './helpers/renderSavedReportsPage';
import {
  lastSavedReportRequest,
  mockFetchSavedReportPages,
  mockFetchSavedReports,
} from './mocks/savedReportApi';

import { notify } from '@/components/ui/Notification';
import { fetchSavedReports } from '@/services/savedReportApi';
import { saveUserPreference } from '@/services/userApi';
import { testQueryClient } from '@/setupTests';
import { knownFailure } from '@/testUtils/knownFailure';
import { clickMenuAction, clickQuickAction } from '@/testUtils/rowActions';
import { captureUnhandledRejections } from '@/testUtils/unhandledRejections';

// =============================================================================
// Module mocks
// =============================================================================

const mockNavigate = vi.fn();
vi.mock('react-router-dom', async (importOriginal) => {
  const actual = await importOriginal();
  return { ...(actual as object), useNavigate: () => mockNavigate };
});

vi.mock('@/services/savedReportApi', () => ({
  fetchSavedReports: vi.fn().mockResolvedValue({ rows: [], totalCount: 0 }),
  deleteSavedReport: vi.fn().mockResolvedValue(undefined),
  fetchSavedReportData: vi.fn(),
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
vi.mock('../hooks/useSavedReportFilterOptions', () => ({
  useSavedReportFilterOptions: vi.fn(() => ({
    filterOptions: [],
    reportDescriptionMap: { proofofplayReport: 'Proof of Play' },
    isLoading: false,
  })),
}));
// Pretend to be a /cms/ install.
vi.mock('@/config/publicPath', async (importOriginal) => ({
  ...((await importOriginal()) as object),
  withPublicPath: (path: string) => '/cms/' + path.replace(/^\//, ''),
}));

// =============================================================================
// Helpers
// =============================================================================

const realLocation = window.location;
const assign = vi.fn();

const stubLocation = () =>
  Object.defineProperty(window, 'location', {
    configurable: true,
    writable: true,
    value: { ...realLocation, href: '', assign },
  });

const savedSchedulePref = () => {
  const calls = vi.mocked(saveUserPreference).mock.calls;
  return calls[calls.length - 1]![0] as { option: string; value: Record<string, unknown> };
};

// =============================================================================
// Tests
// =============================================================================

describe('Saved Reports page - navigation', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    // clearAllMocks keeps earlier implementations; reset the overridden ones.
    vi.mocked(saveUserPreference).mockReset().mockResolvedValue(undefined);
    vi.mocked(fetchSavedReports).mockReset();
    mockFetchSavedReports(SINGLE_SAVED_REPORT);
    sessionStorage.clear();
    stubLocation();
  });

  afterEach(() => {
    Object.defineProperty(window, 'location', { configurable: true, value: realLocation });
    vi.restoreAllMocks();
  });

  // Open

  test('Open goes to the viewer and remembers the current list page', async () => {
    const user = userEvent.setup({ delay: null });
    mockFetchSavedReportPages(PAGED_SAVED_REPORTS_P1, PAGED_SAVED_REPORTS_P2);
    renderSavedReportsPage();
    await screen.findByText('Saved 1');
    await user.click(screen.getByRole('button', { name: /next/i }));
    await screen.findByText('Saved 11');

    await clickQuickAction(user, 'Saved 11', /^open$/i);

    expect(sessionStorage.getItem(PAGE_INDEX_STORAGE_KEY)).toBe('1');
    expect(mockNavigate).toHaveBeenCalledWith(
      `/reporting/saved-reports/110/${mockSavedReport.reportName}/view`,
    );
  });

  test('the list reopens on the remembered page', async () => {
    sessionStorage.setItem(PAGE_INDEX_STORAGE_KEY, '1');
    mockFetchSavedReportPages(PAGED_SAVED_REPORTS_P1, PAGED_SAVED_REPORTS_P2);
    renderSavedReportsPage();

    await screen.findByText('Saved 11');
    expect(vi.mocked(fetchSavedReports).mock.calls[0]![0]).toEqual(
      expect.objectContaining({ start: 10 }),
    );
  });

  // Back to Reports

  test('Back to Reports opens the report page in the app', async () => {
    const user = userEvent.setup({ delay: null });
    renderSavedReportsPage();

    await clickMenuAction(user, mockSavedReport.saveAs, /^back to reports$/i);

    expect(mockNavigate).toHaveBeenCalledWith('/reporting/proof-of-play');
    expect(assign).not.toHaveBeenCalled();
  });

  // Custom reports have no app page; fall back to the classic form.
  test('Back to Reports for an unknown type falls back to the classic page', async () => {
    const user = userEvent.setup({ delay: null });
    mockFetchSavedReports({ rows: [UNKNOWN_TYPE_SAVED_REPORT], totalCount: 1 });
    renderSavedReportsPage();

    await clickMenuAction(user, UNKNOWN_TYPE_SAVED_REPORT.saveAs, /^back to reports$/i);

    expect(assign).toHaveBeenCalledWith('/cms/report/form/customReport');
    expect(mockNavigate).not.toHaveBeenCalled();
  });

  // Go to Schedule

  test('Go to Schedule saves a filter for that schedule, then opens Report Schedules', async () => {
    const user = userEvent.setup({ delay: null });
    testQueryClient.setQueryData(queryKeys.reportSchedulesPage, null);
    const invalidate = vi.spyOn(testQueryClient, 'invalidateQueries');
    renderSavedReportsPage();

    await clickMenuAction(user, mockSavedReport.saveAs, /^go to schedule$/i);

    await waitFor(() => expect(mockNavigate).toHaveBeenCalledWith('/reporting/report-schedules'));
    const { option, value } = savedSchedulePref();
    expect(option).toBe('report_schedules_page');
    expect(value.filterInputs).toEqual(
      expect.objectContaining({ reportScheduleId: mockSavedReport.reportScheduleId }),
    );
    expect(value.filterInputs).toEqual({
      ...SCHEDULE_INITIAL_FILTER_STATE,
      reportScheduleId: mockSavedReport.reportScheduleId,
    });
    // Must invalidate, and save before navigating.
    expect(invalidate).toHaveBeenCalledWith({ queryKey: queryKeys.reportSchedulesPage });
    expect(vi.mocked(saveUserPreference).mock.invocationCallOrder[0]!).toBeLessThan(
      mockNavigate.mock.invocationCallOrder[0]!,
    );
  });

  // Export as PDF

  test('Export as PDF goes to the export address', async () => {
    const user = userEvent.setup({ delay: null });
    renderSavedReportsPage();

    await clickMenuAction(user, mockSavedReport.saveAs, /^export as pdf$/i);

    expect(window.location.href).toBe(
      `/json/report/savedreport/${mockSavedReport.savedReportId}/report/${mockSavedReport.reportName}/export`,
    );
  });

  // Known issues, quarantined until fixed.

  // Known issue 1: xibosignage/xibo#4088
  describe('Pinned until https://github.com/xibosignage/xibo/issues/4088 is fixed', () => {
    knownFailure(
      'Export as PDF respects the CMS install folder',
      async () => {
        const user = userEvent.setup({ delay: null });
        renderSavedReportsPage();
        await clickMenuAction(user, mockSavedReport.saveAs, /^export as pdf$/i);
        expect(window.location.href).toContain('/report/savedreport/');
      },
      () => expect(window.location.href.startsWith('/cms/')).toBe(true),
    );
  });

  // Known issue 2: xibosignage/xibo#4089
  describe('Pinned until https://github.com/xibosignage/xibo/issues/4089 is fixed', () => {
    knownFailure(
      "Go to Schedule keeps the user's sorting and column choices on Report Schedules",
      async () => {
        const user = userEvent.setup({ delay: null });
        renderSavedReportsPage();
        await clickMenuAction(user, mockSavedReport.saveAs, /^go to schedule$/i);
        await waitFor(() => expect(mockNavigate).toHaveBeenCalled());
        return savedSchedulePref().value;
      },
      (value) => expect(Object.keys(value)).toEqual(expect.arrayContaining(['sorting'])),
    );
  });

  // Known issue 3: xibosignage/xibo#4090
  describe('Pinned until https://github.com/xibosignage/xibo/issues/4090 is fixed', () => {
    // The failed save is an unhandled rejection; capture it so it can't fail the whole run.
    let rejections: ReturnType<typeof captureUnhandledRejections> | undefined;
    afterEach(() => {
      rejections?.restore();
      rejections = undefined;
    });

    knownFailure(
      'Go to Schedule tells the user when saving the filter fails',
      async () => {
        rejections?.restore();
        rejections = captureUnhandledRejections();
        const user = userEvent.setup({ delay: null });
        vi.mocked(saveUserPreference).mockRejectedValueOnce(new Error('Network Error'));
        renderSavedReportsPage();
        await clickMenuAction(user, mockSavedReport.saveAs, /^go to schedule$/i);
        // Control: the save failed and nothing navigated. Once #4090 is fixed the rejection is
        // handled, so this goes red too: that means "fixed", not "setup broke".
        await waitFor(() => expect(rejections!.reasons).toHaveLength(1));
        expect(mockNavigate).not.toHaveBeenCalled();
      },
      () => expect(notify.error).toHaveBeenCalled(),
    );
  });

  // Known issue 4: xibosignage/xibo#4091
  describe('Pinned until https://github.com/xibosignage/xibo/issues/4091 is fixed', () => {
    knownFailure(
      'a corrupted remembered page falls back to the first page',
      async () => {
        sessionStorage.setItem(PAGE_INDEX_STORAGE_KEY, 'abc');
        renderSavedReportsPage();
        await waitFor(() => expect(fetchSavedReports).toHaveBeenCalled());
        return lastSavedReportRequest();
      },
      (request) => expect(request.start).toBe(0),
    );
  });
});
