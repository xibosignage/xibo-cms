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
// The page with the real Edit modal. ReportSchedules.edit.test.tsx stubs the
// modal to cover the page wiring; this file drives the whole edit end to end.
// =============================================================================

import { screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { vi, afterAll, beforeAll, beforeEach, describe, test, expect } from 'vitest';

import { buildReportSchedule } from './fixtures/reportSchedule';
import { renderReportSchedulesPage } from './helpers/renderReportSchedulesPage';
import { mockFetchReportSchedules } from './mocks/reportScheduleApi';

import { updateReportSchedule } from '@/services/reportScheduleApi';
import { testQueryClient } from '@/setupTests';
import { escapeRegExp } from '@/testUtils/escapeRegExp';
import { findRow, getRow, openViaQuickAction } from '@/testUtils/rowActions';
import { setTimezone } from '@/testUtils/timezone';
import type { ReportSchedule } from '@/types/reportSchedule';

// =============================================================================
// Module mocks
// =============================================================================

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
// Fixtures
// =============================================================================

const JAN_1_2027 = 1798761600; // 2027-01-01 00:00:00 UTC
const FEB_1_2027 = 1801440000; // 2027-02-01 00:00:00 UTC

// Future dates, so the server would accept them being sent back unchanged.
const datedSchedule = buildReportSchedule({
  reportScheduleId: 42,
  name: 'Weekly PoP',
  fromDt: JAN_1_2027,
  toDt: FEB_1_2027,
});
const otherSchedule = buildReportSchedule({ reportScheduleId: 43, name: 'Daily Alerts' });

const listOf = (rows: ReportSchedule[]) => ({ rows, totalCount: rows.length });

const originalTz = process.env.TZ;

// =============================================================================
// Tests
// =============================================================================

describe('Report Schedules page - edit with the real modal', () => {
  // Browser and CMS both in UTC: the zone mismatch is a separate, pinned issue
  // (modals/EditReportScheduleModal.test.tsx, xibosignage/xibo#4080).
  beforeAll(() => {
    setTimezone('UTC');
  });
  afterAll(() => {
    setTimezone(originalTz);
  });
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    mockFetchReportSchedules(listOf([datedSchedule, otherSchedule]));
  });

  test(
    'renaming a schedule from its row sends the new name with its dates unchanged and refreshes the list',
    { tags: ['smoke'] },
    async () => {
      const user = userEvent.setup({ delay: null });
      renderReportSchedulesPage();

      const dialog = await openViaQuickAction(
        user,
        datedSchedule.name,
        /^edit$/i,
        'Edit Report Schedule',
      );
      const nameInput = within(dialog).getByRole('textbox', { name: 'Schedule Name' });
      expect(nameInput).toHaveValue('Weekly PoP');

      // Once saved, the refreshed list carries the new name.
      mockFetchReportSchedules(listOf([{ ...datedSchedule, name: 'Monthly PoP' }, otherSchedule]));

      await user.clear(nameInput);
      await user.type(nameInput, 'Monthly PoP');
      await user.click(within(dialog).getByRole('button', { name: 'Save' }));

      await waitFor(() =>
        expect(updateReportSchedule).toHaveBeenCalledWith(42, {
          name: 'Monthly PoP',
          fromDt: '2027-01-01 00:00:00',
          toDt: '2027-02-01 00:00:00',
        }),
      );
      expect(updateReportSchedule).toHaveBeenCalledTimes(1);

      await waitFor(() => expect(dialog).not.toBeInTheDocument());
      await findRow('Monthly PoP');
      expect(
        screen.queryByRole('row', { name: new RegExp(escapeRegExp('Weekly PoP')) }),
      ).not.toBeInTheDocument();
      // The other schedule is untouched.
      expect(getRow(otherSchedule.name)).toBeInTheDocument();
    },
  );
});
