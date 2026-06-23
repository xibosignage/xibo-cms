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

import { mockDisplayProfile, SINGLE_DISPLAY_PROFILE } from './fixtures/displayProfile';
import { renderDisplayProfilePage } from './helpers/renderDisplayProfilePage';
import { mockFetchDisplayProfile } from './mocks/displayProfileApi';

import { testQueryClient } from '@/setupTests';

// =============================================================================
// Module mocks
// =============================================================================

vi.mock('@/services/displayProfileApi');
vi.mock('@/services/userApi', () => ({
  fetchUserPreference: vi.fn().mockResolvedValue(null),
  saveUserPreference: vi.fn().mockResolvedValue(undefined),
}));
vi.mock('@/components/ui/modals/Modal');
vi.mock('../hooks/useDisplayProfileFilterOptions', () => ({
  useDisplayProfileFilterOptions: () => ({ filterOptions: [], isLoading: false }),
}));

// =============================================================================
// Tests — DisplayProfile page column visibility
// =============================================================================

// Full-page render + interaction can exceed the 5s default under parallel
// JSDOM contention (each test still runs in ~1s in isolation).
vi.setConfig({ testTimeout: 20_000 });

describe('DisplayProfile page - column visibility', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    mockFetchDisplayProfile(SINGLE_DISPLAY_PROFILE);
  });

  test('the Name column is always visible', async () => {
    renderDisplayProfilePage();
    await screen.findByText(mockDisplayProfile.name);

    expect(screen.getByRole('columnheader', { name: /^name$/i })).toBeInTheDocument();
  });

  test('the ID column is visible by default', async () => {
    renderDisplayProfilePage();
    await screen.findByText(mockDisplayProfile.name);

    expect(screen.getByRole('columnheader', { name: /^id$/i })).toBeInTheDocument();
  });

  test('all default columns are visible on first load', async () => {
    renderDisplayProfilePage();
    await screen.findByText(mockDisplayProfile.name);

    for (const name of [/^id$/i, /^name$/i, /^type$/i, /^default$/i]) {
      expect(screen.getByRole('columnheader', { name })).toBeInTheDocument();
    }
  });

  test('the Columns button opens the column picker', async () => {
    const user = userEvent.setup();
    renderDisplayProfilePage();
    await screen.findByText(mockDisplayProfile.name);

    await user.click(screen.getByRole('button', { name: /toggle columns/i }));

    expect(screen.getByRole('checkbox', { name: /^type$/i })).toBeInTheDocument();
  });

  test('the column picker lists every hideable column', async () => {
    const user = userEvent.setup();
    renderDisplayProfilePage();
    await screen.findByText(mockDisplayProfile.name);

    await user.click(screen.getByRole('button', { name: /toggle columns/i }));

    expect(screen.getByRole('checkbox', { name: /^id$/i })).toBeInTheDocument();
    expect(screen.getByRole('checkbox', { name: /^type$/i })).toBeInTheDocument();
    expect(screen.getByRole('checkbox', { name: /^default$/i })).toBeInTheDocument();
  });

  test('the Name column cannot be hidden', async () => {
    const user = userEvent.setup();
    renderDisplayProfilePage();
    await screen.findByText(mockDisplayProfile.name);

    await user.click(screen.getByRole('button', { name: /toggle columns/i }));

    expect(screen.queryByRole('checkbox', { name: /^name$/i })).not.toBeInTheDocument();
  });

  test('unchecking a column hides it from the table', async () => {
    const user = userEvent.setup();
    renderDisplayProfilePage();
    await screen.findByText(mockDisplayProfile.name);

    await user.click(screen.getByRole('button', { name: /toggle columns/i }));
    const typeCheckbox = screen.getByRole('checkbox', { name: /^type$/i });
    expect(typeCheckbox).toBeChecked();

    await user.click(typeCheckbox);

    expect(screen.queryByRole('columnheader', { name: /^type$/i })).not.toBeInTheDocument();
  });

  test('re-checking a hidden column brings it back', async () => {
    const user = userEvent.setup();
    renderDisplayProfilePage();
    await screen.findByText(mockDisplayProfile.name);

    await user.click(screen.getByRole('button', { name: /toggle columns/i }));
    const typeCheckbox = screen.getByRole('checkbox', { name: /^type$/i });

    await user.click(typeCheckbox);
    expect(screen.queryByRole('columnheader', { name: /^type$/i })).not.toBeInTheDocument();

    await user.click(typeCheckbox);
    expect(await screen.findByRole('columnheader', { name: /^type$/i })).toBeInTheDocument();
  });
});
