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

// mocks/userApi must be imported before anything that imports userApi.
import './mocks/userApi';

import { screen } from '@testing-library/react';
import { vi, beforeEach, describe, test, expect } from 'vitest';

import { EMPTY_FONT_TABLE, mockFont, SINGLE_FONT } from './fixtures/font';
import { renderFontsPage } from './helpers/renderFontsPage';
import { mockFetchFonts } from './mocks/fontApi';

import { fetchFonts } from '@/services/fontApi';
import { testQueryClient } from '@/setupTests';
import { formatFileSize } from '@/utils/formatters';

// =============================================================================
// Module mocks
// =============================================================================

vi.mock('@/services/fontApi');

vi.mock('@/hooks/useFilteredTabs', () => ({
  useFilteredTabs: vi.fn(() => [{ name: 'Fonts', path: '/administration/fonts' }]),
}));

vi.mock('@/components/ui/modals/Modal');

// =============================================================================
// Tests
// =============================================================================

describe('Fonts page - render', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
  });

  // ---------------------------------------------------------------------------
  // "Upload Font" is the primary CTA — mockUser is superAdmin so font.add passes.
  // ---------------------------------------------------------------------------
  test('Upload Font button is present', async () => {
    mockFetchFonts(EMPTY_FONT_TABLE);
    renderFontsPage();

    expect(await screen.findByRole('button', { name: /upload font/i })).toBeEnabled();
  });

  // ---------------------------------------------------------------------------
  // Known bug (xibosignage/xibo#4048): the search input has no aria-label —
  // query by placeholder.
  // ---------------------------------------------------------------------------
  test('search input is present with correct placeholder', async () => {
    mockFetchFonts(EMPTY_FONT_TABLE);
    renderFontsPage();

    await screen.findByPlaceholderText('Search fonts...');
  });

  test(
    'table shows the font row with its visible columns once data has loaded',
    { tags: ['smoke'] },
    async () => {
      mockFetchFonts(SINGLE_FONT);
      renderFontsPage();

      // name and familyName are both 'Roboto' — two cells
      expect(await screen.findAllByText(mockFont.name)).toHaveLength(2);
      expect(screen.getByText(mockFont.fileName)).toBeInTheDocument();
      expect(screen.getByText(mockFont.modifiedAt)).toBeInTheDocument();
      expect(screen.getByText(mockFont.modifiedBy)).toBeInTheDocument();
      expect(screen.getByText(formatFileSize(mockFont.size))).toBeInTheDocument();
    },
  );

  test('table shows a header for each visible column', async () => {
    mockFetchFonts(SINGLE_FONT);
    renderFontsPage();

    await screen.findByText(mockFont.fileName);

    for (const header of [
      /^name$/i,
      /^file name$/i,
      /^family name$/i,
      /^modified$/i,
      /^modified by$/i,
      /^size$/i,
    ]) {
      expect(screen.getByRole('columnheader', { name: header })).toBeInTheDocument();
    }
  });

  // ---------------------------------------------------------------------------
  // The page hides the ID and Created columns by default (columnVisibility).
  // ---------------------------------------------------------------------------
  test('ID and Created columns are hidden by default', async () => {
    mockFetchFonts(SINGLE_FONT);
    renderFontsPage();

    await screen.findByText(mockFont.fileName);

    expect(screen.getByRole('columnheader', { name: /^name$/i })).toBeInTheDocument();
    expect(screen.queryByRole('columnheader', { name: /^id$/i })).not.toBeInTheDocument();
    expect(screen.queryByRole('columnheader', { name: /^created$/i })).not.toBeInTheDocument();
  });

  test('shows "No results found." when there are no fonts', async () => {
    mockFetchFonts(EMPTY_FONT_TABLE);
    renderFontsPage();

    await screen.findByRole('button', { name: /upload font/i });

    await screen.findByText('No results found.');
  });

  test('Filters button is present', async () => {
    mockFetchFonts(EMPTY_FONT_TABLE);
    renderFontsPage();

    expect(await screen.findByRole('button', { name: /filters/i })).toBeEnabled();
  });

  test('shows an error banner when loading fonts fails', async () => {
    vi.mocked(fetchFonts).mockRejectedValue(new Error('Server error'));
    renderFontsPage();

    await screen.findByText(/server error/i);
    expect(fetchFonts).toHaveBeenCalled();
  });
});
