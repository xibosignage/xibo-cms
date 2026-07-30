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

import { screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { vi, beforeEach, describe, test, expect } from 'vitest';

import {
  EMPTY_MODULE_TABLE,
  MULTIPLE_MODULES,
  REGION_SPECIFIC_MODULE,
  mockModule,
  SINGLE_MODULE,
} from './fixtures/module';
import { renderModulesPage } from './helpers/renderModulesPage';
import { mockFetchModules } from './mocks/moduleApi';

import { fetchModules } from '@/services/moduleApi';
import { testQueryClient } from '@/setupTests';

// =============================================================================
// Module mocks
// =============================================================================

vi.mock('@/services/moduleApi');
vi.mock('@/services/userApi', () => ({
  fetchUserPreference: vi.fn().mockResolvedValue(null),
  saveUserPreference: vi.fn().mockResolvedValue(undefined),
}));
vi.mock('@/components/ui/modals/Modal');
vi.mock('../hooks/useModuleFilterOptions', () => ({
  useModuleFilterOptions: () => ({ filterOptions: [], isLoading: false }),
}));

// =============================================================================
// Tests — Modules page default state
// =============================================================================

describe('Modules page - render', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
  });

  test('the table shows a row for each module', async () => {
    mockFetchModules(SINGLE_MODULE);
    renderModulesPage();

    expect(await screen.findByText(mockModule.name)).toBeInTheDocument();
  });

  test('an empty state is shown when there are no modules', async () => {
    mockFetchModules(EMPTY_MODULE_TABLE);
    renderModulesPage();

    expect(await screen.findByText('No results found.')).toBeInTheDocument();
  });

  // Modules are installed on the server, not created in the UI — so unlike the
  // CRUD pages there is deliberately no "Add" button.
  test('there is no "Add" button', async () => {
    mockFetchModules(SINGLE_MODULE);
    renderModulesPage();
    await screen.findByText(mockModule.name);

    expect(screen.queryByRole('button', { name: /^add/i })).not.toBeInTheDocument();
  });

  test('the search box is present with the correct placeholder', async () => {
    mockFetchModules(SINGLE_MODULE);
    renderModulesPage();

    expect(await screen.findByPlaceholderText('Search modules...')).toBeInTheDocument();
  });

  test('the Filters button is visible', async () => {
    mockFetchModules(SINGLE_MODULE);
    renderModulesPage();

    expect(await screen.findByRole('button', { name: /filters/i })).toBeInTheDocument();
  });

  test('the tab navigation includes "Modules"', async () => {
    mockFetchModules(SINGLE_MODULE);
    renderModulesPage();

    const tabs = await screen.findAllByRole('button', { name: /^modules$/i });
    expect(tabs.length).toBeGreaterThan(0);
  });

  test('modules are requested 25 to a page', async () => {
    mockFetchModules(SINGLE_MODULE);
    renderModulesPage();
    await screen.findByText(mockModule.name);

    expect(fetchModules).toHaveBeenCalledWith(expect.objectContaining({ start: 0, length: 25 }));
  });

  test('a failed fetch shows the error message in an alert above the table', async () => {
    // A plain Error has no response.status, so useModuleData does NOT re-throw it
    // (it only re-throws HTTP >= 500) — it surfaces in the inline alert instead.
    vi.mocked(fetchModules).mockRejectedValueOnce(new Error('Network failure'));
    renderModulesPage();

    const alert = await screen.findByRole('alert');
    expect(alert).toHaveTextContent('Network failure');
  });

  test('all default columns are visible on first load', async () => {
    mockFetchModules(SINGLE_MODULE);
    renderModulesPage();
    await screen.findByText(mockModule.name);

    for (const name of [
      /^name$/i,
      /^description$/i,
      /^library media$/i,
      /^default duration$/i,
      /^preview enabled$/i,
      /^assignable$/i,
      /^enabled$/i,
      /^errors$/i,
    ]) {
      expect(screen.getByRole('columnheader', { name })).toBeInTheDocument();
    }
  });

  test('every row exposes a Configure quick-action', async () => {
    mockFetchModules(SINGLE_MODULE);
    renderModulesPage();
    await screen.findByText(mockModule.name);

    expect(screen.getByRole('button', { name: /^configure$/i })).toBeInTheDocument();
  });

  // A library-media module (regionSpecific === 0) has no cache to clear.
  test('a library-media module does not offer Clear Cache', async () => {
    const user = userEvent.setup();
    mockFetchModules(SINGLE_MODULE);
    renderModulesPage();
    await screen.findByText(mockModule.name);

    await user.click(screen.getByRole('button', { name: /more actions/i }));
    // Wait until the menu is open (its Configure item appears alongside the inline one).
    await waitFor(() =>
      expect(screen.getAllByRole('button', { name: /^configure$/i }).length).toBeGreaterThan(1),
    );
    expect(screen.queryByRole('button', { name: /clear cache/i })).not.toBeInTheDocument();
  }, 20000);

  test('a region-specific module offers Clear Cache', async () => {
    const user = userEvent.setup();
    mockFetchModules({ rows: [REGION_SPECIFIC_MODULE], totalCount: 1 });
    renderModulesPage();
    await screen.findByText(REGION_SPECIFIC_MODULE.name);

    await user.click(screen.getByRole('button', { name: /more actions/i }));

    expect(await screen.findByRole('button', { name: /clear cache/i })).toBeInTheDocument();
  }, 20000);

  // Selection checkboxes are injected by DataTable, but the page defines no bulk
  // actions — so selecting rows must NOT reveal a bulk-action toolbar.
  test('selection checkboxes render but there is no bulk-action toolbar', async () => {
    const user = userEvent.setup();
    mockFetchModules(MULTIPLE_MODULES);
    renderModulesPage();
    await screen.findByText('Image');

    // This query excludes the header's "select all" checkbox (its
    // accessible name is "Select all rows", which doesn't match /select
    // row/i), so it's one checkbox per row — MULTIPLE_MODULES has 2 rows.
    const checkboxes = screen.getAllByRole('checkbox', { name: /select row/i });
    expect(checkboxes.length).toBe(2);

    await user.click(checkboxes[0]!);

    expect(screen.queryByRole('button', { name: /selected/i })).not.toBeInTheDocument();
  });
});
