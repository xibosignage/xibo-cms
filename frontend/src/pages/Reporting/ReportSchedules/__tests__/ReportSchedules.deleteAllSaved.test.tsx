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
// Dialog has no accessible name, so it is found by heading (xibosignage/xibo#4039).
// =============================================================================

import { screen, waitFor } from '@testing-library/react';
import userEvent, { type UserEvent } from '@testing-library/user-event';
import { vi, beforeEach, describe, test, expect } from 'vitest';

import { apiError, queryKeys, SCHEDULE_WITH_SAVED } from './fixtures/reportSchedule';
import { renderReportSchedulesPage } from './helpers/renderReportSchedulesPage';
import { mockFetchReportSchedules } from './mocks/reportScheduleApi';

import { notify } from '@/components/ui/Notification';
import { deleteAllSavedReportsForSchedule } from '@/services/reportScheduleApi';
import { testQueryClient } from '@/setupTests';
import { deferred } from '@/testUtils/deferred';
import { clickMenuAction } from '@/testUtils/rowActions';
import { waitForDialogToClose } from '@/testUtils/rtl';

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
// Helpers
// =============================================================================

const HEADING = 'Delete All Saved Reports?';
// A Saved Reports list query as that page would have cached it.
const SAVED_REPORTS_LIST_KEY = [
  ...queryKeys.savedReportAll,
  'list',
  { pageIndex: 0, pageSize: 10 },
];

const openDeleteAll = async (user: UserEvent) => {
  await clickMenuAction(user, SCHEDULE_WITH_SAVED.name, /^delete all saved reports$/i);
  return screen.findByRole('heading', { name: HEADING });
};

// =============================================================================
// Tests
// =============================================================================

describe('Report Schedules page - delete all saved reports', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    // clearAllMocks keeps earlier implementations, so reset this one.
    vi.mocked(deleteAllSavedReportsForSchedule).mockReset().mockResolvedValue(undefined);
    mockFetchReportSchedules({ rows: [SCHEDULE_WITH_SAVED], totalCount: 1 });
  });

  test('the confirmation names the schedule', async () => {
    const user = userEvent.setup({ delay: null });
    renderReportSchedulesPage();

    await openDeleteAll(user);

    expect(screen.getByRole('dialog')).toHaveTextContent(SCHEDULE_WITH_SAVED.name);
  });

  test('confirming deletes the saved reports and marks the Saved Reports list out of date', async () => {
    const user = userEvent.setup({ delay: null });
    testQueryClient.setQueryData(SAVED_REPORTS_LIST_KEY, { rows: [], totalCount: 0 });
    renderReportSchedulesPage();
    await openDeleteAll(user);
    expect(testQueryClient.getQueryState(SAVED_REPORTS_LIST_KEY)?.isInvalidated).toBe(false);

    await user.click(screen.getByRole('button', { name: 'Yes, Delete All' }));

    await waitForDialogToClose(HEADING);
    expect(deleteAllSavedReportsForSchedule).toHaveBeenCalledWith(
      SCHEDULE_WITH_SAVED.reportScheduleId,
    );
    expect(notify.success).toHaveBeenCalledWith('All saved reports deleted successfully');
    await waitFor(() =>
      expect(testQueryClient.getQueryState(SAVED_REPORTS_LIST_KEY)?.isInvalidated).toBe(true),
    );
  });

  test('while working the button reads Deleting…', async () => {
    const user = userEvent.setup({ delay: null });
    const pending = deferred<void>();
    vi.mocked(deleteAllSavedReportsForSchedule).mockReturnValueOnce(pending.promise);
    renderReportSchedulesPage();
    await openDeleteAll(user);

    await user.click(screen.getByRole('button', { name: 'Yes, Delete All' }));

    expect(await screen.findByRole('button', { name: 'Deleting…' })).toBeDisabled();
    pending.resolve();
    await waitForDialogToClose(HEADING);
  });

  test('a rejection shows the server message and the confirmation stays open', async () => {
    const user = userEvent.setup({ delay: null });
    vi.mocked(deleteAllSavedReportsForSchedule).mockRejectedValueOnce(
      apiError('Report files missing'),
    );
    renderReportSchedulesPage();
    await openDeleteAll(user);

    await user.click(screen.getByRole('button', { name: 'Yes, Delete All' }));

    expect(await screen.findByRole('alert')).toHaveTextContent('Report files missing');
    expect(deleteAllSavedReportsForSchedule).toHaveBeenCalledWith(
      SCHEDULE_WITH_SAVED.reportScheduleId,
    );
    expect(screen.getByRole('heading', { name: HEADING })).toBeInTheDocument();
    expect(notify.success).not.toHaveBeenCalled();
  });
});
