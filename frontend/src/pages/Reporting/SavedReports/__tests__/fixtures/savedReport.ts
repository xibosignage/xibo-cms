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

import { mockReport } from '../../../__tests__/fixtures/report';

import type { FetchSavedReportsResponse } from '@/services/savedReportApi';
import { buildCurrentUser, PERSONAS } from '@/testUtils/personas';
import type { SavedReport } from '@/types/savedReport';
import type { User } from '@/types/user';

// reportName is the internal name here (unlike report schedules); URLs use it.
export const buildSavedReport = (overrides: Partial<SavedReport> = {}): SavedReport => ({
  savedReportId: 10,
  saveAs: 'Weekly PoP 2027-01-04',
  reportScheduleId: 1,
  reportScheduleName: 'Weekly PoP',
  reportName: mockReport.name,
  generatedOn: 1798761600, // 2027-01-01 00:00:00 UTC
  owner: 'TestUser',
  userId: 1,
  ...overrides,
});

export const mockSavedReport = buildSavedReport();

export const SINGLE_SAVED_REPORT: FetchSavedReportsResponse = {
  rows: [mockSavedReport],
  totalCount: 1,
};

export const EMPTY_SAVED_REPORT_TABLE: FetchSavedReportsResponse = {
  rows: [],
  totalCount: 0,
};

// Not in REPORT_META, so Back to Reports falls back to the classic report page.
export const UNKNOWN_TYPE_SAVED_REPORT = buildSavedReport({ reportName: 'customReport' });

export const MULTIPLE_SAVED_REPORTS: FetchSavedReportsResponse = {
  rows: [
    buildSavedReport({ savedReportId: 10, saveAs: 'Saved Alpha' }),
    buildSavedReport({ savedReportId: 11, saveAs: 'Saved Beta' }),
    buildSavedReport({ savedReportId: 12, saveAs: 'Saved Gamma' }),
  ],
  totalCount: 3,
};

// Two pages (10 + 2) for paging and cross-page selection.
export const PAGED_SAVED_REPORTS_P1: FetchSavedReportsResponse = {
  rows: Array.from({ length: 10 }, (_, i) =>
    buildSavedReport({ savedReportId: 100 + i, saveAs: `Saved ${i + 1}` }),
  ),
  totalCount: 12,
};

export const PAGED_SAVED_REPORTS_P2: FetchSavedReportsResponse = {
  rows: [
    buildSavedReport({ savedReportId: 110, saveAs: 'Saved 11' }),
    buildSavedReport({ savedReportId: 111, saveAs: 'Saved 12' }),
  ],
  totalCount: 12,
};

// Super admin matching the owner above, in UTC.
export const mockUser: User = buildCurrentUser(PERSONAS.superAdmin, {
  userId: 1,
  userName: 'TestUser',
  groupId: 1,
  settings: {
    defaultTimezone: 'UTC',
    defaultLanguage: 'en',
    DATE_FORMAT_JS: 'YYYY-MM-DD',
    TIME_FORMAT_JS: 'HH:mm',
  },
});

// Mirrors the page's query keys.
export const queryKeys = {
  savedReportsPage: ['userPref', 'saved_reports_page'] as const,
  reportSchedulesPage: ['userPref', 'report_schedules_page'] as const,
  savedReportAll: ['savedReport'] as const,
};

export const PAGE_INDEX_STORAGE_KEY = 'savedReports_pageIndex';

export { apiError } from '../../../__tests__/fixtures/apiError';
