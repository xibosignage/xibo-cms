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

import { vi } from 'vitest';

import { REPORTS_BY_CATEGORY } from '../../../__tests__/fixtures/report';

import { fetchReports } from '@/services/reportApi';
import { fetchReportSchedules } from '@/services/reportScheduleApi';
import type { FetchReportSchedulesResponse } from '@/services/reportScheduleApi';
import type { ReportsByCategory } from '@/types/report';

// Requires vi.mock('@/services/reportScheduleApi').
export const mockFetchReportSchedules = (data: FetchReportSchedulesResponse) => {
  vi.mocked(fetchReportSchedules).mockResolvedValue(data);
};

// Picks the page from the request's offset, so a refetch of page 1 (after a delete, a search
// from page 2, a reload) still shows page 1. A deliberate exception to "no pagination rules in
// mocks": a call-order sequence would show page 2's rows there.
export const mockFetchReportSchedulePages = (
  page1: FetchReportSchedulesResponse,
  page2: FetchReportSchedulesResponse,
) => {
  vi.mocked(fetchReportSchedules).mockImplementation(async ({ start } = {}) =>
    start ? page2 : page1,
  );
};

export const lastScheduleRequest = () => {
  const calls = vi.mocked(fetchReportSchedules).mock.calls;
  return calls[calls.length - 1]![0]!;
};

// Requires vi.mock('@/services/reportApi').
export const mockFetchReports = (data: ReportsByCategory = REPORTS_BY_CATEGORY) => {
  vi.mocked(fetchReports).mockResolvedValue(data);
};
