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

import { screen } from '@testing-library/react';
import userEvent, { type UserEvent } from '@testing-library/user-event';
import { vi, beforeEach, describe, test, expect } from 'vitest';

import { apiError, mockReportSchedule, SINGLE_REPORT_SCHEDULE } from './fixtures/reportSchedule';
import { renderReportSchedulesPage } from './helpers/renderReportSchedulesPage';
import { mockFetchReportSchedules } from './mocks/reportScheduleApi';

import { notify } from '@/components/ui/Notification';
import { resetReportSchedule } from '@/services/reportScheduleApi';
import { testQueryClient } from '@/setupTests';
import { deferred } from '@/testUtils/deferred';
import { clickMenuAction } from '@/testUtils/rowActions';
import { waitForDialogToClose } from '@/testUtils/rtl';
import type { ReportSchedule } from '@/types/reportSchedule';

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

const HEADING = 'Reset to Previous Run?';

const openReset = async (user: UserEvent) => {
  await clickMenuAction(user, mockReportSchedule.name, /^reset to previous run$/i);
  return screen.findByRole('heading', { name: HEADING });
};

// =============================================================================
// Tests
// =============================================================================

describe('Report Schedules page - reset to previous run', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    // clearAllMocks keeps earlier implementations, so reset this one.
    vi.mocked(resetReportSchedule)
      .mockReset()
      .mockResolvedValue({} as unknown as ReportSchedule);
    mockFetchReportSchedules(SINGLE_REPORT_SCHEDULE);
  });

  test('the confirmation names the schedule', async () => {
    const user = userEvent.setup({ delay: null });
    renderReportSchedulesPage();

    await openReset(user);

    expect(screen.getByRole('dialog')).toHaveTextContent(mockReportSchedule.name);
  });

  test('Cancel closes without resetting', async () => {
    const user = userEvent.setup({ delay: null });
    renderReportSchedulesPage();
    await openReset(user);

    await user.click(screen.getByRole('button', { name: /^cancel$/i }));

    await waitForDialogToClose(HEADING);
    expect(resetReportSchedule).not.toHaveBeenCalled();
  });

  test('confirming resets that schedule and confirms success', async () => {
    const user = userEvent.setup({ delay: null });
    renderReportSchedulesPage();
    await openReset(user);

    await user.click(screen.getByRole('button', { name: 'Yes, Reset' }));

    await waitForDialogToClose(HEADING);
    expect(resetReportSchedule).toHaveBeenCalledWith(mockReportSchedule.reportScheduleId);
    expect(notify.success).toHaveBeenCalledWith('Schedule reset successfully');
  });

  test('while working the button reads Resetting…', async () => {
    const user = userEvent.setup({ delay: null });
    const pending = deferred<ReportSchedule>();
    vi.mocked(resetReportSchedule).mockReturnValueOnce(pending.promise);
    renderReportSchedulesPage();
    await openReset(user);

    await user.click(screen.getByRole('button', { name: 'Yes, Reset' }));

    expect(await screen.findByRole('button', { name: 'Resetting…' })).toBeDisabled();
    pending.resolve({} as unknown as ReportSchedule);
    await waitForDialogToClose(HEADING);
  });

  test('a rejection shows the server message and the confirmation stays open', async () => {
    const user = userEvent.setup({ delay: null });
    vi.mocked(resetReportSchedule).mockRejectedValueOnce(apiError('Nothing to reset'));
    renderReportSchedulesPage();
    await openReset(user);

    await user.click(screen.getByRole('button', { name: 'Yes, Reset' }));

    expect(await screen.findByRole('alert')).toHaveTextContent('Nothing to reset');
    expect(resetReportSchedule).toHaveBeenCalledWith(mockReportSchedule.reportScheduleId);
    expect(screen.getByRole('heading', { name: HEADING })).toBeInTheDocument();
    expect(notify.success).not.toHaveBeenCalled();
  });
});
