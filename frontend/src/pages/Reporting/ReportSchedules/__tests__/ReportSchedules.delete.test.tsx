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
// Confirmation has no title, so it is found by heading text (xibosignage/xibo#4039).
// =============================================================================

import { screen, waitFor } from '@testing-library/react';
import userEvent, { type UserEvent } from '@testing-library/user-event';
import { vi, afterEach, beforeEach, describe, test, expect } from 'vitest';

import {
  apiError,
  EMPTY_REPORT_SCHEDULE_TABLE,
  MULTIPLE_REPORT_SCHEDULES,
  mockReportSchedule,
  PAGED_SCHEDULES_P1,
  PAGED_SCHEDULES_P2,
  queryKeys,
  SINGLE_REPORT_SCHEDULE,
} from './fixtures/reportSchedule';
import { renderReportSchedulesPage } from './helpers/renderReportSchedulesPage';
import { mockFetchReportSchedulePages, mockFetchReportSchedules } from './mocks/reportScheduleApi';

import { notify } from '@/components/ui/Notification';
import { deleteReportSchedule } from '@/services/reportScheduleApi';
import { testQueryClient } from '@/setupTests';
import { deferred } from '@/testUtils/deferred';
import { clickMenuAction, selectAllRows, selectRow } from '@/testUtils/rowActions';
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

const SINGLE_HEADING = 'Delete Report Schedule?';
const BULK_HEADING = 'Delete Report Schedules?';

const openRowDelete = async (user: UserEvent, rowName = mockReportSchedule.name) => {
  await clickMenuAction(user, rowName, /^delete$/i);
  return screen.findByRole('heading', { name: SINGLE_HEADING });
};

const openBulkDelete = async (user: UserEvent) => {
  await screen.findByText('Schedule Alpha');
  await selectAllRows(user);
  await user.click(await screen.findByRole('button', { name: /delete selected/i }));
  return screen.findByRole('heading', { name: BULK_HEADING });
};

const deletedIds = () => vi.mocked(deleteReportSchedule).mock.calls.map(([id]) => id);

// =============================================================================
// Tests — single delete
// =============================================================================

describe('Report Schedules page - single delete', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    // clearAllMocks keeps earlier implementations, so reset this one.
    vi.mocked(deleteReportSchedule).mockReset().mockResolvedValue(undefined);
    mockFetchReportSchedules(SINGLE_REPORT_SCHEDULE);
  });

  test('the row Delete opens a confirmation naming the schedule', async () => {
    const user = userEvent.setup({ delay: null });
    renderReportSchedulesPage();

    await openRowDelete(user);

    expect(screen.getByRole('dialog')).toHaveTextContent(mockReportSchedule.name);
  });

  test('Cancel closes the confirmation without deleting', async () => {
    const user = userEvent.setup({ delay: null });
    renderReportSchedulesPage();
    await openRowDelete(user);

    await user.click(screen.getByRole('button', { name: /^cancel$/i }));

    await waitForDialogToClose(SINGLE_HEADING);
    expect(deleteReportSchedule).not.toHaveBeenCalled();
  });

  test('confirming deletes the schedule, shows a success message and refreshes', async () => {
    const user = userEvent.setup({ delay: null });
    renderReportSchedulesPage();
    await openRowDelete(user);
    mockFetchReportSchedules(EMPTY_REPORT_SCHEDULE_TABLE);

    await user.click(screen.getByRole('button', { name: /yes, delete/i }));

    await waitForDialogToClose(SINGLE_HEADING);
    expect(deleteReportSchedule).toHaveBeenCalledWith(mockReportSchedule.reportScheduleId);
    expect(notify.success).toHaveBeenCalledWith('1 report schedule(s) deleted successfully.');
    await screen.findByText('No results found.');
  });

  test('while deleting the button reads Deleting… and a second click does nothing', async () => {
    const user = userEvent.setup({ delay: null });
    const pending = deferred<void>();
    vi.mocked(deleteReportSchedule).mockReturnValueOnce(pending.promise);
    renderReportSchedulesPage();
    await openRowDelete(user);

    await user.click(screen.getByRole('button', { name: /yes, delete/i }));

    const busy = await screen.findByRole('button', { name: 'Deleting…' });
    expect(busy).toBeDisabled();
    await user.click(busy);
    expect(deleteReportSchedule).toHaveBeenCalledTimes(1);

    pending.resolve();
    await waitForDialogToClose(SINGLE_HEADING);
  });
});

// =============================================================================
// Tests — bulk delete
// =============================================================================

describe('Report Schedules page - bulk delete', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    vi.mocked(deleteReportSchedule).mockReset().mockResolvedValue(undefined);
    mockFetchReportSchedules(MULTIPLE_REPORT_SCHEDULES);
  });

  afterEach(() => {
    vi.restoreAllMocks();
  });

  test('bulk delete says how many and sends every selected id', async () => {
    const user = userEvent.setup({ delay: null });
    renderReportSchedulesPage();

    await openBulkDelete(user);
    expect(screen.getByRole('dialog')).toHaveTextContent(
      'Are you sure you want to delete 3 report schedules?',
    );
    await user.click(screen.getByRole('button', { name: /yes, delete/i }));

    await waitForDialogToClose(BULK_HEADING);
    expect(deletedIds()).toEqual(expect.arrayContaining([1, 2, 3]));
    expect(deletedIds()).toHaveLength(3);
  });

  test('schedules selected on another page are still deleted', async () => {
    const user = userEvent.setup({ delay: null });
    mockFetchReportSchedulePages(PAGED_SCHEDULES_P1, PAGED_SCHEDULES_P2);
    renderReportSchedulesPage();

    await selectRow(user, 'Schedule 1');
    await user.click(screen.getByRole('button', { name: /next/i }));
    await selectRow(user, 'Schedule 11');
    await user.click(await screen.findByRole('button', { name: /delete selected/i }));
    await screen.findByRole('heading', { name: BULK_HEADING });
    await user.click(screen.getByRole('button', { name: /yes, delete/i }));

    await waitFor(() => expect(deletedIds()).toEqual(expect.arrayContaining([1, 11])));
    expect(deletedIds()).toHaveLength(2);
  }, 30_000); // ~7.5s alone; timed out at 15s under the full suite

  test('a partial failure keeps the confirmation open and explains what happened', async () => {
    const user = userEvent.setup({ delay: null });
    // Deletes go out in row order, so the second call is schedule 2.
    vi.mocked(deleteReportSchedule)
      .mockResolvedValueOnce(undefined)
      .mockRejectedValueOnce(apiError('Locked by another user', 500))
      .mockResolvedValueOnce(undefined);
    renderReportSchedulesPage();
    await openBulkDelete(user);
    const invalidate = vi.spyOn(testQueryClient, 'invalidateQueries');

    await user.click(screen.getByRole('button', { name: /yes, delete/i }));

    const alert = await screen.findByRole('alert');
    // Selection clears and the list refreshes even though the dialog stays open.
    await waitFor(() =>
      expect(screen.queryByRole('button', { name: /delete selected/i })).not.toBeInTheDocument(),
    );
    expect(invalidate).toHaveBeenCalledWith({ queryKey: queryKeys.reportScheduleAll });
    expect(alert).toHaveTextContent('2 report schedule(s) deleted successfully.');
    expect(alert).toHaveTextContent('Locked by another user');
    expect(screen.getByRole('heading', { name: BULK_HEADING })).toBeInTheDocument();
    expect(deletedIds()).toEqual(expect.arrayContaining([1, 2, 3]));
    expect(notify.success).not.toHaveBeenCalled();
  });

  test('a failure with no server message uses the count wording', async () => {
    const user = userEvent.setup({ delay: null });
    vi.mocked(deleteReportSchedule)
      .mockResolvedValueOnce(undefined)
      .mockRejectedValueOnce(new Error('network down'))
      .mockResolvedValueOnce(undefined);
    renderReportSchedulesPage();
    await openBulkDelete(user);

    await user.click(screen.getByRole('button', { name: /yes, delete/i }));

    expect(await screen.findByRole('alert')).toHaveTextContent(
      '1 report schedule(s) could not be deleted.',
    );
    expect(deletedIds()).toEqual(expect.arrayContaining([1, 2, 3]));
  });

  // A 404 means it's already gone, so it counts as deleted.
  test('an already-deleted schedule counts as deleted', async () => {
    const user = userEvent.setup({ delay: null });
    vi.mocked(deleteReportSchedule)
      .mockResolvedValueOnce(undefined)
      .mockRejectedValueOnce(apiError('Not found', 404))
      .mockResolvedValueOnce(undefined);
    renderReportSchedulesPage();
    await openBulkDelete(user);

    await user.click(screen.getByRole('button', { name: /yes, delete/i }));

    await waitForDialogToClose(BULK_HEADING);
    expect(notify.success).toHaveBeenCalledWith('3 report schedule(s) deleted successfully.');
  });
});
