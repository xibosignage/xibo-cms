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
// Active/Paused cells and Last Run format are covered by the table contract tests.
// =============================================================================

import { screen, waitFor, within } from '@testing-library/react';
import userEvent, { type UserEvent } from '@testing-library/user-event';
import { vi, beforeEach, describe, test, expect } from 'vitest';

import {
  buildReportSchedule,
  mockReportSchedule,
  PAGED_SCHEDULES_P1,
  PAGED_SCHEDULES_P2,
  PAUSED_SCHEDULE,
  SCHEDULE_WITH_SAVED,
  SINGLE_REPORT_SCHEDULE,
} from './fixtures/reportSchedule';
import { renderReportSchedulesPage } from './helpers/renderReportSchedulesPage';
import {
  lastScheduleRequest,
  mockFetchReportSchedulePages,
  mockFetchReportSchedules,
} from './mocks/reportScheduleApi';

import { testQueryClient } from '@/setupTests';
import { findRow, openRowMenu, queryMenuItems } from '@/testUtils/rowActions';

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

const openColumnPicker = async (user: UserEvent) => {
  await user.click(screen.getByRole('button', { name: /toggle columns/i }));
  return screen.findByRole('checkbox', { name: /^owner$/i });
};

const clickSort = async (user: UserEvent, header: RegExp) => {
  const th = screen.getByRole('columnheader', { name: header });
  await user.click(within(th).getByRole('button'));
};

// =============================================================================
// Part 1 — column picker
// =============================================================================

describe('Report Schedules page - column picker', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    mockFetchReportSchedules(SINGLE_REPORT_SCHEDULE);
  });

  // The actions column has no header text, so it never appears in the picker.
  test('Name cannot be hidden from the column picker; the other columns can', async () => {
    const user = userEvent.setup({ delay: null });
    renderReportSchedulesPage();
    await screen.findByText(mockReportSchedule.name);

    await openColumnPicker(user);

    expect(screen.queryByRole('checkbox', { name: /^name$/i })).not.toBeInTheDocument();
    for (const label of [
      /^id$/i,
      /^report name$/i,
      /^schedule$/i,
      /^owner$/i,
      /^last run$/i,
      /^next run$/i,
      /^failed message$/i,
      /^created$/i,
      /^active$/i,
    ]) {
      expect(screen.getByRole('checkbox', { name: label })).toBeInTheDocument();
    }
  });

  test('unticking a column in the picker removes it from the table', async () => {
    const user = userEvent.setup({ delay: null });
    renderReportSchedulesPage();
    await screen.findByRole('columnheader', { name: /^owner$/i });

    const ownerToggle = await openColumnPicker(user);
    await user.click(ownerToggle);

    await waitFor(() =>
      expect(screen.queryByRole('columnheader', { name: /^owner$/i })).not.toBeInTheDocument(),
    );
  });
});

// =============================================================================
// Part 2 — sorting
// =============================================================================

describe('Report Schedules grid - sorting', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
  });

  // Sortable columns per grid are checked in table contracts/sortFilter.test.tsx.
  test('clicking a column sort sends that column to the server', async () => {
    const user = userEvent.setup({ delay: null });
    mockFetchReportSchedules(SINGLE_REPORT_SCHEDULE);
    renderReportSchedulesPage();
    await screen.findByText(mockReportSchedule.name);

    await clickSort(user, /^owner$/i);

    await waitFor(() =>
      expect(lastScheduleRequest()).toEqual(
        expect.objectContaining({ sortBy: 'owner', sortDir: 'asc' }),
      ),
    );
  });

  test('sorting the same column twice sorts descending', async () => {
    const user = userEvent.setup({ delay: null });
    mockFetchReportSchedules(SINGLE_REPORT_SCHEDULE);
    renderReportSchedulesPage();
    await screen.findByText(mockReportSchedule.name);

    await clickSort(user, /^name$/i);
    await waitFor(() =>
      expect(lastScheduleRequest()).toEqual(expect.objectContaining({ sortBy: 'name' })),
    );
    await clickSort(user, /^name$/i);

    await waitFor(() =>
      expect(lastScheduleRequest()).toEqual(
        expect.objectContaining({ sortBy: 'name', sortDir: 'desc' }),
      ),
    );
  });

  test('sorting returns to page 1', async () => {
    const user = userEvent.setup({ delay: null });
    mockFetchReportSchedulePages(PAGED_SCHEDULES_P1, PAGED_SCHEDULES_P2);
    renderReportSchedulesPage();
    await screen.findByText('Schedule 1');
    await user.click(screen.getByRole('button', { name: /next/i }));
    await screen.findByText('Schedule 11');

    await clickSort(user, /^name$/i);

    await waitFor(() =>
      expect(lastScheduleRequest()).toEqual(expect.objectContaining({ sortBy: 'name', start: 0 })),
    );
  });
});

// =============================================================================
// Part 3 — cell content and row-dependent actions
// =============================================================================

describe('Report Schedules grid - cells and row actions', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
  });

  test('Report Name shows the friendly name, falling back to the raw value', async () => {
    mockFetchReportSchedules({
      rows: [
        buildReportSchedule({
          reportScheduleId: 1,
          name: 'Known',
          reportName: 'proofofplayReport',
        }),
        buildReportSchedule({ reportScheduleId: 2, name: 'Unknown', reportName: 'Custom Thing' }),
      ],
      totalCount: 2,
    });
    renderReportSchedulesPage();

    expect(await findRow('Known')).toHaveTextContent('Proof of Play');
    expect(await findRow('Unknown')).toHaveTextContent('Custom Thing');
  });

  test('Open Last Report and Delete All Saved Reports only appear once a report has been saved', async () => {
    const user = userEvent.setup({ delay: null });
    mockFetchReportSchedules({
      rows: [
        buildReportSchedule({ reportScheduleId: 1, name: 'Never Saved' }),
        { ...SCHEDULE_WITH_SAVED, reportScheduleId: 2, name: 'Has Saved' },
      ],
      totalCount: 2,
    });
    renderReportSchedulesPage();

    await openRowMenu(user, 'Never Saved');
    expect(queryMenuItems(/^open last report$/i)).toHaveLength(0);
    expect(queryMenuItems(/^delete all saved reports$/i)).toHaveLength(0);
    await user.keyboard('{Escape}');

    await openRowMenu(user, 'Has Saved');
    expect(queryMenuItems(/^open last report$/i)).toHaveLength(1);
    expect(queryMenuItems(/^delete all saved reports$/i)).toHaveLength(1);
  });

  test('an active schedule offers Pause and a paused one offers Resume', async () => {
    mockFetchReportSchedules({
      rows: [
        buildReportSchedule({ reportScheduleId: 1, name: 'Running' }),
        { ...PAUSED_SCHEDULE, reportScheduleId: 2, name: 'Stopped' },
      ],
      totalCount: 2,
    });
    renderReportSchedulesPage();

    const running = await findRow('Running');
    const stopped = await findRow('Stopped');

    expect(within(running).getByRole('button', { name: /^pause$/i })).toBeInTheDocument();
    expect(within(running).queryByRole('button', { name: /^resume$/i })).not.toBeInTheDocument();
    expect(within(stopped).getByRole('button', { name: /^resume$/i })).toBeInTheDocument();
    expect(within(stopped).queryByRole('button', { name: /^pause$/i })).not.toBeInTheDocument();
  });
});
