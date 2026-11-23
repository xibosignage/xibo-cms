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
// Test type: Page integration — the All Reports card list
// =============================================================================

import { screen, waitFor, within } from '@testing-library/react';
import { vi, beforeEach, describe, test, expect } from 'vitest';

import { ALL_REPORTS } from './fixtures/allReports';
import { findCard, queryCard, renderAllReportsPage } from './helpers/renderAllReportsPage';
import { mockFetchReports } from './mocks/reportApi';

import { fetchReports } from '@/services/reportApi';
import { fetchUserPreference } from '@/services/userApi';
import { testQueryClient } from '@/setupTests';
import { deferred } from '@/testUtils/deferred';

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
// Tests
// =============================================================================

describe('All Reports page - render', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    // clearAllMocks keeps earlier behaviour; reset what this file overrides.
    vi.mocked(fetchUserPreference).mockReset().mockResolvedValue(null);
    vi.mocked(fetchReports).mockReset();
    mockFetchReports(ALL_REPORTS);
  });

  test(
    "each report appears under its category, in the server's order",
    { tags: ['smoke'] },
    async () => {
      renderAllReportsPage();

      await findCard('Proof of Play');
      const headings = screen.getAllByRole('heading', { level: 3 }).map((h) => h.textContent);
      expect(headings).toEqual(['Proof of Play', 'Audit', 'Library']);
      expect(await findCard('Summary by Layout, Media or Event')).toBeInTheDocument();
    },
  );

  test("hidden reports don't appear", async () => {
    renderAllReportsPage();

    await findCard('Proof of Play');
    expect(queryCard('Hidden Proof of Play')).not.toBeInTheDocument();
  });

  test("a category whose reports are all hidden doesn't appear", async () => {
    renderAllReportsPage();

    await findCard('Proof of Play');
    expect(screen.queryByRole('heading', { name: 'Display' })).not.toBeInTheDocument();
  });

  test('each card is labelled by its report type', async () => {
    renderAllReportsPage();

    expect(within(await findCard('Proof of Play')).getByRole('button')).toHaveTextContent(
      'View Report',
    );
    expect(
      within(await findCard('Summary by Layout, Media or Event')).getByRole('button'),
    ).toHaveTextContent('View Chart');
    expect(within(await findCard('Export Stats')).getByRole('button')).toHaveTextContent('Export');
  });

  test('nothing is requested or shown until the saved settings load', async () => {
    const pref = deferred<null>();
    vi.mocked(fetchUserPreference).mockReturnValueOnce(pref.promise);
    renderAllReportsPage({ hydrated: false });

    await waitFor(() => expect(fetchUserPreference).toHaveBeenCalled());
    expect(fetchReports).not.toHaveBeenCalled();
    expect(queryCard('Proof of Play')).not.toBeInTheDocument();

    pref.resolve(null);
    await findCard('Proof of Play');
  });

  test('a failed load says so', async () => {
    vi.mocked(fetchReports).mockRejectedValueOnce(new Error('Server error'));
    renderAllReportsPage();

    await screen.findByText('Failed to load reports. Please try again.');
    expect(screen.queryAllByRole('link')).toHaveLength(0);
  });
});
