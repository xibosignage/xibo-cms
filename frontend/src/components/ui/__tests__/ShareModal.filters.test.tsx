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

// =============================================================================
// Test type: Component test
// ShareModal filters — every control above the permissions table must reach
// the permissions request. The table total is 25 so the pager has pages and
// the paging controls can be exercised. The name search has no debounce, so
// each keystroke reloads; tests assert on the latest request.
//
// Accessibility fallbacks: the name search is the only textbox
// (xibosignage/xibo#4066); the Name column's sort toggle is an unnamed div
// beside the header text (xibosignage/xibo#4040), found through that text.
// =============================================================================

import { screen, waitFor, within } from '@testing-library/react';
import userEvent, { type UserEvent } from '@testing-library/user-event';
import { vi, beforeEach, describe, test, expect } from 'vitest';

import { OWNER_LIST, PAGED_PERMISSION_TABLE } from './fixtures/permissions';
import {
  chooseOption,
  findPermissionRow,
  nameSearch,
  renderShareModal,
} from './helpers/renderShareModal';

import { fetchPermissions } from '@/services/permissionsApi';
import { fetchUsers } from '@/services/userApi';

vi.mock('@/components/ui/modals/Modal');
vi.mock('@/hooks/useDebounce');
vi.mock('@/components/ui/Notification', () => ({
  notify: { info: vi.fn(), error: vi.fn(), success: vi.fn(), warning: vi.fn() },
}));
vi.mock('@/services/permissionsApi', () => ({
  fetchPermissions: vi.fn(),
  fetchMultiPermissions: vi.fn(),
  savePermissions: vi.fn(),
  saveMultiPermissions: vi.fn(),
}));
vi.mock('@/services/userApi', () => ({
  fetchUsers: vi.fn(),
}));

const latestRequest = () => {
  const calls = vi.mocked(fetchPermissions).mock.calls;
  return calls[calls.length - 1]![0];
};

const goToNextPage = (user: UserEvent) => user.click(screen.getByRole('button', { name: 'Next' }));

beforeEach(() => {
  vi.clearAllMocks();
  vi.mocked(fetchPermissions).mockResolvedValue(PAGED_PERMISSION_TABLE);
  vi.mocked(fetchUsers).mockResolvedValue(OWNER_LIST);
});

describe('ShareModal — filters', () => {
  test('filtering by User reloads only users, from the first page', async () => {
    const user = userEvent.setup();
    renderShareModal();
    await findPermissionRow('Alice');
    await goToNextPage(user);
    await waitFor(() => expect(latestRequest().start).toBe(10));

    await chooseOption(user, 'Filter by', 'User');

    await waitFor(() => expect(latestRequest()).toMatchObject({ type: 'user', start: 0 }));
  });

  test('filtering by Group reloads only groups, from the first page', async () => {
    const user = userEvent.setup();
    renderShareModal();
    await findPermissionRow('Alice');

    await chooseOption(user, 'Filter by', 'Group');

    await waitFor(() => expect(latestRequest()).toMatchObject({ type: 'group', start: 0 }));
  });

  test('typing in Search reloads the list filtered by name, from the first page', async () => {
    const user = userEvent.setup();
    renderShareModal();
    await findPermissionRow('Alice');
    await goToNextPage(user);
    await waitFor(() => expect(latestRequest().start).toBe(10));

    await user.type(nameSearch(), 'Ali');

    await waitFor(() => expect(latestRequest()).toMatchObject({ name: 'Ali', start: 0 }));
  });

  test('Reset puts the filter back to All, empties Search and reloads', async () => {
    const user = userEvent.setup();
    renderShareModal();
    await findPermissionRow('Alice');

    await chooseOption(user, 'Filter by', 'Group');
    await user.type(nameSearch(), 'Ed');
    await waitFor(() => expect(latestRequest()).toMatchObject({ type: 'group', name: 'Ed' }));

    await user.click(screen.getByRole('button', { name: 'Reset' }));

    await waitFor(() =>
      expect(latestRequest()).toMatchObject({ type: 'all', name: undefined, start: 0 }),
    );
    expect(nameSearch()).toHaveValue('');
    expect(screen.getByRole('combobox', { name: 'Filter by' })).toHaveTextContent('All');
  });

  test('sorting by Name reloads the list in that order', async () => {
    const user = userEvent.setup();
    renderShareModal();
    await findPermissionRow('Alice');

    const nameHeader = screen.getByRole('columnheader', { name: /Name/ });
    const sortToggle = within(nameHeader).getByText('Name').nextElementSibling as HTMLElement;
    await user.click(sortToggle);

    await waitFor(() => expect(latestRequest()).toMatchObject({ sortBy: 'name', sortDir: 'asc' }));
  });

  test('moving to the next page loads the next 10 rows', async () => {
    const user = userEvent.setup();
    renderShareModal();
    await findPermissionRow('Alice');

    await user.click(screen.getByRole('button', { name: 'Next' }));

    await waitFor(() => expect(latestRequest()).toMatchObject({ start: 10, length: 10 }));
  });
});
