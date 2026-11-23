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

import { screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { vi, beforeEach, describe, test, expect } from 'vitest';

import { SINGLE_MODULE, mockModule } from './fixtures/module';
import { renderModulesPage } from './helpers/renderModulesPage';
import { mockFetchModules } from './mocks/moduleApi';

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
// Tests — Modules page column visibility
// =============================================================================

describe('Modules page - column visibility', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    mockFetchModules(SINGLE_MODULE);
  });

  test('the Name column is always visible', async () => {
    renderModulesPage();
    await screen.findByText(mockModule.name);

    expect(screen.getByRole('columnheader', { name: /^name$/i })).toBeInTheDocument();
  });

  test('all default columns are visible on first load', async () => {
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

  test('the Columns button opens the column picker', async () => {
    const user = userEvent.setup();
    renderModulesPage();
    await screen.findByText(mockModule.name);

    await user.click(screen.getByRole('button', { name: /toggle columns/i }));

    expect(screen.getByRole('checkbox', { name: /^description$/i })).toBeInTheDocument();
  });

  test('the column picker lists every hideable column', async () => {
    const user = userEvent.setup();
    renderModulesPage();
    await screen.findByText(mockModule.name);

    await user.click(screen.getByRole('button', { name: /toggle columns/i }));

    for (const name of [
      /^description$/i,
      /^library media$/i,
      /^default duration$/i,
      /^preview enabled$/i,
      /^assignable$/i,
      /^enabled$/i,
      /^errors$/i,
    ]) {
      expect(screen.getByRole('checkbox', { name })).toBeInTheDocument();
    }
  });

  test('the Name column cannot be hidden', async () => {
    const user = userEvent.setup();
    renderModulesPage();
    await screen.findByText(mockModule.name);

    await user.click(screen.getByRole('button', { name: /toggle columns/i }));

    expect(screen.queryByRole('checkbox', { name: /^name$/i })).not.toBeInTheDocument();
  });

  test('unchecking a column hides it from the table', async () => {
    const user = userEvent.setup();
    renderModulesPage();
    await screen.findByText(mockModule.name);

    await user.click(screen.getByRole('button', { name: /toggle columns/i }));
    const descCheckbox = screen.getByRole('checkbox', { name: /^description$/i });
    expect(descCheckbox).toBeChecked();

    await user.click(descCheckbox);

    expect(screen.queryByRole('columnheader', { name: /^description$/i })).not.toBeInTheDocument();
  });

  test('re-checking a hidden column brings it back', async () => {
    const user = userEvent.setup();
    renderModulesPage();
    await screen.findByText(mockModule.name);

    await user.click(screen.getByRole('button', { name: /toggle columns/i }));
    const descCheckbox = screen.getByRole('checkbox', { name: /^description$/i });

    await user.click(descCheckbox);
    expect(screen.queryByRole('columnheader', { name: /^description$/i })).not.toBeInTheDocument();

    await user.click(descCheckbox);
    expect(await screen.findByRole('columnheader', { name: /^description$/i })).toBeInTheDocument();
  });
});
