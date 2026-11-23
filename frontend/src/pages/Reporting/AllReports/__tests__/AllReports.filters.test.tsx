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
// Test type: Page integration — All Reports filters
// =============================================================================

import { screen, waitFor, within } from '@testing-library/react';
import userEvent, { type UserEvent } from '@testing-library/user-event';
import { vi, beforeEach, describe, test, expect } from 'vitest';

import { ALL_REPORTS } from './fixtures/allReports';
import { findCard, queryCard, renderAllReportsPage } from './helpers/renderAllReportsPage';
import { mockFetchReports } from './mocks/reportApi';

import { fetchReports } from '@/services/reportApi';
import { saveUserPreference } from '@/services/userApi';
import { testQueryClient } from '@/setupTests';

// =============================================================================
// Module mocks
// =============================================================================

const mockNavigate = vi.fn();
vi.mock('react-router-dom', async (importOriginal) => {
  const actual = await importOriginal();
  return { ...(actual as object), useNavigate: () => mockNavigate };
});
vi.mock('@/services/reportApi', () => ({
  fetchReports: vi.fn().mockResolvedValue({}),
}));
vi.mock('@/services/userApi', () => ({
  fetchUserPreference: vi.fn().mockResolvedValue(null),
  saveUserPreference: vi.fn().mockResolvedValue(undefined),
}));

// =============================================================================
// Helpers
// =============================================================================

const openFilters = async (user: UserEvent) => {
  await user.click(screen.getByRole('button', { name: /filters/i }));
  return screen.findByRole('button', { name: /reset/i });
};

// The Name filter has no accessible name (xibosignage/xibo#4083).
const nameFilter = () => screen.getByRole('textbox');

const pickOption = async (user: UserEvent, comboboxName: RegExp, option: string) => {
  await user.click(screen.getByRole('combobox', { name: comboboxName }));
  await user.click(await screen.findByRole('option', { name: option }));
};

const visibleCards = () => screen.queryAllByRole('link').map((card) => card.textContent);

// =============================================================================
// Tests
// =============================================================================

describe('All Reports page - filters', () => {
  beforeEach(async () => {
    testQueryClient.clear();
    vi.clearAllMocks();
    mockFetchReports(ALL_REPORTS);
  });

  test('the filter panel is closed by default and toggles with Filters', async () => {
    const user = userEvent.setup({ delay: null });
    renderAllReportsPage();
    await findCard('Proof of Play');
    expect(screen.queryByRole('button', { name: /reset/i })).not.toBeInTheDocument();

    await openFilters(user);
    await user.click(screen.getByRole('button', { name: /filters/i }));

    await waitFor(() =>
      expect(screen.queryByRole('button', { name: /reset/i })).not.toBeInTheDocument(),
    );
  });

  test('Name matches the description, ignoring case', async () => {
    const user = userEvent.setup({ delay: null });
    renderAllReportsPage();
    await findCard('Proof of Play');
    await openFilters(user);

    await user.type(nameFilter(), 'SUMMARY');

    await waitFor(() => expect(queryCard('Proof of Play')).not.toBeInTheDocument());
    expect(visibleCards()).toEqual([expect.stringMatching(/^Summary by Layout/)]);
  });

  test('choosing a Category shows only that category', async () => {
    const user = userEvent.setup({ delay: null });
    renderAllReportsPage();
    await findCard('Proof of Play');
    await openFilters(user);

    await pickOption(user, /^category$/i, 'Audit');

    await waitFor(() =>
      expect(visibleCards()).toEqual([expect.stringMatching(/^Session History/)]),
    );
    expect(screen.getAllByRole('heading', { level: 3 }).map((h) => h.textContent)).toEqual([
      'Audit',
    ]);
  });

  test('choosing an Action Type shows only that type', async () => {
    const user = userEvent.setup({ delay: null });
    renderAllReportsPage();
    await findCard('Proof of Play');
    await openFilters(user);

    await pickOption(user, /^action type$/i, 'Chart');

    await waitFor(() =>
      expect(visibleCards()).toEqual([expect.stringMatching(/^Summary by Layout/)]),
    );
  });

  test('Category and Name combine', async () => {
    const user = userEvent.setup({ delay: null });
    renderAllReportsPage();
    await findCard('Proof of Play');
    await openFilters(user);

    await pickOption(user, /^category$/i, 'Proof of Play');
    await user.type(nameFilter(), 'proof');

    await waitFor(() => expect(visibleCards()).toEqual([expect.stringMatching(/^Proof of Play/)]));
  });

  test('a category with no matching reports disappears with its heading', async () => {
    const user = userEvent.setup({ delay: null });
    renderAllReportsPage();
    await findCard('Proof of Play');
    await openFilters(user);

    await user.type(nameFilter(), 'session');

    await waitFor(() =>
      expect(screen.getAllByRole('heading', { level: 3 }).map((h) => h.textContent)).toEqual([
        'Audit',
      ]),
    );
  });

  test('the Filters button counts active filters', async () => {
    const user = userEvent.setup({ delay: null });
    renderAllReportsPage();
    await findCard('Proof of Play');
    await openFilters(user);

    await user.type(nameFilter(), 'proof');
    await pickOption(user, /^category$/i, 'Proof of Play');

    // The count is its own badge; match exactly so 12 can't pass for 2.
    await waitFor(() =>
      expect(
        within(screen.getByRole('button', { name: /filters/i })).getByText('2'),
      ).toBeInTheDocument(),
    );
  });

  test('Reset shows every report again and keeps the panel open', async () => {
    const user = userEvent.setup({ delay: null });
    renderAllReportsPage();
    await findCard('Proof of Play');
    await openFilters(user);
    await pickOption(user, /^category$/i, 'Audit');
    await waitFor(() => expect(visibleCards()).toHaveLength(1));

    await user.click(screen.getByRole('button', { name: /reset/i }));

    expect(screen.getByRole('button', { name: /reset/i })).toBeInTheDocument();
    await waitFor(() => expect(visibleCards()).toHaveLength(5));
  });

  test("filtering doesn't ask the server for anything", async () => {
    const user = userEvent.setup({ delay: null });
    renderAllReportsPage();
    await findCard('Proof of Play');
    await openFilters(user);
    const callsBefore = vi.mocked(fetchReports).mock.calls.length;

    await user.type(nameFilter(), 'proof');
    await pickOption(user, /^category$/i, 'Proof of Play');
    await pickOption(user, /^action type$/i, 'Report');
    await waitFor(() => expect(visibleCards()).toHaveLength(1));

    expect(vi.mocked(fetchReports).mock.calls.length).toBe(callsBefore);
  });

  test('the chosen filters are remembered', async () => {
    const user = userEvent.setup({ delay: null });
    renderAllReportsPage();
    await findCard('Proof of Play');
    await openFilters(user);

    await pickOption(user, /^category$/i, 'Audit');

    // useTableState saves after a 500 ms debounce.
    await waitFor(() =>
      expect(saveUserPreference).toHaveBeenCalledWith(
        expect.objectContaining({
          option: 'all_reports_page',
          value: expect.objectContaining({
            filterInputs: expect.objectContaining({ reportType: 'Audit' }),
          }),
        }),
        // React Query passes the mutation context as a second argument.
        expect.anything(),
      ),
    );
  });
});
