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
// ShareModal — what loads and what is shown when the modal opens, for a single
// item, several items, and no item. The real DataTable, SelectDropdown and
// Checkbox are used; only the Modal shell, notifications and the two APIs are
// mocked.
// =============================================================================

import { screen, waitFor } from '@testing-library/react';
import { vi, beforeEach, afterEach, describe, test, expect } from 'vitest';

import { mixedPermissionRows, OWNER_LIST, PERMISSION_TABLE } from './fixtures/permissions';
import {
  findPermissionRow,
  permissionBox,
  renderShareModal,
  saveButton,
  SHARE_TITLE,
} from './helpers/renderShareModal';

import { notify } from '@/components/ui/Notification';
import { fetchMultiPermissions, fetchPermissions } from '@/services/permissionsApi';
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

beforeEach(() => {
  vi.clearAllMocks();
  // Populated by default — an empty table would make row checks vacuous.
  vi.mocked(fetchPermissions).mockResolvedValue(PERMISSION_TABLE);
  vi.mocked(fetchMultiPermissions).mockResolvedValue({
    rows: mixedPermissionRows,
    totalCount: mixedPermissionRows.length,
  });
  vi.mocked(fetchUsers).mockResolvedValue(OWNER_LIST);
});

// Undo the console.error spies some tests install.
afterEach(() => {
  vi.restoreAllMocks();
});

describe('ShareModal — opening', () => {
  test('shows the title with Cancel, and Save becomes available once rows load', async () => {
    renderShareModal();

    expect(screen.getByRole('dialog', { name: SHARE_TITLE })).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Cancel' })).toBeInTheDocument();
    await findPermissionRow('Alice');
    expect(saveButton()).toBeEnabled();
  });

  test('loads the first page of permissions for a single item', async () => {
    renderShareModal();
    await findPermissionRow('Alice');

    expect(fetchPermissions).toHaveBeenCalledWith({
      entity: 'Layout',
      id: 7,
      start: 0,
      length: 10,
      type: 'all',
      name: undefined,
      sortBy: undefined,
      sortDir: undefined,
    });
    expect(fetchMultiPermissions).not.toHaveBeenCalled();
  });

  test('shows each row as a User or a Group', async () => {
    renderShareModal();

    expect(await findPermissionRow('Alice')).toHaveTextContent('User');
    expect(screen.getByRole('row', { name: /Editors/ })).toHaveTextContent('Group');
    expect(screen.getByRole('row', { name: /Viewers/ })).toHaveTextContent('Group');
  });

  test('shows ticked, empty and partly ticked permission boxes', async () => {
    renderShareModal({ entityId: [7, 8] });
    await findPermissionRow('Alice');

    expect(permissionBox('Editors', 'View')).toBeChecked();
    expect(permissionBox('Editors', 'Edit')).toBeChecked();
    expect(permissionBox('Editors', 'Delete')).not.toBeChecked();
    expect(permissionBox('Alice', 'View')).toBePartiallyChecked();
  });

  test('shows the owner picker for a single item', async () => {
    renderShareModal();
    await findPermissionRow('Alice');

    expect(screen.getByRole('combobox', { name: 'Select Owner' })).toBeInTheDocument();
  });

  test('hides the owner picker when owner selection is turned off', async () => {
    renderShareModal({ showOwner: false });
    await findPermissionRow('Alice');

    expect(screen.queryByRole('combobox', { name: 'Select Owner' })).not.toBeInTheDocument();
    expect(screen.getByRole('combobox', { name: 'Filter by' })).toBeInTheDocument();
  });

  test('loads combined permissions for several items and hides the owner picker', async () => {
    renderShareModal({ entityId: [7, 8] });
    await findPermissionRow('Alice');

    expect(fetchMultiPermissions).toHaveBeenCalledWith(
      expect.objectContaining({ entity: 'Layout', ids: [7, 8], start: 0, length: 10 }),
    );
    expect(fetchPermissions).not.toHaveBeenCalled();
    expect(screen.queryByRole('combobox', { name: 'Select Owner' })).not.toBeInTheDocument();
  });

  test('loads nothing when no item is given', async () => {
    renderShareModal({ entityId: null });

    // The owner list still loads; waiting on it lets any permission load surface.
    await waitFor(() => expect(fetchUsers).toHaveBeenCalled());
    expect(fetchPermissions).not.toHaveBeenCalled();
    expect(fetchMultiPermissions).not.toHaveBeenCalled();
    expect(screen.queryByRole('row', { name: /Alice/ })).not.toBeInTheDocument();
  });

  test('shows nothing and loads nothing while closed', () => {
    renderShareModal({ isOpen: false });

    expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
    expect(fetchPermissions).not.toHaveBeenCalled();
    expect(fetchUsers).not.toHaveBeenCalled();
  });

  test('shows an error message when permissions fail to load', async () => {
    vi.spyOn(console, 'error').mockImplementation(() => {});
    vi.mocked(fetchPermissions).mockRejectedValue(new Error('500'));
    renderShareModal();

    await waitFor(() => expect(notify.error).toHaveBeenCalledWith('Failed to load data'));
    expect(fetchPermissions).toHaveBeenCalled();
    expect(screen.getByRole('dialog', { name: SHARE_TITLE })).toBeInTheDocument();
  });
});
