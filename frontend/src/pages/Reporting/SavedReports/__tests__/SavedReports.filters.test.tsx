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
// Page integration: search and filters (filter-options hook runs for real)
// =============================================================================

import { screen, waitFor, within } from '@testing-library/react';
import userEvent, { type UserEvent } from '@testing-library/user-event';
import { vi, afterEach, beforeEach, describe, test, expect } from 'vitest';

import { INITIAL_FILTER_STATE } from '../SavedReportsConfig';

import {
  mockSavedReport,
  PAGE_INDEX_STORAGE_KEY,
  PAGED_SAVED_REPORTS_P1,
  PAGED_SAVED_REPORTS_P2,
  SINGLE_SAVED_REPORT,
} from './fixtures/savedReport';
import { renderSavedReportsPage } from './helpers/renderSavedReportsPage';
import {
  lastSavedReportRequest,
  mockFetchReports,
  mockFetchSavedReportPages,
  mockFetchSavedReports,
} from './mocks/savedReportApi';

import { fetchSavedReports } from '@/services/savedReportApi';
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

vi.mock('@/services/savedReportApi', () => ({
  fetchSavedReports: vi.fn().mockResolvedValue({ rows: [], totalCount: 0 }),
  deleteSavedReport: vi.fn().mockResolvedValue(undefined),
  fetchSavedReportData: vi.fn(),
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

// Search and Filters stay disabled until a row has loaded.
const waitForPageReady = () => findRow(mockSavedReport.saveAs);

// No accessible name yet (xibosignage/xibo#4083).
const searchBox = () => screen.getByPlaceholderText('Search');
const nameFilter = () => screen.getByRole('textbox', { name: /^name$/i });
const regexToggle = () => screen.getByRole('button', { name: /use regex pattern matching/i });

const openFilters = async (user: UserEvent) => {
  await user.click(screen.getByRole('button', { name: /filters/i }));
  return screen.findByRole('button', { name: /reset/i });
};

const pickOption = async (user: UserEvent, comboboxName: RegExp, option: string) => {
  await user.click(screen.getByRole('combobox', { name: comboboxName }));
  await user.click(await screen.findByRole('option', { name: option }));
};

const expectLastRequest = (params: Record<string, unknown>) =>
  waitFor(() => expect(lastSavedReportRequest()).toEqual(expect.objectContaining(params)));

const goToPage2 = async (user: UserEvent) => {
  await findRow('Saved 1');
  await user.click(screen.getByRole('button', { name: /next/i }));
  await findRow('Saved 11');
};

// =============================================================================
// Tests
// =============================================================================

describe('Saved Reports page - filters', () => {
  afterEach(() => {
    vi.useRealTimers();
  });

  beforeEach(() => {
    // The page remembers its list page here; start every test on page 1.
    sessionStorage.clear();
    testQueryClient.clear();
    vi.clearAllMocks();
    mockFetchSavedReports(SINGLE_SAVED_REPORT);
    mockFetchReports();
    vi.mocked(fetchUsers)
      .mockReset()
      .mockResolvedValue({
        rows: [
          { userId: 7, userName: 'alice' },
          { userId: 8, userName: 'bob' },
        ],
        totalCount: 2,
      } as unknown as Awaited<ReturnType<typeof fetchUsers>>);
  });

  test('the filter panel is closed by default and toggles with Filters', async () => {
    const user = userEvent.setup({ delay: null });
    renderSavedReportsPage();
    await waitForPageReady();
    expect(screen.queryByRole('button', { name: /reset/i })).not.toBeInTheDocument();

    await openFilters(user);
    await user.click(screen.getByRole('button', { name: /filters/i }));

    await waitFor(() =>
      expect(screen.queryByRole('button', { name: /reset/i })).not.toBeInTheDocument(),
    );
  });

  test('typing in the search box searches by name and returns to page 1', async () => {
    const user = userEvent.setup({ delay: null });
    mockFetchSavedReportPages(PAGED_SAVED_REPORTS_P1, PAGED_SAVED_REPORTS_P2);
    renderSavedReportsPage();
    await goToPage2(user);

    await user.click(searchBox());
    await user.paste('weekly');

    await expectLastRequest({ saveAs: 'weekly', start: 0 });
  });

  // Both feed saveAs; the Name filter takes priority.
  test('the Name filter wins over the search box', async () => {
    const user = userEvent.setup({ delay: null });
    renderSavedReportsPage();
    await waitForPageReady();

    await user.click(searchBox());
    await user.paste('box');
    await expectLastRequest({ saveAs: 'box' });
    await openFilters(user);
    await user.type(nameFilter(), 'panel');

    await expectLastRequest({ saveAs: 'panel' });
  });

  test('AND/OR is sent with the Name filter only', async () => {
    const user = userEvent.setup({ delay: null });
    renderSavedReportsPage();
    await waitForPageReady();

    await user.click(searchBox());
    await user.paste('weekly');
    await expectLastRequest({ saveAs: 'weekly' });
    expect(lastSavedReportRequest()).not.toHaveProperty('logicalOperatorName');

    await user.clear(searchBox());
    await openFilters(user);
    await user.type(nameFilter(), 'weekly');
    await user.click(screen.getByRole('button', { name: /^or$/i }));
    await user.click(await screen.findByRole('button', { name: /^and$/i }));

    await expectLastRequest({ saveAs: 'weekly', logicalOperatorName: 'AND' });
  });

  test('a valid regex is sent as a regex search', async () => {
    const user = userEvent.setup({ delay: null });
    renderSavedReportsPage();
    await waitForPageReady();
    await openFilters(user);

    await user.type(nameFilter(), '^Week');
    await expectLastRequest({ saveAs: '^Week' });
    await user.click(regexToggle());

    await expectLastRequest({ saveAs: '^Week', useRegexForName: 1 });
  });

  test('an invalid regex is flagged and never sent', async () => {
    // Fake timers to get past the 300 ms debounce before asserting no send.
    vi.useFakeTimers({ shouldAdvanceTime: true });
    const user = userEvent.setup({ advanceTimers: vi.advanceTimersByTime });
    renderSavedReportsPage();
    await waitForPageReady();
    await openFilters(user);

    await user.click(regexToggle());
    // '[[' is user-event's escape for a literal '['.
    await user.type(nameFilter(), '[[');

    await screen.findByText('Invalid regular expression');
    await vi.advanceTimersByTimeAsync(1000);
    const sentNames = vi.mocked(fetchSavedReports).mock.calls.map(([req]) => req?.saveAs);
    expect(sentNames).not.toContain('[');
  });

  test('choosing an Owner sends that user ID as a number', async () => {
    const user = userEvent.setup({ delay: null });
    renderSavedReportsPage();
    await waitForPageReady();
    await openFilters(user);

    await pickOption(user, /^owner$/i, 'alice');

    await expectLastRequest({ userId: 7 });
  });

  test("choosing a Type sends the report's internal name", async () => {
    const user = userEvent.setup({ delay: null });
    renderSavedReportsPage();
    await waitForPageReady();
    await openFilters(user);

    await pickOption(user, /^type$/i, 'Display Statistics: Bandwidth');

    await expectLastRequest({ reportName: 'bandwidth' });
  });

  // The server reads the singular onlyMyReport (SavedReport.php).
  test('Only My Reports: Yes sends the flag under the name the server reads', async () => {
    const user = userEvent.setup({ delay: null });
    renderSavedReportsPage();
    await waitForPageReady();
    await openFilters(user);

    await pickOption(user, /only my reports/i, 'Yes');

    await expectLastRequest({ onlyMyReport: 1 });
  });

  test('the Filters button counts active filters', async () => {
    const user = userEvent.setup({ delay: null });
    renderSavedReportsPage();
    await waitForPageReady();
    await openFilters(user);

    await user.type(nameFilter(), 'weekly');
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
    mockFetchSavedReportPages(PAGED_SAVED_REPORTS_P1, PAGED_SAVED_REPORTS_P2);
    renderSavedReportsPage();
    await goToPage2(user);
    await openFilters(user);

    await pickOption(user, /^owner$/i, 'alice');

    await expectLastRequest({ userId: 7, start: 0 });
  });

  test('Reset clears filters and keeps the panel open', async () => {
    const user = userEvent.setup({ delay: null });
    renderSavedReportsPage();
    await waitForPageReady();
    await openFilters(user);
    await user.type(nameFilter(), 'weekly');
    await pickOption(user, /^owner$/i, 'alice');
    await expectLastRequest({ saveAs: 'weekly', userId: 7 });

    await user.click(screen.getByRole('button', { name: /reset/i }));

    expect(screen.getByRole('button', { name: /reset/i })).toBeInTheDocument();
    await waitFor(() => expect(nameFilter()).toHaveValue(''));
    expect(screen.getByRole('combobox', { name: /^owner$/i })).not.toHaveTextContent('alice');
  });

  test('Reset clears an active filter and returns to page 1', async () => {
    const user = userEvent.setup({ delay: null });
    mockFetchSavedReportPages(PAGED_SAVED_REPORTS_P1, PAGED_SAVED_REPORTS_P2);
    sessionStorage.setItem(PAGE_INDEX_STORAGE_KEY, '1');
    renderSavedReportsPage({ savedFilters: { ...INITIAL_FILTER_STATE, saveAs: 'weekly' } });
    await findRow('Saved 11');
    await openFilters(user);
    expect(nameFilter()).toHaveValue('weekly');

    await user.click(screen.getByRole('button', { name: /reset/i }));

    // Page 1 is cached, so no new request is made.
    await findRow('Saved 1');
    expect(nameFilter()).toHaveValue('');
  });

  // Known issues, pinned until fixed.

  // Known issue: xibosignage/xibo#4083
  describe('Pinned until https://github.com/xibosignage/xibo/issues/4083 is fixed', () => {
    knownFailure(
      'the search box is announced as Search',
      async () => {
        renderSavedReportsPage();
        await waitForPageReady();
        expect(searchBox()).toBeInTheDocument();
      },
      () => expect(screen.getByRole('textbox', { name: /^search$/i })).toBeInTheDocument(),
    );
  });

  // Known issue: xibosignage/xibo#4084
  describe('Pinned until https://github.com/xibosignage/xibo/issues/4084 is fixed', () => {
    knownFailure(
      'the Owner filter says when its options failed to load',
      async () => {
        const user = userEvent.setup({ delay: null });
        vi.mocked(fetchUsers).mockRejectedValue(new Error('Network Error'));
        renderSavedReportsPage();
        await waitForPageReady();
        await openFilters(user);
        await user.click(screen.getByRole('combobox', { name: /^owner$/i }));
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
    renderSavedReportsPage();
    await waitForPageReady();
    await openFilters(user);
    await user.click(regexToggle());
    await user.type(nameFilter(), 'abc');
    await expectLastRequest({ saveAs: 'abc' });
    await user.type(nameFilter(), '(');
    await screen.findByText('Invalid regular expression');

    await user.click(regexToggle());

    await expectLastRequest({ saveAs: 'abc(' });
  });
});
