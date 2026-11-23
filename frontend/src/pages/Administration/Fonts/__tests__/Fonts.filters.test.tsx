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

import { screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { vi, beforeEach, describe, test, expect } from 'vitest';

import { mockFont, SINGLE_FONT } from './fixtures/font';
import { renderFontsPage } from './helpers/renderFontsPage';
import { mockFetchFonts } from './mocks/fontApi';

import { fetchFonts } from '@/services/fontApi';
import { testQueryClient } from '@/setupTests';

// =============================================================================
// Module mocks
// =============================================================================

vi.mock('@/services/fontApi');

vi.mock('@/hooks/useFilteredTabs', () => ({
  useFilteredTabs: vi.fn(() => [{ name: 'Fonts', path: '/administration/fonts' }]),
}));

vi.mock('@/components/ui/modals/Modal');

// useFontFilterOptions is NOT mocked — it returns static config (ID + Name).
//
// The ID filter (InputFilter, type number) has no accessible name: its <label>
// is not linked to the input and the placeholder is ' '. It is the only number
// input on the page, so it is queried as the unnamed spinbutton. InputFilter
// converts the value with Number(), so fetchFonts receives id as a number.

// =============================================================================
// Helpers
// =============================================================================

// Search and Filters are disabled until isHydrated; the file name only appears
// once the first fetch has rendered a row.
const waitForPageReady = () => screen.findByText(mockFont.fileName);

// =============================================================================
// Tests
// =============================================================================

describe('Fonts page - filters', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    mockFetchFonts(SINGLE_FONT);
  });

  // ---------------------------------------------------------------------------
  // Panel visibility
  // ---------------------------------------------------------------------------

  test('filter panel controls are not accessible before Filters is clicked', async () => {
    renderFontsPage();
    await waitForPageReady();

    expect(screen.queryByRole('button', { name: /reset/i })).not.toBeInTheDocument();
    expect(screen.queryByRole('textbox', { name: /^name$/i })).not.toBeInTheDocument();
  });

  test('clicking Filters shows the ID and Name filter fields', async () => {
    const user = userEvent.setup();
    renderFontsPage();
    await waitForPageReady();

    await user.click(screen.getByRole('button', { name: /filters/i }));

    await screen.findByRole('button', { name: /reset/i });
    expect(screen.getByRole('spinbutton')).toBeInTheDocument();
    expect(screen.getByRole('textbox', { name: /^name$/i })).toBeInTheDocument();
  });

  test('clicking Filters again hides the filter panel', async () => {
    const user = userEvent.setup();
    renderFontsPage();
    await waitForPageReady();

    const filtersButton = screen.getByRole('button', { name: /filters/i });
    await user.click(filtersButton);
    await screen.findByRole('button', { name: /reset/i });

    await user.click(filtersButton);

    await waitFor(() => {
      expect(screen.queryByRole('button', { name: /reset/i })).not.toBeInTheDocument();
    });
  });

  // ---------------------------------------------------------------------------
  // Search
  // ---------------------------------------------------------------------------

  test('typing in the search input calls fetchFonts with the name', async () => {
    const user = userEvent.setup();
    renderFontsPage();
    await waitForPageReady();

    await user.type(screen.getByPlaceholderText('Search fonts...'), 'Rob');

    await waitFor(() => {
      expect(fetchFonts).toHaveBeenCalledWith(expect.objectContaining({ name: 'Rob' }));
    });
  });

  // ---------------------------------------------------------------------------
  // Starts on page 2 so the reset is observable: without it the search would
  // be sent with start: 10.
  // ---------------------------------------------------------------------------
  test('typing in the search input resets pagination to the first page', async () => {
    const user = userEvent.setup();
    // 30 fonts at 10 per page = 3 pages
    mockFetchFonts({ ...SINGLE_FONT, totalCount: 30 });
    renderFontsPage();
    await waitForPageReady();

    await user.click(screen.getByRole('button', { name: /^next$/i }));
    await waitFor(() => {
      expect(fetchFonts).toHaveBeenCalledWith(expect.objectContaining({ start: 10 }));
    });

    await user.type(screen.getByPlaceholderText('Search fonts...'), 'Rob');

    await waitFor(() => {
      expect(fetchFonts).toHaveBeenCalledWith(expect.objectContaining({ start: 0, name: 'Rob' }));
    });
    expect(fetchFonts).not.toHaveBeenCalledWith(
      expect.objectContaining({ start: 10, name: 'Rob' }),
    );
  });

  // ---------------------------------------------------------------------------
  // Advanced filters
  // ---------------------------------------------------------------------------

  test('entering an ID updates the query and resets to page 1', async () => {
    const user = userEvent.setup();
    renderFontsPage();
    await waitForPageReady();

    await user.click(screen.getByRole('button', { name: /filters/i }));
    await user.type(await screen.findByRole('spinbutton'), '5');

    await waitFor(() => {
      expect(fetchFonts).toHaveBeenCalledWith(expect.objectContaining({ id: 5, start: 0 }));
    });
  });

  test('entering a Name updates the query and resets to page 1', async () => {
    const user = userEvent.setup();
    renderFontsPage();
    await waitForPageReady();

    await user.click(screen.getByRole('button', { name: /filters/i }));
    await user.type(await screen.findByRole('textbox', { name: /^name$/i }), 'Open');

    await waitFor(() => {
      expect(fetchFonts).toHaveBeenCalledWith(expect.objectContaining({ name: 'Open', start: 0 }));
    });
  });

  test('switching the Name operator to AND sends logicalOperatorName AND', async () => {
    const user = userEvent.setup();
    renderFontsPage();
    await waitForPageReady();

    // INITIAL_FILTER_STATE sets OR, which is always sent
    expect(fetchFonts).toHaveBeenCalledWith(expect.objectContaining({ logicalOperatorName: 'OR' }));

    await user.click(screen.getByRole('button', { name: /filters/i }));
    await screen.findByRole('button', { name: /reset/i });
    await user.click(screen.getByRole('button', { name: /^or$/i }));
    await user.click(await screen.findByRole('button', { name: /^and$/i }));

    await waitFor(() => {
      expect(fetchFonts).toHaveBeenCalledWith(
        expect.objectContaining({ logicalOperatorName: 'AND' }),
      );
    });
  });

  test('the Filters button shows the active filter count', async () => {
    const user = userEvent.setup();
    renderFontsPage();
    await waitForPageReady();

    await user.click(screen.getByRole('button', { name: /filters/i }));
    await user.type(await screen.findByRole('spinbutton'), '5');

    // ID input is debounced (300 ms); the 3 s asyncUtilTimeout from setupTests.ts covers it.
    await screen.findByRole('button', { name: /filters\s*1/i });
  });

  // ---------------------------------------------------------------------------
  // Reset
  // ---------------------------------------------------------------------------

  test('clicking Reset clears the filter inputs and keeps the panel open', async () => {
    const user = userEvent.setup();
    renderFontsPage();
    await waitForPageReady();

    await user.click(screen.getByRole('button', { name: /filters/i }));
    await user.type(await screen.findByRole('spinbutton'), '5');
    await user.type(screen.getByRole('textbox', { name: /^name$/i }), 'Open');

    await waitFor(() => {
      expect(fetchFonts).toHaveBeenCalledWith(expect.objectContaining({ id: 5, name: 'Open' }));
    });

    await user.click(screen.getByRole('button', { name: /reset/i }));

    await waitFor(() => {
      expect(screen.getByRole('textbox', { name: /^name$/i })).toHaveValue('');
    });
    expect(screen.getByRole('spinbutton')).toHaveValue(null);
    expect(screen.getByRole('button', { name: /reset/i })).toBeInTheDocument();
    // Active filter badge cleared. No refetch is asserted: the reset query key
    // matches the initial one, so React Query serves it from cache (staleTime 1m).
    expect(screen.getByRole('button', { name: /^filters$/i })).toBeInTheDocument();
  }, 20_000);
});
