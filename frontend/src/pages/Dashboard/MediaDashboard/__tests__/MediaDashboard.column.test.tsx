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
  buildDashboardMedia,
  buildDashboardMediaRows,
  EMPTY_MEDIA_TABLE,
  mockMediaDashboardData,
} from './fixtures/mediaDashboard';
import {
  getSection,
  getTableUnder,
  renderMediaDashboardPage,
} from './helpers/renderMediaDashboardPage';
import {
  mockMediaDashboard,
  mockUnreleasedMedia,
  mockUnusedMedia,
} from './mocks/mediaDashboardApi';

import { fetchUnreleasedMedia, fetchUnusedMedia } from '@/services/dashboardApi';
import { testQueryClient } from '@/setupTests';

// =============================================================================
// Module mocks
// =============================================================================

vi.mock('@/services/dashboardApi', () => ({
  fetchMediaDashboard: vi.fn(),
  fetchUnusedMedia: vi.fn().mockResolvedValue({ rows: [], totalCount: 0 }),
  fetchUnreleasedMedia: vi.fn().mockResolvedValue({ rows: [], totalCount: 0 }),
}));
vi.mock('@/services/mediaApi', () => ({
  tidyLibrary: vi.fn().mockResolvedValue({ countDeleted: 0 }),
}));
vi.mock('@/components/ui/Notification', () => ({
  notify: { success: vi.fn(), error: vi.fn(), info: vi.fn(), warning: vi.fn() },
}));
vi.mock('@/components/ui/modals/Modal');
vi.mock('recharts', async (importOriginal) => {
  const actual = await importOriginal<typeof RechartsModule>();
  const { cloneElement } = await import('react');
  return {
    ...actual,
    ResponsiveContainer: ({ children }: { children: React.ReactElement }) =>
      cloneElement(children, { width: 200, height: 200 } as Record<string, unknown>),
  };
});

// =============================================================================
// Helpers local to this file
// =============================================================================

type Fetcher = typeof fetchUnusedMedia | typeof fetchUnreleasedMedia;

// The paging/sorting params of the most recent request. Index access, not .at(-1).
const lastRequest = (fetcher: Fetcher) => {
  const calls = vi.mocked(fetcher).mock.calls;
  return calls[calls.length - 1]![0];
};

// Five rows out of 11, so each table has three pages. The defaults return an empty table,
// which can't show paging and would let "the other table is unaffected" pass vacuously.
const FIVE_OF_ELEVEN = { rows: buildDashboardMediaRows(5), totalCount: 11 };

const headersOf = (heading: 'Unused Media' | 'Unreleased Media') =>
  within(getTableUnder(heading))
    .getAllByRole('columnheader')
    .map((h) => h.textContent);

// The cells of the Unused Media row whose Name reads `name`, in column order.
const unusedCellsOf = (name: string) =>
  within(within(getTableUnder('Unused Media')).getByText(name).closest('tr')!).getAllByRole('cell');

// DataTable's sort toggle is a bare <div onClick> with no role or keyboard access
// (xibosignage/xibo#ISSUE_SORT_TOGGLE). It is the element that holds the header's sort icon, the only icon in
// the header.
const getSortToggle = (heading: 'Unused Media' | 'Unreleased Media', column: string) => {
  const header = within(getTableUnder(heading)).getByRole('columnheader', { name: column });
  const icon = header.querySelector('svg');
  if (!icon?.parentElement) throw new Error(`Column "${column}" has no sort toggle`);
  return icon.parentElement;
};

// =============================================================================
// Tests
// =============================================================================

describe('Media Manager Dashboard - table columns', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    mockMediaDashboard(mockMediaDashboardData);
  });

  test('Unused Media shows ID, Name, Type and Size columns', async () => {
    mockUnusedMedia({ rows: [buildDashboardMedia({ name: 'Logo.png' })], totalCount: 1 });
    renderMediaDashboardPage();
    await screen.findByText('Logo.png');

    expect(headersOf('Unused Media')).toEqual(['ID', 'Name', 'Type', 'Size']);
  });

  test('Unreleased Media shows ID and Name columns', async () => {
    mockUnreleasedMedia({ rows: [buildDashboardMedia({ name: 'Draft.mp4' })], totalCount: 1 });
    renderMediaDashboardPage();
    await screen.findByText('Draft.mp4');

    expect(headersOf('Unreleased Media')).toEqual(['ID', 'Name']);
  });

  test('each unused row shows its ID, name, type and size', async () => {
    mockUnusedMedia({
      rows: [
        buildDashboardMedia({
          mediaId: 42,
          name: 'Logo.png',
          mediaType: 'image',
          fileSizeFormatted: '2.4 MiB',
        }),
      ],
      totalCount: 1,
    });
    renderMediaDashboardPage();
    await screen.findByText('Logo.png');

    const cells = unusedCellsOf('Logo.png');
    expect(cells.map((c) => c.textContent)).toEqual(['42', 'Logo.png', 'image', '2.4 MiB']);
  });

  // Whether the Type column draws each media type distinctly is checked in the shared
  // contract file components/ui/table/__tests__/contracts/columns.dashboard.test.tsx.

  test('empty tables show their empty state', async () => {
    mockUnusedMedia(EMPTY_MEDIA_TABLE);
    mockUnreleasedMedia(EMPTY_MEDIA_TABLE);
    renderMediaDashboardPage();

    await within(getSection('Unused Media')).findByRole('heading', { name: 'No results found.' });
    expect(
      within(getSection('Unreleased Media')).getByRole('heading', { name: 'No results found.' }),
    ).toBeInTheDocument();
  });

  test('neither table has a search box, filters or selection checkboxes', async () => {
    mockUnusedMedia({ rows: buildDashboardMediaRows(2), totalCount: 2 });
    mockUnreleasedMedia({ rows: buildDashboardMediaRows(2), totalCount: 2 });
    renderMediaDashboardPage();
    await waitFor(() => expect(screen.getAllByText('Test Media 1')).toHaveLength(2));

    expect(screen.queryByRole('textbox')).not.toBeInTheDocument();
    expect(screen.queryByRole('button', { name: /filters/i })).not.toBeInTheDocument();
    expect(screen.queryByRole('checkbox')).not.toBeInTheDocument();
  });
});

describe('Media Manager Dashboard - table paging and sorting', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    mockMediaDashboard(mockMediaDashboardData);
    mockUnusedMedia(FIVE_OF_ELEVEN);
    mockUnreleasedMedia(FIVE_OF_ELEVEN);
  });

  const waitForBothTables = () =>
    waitFor(() => expect(screen.getAllByText('Test Media 1')).toHaveLength(2));

  // Unlike the Displays table, these hooks always send a direction, even when unsorted.
  test('on first load, Unused Media asks for the first five in ascending order', async () => {
    renderMediaDashboardPage();
    await waitForBothTables();

    expect(lastRequest(fetchUnusedMedia)).toEqual({
      start: 0,
      length: 5,
      sortBy: undefined,
      sortDir: 'asc',
    });
  });

  test('on first load, Unreleased Media asks for the first five in ascending order', async () => {
    renderMediaDashboardPage();
    await waitForBothTables();

    expect(lastRequest(fetchUnreleasedMedia)).toEqual({
      start: 0,
      length: 5,
      sortBy: undefined,
      sortDir: 'asc',
    });
  });

  test('moving Unused Media to page 2 asks for items 6 to 10 and leaves Unreleased alone', async () => {
    const user = userEvent.setup();
    renderMediaDashboardPage();
    await waitForBothTables();
    const unreleasedCalls = vi.mocked(fetchUnreleasedMedia).mock.calls.length;

    await user.click(within(getSection('Unused Media')).getByRole('button', { name: 'Next' }));

    await waitFor(() =>
      expect(lastRequest(fetchUnusedMedia)).toEqual(
        expect.objectContaining({ start: 5, length: 5 }),
      ),
    );
    expect(vi.mocked(fetchUnreleasedMedia).mock.calls.length).toBe(unreleasedCalls);
    expect(lastRequest(fetchUnreleasedMedia)).toEqual(expect.objectContaining({ start: 0 }));
  });

  test('sorting Unused Media by Name asks the server to sort by name, ascending first', async () => {
    const user = userEvent.setup();
    renderMediaDashboardPage();
    await waitForBothTables();

    await user.click(getSortToggle('Unused Media', 'Name'));

    await waitFor(() =>
      expect(lastRequest(fetchUnusedMedia)).toEqual(
        expect.objectContaining({
          sortBy: 'name',
          sortDir: 'asc',
        }),
      ),
    );
  });

  // The first request is already 'asc', so only the second click proves the direction is sent.
  test('sorting Unused Media by Name a second time asks for descending order', async () => {
    const user = userEvent.setup();
    renderMediaDashboardPage();
    await waitForBothTables();

    await user.click(getSortToggle('Unused Media', 'Name'));
    await waitFor(() => expect(lastRequest(fetchUnusedMedia)?.sortBy).toBe('name'));
    await user.click(getSortToggle('Unused Media', 'Name'));

    await waitFor(() =>
      expect(lastRequest(fetchUnusedMedia)).toEqual(
        expect.objectContaining({ sortBy: 'name', sortDir: 'desc' }),
      ),
    );
  });

  test('moving Unreleased Media to page 2 does not change the Unused Media request', async () => {
    const user = userEvent.setup();
    renderMediaDashboardPage();
    await waitForBothTables();
    const unusedCalls = vi.mocked(fetchUnusedMedia).mock.calls.length;

    await user.click(within(getSection('Unreleased Media')).getByRole('button', { name: 'Next' }));

    await waitFor(() =>
      expect(lastRequest(fetchUnreleasedMedia)).toEqual(expect.objectContaining({ start: 5 })),
    );
    expect(vi.mocked(fetchUnusedMedia).mock.calls.length).toBe(unusedCalls);
  });

  test('with 11 items, Unused Media offers three pages', async () => {
    renderMediaDashboardPage();
    await waitForBothTables();

    const section = within(getSection('Unused Media'));
    expect(section.getByRole('button', { name: '3' })).toBeInTheDocument();
    expect(section.queryByRole('button', { name: '4' })).not.toBeInTheDocument();
  });
});
