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

import { mockSuperAdmin, mockUser, SINGLE_USER } from './fixtures/user';
import { renderUsersPage } from './helpers/renderUsersPage';
import { mockFetchUsers } from './mocks/userApi';

import { fetchUsers } from '@/services/userApi';
import { testQueryClient } from '@/setupTests';

// =============================================================================
// Module mocks
// =============================================================================

vi.mock('@/services/userApi');

vi.mock('@/services/userGroupApi');

vi.mock('@/services/folderApi', () => ({
  fetchFolderTree: vi.fn().mockResolvedValue([]),
}));

vi.mock('@/services/permissionsApi', () => ({
  fetchGroupFolderPermissions: vi.fn().mockResolvedValue(new Map()),
  saveMultiPermissions: vi.fn().mockResolvedValue(undefined),
}));

vi.mock('@/hooks/useFilteredTabs', () => ({
  useFilteredTabs: vi.fn(() => [{ name: 'Users', path: '/administration/users' }]),
}));

vi.mock('@/components/ui/modals/Modal');

vi.mock('@/components/ui/forms/SelectDropdown', () => ({
  default: ({
    label,
    value,
    options,
    onSelect,
    placeholder,
  }: {
    label?: string;
    value?: string | number | null;
    options?: Array<{ value: string | number | null; label: string }>;
    onSelect?: (value: string) => void;
    placeholder?: string;
  }) => (
    <select aria-label={label} value={value ?? ''} onChange={(e) => onSelect?.(e.target.value)}>
      {placeholder !== undefined && <option value="">{placeholder}</option>}
      {options?.map((o, i) => (
        <option key={i} value={String(o.value ?? '')}>
          {o.label}
        </option>
      ))}
    </select>
  ),
}));

// useUsersFilterOptions is NOT mocked — getBaseFilterKeys(t) is synchronous, runs for real

const waitForPageReady = () => screen.findByText(mockUser.userName);

// =============================================================================
// Tests
// =============================================================================

describe('Users page - search and filters', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    mockFetchUsers(SINGLE_USER);
  });

  test('the filter panel is hidden by default', async () => {
    renderUsersPage();
    await waitForPageReady();

    expect(screen.queryByRole('textbox', { name: /^username$/i })).not.toBeInTheDocument();
    expect(screen.queryByRole('button', { name: /reset/i })).not.toBeInTheDocument();
  });

  test('clicking Filters opens the panel', async () => {
    const user = userEvent.setup();
    renderUsersPage();
    await waitForPageReady();

    await user.click(screen.getByRole('button', { name: /filters/i }));

    expect(await screen.findByRole('textbox', { name: /^username$/i })).toBeInTheDocument();
  });

  test('clicking Filters again closes the panel', async () => {
    const user = userEvent.setup();
    renderUsersPage();
    await waitForPageReady();

    const filtersButton = screen.getByRole('button', { name: /filters/i });
    await user.click(filtersButton);
    await screen.findByRole('textbox', { name: /^username$/i });

    await user.click(filtersButton);

    await waitFor(() => {
      expect(screen.queryByRole('textbox', { name: /^username$/i })).not.toBeInTheDocument();
    });
  });

  test('typing in the search box fetches results with that keyword', async () => {
    const user = userEvent.setup();
    renderUsersPage();
    await waitForPageReady();

    await user.type(screen.getByPlaceholderText('Search user...'), 'jbloggs');

    await waitFor(
      () => {
        expect(fetchUsers).toHaveBeenCalledWith(expect.objectContaining({ keyword: 'jbloggs' }));
      },
      { timeout: 2000 },
    );
  });

  test('typing in the search box resets to page 1', async () => {
    const user = userEvent.setup();
    renderUsersPage();
    await waitForPageReady();

    await user.type(screen.getByPlaceholderText('Search user...'), 'jbloggs');

    await waitFor(
      () => {
        expect(fetchUsers).toHaveBeenCalledWith(
          expect.objectContaining({ keyword: 'jbloggs', start: 0 }),
        );
      },
      { timeout: 2000 },
    );
  });

  test('clearing the search restores the unfiltered list', async () => {
    const user = userEvent.setup();
    renderUsersPage();
    await waitForPageReady();

    const search = screen.getByPlaceholderText('Search user...');
    await user.type(search, 'jbloggs');
    await waitFor(
      () => {
        expect(fetchUsers).toHaveBeenCalledWith(expect.objectContaining({ keyword: 'jbloggs' }));
      },
      { timeout: 2000 },
    );

    await user.clear(search);
    await waitFor(
      () => {
        expect(fetchUsers).toHaveBeenCalledWith(expect.objectContaining({ keyword: undefined }));
      },
      { timeout: 2000 },
    );
  });

  // The search box must start empty on a fresh page load — it should never
  // pre-fill with the logged-in user's own username.
  test('the search box is empty on a fresh mount and does not auto-populate with the logged-in username', async () => {
    renderUsersPage(mockSuperAdmin);
    await waitForPageReady();

    expect(screen.getByPlaceholderText('Search user...')).toHaveValue('');
  });

  test('entering a Username filter updates the query and resets to page 1', async () => {
    const user = userEvent.setup();
    renderUsersPage();
    await waitForPageReady();

    await user.click(screen.getByRole('button', { name: /filters/i }));
    const usernameInput = await screen.findByRole('textbox', { name: /^username$/i });
    await user.type(usernameInput, 'bloggs');

    await waitFor(
      () => {
        expect(fetchUsers).toHaveBeenCalledWith(
          expect.objectContaining({ userName: 'bloggs', start: 0 }),
        );
      },
      { timeout: 2000 },
    );
  });

  test('selecting a User Type filter updates the query and resets to page 1', async () => {
    const user = userEvent.setup();
    renderUsersPage();
    await waitForPageReady();

    await user.click(screen.getByRole('button', { name: /filters/i }));
    const userTypeSelect = await screen.findByRole('combobox', { name: /^user type$/i });
    await user.selectOptions(userTypeSelect, '1');

    await waitFor(
      () => {
        expect(fetchUsers).toHaveBeenCalledWith(
          expect.objectContaining({ userTypeId: '1', start: 0 }),
        );
      },
      { timeout: 2000 },
    );
  });

  test('selecting a Retired filter updates the query and resets to page 1', async () => {
    const user = userEvent.setup();
    renderUsersPage();
    await waitForPageReady();

    await user.click(screen.getByRole('button', { name: /filters/i }));
    const retiredSelect = await screen.findByRole('combobox', { name: /^retired$/i });
    await user.selectOptions(retiredSelect, '1');

    await waitFor(
      () => {
        expect(fetchUsers).toHaveBeenCalledWith(
          expect.objectContaining({ retired: '1', start: 0 }),
        );
      },
      { timeout: 2000 },
    );
  });

  test('typing in the First Name filter updates the query and resets to page 1', async () => {
    const user = userEvent.setup();
    renderUsersPage();
    await waitForPageReady();

    await user.click(screen.getByRole('button', { name: /filters/i }));
    const firstNameInput = await screen.findByPlaceholderText('First Name');
    await user.type(firstNameInput, 'Jane');

    await waitFor(
      () => {
        expect(fetchUsers).toHaveBeenCalledWith(
          expect.objectContaining({ firstName: 'Jane', start: 0 }),
        );
      },
      { timeout: 2000 },
    );
  });

  test('typing in the Last Name filter updates the query and resets to page 1', async () => {
    const user = userEvent.setup();
    renderUsersPage();
    await waitForPageReady();

    await user.click(screen.getByRole('button', { name: /filters/i }));
    const lastNameInput = await screen.findByPlaceholderText('Last Name');
    await user.type(lastNameInput, 'Bloggs');

    await waitFor(
      () => {
        expect(fetchUsers).toHaveBeenCalledWith(
          expect.objectContaining({ lastName: 'Bloggs', start: 0 }),
        );
      },
      { timeout: 2000 },
    );
  });

  test('clicking Reset clears the filter inputs', async () => {
    const user = userEvent.setup();
    renderUsersPage();
    await waitForPageReady();

    await user.click(screen.getByRole('button', { name: /filters/i }));
    const usernameInput = (await screen.findByRole('textbox', {
      name: /^username$/i,
    })) as HTMLInputElement;
    await user.type(usernameInput, 'bloggs');

    await waitFor(
      () => {
        expect(fetchUsers).toHaveBeenCalledWith(expect.objectContaining({ userName: 'bloggs' }));
      },
      { timeout: 2000 },
    );

    await user.click(screen.getByRole('button', { name: /reset/i }));

    await waitFor(() => {
      expect(screen.getByRole('textbox', { name: /^username$/i })).toHaveValue('');
    });
  });

  test('Reset keeps the filter panel open', async () => {
    const user = userEvent.setup();
    renderUsersPage();
    await waitForPageReady();

    await user.click(screen.getByRole('button', { name: /filters/i }));
    await screen.findByRole('textbox', { name: /^username$/i });

    await user.click(screen.getByRole('button', { name: /reset/i }));

    expect(screen.getByRole('textbox', { name: /^username$/i })).toBeInTheDocument();
  }, 20_000);

  // Reset must clear the top search box too, not just the filter panel.
  test('Reset also clears the top search box', async () => {
    const user = userEvent.setup();
    renderUsersPage();
    await waitForPageReady();

    const search = screen.getByPlaceholderText('Search user...');
    await user.type(search, 'jbloggs');
    await waitFor(() => expect(search).toHaveValue('jbloggs'));

    await user.click(screen.getByRole('button', { name: /filters/i }));
    await screen.findByRole('textbox', { name: /^username$/i });

    await user.click(screen.getByRole('button', { name: /reset/i }));

    expect(search).toHaveValue('');
  });
});
