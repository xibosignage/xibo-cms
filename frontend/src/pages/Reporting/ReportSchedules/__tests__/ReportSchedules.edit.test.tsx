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
// Modal is stubbed; the form is covered in modals/EditReportScheduleModal.test.tsx.
// =============================================================================

import { screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { vi, afterEach, beforeEach, describe, test, expect } from 'vitest';

import {
  buildReportSchedule,
  mockReportSchedule,
  SINGLE_REPORT_SCHEDULE,
} from './fixtures/reportSchedule';
import { renderReportSchedulesPage } from './helpers/renderReportSchedulesPage';
import { mockFetchReportSchedules } from './mocks/reportScheduleApi';

import { fetchReportSchedules } from '@/services/reportScheduleApi';
import { testQueryClient } from '@/setupTests';
import { openViaMenu, openViaQuickAction } from '@/testUtils/rowActions';
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

vi.mock('../components/EditReportScheduleModal', () => ({
  default: ({
    schedule,
    onSuccess,
    onClose,
  }: {
    schedule: ReportSchedule | null;
    onSuccess: () => void;
    onClose: () => void;
  }) => (
    <div role="dialog" aria-label="Edit Report Schedule">
      <span>Editing: {schedule?.name}</span>
      <button
        onClick={() => {
          onSuccess();
          onClose();
        }}
      >
        Stub save
      </button>
      <button onClick={onClose}>Stub close</button>
    </div>
  ),
}));

// =============================================================================
// Tests
// =============================================================================

describe('Report Schedules page - edit', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    mockFetchReportSchedules(SINGLE_REPORT_SCHEDULE);
  });

  afterEach(() => {
    vi.restoreAllMocks();
  });

  test('the Edit icon opens the modal for that schedule', async () => {
    const user = userEvent.setup({ delay: null });
    renderReportSchedulesPage();

    const dialog = await openViaQuickAction(
      user,
      mockReportSchedule.name,
      /^edit$/i,
      'Edit Report Schedule',
    );
    expect(dialog).toHaveTextContent('Editing: Weekly PoP');
  });

  test('the Edit menu item opens the modal for that schedule', async () => {
    const user = userEvent.setup({ delay: null });
    renderReportSchedulesPage();

    const dialog = await openViaMenu(
      user,
      mockReportSchedule.name,
      /^edit$/i,
      'Edit Report Schedule',
    );
    expect(dialog).toHaveTextContent('Editing: Weekly PoP');
  });

  test('saving refreshes the schedule list', async () => {
    const user = userEvent.setup({ delay: null });
    renderReportSchedulesPage();

    await openViaQuickAction(user, mockReportSchedule.name, /^edit$/i, 'Edit Report Schedule');
    vi.mocked(fetchReportSchedules).mockResolvedValue({
      rows: [buildReportSchedule({ name: 'Monthly PoP' })],
      totalCount: 1,
    });
    await user.click(screen.getByRole('button', { name: 'Stub save' }));

    await screen.findByText('Monthly PoP');
    expect(screen.queryByText(mockReportSchedule.name)).not.toBeInTheDocument();
    expect(screen.queryByRole('dialog', { name: 'Edit Report Schedule' })).not.toBeInTheDocument();
  });

  test('closing without saving does not refresh the list', async () => {
    const user = userEvent.setup({ delay: null });
    renderReportSchedulesPage();

    await openViaQuickAction(user, mockReportSchedule.name, /^edit$/i, 'Edit Report Schedule');
    // Spy on invalidation; waiting for a refetch that never happens is unreliable.
    const invalidate = vi.spyOn(testQueryClient, 'invalidateQueries');

    await user.click(screen.getByRole('button', { name: 'Stub close' }));

    await waitFor(() =>
      expect(
        screen.queryByRole('dialog', { name: 'Edit Report Schedule' }),
      ).not.toBeInTheDocument(),
    );
    expect(invalidate).not.toHaveBeenCalled();
    expect(screen.getByText(mockReportSchedule.name)).toBeInTheDocument();
  });
});
