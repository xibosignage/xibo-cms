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

import { screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { vi, beforeEach, describe, test, expect } from 'vitest';

import {
  EMPTY_DAYPART_TABLE,
  mockDaypart,
  SINGLE_DAYPART,
  SINGLE_SPECIAL_DAYPART,
} from './fixtures/daypart';
import { renderDaypartPage } from './helpers/renderDaypartPage';
import { mockFetchDaypart } from './mocks/daypartApi';

import { fetchDaypart } from '@/services/daypartApi';
import { testQueryClient } from '@/setupTests';

// =============================================================================
// Module mocks
// =============================================================================

vi.mock('@/services/daypartApi');
vi.mock('@/services/userApi', () => ({
  fetchUserPreference: vi.fn().mockResolvedValue(null),
  saveUserPreference: vi.fn().mockResolvedValue(undefined),
}));
vi.mock('@/components/ui/modals/Modal');

vi.mock('../hooks/useDaypartFilterOptions', () => ({
  useDaypartFilterOptions: () => ({ filterOptions: [], isLoading: false }),
}));

// =============================================================================
// Tests
// =============================================================================

describe('Dayparting page - render', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
  });

  // The table shows a row for each daypart returned by the API.
  test('the table renders a row for each daypart', async () => {
    mockFetchDaypart(SINGLE_DAYPART);
    renderDaypartPage();

    expect(await screen.findByText(mockDaypart.name)).toBeInTheDocument();
  });

  // With no dayparts the table shows its empty state instead of rows.
  test('an empty state is shown when no dayparts exist', async () => {
    mockFetchDaypart(EMPTY_DAYPART_TABLE);
    renderDaypartPage();

    // Wait for hydration / first fetch to settle before asserting empty state.
    await screen.findByRole('button', { name: /add daypart/i });

    expect(await screen.findByText('No results found.')).toBeInTheDocument();
  });

  // The primary create button is visible once preferences have hydrated.
  test('the "Add Daypart" button is visible', async () => {
    mockFetchDaypart(SINGLE_DAYPART);
    renderDaypartPage();

    expect(await screen.findByRole('button', { name: /add daypart/i })).toBeInTheDocument();
  });

  // All of the default-visible columns render their headers on first load.
  test('the default columns are visible', async () => {
    mockFetchDaypart(SINGLE_DAYPART);
    renderDaypartPage();
    await screen.findByText(mockDaypart.name);

    expect(screen.getByRole('columnheader', { name: /^id$/i })).toBeInTheDocument();
    expect(screen.getByRole('columnheader', { name: /^name$/i })).toBeInTheDocument();
    expect(screen.getByRole('columnheader', { name: /^start time$/i })).toBeInTheDocument();
    expect(screen.getByRole('columnheader', { name: /^end time$/i })).toBeInTheDocument();
    expect(screen.getByRole('columnheader', { name: /^description$/i })).toBeInTheDocument();
  });

  // The search box has the expected placeholder text.
  test('the search input has the placeholder "Search daypart..."', async () => {
    mockFetchDaypart(SINGLE_DAYPART);
    renderDaypartPage();
    await screen.findByText(mockDaypart.name);

    expect(screen.getByPlaceholderText('Search daypart...')).toBeInTheDocument();
  });

  // The Filters toggle button is present in the toolbar.
  test('the Filters button is visible', async () => {
    mockFetchDaypart(SINGLE_DAYPART);
    renderDaypartPage();
    await screen.findByText(mockDaypart.name);

    expect(screen.getByRole('button', { name: /filters/i })).toBeInTheDocument();
  });

  // The Schedule tab nav surfaces the active "Dayparting" tab.
  test('the tab navigation includes the "Dayparting" tab', async () => {
    mockFetchDaypart(SINGLE_DAYPART);
    renderDaypartPage();
    await screen.findByText(mockDaypart.name);

    expect(screen.getByRole('button', { name: /dayparting/i })).toBeInTheDocument();
  });

  // The first page request asks for 10 rows starting at offset 0.
  test('dayparts are paginated at 10 per page by default', async () => {
    mockFetchDaypart(SINGLE_DAYPART);
    renderDaypartPage();
    await screen.findByText(mockDaypart.name);

    expect(fetchDaypart).toHaveBeenCalledWith(expect.objectContaining({ start: 0, length: 10 }));
  });

  // A failed fetch surfaces the error message in an alert above the table.
  test('a fetch error renders the error alert above the table', async () => {
    vi.mocked(fetchDaypart).mockRejectedValue(new Error('Something went wrong.'));
    renderDaypartPage();

    expect(await screen.findByRole('alert')).toHaveTextContent('Something went wrong.');
  });

  // A normal daypart exposes Edit and Delete (quick actions) plus Share.
  test('a normal daypart row shows Edit and Delete quick actions', async () => {
    mockFetchDaypart(SINGLE_DAYPART);
    renderDaypartPage();
    await screen.findByText(mockDaypart.name);

    expect(screen.getByRole('button', { name: /^edit$/i })).toBeInTheDocument();
    expect(screen.getByRole('button', { name: /^delete$/i })).toBeInTheDocument();
  });

  // Share is not a quick action — it lives in the row's overflow menu.
  test('a normal daypart row exposes the Share action in the overflow menu', async () => {
    const user = userEvent.setup();
    mockFetchDaypart(SINGLE_DAYPART);
    renderDaypartPage();
    await screen.findByText(mockDaypart.name);

    await user.click(screen.getByRole('button', { name: /more actions/i }));

    expect(await screen.findByRole('button', { name: /^share$/i })).toBeInTheDocument();
  });

  // A special daypart (isAlways) cannot be edited or deleted — only shared —
  // so neither the Edit nor the Delete quick action renders.
  test('a special (Always) daypart hides the Edit and Delete actions', async () => {
    mockFetchDaypart(SINGLE_SPECIAL_DAYPART);
    renderDaypartPage();
    await screen.findByText('Always');

    expect(screen.queryByRole('button', { name: /^edit$/i })).not.toBeInTheDocument();
    expect(screen.queryByRole('button', { name: /^delete$/i })).not.toBeInTheDocument();
    // The overflow menu (holding Share) is still available.
    expect(screen.getByRole('button', { name: /more actions/i })).toBeInTheDocument();
  });
});
