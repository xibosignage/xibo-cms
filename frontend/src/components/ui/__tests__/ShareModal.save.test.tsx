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
// Test type: Component test — payload
// ShareModal changing permissions and saving. The payload is asserted three
// ways: the exact groups sent, the invariant that only changed rows are sent,
// and the opposite single/multi branch staying unused.
//
// Accessibility fallback (xibosignage/xibo#4066): permission checkboxes are
// found by position in their row — View, Edit, Delete.
// =============================================================================

import { screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { vi, beforeEach, afterEach, describe, test, expect } from 'vitest';

import { mixedPermissionRows, OWNER_LIST, PERMISSION_TABLE } from './fixtures/permissions';
import {
  chooseOption,
  findPermissionRow,
  nameSearch,
  permissionBox,
  renderShareModal,
  saveButton,
} from './helpers/renderShareModal';

import { notify } from '@/components/ui/Notification';
import {
  fetchMultiPermissions,
  fetchPermissions,
  saveMultiPermissions,
  savePermissions,
} from '@/services/permissionsApi';
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

const lastSinglePayload = () => {
  const calls = vi.mocked(savePermissions).mock.calls;
  return calls[calls.length - 1]![0];
};

beforeEach(() => {
  vi.clearAllMocks();
  vi.mocked(fetchPermissions).mockResolvedValue(PERMISSION_TABLE);
  vi.mocked(fetchMultiPermissions).mockResolvedValue(PERMISSION_TABLE);
  vi.mocked(fetchUsers).mockResolvedValue(OWNER_LIST);
  vi.mocked(savePermissions).mockResolvedValue(undefined);
  vi.mocked(saveMultiPermissions).mockResolvedValue(undefined);
});

// Undo the console.error spies some tests install.
afterEach(() => {
  vi.restoreAllMocks();
});

describe('ShareModal — changing permissions', () => {
  test('clicking an empty box ticks it', async () => {
    const user = userEvent.setup();
    renderShareModal();
    await findPermissionRow('Alice');

    await user.click(permissionBox('Alice', 'Edit'));

    expect(permissionBox('Alice', 'Edit')).toBeChecked();
  });

  test('clicking a ticked box clears it', async () => {
    const user = userEvent.setup();
    renderShareModal();
    await findPermissionRow('Editors');

    await user.click(permissionBox('Editors', 'Edit'));

    expect(permissionBox('Editors', 'Edit')).not.toBeChecked();
  });

  test('clicking a partly ticked box ticks it', async () => {
    const user = userEvent.setup();
    vi.mocked(fetchMultiPermissions).mockResolvedValue({
      rows: mixedPermissionRows,
      totalCount: mixedPermissionRows.length,
    });
    renderShareModal({ entityId: [7, 8] });
    await findPermissionRow('Alice');

    await user.click(permissionBox('Alice', 'View'));

    expect(permissionBox('Alice', 'View')).toBeChecked();
    expect(permissionBox('Alice', 'View')).not.toBePartiallyChecked();
  });

  test('changes are kept when the same row comes back after filtering', async () => {
    const user = userEvent.setup();
    renderShareModal();
    await findPermissionRow('Alice');

    await user.click(permissionBox('Alice', 'Edit'));
    await chooseOption(user, 'Filter by', 'User');
    await chooseOption(user, 'Filter by', 'All');
    await findPermissionRow('Alice');

    expect(permissionBox('Alice', 'Edit')).toBeChecked();
  });
});

describe('ShareModal — saving', () => {
  test('saving a single item sends only the changed rows, plus the owner', async () => {
    const user = userEvent.setup();
    renderShareModal({ currentOwnerId: 1 });
    await findPermissionRow('Alice');

    await user.click(permissionBox('Alice', 'Edit'));
    await user.click(permissionBox('Editors', 'View'));
    await user.click(saveButton());

    // A. The exact payload.
    await waitFor(() =>
      expect(savePermissions).toHaveBeenCalledWith({
        entity: 'Layout',
        id: 7,
        ownerId: 1,
        groupIds: {
          10: { view: 1, edit: 1, delete: 0 },
          20: { view: 0, edit: 1, delete: 0 },
        },
      }),
    );
    expect(savePermissions).toHaveBeenCalledTimes(1);
    // B. The invariant — untouched rows (Viewers, 30) are never sent.
    expect(Object.keys(lastSinglePayload().groupIds).sort()).toEqual(['10', '20']);
    // C. The other branch stays unused.
    expect(saveMultiPermissions).not.toHaveBeenCalled();
  });

  test('saving without changing anything still sends a save with no rows', async () => {
    const user = userEvent.setup();
    renderShareModal();
    await findPermissionRow('Alice');

    await user.click(saveButton());

    await waitFor(() => expect(savePermissions).toHaveBeenCalledTimes(1));
    expect(lastSinglePayload().groupIds).toEqual({});
  });

  test('saving several items sends one combined save without an owner', async () => {
    const user = userEvent.setup();
    renderShareModal({ entityId: [7, 8], currentOwnerId: 1 });
    await findPermissionRow('Viewers');

    await user.click(permissionBox('Viewers', 'View'));
    await user.click(saveButton());

    await waitFor(() =>
      expect(saveMultiPermissions).toHaveBeenCalledWith({
        entity: 'Layout',
        ids: [7, 8],
        groupIds: { 30: { view: 1, edit: 0, delete: 0 } },
      }),
    );
    expect(saveMultiPermissions).toHaveBeenCalledTimes(1);
    expect(savePermissions).not.toHaveBeenCalled();
  });

  test('a successful save shows a message and closes the modal', async () => {
    const user = userEvent.setup();
    const { onClose } = renderShareModal();
    await findPermissionRow('Alice');

    await user.click(permissionBox('Alice', 'Edit'));
    await user.click(saveButton());

    await waitFor(() => expect(onClose).toHaveBeenCalledTimes(1));
    expect(savePermissions).toHaveBeenCalledTimes(1);
    expect(notify.success).toHaveBeenCalledWith('Permissions saved successfully');
  });

  test('a failed save shows an error and keeps the modal open', async () => {
    const user = userEvent.setup();
    vi.spyOn(console, 'error').mockImplementation(() => {});
    vi.mocked(savePermissions).mockRejectedValue(new Error('500'));
    const { onClose } = renderShareModal();
    await findPermissionRow('Alice');

    await user.click(permissionBox('Alice', 'Edit'));
    await user.click(saveButton());

    await waitFor(() => expect(notify.error).toHaveBeenCalledWith('Failed to save permissions'));
    expect(savePermissions).toHaveBeenCalledTimes(1);
    expect(onClose).not.toHaveBeenCalled();
    // The pending label clears once the failed save settles, so the user can retry.
    expect(await screen.findByRole('button', { name: 'Save' })).toBeEnabled();
  });

  test('while saving, the button reads "Saving..." and cannot be clicked', async () => {
    const user = userEvent.setup();
    let resolve!: () => void;
    vi.mocked(savePermissions).mockReturnValue(
      new Promise<void>((res) => {
        resolve = res;
      }),
    );
    const { onClose } = renderShareModal();
    await findPermissionRow('Alice');

    await user.click(saveButton());

    const saving = await screen.findByRole('button', { name: 'Saving...' });
    expect(saving).toBeDisabled();

    resolve();
    await waitFor(() => expect(onClose).toHaveBeenCalledTimes(1));
  });
});

describe('ShareModal — cancelling', () => {
  test('Cancel closes the modal without saving', async () => {
    const user = userEvent.setup();
    const { onClose } = renderShareModal();
    await findPermissionRow('Alice');

    await user.click(permissionBox('Alice', 'Edit'));
    await user.click(screen.getByRole('button', { name: 'Cancel' }));

    expect(onClose).toHaveBeenCalledTimes(1);
    expect(savePermissions).not.toHaveBeenCalled();
    expect(saveMultiPermissions).not.toHaveBeenCalled();
  });

  test('closing and reopening throws away unsaved changes and resets the filters', async () => {
    const user = userEvent.setup();
    const { rerenderWith } = renderShareModal();
    await findPermissionRow('Alice');

    await user.click(permissionBox('Alice', 'Edit'));
    await chooseOption(user, 'Filter by', 'Group');
    await user.type(nameSearch(), 'Ed');

    rerenderWith({ isOpen: false });
    rerenderWith({ isOpen: true });
    await findPermissionRow('Alice');

    const calls = vi.mocked(fetchPermissions).mock.calls;
    expect(calls[calls.length - 1]![0]).toMatchObject({ type: 'all', name: undefined, start: 0 });
    expect(permissionBox('Alice', 'Edit')).not.toBeChecked();

    await user.click(saveButton());
    await waitFor(() => expect(savePermissions).toHaveBeenCalledTimes(1));
    expect(lastSinglePayload().groupIds).toEqual({});
  });
});
