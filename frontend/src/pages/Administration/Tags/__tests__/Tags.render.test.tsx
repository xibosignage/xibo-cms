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

import { QueryClientProvider } from '@tanstack/react-query';
import { render, screen, waitFor } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import { beforeEach, describe, expect, test, vi } from 'vitest';

import Tags from '../Tags';

import { mockTag, mockNonAdminUser, mockUser, SINGLE_TAG, EMPTY_TAG_TABLE } from './fixtures/tag';
import { renderTagsPage } from './helpers/renderTagsPage';
import { mockFetchTags } from './mocks/tagApi';

import { UserProvider } from '@/context/UserContext';
import { fetchTags } from '@/services/tagApi';
import { fetchUserPreference } from '@/services/userApi';
import { testQueryClient } from '@/setupTests';

// =============================================================================
// Module mocks
// =============================================================================

vi.mock('@/services/tagApi');

vi.mock('@/services/userApi', () => ({
  fetchUserPreference: vi.fn().mockResolvedValue(null),
  saveUserPreference: vi.fn().mockResolvedValue(undefined),
}));

vi.mock('@/hooks/useFilteredTabs', () => ({
  useFilteredTabs: vi.fn(() => [{ name: 'Tags', path: '/administration/tags' }]),
}));

vi.mock('@/components/ui/modals/Modal');

// useTagFilterOptions is NOT mocked — pure synchronous function, runs for real

// =============================================================================
// Helper for loading-state tests (does NOT pre-seed the userPref cache)
// =============================================================================

const renderWithPendingPrefs = () =>
  render(
    <QueryClientProvider client={testQueryClient}>
      <UserProvider initialUser={mockUser}>
        <MemoryRouter>
          <Tags />
        </MemoryRouter>
      </UserProvider>
    </QueryClientProvider>,
  );

// =============================================================================
// Tests
// =============================================================================

describe('Tags page - render', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    mockFetchTags(SINGLE_TAG);
  });

  // ---------------------------------------------------------------------------
  // Table rows
  // ---------------------------------------------------------------------------
  test('renders tag rows once data has loaded', async () => {
    renderTagsPage();

    await screen.findByText(mockTag.tag);
  });

  // ---------------------------------------------------------------------------
  // Empty state
  // ---------------------------------------------------------------------------
  test('shows "No results found." when there are no tags', async () => {
    mockFetchTags(EMPTY_TAG_TABLE);
    renderTagsPage();

    // Wait for hydration to complete (Add Tag button confirms it)
    await screen.findByRole('button', { name: /add tag/i });

    await screen.findByText('No results found.');
  });

  // ---------------------------------------------------------------------------
  // Add Tag button
  // ---------------------------------------------------------------------------
  test('"Add Tag" button is present for superAdmin', async () => {
    renderTagsPage(mockUser);

    expect(await screen.findByRole('button', { name: /add tag/i })).toBeEnabled();
  });

  test('"Add Tag" button is hidden for non-superAdmin', async () => {
    mockFetchTags(SINGLE_TAG);
    renderTagsPage(mockNonAdminUser);

    await screen.findByText(mockTag.tag);

    expect(screen.queryByRole('button', { name: /add tag/i })).not.toBeInTheDocument();
  });

  test('"Add Tag" button is disabled while not hydrated', async () => {
    let resolvePref!: (v: null) => void;
    vi.mocked(fetchUserPreference).mockReturnValueOnce(
      new Promise<null>((res) => {
        resolvePref = res;
      }),
    );

    renderWithPendingPrefs();

    expect(await screen.findByRole('button', { name: /add tag/i })).toBeDisabled();

    // Resolve to let the component settle and avoid act() warnings.
    resolvePref(null);
    await waitFor(() => expect(screen.getByRole('button', { name: /add tag/i })).toBeEnabled());
  });

  // ---------------------------------------------------------------------------
  // Filters button
  // ---------------------------------------------------------------------------
  test('Filters button is present', async () => {
    renderTagsPage();

    await screen.findByRole('button', { name: /filters/i });
  });

  // ---------------------------------------------------------------------------
  // Error state
  // ---------------------------------------------------------------------------
  test('shows an error alert when the API call fails', async () => {
    vi.mocked(fetchTags).mockRejectedValue(new Error('Server Error'));

    renderTagsPage();

    expect(await screen.findByRole('alert')).toHaveTextContent('Server Error');
  });

  // ---------------------------------------------------------------------------
  // Loading pulse
  // ---------------------------------------------------------------------------
  test('shows "Loading..." when the page is not yet hydrated', async () => {
    let resolvePref!: (v: null) => void;
    vi.mocked(fetchUserPreference).mockReturnValueOnce(
      new Promise<null>((res) => {
        resolvePref = res;
      }),
    );

    renderWithPendingPrefs();

    await screen.findByText('Loading...');

    resolvePref(null);
    await waitFor(() => expect(screen.queryByText('Loading...')).not.toBeInTheDocument());
  });
});
