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

import { screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { vi, beforeEach, describe, test, expect } from 'vitest';

import { SINGLE_MODULE, mockModule } from './fixtures/module';
import { renderModulesPage } from './helpers/renderModulesPage';
import { mockFetchModules } from './mocks/moduleApi';

import { fetchModules } from '@/services/moduleApi';
import { testQueryClient } from '@/setupTests';

// =============================================================================
// Module mocks
// =============================================================================
// NOTE: this file does NOT stub useModuleFilterOptions — it needs the real
// Name filter field to render.

vi.mock('@/services/moduleApi');
vi.mock('@/services/userApi', () => ({
  fetchUserPreference: vi.fn().mockResolvedValue(null),
  saveUserPreference: vi.fn().mockResolvedValue(undefined),
}));
vi.mock('@/components/ui/modals/Modal');

const waitForPageReady = () => screen.findByText(mockModule.name);

const getNameFilterInput = (): HTMLInputElement => {
  const input = screen
    .getAllByRole('textbox')
    .find((el) => el.getAttribute('placeholder') !== 'Search modules...');
  if (!input) throw new Error('Name filter input not found');
  return input as HTMLInputElement;
};

// =============================================================================
// Tests — Modules page search + filter panel
// =============================================================================

describe('Modules page - filters', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    mockFetchModules(SINGLE_MODULE);
  });

  test('the filter panel is hidden by default', async () => {
    renderModulesPage();
    await waitForPageReady();

    expect(screen.queryByRole('button', { name: /reset/i })).not.toBeInTheDocument();
  });

  test('clicking Filters opens the panel with the Name filter', async () => {
    const user = userEvent.setup();
    renderModulesPage();
    await waitForPageReady();

    await user.click(screen.getByRole('button', { name: /filters/i }));

    await screen.findByRole('button', { name: /reset/i });
    // Search box + Name filter box are now both accessible.
    await waitFor(() => expect(screen.getAllByRole('textbox')).toHaveLength(2));
    expect(getNameFilterInput()).toBeInTheDocument();
  });

  test('clicking Filters again closes the panel', async () => {
    const user = userEvent.setup();
    renderModulesPage();
    await waitForPageReady();

    const filtersButton = screen.getByRole('button', { name: /filters/i });
    await user.click(filtersButton);
    await screen.findByRole('button', { name: /reset/i });

    await user.click(filtersButton);

    await waitFor(() => {
      expect(screen.queryByRole('button', { name: /reset/i })).not.toBeInTheDocument();
    });
  });

  test('typing in the search box fetches results with that keyword', async () => {
    const user = userEvent.setup();
    renderModulesPage();
    await waitForPageReady();

    await user.type(screen.getByPlaceholderText('Search modules...'), 'Clock');

    await waitFor(() => {
      expect(fetchModules).toHaveBeenCalledWith(expect.objectContaining({ keyword: 'Clock' }));
    });
  });

  test('typing in the search box resets pagination to page 1', async () => {
    const user = userEvent.setup();
    renderModulesPage();
    await waitForPageReady();

    await user.type(screen.getByPlaceholderText('Search modules...'), 'Video');

    await waitFor(() => {
      expect(fetchModules).toHaveBeenCalledWith(
        expect.objectContaining({ start: 0, keyword: 'Video' }),
      );
    });
  });

  test('entering a Name filter updates the query and resets to page 1', async () => {
    const user = userEvent.setup();
    renderModulesPage();
    await waitForPageReady();

    await user.click(screen.getByRole('button', { name: /filters/i }));
    await screen.findByRole('button', { name: /reset/i });
    await user.type(getNameFilterInput(), 'Clock');

    await waitFor(() => {
      expect(fetchModules).toHaveBeenCalledWith(
        expect.objectContaining({ start: 0, name: 'Clock' }),
      );
    });
  });

  test('clicking Reset clears the Name filter input', async () => {
    const user = userEvent.setup();
    renderModulesPage();
    await waitForPageReady();

    await user.click(screen.getByRole('button', { name: /filters/i }));
    await screen.findByRole('button', { name: /reset/i });
    const nameInput = getNameFilterInput();
    await user.type(nameInput, 'Clock');

    // Wait for the debounced value to reach the query before resetting.
    await waitFor(() => {
      expect(fetchModules).toHaveBeenCalledWith(expect.objectContaining({ name: 'Clock' }));
    });

    await user.click(screen.getByRole('button', { name: /reset/i }));

    await waitFor(() => expect(nameInput.value).toBe(''));
  });

  test('Reset keeps the filter panel open', async () => {
    const user = userEvent.setup();
    renderModulesPage();
    await waitForPageReady();

    await user.click(screen.getByRole('button', { name: /filters/i }));
    await user.click(await screen.findByRole('button', { name: /reset/i }));

    expect(screen.getByRole('button', { name: /reset/i })).toBeInTheDocument();
  });
});
