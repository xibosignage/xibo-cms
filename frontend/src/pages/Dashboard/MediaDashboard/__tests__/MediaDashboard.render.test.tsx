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
import type * as RechartsModule from 'recharts';
import { vi, beforeEach, describe, test, expect, onTestFinished } from 'vitest';

import { buildLibraryType, buildMediaDashboardData } from './fixtures/mediaDashboard';
import {
  getSection,
  getStatCardValue,
  renderMediaDashboardPage,
} from './helpers/renderMediaDashboardPage';
import {
  mockMediaDashboard,
  mockMediaDashboardError,
  mockMediaDashboardPending,
} from './mocks/mediaDashboardApi';

import { fetchMediaDashboard } from '@/services/dashboardApi';
import { testQueryClient } from '@/setupTests';
import { knownFailure } from '@/testUtils/knownFailure';
import { formatFileSize } from '@/utils/formatters';

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
// Recharts' ResponsiveContainer measures 0×0 in JSDOM and renders nothing; give it a size.
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
// Tests
// =============================================================================

describe('Media Manager Dashboard - summary cards', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
  });

  test('Library Count shows how many media items there are', async () => {
    mockMediaDashboard(buildMediaDashboardData({ countOf: 245 }));
    renderMediaDashboardPage();

    await waitFor(() => expect(getStatCardValue('Library Count')).toHaveTextContent(/^245$/));
  });

  // The page calls toLocaleString() with no locale, so the separator follows the browser's
  // locale. Rule 1.12: run it under two locales whose separators differ, by setting the default
  // locale toLocaleString() falls back to, so neither the machine's own setting nor one
  // hard-coded locale decides the result.
  test.each([
    ['en-US', '12,345'],
    ['de-DE', '12.345'],
  ])('in %s, Library Count reads %s', async (locale, expected) => {
    const toLocaleString = Number.prototype.toLocaleString;
    const browserLocale = vi.spyOn(Number.prototype, 'toLocaleString').mockImplementation(function (
      this: number,
      locales?: Intl.LocalesArgument,
      options?: Intl.NumberFormatOptions,
    ) {
      return toLocaleString.call(this, locales ?? locale, options);
    });
    onTestFinished(() => browserLocale.mockRestore());
    mockMediaDashboard(buildMediaDashboardData({ countOf: 12345 }));
    renderMediaDashboardPage();

    await waitFor(() => expect(getStatCardValue('Library Count').textContent).toBe(expected));
  });

  test('Library Size shows the size exactly as the server sent it', async () => {
    mockMediaDashboard(buildMediaDashboardData({ size: '4.2 GiB' }));
    renderMediaDashboardPage();

    await waitFor(() => expect(getStatCardValue('Library Size')).toHaveTextContent('4.2 GiB'));
  });

  // countOf 0 is a real value. A check on the number (`countOf ? … : '—'`) would show a dash
  // for an empty library; the current code formats first, and '0' is a non-empty string.
  test('an empty library shows 0 in Library Count, not a dash', async () => {
    mockMediaDashboard(buildMediaDashboardData({ countOf: 0, types: [] }));
    renderMediaDashboardPage();

    await waitFor(() => expect(getStatCardValue('Library Count')).toHaveTextContent(/^0$/));
  });
});

describe('Media Manager Dashboard - loading', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
  });

  test('while loading, both cards show a placeholder and both charts say Loading', async () => {
    mockMediaDashboardPending();
    renderMediaDashboardPage();

    expect(await screen.findAllByText('Loading...')).toHaveLength(2);
    expect(getStatCardValue('Library Count')).toHaveTextContent(/^$/);
    expect(getStatCardValue('Library Size')).toHaveTextContent(/^$/);
  });
});

describe('Media Manager Dashboard - charts', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
  });

  test('each chart has its title, its centre value and its centre label', async () => {
    mockMediaDashboard(buildMediaDashboardData({ countOf: 10, size: '1 GiB' }));
    renderMediaDashboardPage();

    await screen.findByRole('heading', { name: 'No. of Media Items' });
    const countChart = within(getSection('No. of Media Items'));
    await countChart.findByText('10');
    expect(countChart.getByText('Total Items')).toBeInTheDocument();

    const sizeChart = within(getSection('Size of Media Items'));
    expect(sizeChart.getByText('1 GiB')).toBeInTheDocument();
    expect(sizeChart.getByText('Total Size')).toBeInTheDocument();
  });

  // The two charts read different fields of the same server entries: count vs bytes.
  test('the count legend shows item counts and the size legend shows readable sizes', async () => {
    mockMediaDashboard(
      buildMediaDashboardData({
        types: [
          buildLibraryType({ title: 'Image', count: 3, size: 2048 }),
          buildLibraryType({ title: 'Video', count: 1, size: 1048576 }),
        ],
      }),
    );
    renderMediaDashboardPage();

    await screen.findByText('Image: 3');
    const countChart = within(getSection('No. of Media Items'));
    expect(countChart.getByText('Image: 3')).toBeInTheDocument();
    expect(countChart.getByText('Video: 1')).toBeInTheDocument();

    const sizeChart = within(getSection('Size of Media Items'));
    expect(sizeChart.getByText(`Image: ${formatFileSize(2048)}`)).toBeInTheDocument();
    expect(sizeChart.getByText(`Video: ${formatFileSize(1048576)}`)).toBeInTheDocument();
  });
});

describe('Media Manager Dashboard - error handling', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
  });

  // Pins current behaviour: a failed load is shown only as dashes.
  test('a failed load shows a dash in both cards', async () => {
    mockMediaDashboardError('Server unavailable');
    renderMediaDashboardPage();

    await waitFor(() => expect(getStatCardValue('Library Count')).toHaveTextContent(/^—$/));
    expect(getStatCardValue('Library Size')).toHaveTextContent(/^—$/);
    expect(fetchMediaDashboard).toHaveBeenCalled();
  });

  // xibosignage/xibo#4036: the page never reads the stats query's error, so
  // a failure looks like "no data".
  knownFailure(
    'a failed load tells the user something went wrong',
    async () => {
      mockMediaDashboardError('Server unavailable');
      renderMediaDashboardPage();
      // Control: the failure has reached the page (both cards fall back to a dash).
      await waitFor(() => expect(getStatCardValue('Library Count')).toHaveTextContent(/^—$/));
    },
    async () => expect(await screen.findByRole('alert')).toHaveTextContent('Server unavailable'),
  );
});
