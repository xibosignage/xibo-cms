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
const TYPE = 2;

// DataTable's sort toggle is a bare <div onClick> with no role or keyboard access (Status
// Dashboard plan, K10), so it is found as the header's only cursor-pointer element.
const getSortToggle = (heading: 'Unused Media' | 'Unreleased Media', column: string) =>
  within(getTableUnder(heading))
    .getByRole('columnheader', { name: column })
    .querySelector('.cursor-pointer') as HTMLElement;

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

  // Column contract (§4.6): different media types must look different, and the same type
  // must look the same. Types are the lower-case strings /library actually sends.
  test('different media types look different in Type; the same type looks the same', async () => {
    mockUnusedMedia({
      rows: [
        buildDashboardMedia({ mediaId: 1, name: 'A.png', mediaType: 'image' }),
        buildDashboardMedia({ mediaId: 2, name: 'B.mp4', mediaType: 'video' }),
        buildDashboardMedia({ mediaId: 3, name: 'C.png', mediaType: 'image' }),
      ],
      totalCount: 3,
    });
    renderMediaDashboardPage();
    await screen.findByText('A.png');

    expect(unusedCellsOf('A.png')[TYPE]!.innerHTML).not.toBe(
      unusedCellsOf('B.mp4')[TYPE]!.innerHTML,
    );
    expect(unusedCellsOf('A.png')[TYPE]!.innerHTML).toBe(unusedCellsOf('C.png')[TYPE]!.innerHTML);
  });

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

  test('sorting Unused Media by Name asks the server to sort by name', async () => {
    const user = userEvent.setup();
    renderMediaDashboardPage();
    await waitForBothTables();

    await user.click(getSortToggle('Unused Media', 'Name'));

    await waitFor(() =>
      expect(lastRequest(fetchUnusedMedia)).toEqual(
        expect.objectContaining({
          sortBy: 'name',
          sortDir: expect.stringMatching(/^(asc|desc)$/),
        }),
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
