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

import type { FetchReportSchedulesResponse } from '@/services/reportScheduleApi';
import type { ReportSchedule } from '@/types/reportSchedule';
import type { User } from '@/types/user';

// Server sends the description in reportName and the internal name in reportNameId.
export const buildReportSchedule = (overrides: Partial<ReportSchedule> = {}): ReportSchedule => ({
  reportScheduleId: 1,
  name: 'Weekly PoP',
  reportName: mockReport.description,
  reportNameId: mockReport.name,
  schedule: '',
  lastRunDt: 0,
  nextRunDt: 0,
  previousRunDt: 0,
  fromDt: 0,
  toDt: 0,
  userId: 1,
  owner: 'TestUser',
  isActive: 1,
  message: null,
  createdDt: 1767225600, // 2026-01-01 00:00:00 UTC
  lastSavedReportId: 0,
  ...overrides,
});

export const mockReportSchedule = buildReportSchedule();

export const SINGLE_REPORT_SCHEDULE: FetchReportSchedulesResponse = {
  rows: [mockReportSchedule],
  totalCount: 1,
};

export const EMPTY_REPORT_SCHEDULE_TABLE: FetchReportSchedulesResponse = {
  rows: [],
  totalCount: 0,
};

// Drives the Resume label and "Schedule resumed successfully".
export const PAUSED_SCHEDULE = buildReportSchedule({ isActive: 0 });

// Has produced a saved report, so Open Last Report and Delete All Saved Reports appear.
export const SCHEDULE_WITH_SAVED = buildReportSchedule({ lastSavedReportId: 55 });

// Not in REPORT_META, so Back to Reports falls back to the classic report page.
export const UNKNOWN_REPORT_SCHEDULE = buildReportSchedule({
  reportName: 'Custom Report',
  reportNameId: 'customReport',
});

// Three rows for bulk delete and partial-failure tests (fail the middle one).
export const MULTIPLE_REPORT_SCHEDULES: FetchReportSchedulesResponse = {
  rows: [
    buildReportSchedule({ reportScheduleId: 1, name: 'Schedule Alpha' }),
    buildReportSchedule({ reportScheduleId: 2, name: 'Schedule Beta' }),
    buildReportSchedule({ reportScheduleId: 3, name: 'Schedule Gamma' }),
  ],
  totalCount: 3,
};

// Two pages (10 + 2) so pagination and cross-page selection can be tested.
export const PAGED_SCHEDULES_P1: FetchReportSchedulesResponse = {
  rows: Array.from({ length: 10 }, (_, i) =>
    buildReportSchedule({ reportScheduleId: i + 1, name: `Schedule ${i + 1}` }),
  ),
  totalCount: 12,
};

export const PAGED_SCHEDULES_P2: FetchReportSchedulesResponse = {
  rows: [
    buildReportSchedule({ reportScheduleId: 11, name: 'Schedule 11' }),
    buildReportSchedule({ reportScheduleId: 12, name: 'Schedule 12' }),
  ],
  totalCount: 12,
};

// Super admin with the three Reporting tab features, so the tab bar renders.
export const mockUser: User = {
  userId: 1,
  userName: 'TestUser',
  userTypeId: 1,
  groupId: 1,
  features: {
    'report.view': true,
    'report.scheduling': true,
    'report.saving': true,
  },
  settings: {
    defaultTimezone: 'UTC',
    defaultLanguage: 'en',
    DATE_FORMAT_JS: 'YYYY-MM-DD',
    TIME_FORMAT_JS: 'HH:mm',
  },
};

// Query keys that mirror what the page builds internally.
export const queryKeys = {
  reportSchedulePage: ['userPref', 'report_schedules_page'] as const,
  reportScheduleAll: ['reportSchedule'] as const,
  savedReportAll: ['savedReport'] as const,
};

// Shared with the other Reporting suites.
export { apiError } from '../../../__tests__/fixtures/apiError';
