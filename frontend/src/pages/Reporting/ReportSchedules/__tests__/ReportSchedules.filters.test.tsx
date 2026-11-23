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
// Filter-options hook runs for real so Owner and Type have options.
// =============================================================================

import { screen, waitFor, within } from '@testing-library/react';
import userEvent, { type UserEvent } from '@testing-library/user-event';
import { vi, afterEach, beforeEach, describe, test, expect } from 'vitest';

import { INITIAL_FILTER_STATE } from '../ReportSchedulesConfig';

import {
  mockReportSchedule,
  PAGED_SCHEDULES_P1,
  PAGED_SCHEDULES_P2,
  SINGLE_REPORT_SCHEDULE,
} from './fixtures/reportSchedule';
import { renderReportSchedulesPage } from './helpers/renderReportSchedulesPage';
import {
  lastScheduleRequest,
  mockFetchReports,
  mockFetchReportSchedulePages,
  mockFetchReportSchedules,
} from './mocks/reportScheduleApi';

import { fetchReportSchedules } from '@/services/reportScheduleApi';
import { fetchUsers } from '@/services/userApi';
import { testQueryClient } from '@/setupTests';
import { knownFailure } from '@/testUtils/knownFailure';
import { findRow } from '@/testUtils/rowActions';

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
  fetchUsers: vi.fn().mockResolvedValue({ rows: [], totalCount: 0 }),
}));
vi.mock('@/components/ui/Notification', () => ({
  notify: { success: vi.fn(), error: vi.fn(), info: vi.fn(), warning: vi.fn() },
}));
vi.mock('@/components/ui/modals/Modal');

// =============================================================================
// Helpers
// =============================================================================

// Search and Filters stay disabled until the page hydrates.
const waitForPageReady = () => findRow(mockReportSchedule.name);

const openFilters = async (user: UserEvent) => {
  await user.click(screen.getByRole('button', { name: /filters/i }));
  return screen.findByRole('button', { name: /reset/i });
};

const pickOption = async (user: UserEvent, comboboxName: RegExp, option: string) => {
  await user.click(screen.getByRole('combobox', { name: comboboxName }));
  await user.click(await screen.findByRole('option', { name: option }));
};

const regexToggle = () => screen.getByRole('button', { name: /use regex pattern matching/i });
const searchBox = () => screen.getByRole('textbox', { name: /^search$/i });

const expectLastRequest = (params: Record<string, unknown>) =>
  waitFor(() => expect(lastScheduleRequest()).toEqual(expect.objectContaining(params)));

const goToPage2 = async (user: UserEvent) => {
  await findRow('Schedule 1');
  await user.click(screen.getByRole('button', { name: /next/i }));
  await findRow('Schedule 11');
};

// =============================================================================
// Tests
// =============================================================================

describe('Report Schedules page - filters', () => {
  afterEach(() => {
    vi.useRealTimers();
  });

  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    mockFetchReportSchedules(SINGLE_REPORT_SCHEDULE);
    mockFetchReports();
    vi.mocked(fetchUsers).mockResolvedValue({
      rows: [
        { userId: 7, userName: 'alice' },
        { userId: 8, userName: 'bob' },
      ],
      totalCount: 2,
    } as unknown as Awaited<ReturnType<typeof fetchUsers>>);
  });

  test('the filter panel is closed when the page opens', async () => {
    renderReportSchedulesPage();
    await waitForPageReady();

    expect(screen.queryByRole('button', { name: /reset/i })).not.toBeInTheDocument();
  });

  test('clicking Filters opens the panel and clicking again closes it', async () => {
    const user = userEvent.setup({ delay: null });
    renderReportSchedulesPage();
    await waitForPageReady();

    await openFilters(user);
    await user.click(screen.getByRole('button', { name: /filters/i }));

    await waitFor(() =>
      expect(screen.queryByRole('button', { name: /reset/i })).not.toBeInTheDocument(),
    );
  });

  test('typing in the search box searches schedules by name and returns to page 1', async () => {
    const user = userEvent.setup({ delay: null });
    mockFetchReportSchedulePages(PAGED_SCHEDULES_P1, PAGED_SCHEDULES_P2);
    renderReportSchedulesPage();
    await goToPage2(user);

    await user.click(searchBox());
    await user.paste('weekly');

    await expectLastRequest({ name: 'weekly', start: 0 });
  });

  // ID label isn't tied to its input (xibosignage/xibo#4083); it's the only spinbutton.
  test('filtering by ID sends that schedule ID', async () => {
    const user = userEvent.setup({ delay: null });
    renderReportSchedulesPage();
    await waitForPageReady();
    await openFilters(user);

    await user.type(screen.getByRole('spinbutton'), '42');

    await expectLastRequest({ reportScheduleId: 42 });
  });

  test('the Name filter wins over the search box', async () => {
    const user = userEvent.setup({ delay: null });
    renderReportSchedulesPage();
    await waitForPageReady();

    await user.click(searchBox());
    await user.paste('box');
    await expectLastRequest({ name: 'box' });
    await openFilters(user);
    await user.type(screen.getByRole('textbox', { name: /^name$/i }), 'panel');

    await expectLastRequest({ name: 'panel' });
  });

  test('choosing AND sends it with the Name filter', async () => {
    const user = userEvent.setup({ delay: null });
    renderReportSchedulesPage();
    await waitForPageReady();
    await openFilters(user);

    await user.type(screen.getByRole('textbox', { name: /^name$/i }), 'weekly');
    await user.click(screen.getByRole('button', { name: /^or$/i }));
    await user.click(await screen.findByRole('button', { name: /^and$/i }));

    await expectLastRequest({ name: 'weekly', logicalOperatorName: 'AND' });
  });

  test('AND/OR is not sent when only the search box has text', async () => {
    const user = userEvent.setup({ delay: null });
    renderReportSchedulesPage();
    await waitForPageReady();

    await user.click(searchBox());
    await user.paste('weekly');

    await expectLastRequest({ name: 'weekly' });
    expect(lastScheduleRequest()).not.toHaveProperty('logicalOperatorName');
  });

  test('a valid regex is sent as a regex search', async () => {
    const user = userEvent.setup({ delay: null });
    renderReportSchedulesPage();
    await waitForPageReady();
    await openFilters(user);

    await user.type(screen.getByRole('textbox', { name: /^name$/i }), '^Week');
    await expectLastRequest({ name: '^Week' });
    await user.click(regexToggle());

    await expectLastRequest({ name: '^Week', useRegexForName: 1 });
  });

  test('an invalid regex is flagged and never sent', async () => {
    // Fake timers move past the 300 ms debounce so a missing send is meaningful.
    vi.useFakeTimers({ shouldAdvanceTime: true });
    const user = userEvent.setup({ advanceTimers: vi.advanceTimersByTime });
    renderReportSchedulesPage();
    await waitForPageReady();
    await openFilters(user);

    await user.click(regexToggle());
    // '[[' is user-event's escape for a literal '['.
    await user.type(screen.getByRole('textbox', { name: /^name$/i }), '[[');

    await screen.findByText('Invalid regular expression');
    await vi.advanceTimersByTimeAsync(1000);
    const sentNames = vi.mocked(fetchReportSchedules).mock.calls.map(([req]) => req?.name);
    expect(sentNames).not.toContain('[');
  });

  test('choosing an Owner sends that user ID as a number', async () => {
    const user = userEvent.setup({ delay: null });
    renderReportSchedulesPage();
    await waitForPageReady();
    await openFilters(user);

    await pickOption(user, /^owner$/i, 'alice');

    await expectLastRequest({ userId: 7 });
  });

  test("choosing a Type sends that report's internal name", async () => {
    const user = userEvent.setup({ delay: null });
    renderReportSchedulesPage();
    await waitForPageReady();
    await openFilters(user);

    await pickOption(user, /^type$/i, 'Display Statistics: Bandwidth');

    await expectLastRequest({ reportName: 'bandwidth' });
  });

  test("Only My Schedules: Yes limits the list to the user's own schedules", async () => {
    const user = userEvent.setup({ delay: null });
    renderReportSchedulesPage();
    await waitForPageReady();
    await openFilters(user);

    await pickOption(user, /only my schedules/i, 'Yes');

    await expectLastRequest({ onlyMySchedules: 1 });
  });

  test('the Filters button counts active filters', async () => {
    const user = userEvent.setup({ delay: null });
    renderReportSchedulesPage();
    await waitForPageReady();
    await openFilters(user);

    await user.type(screen.getByRole('textbox', { name: /^name$/i }), 'weekly');
    await pickOption(user, /^owner$/i, 'alice');

    // Exact match so 12 can't pass for 2.
    await waitFor(() =>
      expect(
        within(screen.getByRole('button', { name: /filters/i })).getByText('2'),
      ).toBeInTheDocument(),
    );
  });

  test('changing a filter returns to page 1', async () => {
    const user = userEvent.setup({ delay: null });
    mockFetchReportSchedulePages(PAGED_SCHEDULES_P1, PAGED_SCHEDULES_P2);
    renderReportSchedulesPage();
    await goToPage2(user);
    await openFilters(user);

    await pickOption(user, /^owner$/i, 'alice');

    await expectLastRequest({ userId: 7, start: 0 });
  });

  test('Reset clears filters and keeps the panel open', async () => {
    const user = userEvent.setup({ delay: null });
    renderReportSchedulesPage();
    await waitForPageReady();
    await openFilters(user);
    await user.type(screen.getByRole('textbox', { name: /^name$/i }), 'weekly');
    await pickOption(user, /^owner$/i, 'alice');
    await expectLastRequest({ name: 'weekly', userId: 7 });

    await user.click(screen.getByRole('button', { name: /reset/i }));

    expect(screen.getByRole('button', { name: /reset/i })).toBeInTheDocument();
    await waitFor(() => expect(screen.getByRole('textbox', { name: /^name$/i })).toHaveValue(''));
    expect(screen.getByRole('combobox', { name: /^owner$/i })).not.toHaveTextContent('alice');
    expect(screen.getByRole('button', { name: /filters/i })).not.toHaveTextContent(/\d/);
  });

  test('Reset clears an active filter and returns to page 1', async () => {
    const user = userEvent.setup({ delay: null });
    mockFetchReportSchedulePages(PAGED_SCHEDULES_P1, PAGED_SCHEDULES_P2);
    renderReportSchedulesPage({ savedFilters: { ...INITIAL_FILTER_STATE, name: 'weekly' } });
    await goToPage2(user);
    await openFilters(user);
    expect(screen.getByRole('textbox', { name: /^name$/i })).toHaveValue('weekly');

    await user.click(screen.getByRole('button', { name: /reset/i }));

    // Page 1 is cached, so check the UI rather than a new request.
    await findRow('Schedule 1');
    expect(screen.getByRole('textbox', { name: /^name$/i })).toHaveValue('');
  });

  // Known issue: xibosignage/xibo#4083
  describe('Pinned until https://github.com/xibosignage/xibo/issues/4083 is fixed', () => {
    // The ID filter's label isn't tied to the input.
    knownFailure(
      'the ID filter is announced by its label',
      async () => {
        const user = userEvent.setup({ delay: null });
        renderReportSchedulesPage();
        await waitForPageReady();
        await openFilters(user);
        // Control: the field exists, only its name is missing.
        expect(screen.getByRole('spinbutton')).toBeInTheDocument();
      },
      () => expect(screen.getByRole('spinbutton', { name: /^id$/i })).toBeInTheDocument(),
    );
  });

  // Known issue: xibosignage/xibo#4084
  describe('Pinned until https://github.com/xibosignage/xibo/issues/4084 is fixed', () => {
    // A failed owner lookup looks the same as having no users.
    knownFailure(
      'the Owner filter says when its options failed to load',
      async () => {
        const user = userEvent.setup({ delay: null });
        vi.mocked(fetchUsers).mockRejectedValue(new Error('Network Error'));
        renderReportSchedulesPage();
        await waitForPageReady();
        await openFilters(user);
        await user.click(screen.getByRole('combobox', { name: /^owner$/i }));
        // Control: the dropdown is open and empty.
        await screen.findByText('No results');
      },
      () =>
        expect(
          screen.getByText(/couldn.t be loaded|could not be loaded|failed to load/i),
        ).toBeInTheDocument(),
    );
  });

  // Fixed in xibosignage/xibo#4107.
  test('turning regex off sends the text held back as an invalid regex', async () => {
    vi.useFakeTimers({ shouldAdvanceTime: true });
    const user = userEvent.setup({ advanceTimers: vi.advanceTimersByTime });
    renderReportSchedulesPage();
    await waitForPageReady();
    await openFilters(user);
    await user.click(regexToggle());
    await user.type(screen.getByRole('textbox', { name: /^name$/i }), 'abc');
    await expectLastRequest({ name: 'abc' });
    await user.type(screen.getByRole('textbox', { name: /^name$/i }), '(');
    await screen.findByText('Invalid regular expression');

    await user.click(regexToggle());

    await expectLastRequest({ name: 'abc(' });
  });
});
