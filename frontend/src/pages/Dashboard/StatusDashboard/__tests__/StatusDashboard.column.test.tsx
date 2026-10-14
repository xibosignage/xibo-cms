/*
 * Copyright (C) 2026 Xibo Signage Ltd
 *
 * Xibo - Digital Signage - https://xibosignage.com
 *
 * This file is part of Xibo.
 *
 * Xibo is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * any later version.
 *
 * Xibo is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with Xibo.  If not, see <http://www.gnu.org/licenses/>.
 */

import { screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type * as RechartsModule from 'recharts';
import { vi, beforeEach, describe, test, expect } from 'vitest';

import {
  buildDisplay,
  buildStatusDashboardData,
  EMPTY_DISPLAY_TABLE,
} from './fixtures/statusDashboard';
import {
  getDisplayTable,
  getSection,
  getSortToggle,
  renderStatusDashboardPage,
} from './helpers/renderStatusDashboardPage';
import { mockDashboardDisplays, mockStatusDashboard } from './mocks/statusDashboardApi';

import { fetchDisplays } from '@/services/displaysApi';
import type { FetchDisplaysRequest } from '@/services/displaysApi';
import { testQueryClient } from '@/setupTests';

// =============================================================================
// Module mocks
// =============================================================================

vi.mock('@/services/dashboardApi', () => ({
  fetchStatusDashboard: vi.fn(),
}));
vi.mock('@/services/displaysApi', () => ({
  fetchDisplays: vi.fn().mockResolvedValue({ rows: [], totalCount: 0 }),
}));
vi.mock('recharts', async (importOriginal) => {
  const actual = await importOriginal<typeof RechartsModule>();
  const { cloneElement } = await import('react');
  return {
    ...actual,
    ResponsiveContainer: ({ children }: { children: React.ReactElement }) =>
      cloneElement(children, { width: 500, height: 300 } as Record<string, unknown>),
  };
});

// =============================================================================
// Helpers local to this file
// =============================================================================

// The request the dashboard sent most recently. Index access, not .at(-1) (ES2020 lib).
const lastDisplaysRequest = (): FetchDisplaysRequest => {
  const calls = vi.mocked(fetchDisplays).mock.calls;
  return calls[calls.length - 1]![0] as FetchDisplaysRequest;
};

// Five displays named "Display 1".."Display 5" with unique ids, and a total of 12, so the
// table has three pages. The default mock returns totalCount 0, which can't show paging.
const FIVE_OF_TWELVE = {
  rows: Array.from({ length: 5 }, (_, i) =>
    buildDisplay({ displayId: i + 1, display: `Display ${i + 1}` }),
  ),
  totalCount: 12,
};

// The cells of the row whose Display column reads `name`, in column order.
const cellsOf = (name: string) =>
  within(within(getDisplayTable()).getByText(name).closest('tr')!).getAllByRole('cell');

// Column order: Display (0), Logged In (1), Authorised (2).
const LOGGED_IN = 1;
const AUTHORISED = 2;

// =============================================================================
// Tests
// =============================================================================

describe('Status Dashboard - Display Activity table columns', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    mockStatusDashboard(buildStatusDashboardData());
  });

  test('the table shows Display, Logged In and Authorised, in that order', async () => {
    mockDashboardDisplays({ rows: [buildDisplay({ display: 'Lobby' })], totalCount: 1 });
    renderStatusDashboardPage();
    await screen.findByText('Lobby');

    const headers = within(getDisplayTable()).getAllByRole('columnheader');
    expect(headers.map((h) => h.textContent)).toEqual(['Display', 'Logged In', 'Authorised']);
  });

  // ---------------------------------------------------------------------------
  // Column contract (§4.6). The API sends 0/1, not booleans. A tick/cross column must
  // draw the two meanings differently, and the same meaning the same way. Compared on
  // whole-cell markup because the icons carry no text (known issue K5).
  // ---------------------------------------------------------------------------
  test('a logged-in display and a logged-out display look different in Logged In', async () => {
    mockDashboardDisplays({
      rows: [
        buildDisplay({ displayId: 1, display: 'On', loggedIn: 1 }),
        buildDisplay({ displayId: 2, display: 'Off', loggedIn: 0 }),
      ],
      totalCount: 2,
    });
    renderStatusDashboardPage();
    await screen.findByText('On');

    expect(cellsOf('On')[LOGGED_IN]!.innerHTML).not.toBe(cellsOf('Off')[LOGGED_IN]!.innerHTML);
  });

  test('two logged-in displays look the same in Logged In', async () => {
    mockDashboardDisplays({
      rows: [
        buildDisplay({ displayId: 1, display: 'A', loggedIn: 1 }),
        buildDisplay({ displayId: 2, display: 'B', loggedIn: 1 }),
      ],
      totalCount: 2,
    });
    renderStatusDashboardPage();
    await screen.findByText('A');

    expect(cellsOf('A')[LOGGED_IN]!.innerHTML).toBe(cellsOf('B')[LOGGED_IN]!.innerHTML);
  });

  test('an authorised display and an unauthorised display look different in Authorised', async () => {
    mockDashboardDisplays({
      rows: [
        buildDisplay({ displayId: 1, display: 'Yes', licensed: 1 }),
        buildDisplay({ displayId: 2, display: 'No', licensed: 0 }),
      ],
      totalCount: 2,
    });
    renderStatusDashboardPage();
    await screen.findByText('Yes');

    expect(cellsOf('Yes')[AUTHORISED]!.innerHTML).not.toBe(cellsOf('No')[AUTHORISED]!.innerHTML);
  });

  test('with no displays, the table shows its empty state', async () => {
    mockDashboardDisplays(EMPTY_DISPLAY_TABLE);
    renderStatusDashboardPage();

    await within(getSection('Display Activity', 1)).findByRole('heading', {
      name: 'No results found.',
    });
  });

  // The dashboard table is read-only: hideToolbar and enableSelection={false}.
  test('the table has no search box, no Filters button and no row checkboxes', async () => {
    mockDashboardDisplays({
      rows: [
        buildDisplay({ displayId: 1, display: 'A' }),
        buildDisplay({ displayId: 2, display: 'B' }),
      ],
      totalCount: 2,
    });
    renderStatusDashboardPage();
    await screen.findByText('A');

    expect(screen.queryByRole('textbox')).not.toBeInTheDocument();
    expect(screen.queryByRole('button', { name: /filters/i })).not.toBeInTheDocument();
    expect(screen.queryByRole('checkbox')).not.toBeInTheDocument();
  });
});

describe('Status Dashboard - Display Activity table paging and sorting', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    mockStatusDashboard(buildStatusDashboardData());
    mockDashboardDisplays(FIVE_OF_TWELVE);
  });

  test('on first load the table asks for the first five displays, unsorted', async () => {
    renderStatusDashboardPage();
    await screen.findByText('Display 1');

    expect(lastDisplaysRequest()).toEqual(
      expect.objectContaining({ start: 0, length: 5, sortBy: undefined, sortDir: undefined }),
    );
  });

  test('moving to page 2 asks for displays 6 to 10', async () => {
    const user = userEvent.setup();
    renderStatusDashboardPage();
    await screen.findByText('Display 1');

    await user.click(
      within(getSection('Display Activity', 1)).getByRole('button', { name: 'Next' }),
    );

    await waitFor(() =>
      expect(lastDisplaysRequest()).toEqual(expect.objectContaining({ start: 5, length: 5 })),
    );
  });

  test('sorting by Display asks the server to sort by display name', async () => {
    const user = userEvent.setup();
    renderStatusDashboardPage();
    await screen.findByText('Display 1');

    await user.click(getSortToggle('Display'));

    await waitFor(() =>
      expect(lastDisplaysRequest()).toEqual(
        expect.objectContaining({
          sortBy: 'display',
          sortDir: expect.stringMatching(/^(asc|desc)$/),
        }),
      ),
    );
  });

  test('with 12 displays in total, the table offers three pages', async () => {
    renderStatusDashboardPage();
    await screen.findByText('Display 1');

    const section = within(getSection('Display Activity', 1));
    expect(section.getByRole('button', { name: '1' })).toBeInTheDocument();
    expect(section.getByRole('button', { name: '2' })).toBeInTheDocument();
    expect(section.getByRole('button', { name: '3' })).toBeInTheDocument();
    expect(section.queryByRole('button', { name: '4' })).not.toBeInTheDocument();
  });
});
