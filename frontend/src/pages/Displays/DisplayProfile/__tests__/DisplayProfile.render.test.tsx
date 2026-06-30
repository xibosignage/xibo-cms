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
import { vi, beforeEach, describe, test, expect } from 'vitest';

import {
  EMPTY_DISPLAY_PROFILE_TABLE,
  mockDisplayProfile,
  SINGLE_DISPLAY_PROFILE,
} from './fixtures/displayProfile';
import { renderDisplayProfilePage } from './helpers/renderDisplayProfilePage';
import { mockFetchDisplayProfile } from './mocks/displayProfileApi';

import { fetchDisplayProfile } from '@/services/displayProfileApi';
import { fetchUserPreference } from '@/services/userApi';
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
// Tests — DisplayProfile page default state
// =============================================================================

describe('DisplayProfile page - render', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
  });

  test('the table shows a profile row when the page first opens', async () => {
    mockFetchDisplayProfile(SINGLE_DISPLAY_PROFILE);
    renderDisplayProfilePage();

    expect(await screen.findByText(mockDisplayProfile.name)).toBeInTheDocument();
  });

  test('an empty state is shown when there are no profiles', async () => {
    mockFetchDisplayProfile(EMPTY_DISPLAY_PROFILE_TABLE);
    renderDisplayProfilePage();

    // Wait for hydration / first fetch to complete before asserting empty state.
    await screen.findByRole('button', { name: /add display profile/i });

    expect(await screen.findByText('No results found.')).toBeInTheDocument();
  });

  test('the "Add Display Profile" button is visible', async () => {
    mockFetchDisplayProfile(EMPTY_DISPLAY_PROFILE_TABLE);
    renderDisplayProfilePage();

    expect(await screen.findByRole('button', { name: /add display profile/i })).toBeInTheDocument();
  });

  test('the search box uses the "Search display profiles..." placeholder', async () => {
    mockFetchDisplayProfile(EMPTY_DISPLAY_PROFILE_TABLE);
    renderDisplayProfilePage();

    expect(await screen.findByPlaceholderText('Search display profiles...')).toBeInTheDocument();
  });

  test('the Filters button is visible', async () => {
    mockFetchDisplayProfile(EMPTY_DISPLAY_PROFILE_TABLE);
    renderDisplayProfilePage();

    expect(await screen.findByRole('button', { name: /filters/i })).toBeInTheDocument();
  });

  test('the tab navigation shows "Display Settings" as the active tab', async () => {
    mockFetchDisplayProfile(EMPTY_DISPLAY_PROFILE_TABLE);
    renderDisplayProfilePage();

    const tabs = await screen.findAllByRole('button', { name: /display settings/i });
    expect(tabs.length).toBeGreaterThan(0);
    // The active tab is marked with aria-current="page".
    expect(tabs.some((tab) => tab.getAttribute('aria-current') === 'page')).toBe(true);
  });

  test('profiles are requested 10 per page by default', async () => {
    mockFetchDisplayProfile(SINGLE_DISPLAY_PROFILE);
    renderDisplayProfilePage();

    await screen.findByText(mockDisplayProfile.name);

    expect(fetchDisplayProfile).toHaveBeenCalledWith(
      expect.objectContaining({ start: 0, length: 10 }),
    );
  });

  test('a fetch error renders the error alert above the table', async () => {
    // A plain (sub-500) error does not throw to an error boundary — the hook
    // reports it via isError and the page shows its message in the alert.
    vi.mocked(fetchDisplayProfile).mockRejectedValueOnce(new Error('Network failure'));
    renderDisplayProfilePage();

    const alert = await screen.findByRole('alert');
    expect(alert).toHaveTextContent('Network failure');
  });

  test('the loading pulse shows and the controls are disabled while preferences load', async () => {
    mockFetchDisplayProfile(SINGLE_DISPLAY_PROFILE);
    // Keep the preference request pending so the page stays un-hydrated.
    vi.mocked(fetchUserPreference).mockReturnValueOnce(new Promise(() => {}));

    renderDisplayProfilePage({ hydrated: false });

    expect(await screen.findByText('Loading your display settings...')).toBeInTheDocument();
    expect(screen.getByRole('button', { name: /add display profile/i })).toBeDisabled();
    expect(screen.getByPlaceholderText('Search display profiles...')).toBeDisabled();
    expect(screen.getByRole('button', { name: /filters/i })).toBeDisabled();
  });
});
