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
import userEvent from '@testing-library/user-event';
import { vi, beforeEach, describe, test, expect } from 'vitest';

import {
  apiError,
  mockReportSchedule,
  PAUSED_SCHEDULE,
  SINGLE_REPORT_SCHEDULE,
} from './fixtures/reportSchedule';
import { renderReportSchedulesPage } from './helpers/renderReportSchedulesPage';
import { mockFetchReportSchedules } from './mocks/reportScheduleApi';

import { notify } from '@/components/ui/Notification';
import { toggleActiveReportSchedule } from '@/services/reportScheduleApi';
import { testQueryClient } from '@/setupTests';
import { deferred } from '@/testUtils/deferred';
import { openViaMenu, openViaQuickAction } from '@/testUtils/rowActions';
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
// Tests
// =============================================================================

const PAUSE_HEADING = 'Pause Schedule?';
const RESUME_HEADING = 'Resume Schedule?';

describe('Report Schedules page - pause and resume', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    // clearAllMocks keeps earlier implementations, so reset this one.
    vi.mocked(toggleActiveReportSchedule)
      .mockReset()
      .mockResolvedValue({} as unknown as ReportSchedule);
    mockFetchReportSchedules(SINGLE_REPORT_SCHEDULE);
  });

  test('an active schedule asks to pause', async () => {
    const user = userEvent.setup({ delay: null });
    renderReportSchedulesPage();

    const dialog = await openViaQuickAction(user, mockReportSchedule.name, /^pause$/i);
    expect(dialog).toHaveTextContent(PAUSE_HEADING);
    expect(screen.getByRole('button', { name: 'Yes, Pause' })).toBeEnabled();
  });

  test('a paused schedule asks to resume', async () => {
    const user = userEvent.setup({ delay: null });
    mockFetchReportSchedules({ rows: [PAUSED_SCHEDULE], totalCount: 1 });
    renderReportSchedulesPage();

    const dialog = await openViaQuickAction(user, PAUSED_SCHEDULE.name, /^resume$/i);
    expect(dialog).toHaveTextContent(RESUME_HEADING);
    expect(screen.getByRole('button', { name: 'Yes, Resume' })).toBeEnabled();
  });

  test('the menu item opens the same confirmation', async () => {
    const user = userEvent.setup({ delay: null });
    renderReportSchedulesPage();

    const dialog = await openViaMenu(user, mockReportSchedule.name, /^pause$/i);
    expect(dialog).toHaveTextContent(PAUSE_HEADING);
    expect(screen.getByRole('button', { name: 'Yes, Pause' })).toBeEnabled();
  });

  test(
    'confirming pause sends the change for that schedule and confirms success',
    { tags: ['smoke'] },
    async () => {
      const user = userEvent.setup({ delay: null });
      renderReportSchedulesPage();
      const dialog = await openViaQuickAction(user, mockReportSchedule.name, /^pause$/i);
      expect(dialog).toHaveTextContent(PAUSE_HEADING);

      await user.click(screen.getByRole('button', { name: 'Yes, Pause' }));

      await waitForDialogToClose(PAUSE_HEADING);
      expect(toggleActiveReportSchedule).toHaveBeenCalledWith(mockReportSchedule.reportScheduleId);
      expect(notify.success).toHaveBeenCalledWith('Schedule paused successfully');
    },
  );

  // Wording comes from the state before the toggle.
  test('confirming resume confirms with the resumed wording', async () => {
    const user = userEvent.setup({ delay: null });
    mockFetchReportSchedules({ rows: [PAUSED_SCHEDULE], totalCount: 1 });
    renderReportSchedulesPage();
    const dialog = await openViaQuickAction(user, PAUSED_SCHEDULE.name, /^resume$/i);
    expect(dialog).toHaveTextContent(RESUME_HEADING);

    await user.click(screen.getByRole('button', { name: 'Yes, Resume' }));

    await waitForDialogToClose(RESUME_HEADING);
    expect(notify.success).toHaveBeenCalledWith('Schedule resumed successfully');
  });

  test('while working the button reads Pausing…', async () => {
    const user = userEvent.setup({ delay: null });
    const pending = deferred<ReportSchedule>();
    vi.mocked(toggleActiveReportSchedule).mockReturnValueOnce(pending.promise);
    renderReportSchedulesPage();
    const dialog = await openViaQuickAction(user, mockReportSchedule.name, /^pause$/i);
    expect(dialog).toHaveTextContent(PAUSE_HEADING);

    await user.click(screen.getByRole('button', { name: 'Yes, Pause' }));

    expect(await screen.findByRole('button', { name: 'Pausing…' })).toBeDisabled();
    pending.resolve({} as unknown as ReportSchedule);
    await waitForDialogToClose(PAUSE_HEADING);
  });

  test('a rejection shows the server message and the confirmation stays open', async () => {
    const user = userEvent.setup({ delay: null });
    vi.mocked(toggleActiveReportSchedule).mockRejectedValueOnce(apiError('Schedule is locked'));
    renderReportSchedulesPage();
    const dialog = await openViaQuickAction(user, mockReportSchedule.name, /^pause$/i);
    expect(dialog).toHaveTextContent(PAUSE_HEADING);

    await user.click(screen.getByRole('button', { name: 'Yes, Pause' }));

    expect(await screen.findByRole('alert')).toHaveTextContent('Schedule is locked');
    expect(toggleActiveReportSchedule).toHaveBeenCalledWith(mockReportSchedule.reportScheduleId);
    expect(screen.getByRole('heading', { name: PAUSE_HEADING })).toBeInTheDocument();
    expect(notify.success).not.toHaveBeenCalled();
  });
});
