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
import userEvent, { type UserEvent } from '@testing-library/user-event';
import { vi, beforeEach, describe, test, expect } from 'vitest';

import { userGroupsQueryKeys } from '../hooks/useUserGroupsData';

import { buildUserGroup, mockUserGroup } from './fixtures/userGroup';
import { renderUserGroupsPage } from './helpers/renderUserGroupsPage';

import { fetchUserGroups } from '@/services/userGroupApi';
import type { FetchUserGroupsRequest } from '@/services/userGroupApi';
import { testQueryClient } from '@/setupTests';

// =============================================================================
// Module mocks
// =============================================================================

vi.mock('@/services/userGroupApi');
vi.mock('@/services/userApi', () => ({
  fetchUserPreference: vi.fn().mockResolvedValue(null),
  saveUserPreference: vi.fn().mockResolvedValue(undefined),
  fetchUsers: vi.fn().mockResolvedValue({ rows: [], totalCount: 0 }),
}));
vi.mock('@/hooks/useFilteredTabs', () => ({
  useFilteredTabs: vi.fn(() => [{ name: 'User Groups', path: '/administration/usergroups' }]),
}));
vi.mock('@/components/ui/modals/Modal');
// useUserGroupFilterOptions is NOT mocked — getBaseFilterKeys(t) is synchronous.

// =============================================================================
// Helpers
// =============================================================================

// 25 rows in total so a second page exists; page 2 returns a different row
// so helpers can wait on it.
const PAGE_2_GROUP = buildUserGroup({ groupId: 11, group: 'Viewers' });
const mockPagedUserGroups = () =>
  // Branch needed: React Query may refetch, so call order isn't fixed — serve by page.
  vi.mocked(fetchUserGroups).mockImplementation(async (opts?: FetchUserGroupsRequest) => ({
    rows: [opts?.start === 10 ? PAGE_2_GROUP : mockUserGroup],
    totalCount: 25,
  }));

// No accessible name yet (xibosignage/xibo#4126), so find it by placeholder.
const searchInput = () => screen.getByPlaceholderText('Search user groups...');

const waitForPageReady = () => screen.findByText(mockUserGroup.group);

const lastRequest = () => vi.mocked(fetchUserGroups).mock.lastCall?.[0];

const goToPage2 = async (user: UserEvent) => {
  await user.click(screen.getByRole('button', { name: /^next$/i }));
  return screen.findByText(PAGE_2_GROUP.group);
};

const openFilters = async (user: UserEvent) => {
  await user.click(screen.getByRole('button', { name: /filters/i }));
  return screen.findByRole('textbox', { name: /^name$/i });
};

// =============================================================================
// Tests
// =============================================================================

describe('User Groups page - filters', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    mockPagedUserGroups();
  });

  test('filter panel controls are not accessible before Filters is clicked', async () => {
    renderUserGroupsPage();
    await waitForPageReady();

    expect(screen.queryByRole('textbox', { name: /^name$/i })).not.toBeInTheDocument();
    expect(screen.queryByRole('button', { name: /reset/i })).not.toBeInTheDocument();
  });

  test('clicking Filters makes the filter panel accessible', async () => {
    const user = userEvent.setup();
    renderUserGroupsPage();
    await waitForPageReady();

    await user.click(screen.getByRole('button', { name: /filters/i }));

    await screen.findByRole('textbox', { name: /^name$/i });
    expect(screen.getByRole('button', { name: /reset/i })).toBeInTheDocument();
  });

  test('clicking Filters again hides the filter panel', async () => {
    const user = userEvent.setup();
    renderUserGroupsPage();
    await waitForPageReady();
    await openFilters(user);

    await user.click(screen.getByRole('button', { name: /filters/i }));

    await waitFor(() => {
      expect(screen.queryByRole('textbox', { name: /^name$/i })).not.toBeInTheDocument();
    });
  });

  test('typing in the search input fetches with the userGroup param', async () => {
    const user = userEvent.setup();
    renderUserGroupsPage();
    await waitForPageReady();

    await user.type(searchInput(), 'Edit');

    await waitFor(() => {
      expect(fetchUserGroups).toHaveBeenCalledWith(expect.objectContaining({ userGroup: 'Edit' }));
    });
  });

  test('typing in the search input resets pagination to the first page', async () => {
    const user = userEvent.setup();
    renderUserGroupsPage();
    await waitForPageReady();
    await goToPage2(user);

    await user.type(searchInput(), 'Edit');

    await waitFor(() => {
      expect(lastRequest()).toEqual(expect.objectContaining({ userGroup: 'Edit', start: 0 }));
    });
  });

  test('entering a Name filter updates the query and resets to page 1', async () => {
    const user = userEvent.setup();
    renderUserGroupsPage();
    await waitForPageReady();
    await goToPage2(user);

    await user.type(await openFilters(user), 'Ops');

    await waitFor(() => {
      expect(lastRequest()).toEqual(expect.objectContaining({ userGroup: 'Ops', start: 0 }));
    });
  });

  // Both inputs map to the same `userGroup` param; the Name filter takes precedence.
  test('the Name filter wins over search text', async () => {
    const user = userEvent.setup();
    renderUserGroupsPage();
    await waitForPageReady();

    await user.type(searchInput(), 'Edit');
    await waitFor(() =>
      expect(lastRequest()).toEqual(expect.objectContaining({ userGroup: 'Edit' })),
    );

    await user.type(await openFilters(user), 'Ops');

    await waitFor(() =>
      expect(lastRequest()).toEqual(expect.objectContaining({ userGroup: 'Ops' })),
    );
  });

  test('switching the Name filter to AND sends logicalOperatorName AND', async () => {
    const user = userEvent.setup();
    renderUserGroupsPage();
    await waitForPageReady();
    await openFilters(user);

    await user.click(screen.getByRole('button', { name: /^or$/i }));
    await user.click(await screen.findByRole('button', { name: /^and$/i }));

    await waitFor(() => {
      expect(lastRequest()).toEqual(expect.objectContaining({ logicalOperatorName: 'AND' }));
    });
  });

  test('turning on regex sends useRegexForName true', async () => {
    const user = userEvent.setup();
    renderUserGroupsPage();
    await waitForPageReady();
    await openFilters(user);

    // Default request always carries useRegexForName: false.
    expect(lastRequest()).toEqual(expect.objectContaining({ useRegexForName: false }));

    await user.click(screen.getByRole('button', { name: /use regex pattern matching/i }));

    await waitFor(() => {
      expect(lastRequest()).toEqual(expect.objectContaining({ useRegexForName: true }));
    });
  });

  test('the Filters button shows the active filter count', async () => {
    const user = userEvent.setup();
    renderUserGroupsPage();
    await waitForPageReady();

    await user.type(await openFilters(user), 'Ops');

    await waitFor(() => {
      expect(screen.getByRole('button', { name: /filters/i })).toHaveTextContent(/^filters\s*1$/i);
    });
  });

  test('clicking Reset clears the Name filter, keeps the panel open and returns to page 1', async () => {
    const user = userEvent.setup();
    renderUserGroupsPage();
    await waitForPageReady();

    const nameInput = await openFilters(user);
    await user.type(nameInput, 'Ops');
    await waitFor(() =>
      expect(lastRequest()).toEqual(expect.objectContaining({ userGroup: 'Ops' })),
    );
    await goToPage2(user);

    await user.click(screen.getByRole('button', { name: /reset/i }));

    expect(screen.getByRole('button', { name: /reset/i })).toBeInTheDocument();
    await waitFor(() => {
      expect(screen.getByRole('textbox', { name: /^name$/i })).toHaveValue('');
    });
    // The page-1/no-filter result is still cached (staleTime 1 min), so no new
    // request fires — assert on the active query's params instead.
    await waitFor(() => {
      const [active] = testQueryClient
        .getQueryCache()
        .findAll({ queryKey: [...userGroupsQueryKeys.all, 'list'], type: 'active' });
      expect(active?.queryKey[2]).toEqual(
        expect.objectContaining({ pageIndex: 0, userGroup: null }),
      );
    });
  });
});
