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

import { screen, waitFor, within } from '@testing-library/react';
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

// =============================================================================
// Helpers
// =============================================================================

// The sort button sits in the column header (DataTable.tsx).
// Scoped to the column header so it can't match another button with the same name.
const sortControlFor = (columnName: RegExp) =>
  within(screen.getByRole('columnheader', { name: columnName })).getByRole('button');

// =============================================================================
// Tests
// =============================================================================

describe('Fonts page - sort', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    mockFetchFonts(SINGLE_FONT);
  });

  test('the first request has no sortBy or sortDir', async () => {
    renderFontsPage();

    await screen.findByText(mockFont.fileName);

    expect(fetchFonts).toHaveBeenLastCalledWith(
      expect.objectContaining({ sortBy: undefined, sortDir: undefined }),
    );
  });

  test('clicking the Name header sorts ascending, then descending', async () => {
    const user = userEvent.setup();
    renderFontsPage();
    await screen.findByText(mockFont.fileName);

    await user.click(sortControlFor(/^name$/i));

    await waitFor(() => {
      expect(fetchFonts).toHaveBeenLastCalledWith(
        expect.objectContaining({ sortBy: 'name', sortDir: 'asc' }),
      );
    });

    await user.click(sortControlFor(/^name$/i));

    await waitFor(() => {
      expect(fetchFonts).toHaveBeenLastCalledWith(
        expect.objectContaining({ sortBy: 'name', sortDir: 'desc' }),
      );
    });
  });

  test('clicking a different header sorts by that column', async () => {
    const user = userEvent.setup();
    renderFontsPage();
    await screen.findByText(mockFont.fileName);

    await user.click(sortControlFor(/^file name$/i));

    await waitFor(() => {
      expect(fetchFonts).toHaveBeenLastCalledWith(
        expect.objectContaining({ sortBy: 'fileName', sortDir: 'asc' }),
      );
    });
  });
});
