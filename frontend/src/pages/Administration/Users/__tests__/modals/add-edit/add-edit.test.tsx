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

import { mockSuperAdmin, mockGroupAdmin, mockUser, mockUserGroup } from '../../fixtures/user';
import { setupAddEditUserModalMocks } from '../../mocks/addEditUserModal';

import { renderAddEditUserModal } from './helpers/renderAddEditUserModal';

import { fetchGroupFolderPermissions, saveMultiPermissions } from '@/services/permissionsApi';
import { createUser, fetchHomepages, updateUser } from '@/services/userApi';
import { fetchUserGroups } from '@/services/userGroupApi';

// =============================================================================
// Module mocks
// =============================================================================

vi.mock('@/services/userApi');

vi.mock('@/services/userGroupApi');

vi.mock('@/services/folderApi');

vi.mock('@/services/permissionsApi');

vi.mock('@/components/ui/modals/Modal');

// =============================================================================
// Helpers
// =============================================================================

const selectDropdownOption = async (
  user: ReturnType<typeof userEvent.setup>,
  labelName: RegExp,
  optionName: RegExp,
) => {
  await user.click(screen.getByRole('combobox', { name: labelName }));
  await user.click(await screen.findByRole('option', { name: optionName }));
};

const fillRequiredAddFields = async (user: ReturnType<typeof userEvent.setup>) => {
  await user.type(screen.getByRole('textbox', { name: /^username$/i }), 'newuser');
  await user.type(screen.getByLabelText(/^password$/i), 'Password1!');
  await selectDropdownOption(user, /initial user group/i, new RegExp(mockUserGroup.group, 'i'));
  await selectDropdownOption(user, /^homepage$/i, /icon dashboard/i);
};

// =============================================================================
// Tests
// =============================================================================

describe('AddEditUserModal', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    setupAddEditUserModalMocks();
    vi.mocked(fetchHomepages).mockResolvedValue([
      { homepage: 'icondashboard.view', title: 'Icon Dashboard' },
    ]);
  });

  // ---------------------------------------------------------------------------
  // Tab visibility
  // ---------------------------------------------------------------------------
  test('Folder Permission tab is shown when the current user has folder.view', async () => {
    renderAddEditUserModal({ mode: 'add', currentUser: mockSuperAdmin });

    await screen.findByRole('tab', { name: /folder permission/i });
  });

  test('Folder Permission tab is hidden without folder.view', async () => {
    renderAddEditUserModal({ mode: 'add', currentUser: { ...mockSuperAdmin, features: {} } });

    await screen.findByRole('tab', { name: /^general$/i });
    expect(screen.queryByRole('tab', { name: /folder permission/i })).not.toBeInTheDocument();
  });

  test('Notifications tab is shown only for a super admin', async () => {
    renderAddEditUserModal({ mode: 'add', currentUser: mockSuperAdmin });

    await screen.findByRole('tab', { name: /notifications/i });
  });

  test('Notifications tab is hidden for a non-superAdmin', async () => {
    renderAddEditUserModal({ mode: 'add', currentUser: mockGroupAdmin });

    await screen.findByRole('tab', { name: /^general$/i });
    expect(screen.queryByRole('tab', { name: /notifications/i })).not.toBeInTheDocument();
  });

  // ---------------------------------------------------------------------------
  // Add mode — groups and homepage
  // ---------------------------------------------------------------------------
  test('loads user groups on open filtered for add-user, excluding user-specific groups', async () => {
    renderAddEditUserModal({ mode: 'add' });

    await waitFor(() => {
      expect(fetchUserGroups).toHaveBeenCalledWith(
        expect.objectContaining({ isShownForAddUser: 1, isUser: 0 }),
      );
    });
  });

  test('group search debounces before re-fetching', async () => {
    const user = userEvent.setup();
    renderAddEditUserModal({ mode: 'add' });
    await screen.findByRole('textbox', { name: /^username$/i });

    await user.click(screen.getByRole('combobox', { name: /initial user group/i }));
    await user.type(screen.getByPlaceholderText('Search groups...'), 'mark');

    await waitFor(
      () => {
        expect(fetchUserGroups).toHaveBeenCalledWith(
          expect.objectContaining({ userGroup: 'mark' }),
        );
      },
      { timeout: 2000 },
    );
  });

  test('selecting a group updates the homepage options to include its feature-gated pages', async () => {
    const user = userEvent.setup();
    vi.mocked(fetchUserGroups).mockResolvedValue({
      rows: [{ ...mockUserGroup, features: ['dashboard.status'] }],
      totalCount: 1,
    });
    vi.mocked(fetchHomepages).mockResolvedValue([
      { homepage: 'icondashboard.view', title: 'Icon Dashboard' },
      { homepage: 'statusdashboard.view', title: 'Status Dashboard', feature: 'dashboard.status' },
    ]);

    renderAddEditUserModal({ mode: 'add' });
    await screen.findByRole('textbox', { name: /^username$/i });

    await user.click(screen.getByRole('combobox', { name: /^homepage$/i }));
    expect(screen.queryByRole('option', { name: /status dashboard/i })).not.toBeInTheDocument();
    await user.keyboard('{Escape}');

    await selectDropdownOption(user, /initial user group/i, new RegExp(mockUserGroup.group, 'i'));

    await user.click(screen.getByRole('combobox', { name: /^homepage$/i }));
    expect(await screen.findByRole('option', { name: /status dashboard/i })).toBeInTheDocument();
  });

  // ---------------------------------------------------------------------------
  // Add mode — validation and save
  // ---------------------------------------------------------------------------
  test('saving with empty required fields shows validation errors and does not call createUser', async () => {
    const user = userEvent.setup();
    renderAddEditUserModal({ mode: 'add' });
    await screen.findByRole('textbox', { name: /^username$/i });

    await user.click(screen.getByRole('button', { name: /create user/i }));

    expect(await screen.findByText(/username is required/i)).toBeInTheDocument();
    expect(createUser).not.toHaveBeenCalled();
  });

  test('saving with an invalid email shows a validation error', async () => {
    const user = userEvent.setup();
    renderAddEditUserModal({ mode: 'add' });
    await screen.findByRole('textbox', { name: /^username$/i });

    await user.type(screen.getByRole('textbox', { name: /^email/i }), 'not-an-email');
    await user.click(screen.getByRole('button', { name: /create user/i }));

    expect(await screen.findByText(/valid email/i)).toBeInTheDocument();
    expect(createUser).not.toHaveBeenCalled();
  });

  test('saving without a Homepage shows "Homepage is required" and does not call createUser', async () => {
    const user = userEvent.setup();
    renderAddEditUserModal({ mode: 'add' });
    await screen.findByRole('textbox', { name: /^username$/i });

    await user.type(screen.getByRole('textbox', { name: /^username$/i }), 'newuser');
    await user.type(screen.getByLabelText(/^password$/i), 'Password1!');
    await selectDropdownOption(user, /initial user group/i, new RegExp(mockUserGroup.group, 'i'));

    await user.click(screen.getByRole('button', { name: /create user/i }));

    expect(await screen.findByText(/homepage is required/i)).toBeInTheDocument();
    expect(createUser).not.toHaveBeenCalled();
  });

  test('successful save calls createUser with the entered payload', async () => {
    const user = userEvent.setup();
    vi.mocked(createUser).mockResolvedValue({ ...mockUser, groupId: undefined });
    const onSuccess = vi.fn();
    const onClose = vi.fn();

    renderAddEditUserModal({ mode: 'add', onSuccess, onClose });
    await screen.findByRole('textbox', { name: /^username$/i });

    await fillRequiredAddFields(user);
    await user.click(screen.getByRole('button', { name: /create user/i }));

    await waitFor(() => {
      expect(createUser).toHaveBeenCalledWith(
        expect.objectContaining({
          userName: 'newuser',
          password: 'Password1!',
          groupId: mockUserGroup.groupId,
          homePageId: 'icondashboard.view',
        }),
      );
    });
    expect(onSuccess).toHaveBeenCalledTimes(1);
    expect(onClose).toHaveBeenCalledTimes(1);
  });

  test('successful save with a granted folder permission also saves it', async () => {
    const user = userEvent.setup();
    vi.mocked(createUser).mockResolvedValue({ ...mockUser, groupId: mockUserGroup.groupId });

    renderAddEditUserModal({ mode: 'add' });
    await screen.findByRole('textbox', { name: /^username$/i });

    await fillRequiredAddFields(user);

    await user.click(screen.getByRole('tab', { name: /folder permission/i }));
    await screen.findByText('Marketing');
    const marketingRow = screen.getByText('Marketing').closest('div')!.parentElement!;
    const checkboxes = marketingRow.querySelectorAll('input[type="checkbox"]');
    await user.click(checkboxes[2]!); // Full Access column

    await user.click(screen.getByRole('button', { name: /create user/i }));

    await waitFor(() => {
      expect(saveMultiPermissions).toHaveBeenCalledWith(
        expect.objectContaining({
          entity: 'Folder',
          ids: [2],
          groupIds: { [mockUserGroup.groupId]: { view: 1, edit: 1, delete: 1 } },
        }),
      );
    });
  });

  test('API error from createUser displays in the modal', async () => {
    const user = userEvent.setup();
    vi.mocked(createUser).mockRejectedValue({
      isAxiosError: true,
      response: { data: { message: 'Username already taken' } },
    });

    renderAddEditUserModal({ mode: 'add' });
    await screen.findByRole('textbox', { name: /^username$/i });

    await fillRequiredAddFields(user);
    await user.click(screen.getByRole('button', { name: /create user/i }));

    expect(await screen.findByText('Username already taken')).toBeInTheDocument();
  });

  test('Save button shows "Creating…" while the request is pending', async () => {
    const user = userEvent.setup();
    let resolveCreate!: (v: typeof mockUser) => void;
    vi.mocked(createUser).mockReturnValue(
      new Promise((res) => {
        resolveCreate = res;
      }),
    );

    renderAddEditUserModal({ mode: 'add' });
    await screen.findByRole('textbox', { name: /^username$/i });

    await fillRequiredAddFields(user);
    await user.click(screen.getByRole('button', { name: /create user/i }));

    await waitFor(() => expect(screen.getByRole('button', { name: /creating/i })).toBeDisabled());

    resolveCreate({ ...mockUser, groupId: undefined });
    await waitFor(() =>
      expect(screen.queryByRole('button', { name: /creating/i })).not.toBeInTheDocument(),
    );
  });

  // ---------------------------------------------------------------------------
  // Edit mode
  // ---------------------------------------------------------------------------
  test('edit mode loads existing folder permissions when the user has a group', async () => {
    vi.mocked(fetchGroupFolderPermissions).mockResolvedValue(
      new Map([[2, { view: 1, edit: 1, delete: 0 }]]),
    );

    const user = userEvent.setup();
    renderAddEditUserModal({ mode: 'edit', user: mockUser });
    await screen.findByRole('textbox', { name: /^username$/i });

    await user.click(screen.getByRole('tab', { name: /folder permission/i }));

    expect(await screen.findByText(/read & write/i)).toBeInTheDocument();
  });

  test('password/retype mismatch shows a validation error and makes no API call', async () => {
    const user = userEvent.setup();
    renderAddEditUserModal({ mode: 'edit', user: mockUser });
    await screen.findByRole('textbox', { name: /^username$/i });

    await user.type(screen.getByLabelText(/^new password/i), 'aaaaaaaa');
    await user.type(screen.getByLabelText(/retype new password/i), 'bbbbbbbb');
    await user.click(screen.getByRole('button', { name: /^save$/i }));

    expect(await screen.findByText(/passwords do not match/i)).toBeInTheDocument();
    expect(updateUser).not.toHaveBeenCalled();
  });

  test("successful save calls updateUser with the user's id", async () => {
    const user = userEvent.setup();
    vi.mocked(updateUser).mockResolvedValue(mockUser);

    renderAddEditUserModal({ mode: 'edit', user: mockUser });
    const usernameInput = await screen.findByRole('textbox', { name: /^username$/i });

    await user.clear(usernameInput);
    await user.type(usernameInput, 'updateduser');
    await user.click(screen.getByRole('button', { name: /^save$/i }));

    await waitFor(() => {
      expect(updateUser).toHaveBeenCalledWith(
        mockUser.userId,
        expect.objectContaining({ userName: 'updateduser' }),
      );
    });
  });

  test('userTypeId and notification fields are omitted for a non-superAdmin', async () => {
    const user = userEvent.setup();
    vi.mocked(updateUser).mockResolvedValue(mockUser);

    renderAddEditUserModal({ mode: 'edit', user: mockUser, currentUser: mockGroupAdmin });
    await screen.findByRole('textbox', { name: /^username$/i });

    await user.click(screen.getByRole('button', { name: /^save$/i }));

    await waitFor(() => expect(updateUser).toHaveBeenCalledTimes(1));
    const payload = vi.mocked(updateUser).mock.calls[0]![1];
    expect(payload).not.toHaveProperty('isSystemNotification');
  });

  test('Save button shows "Saving…" while pending (edit mode)', async () => {
    const user = userEvent.setup();
    let resolveUpdate!: (v: typeof mockUser) => void;
    vi.mocked(updateUser).mockReturnValue(
      new Promise((res) => {
        resolveUpdate = res;
      }),
    );

    renderAddEditUserModal({ mode: 'edit', user: mockUser });
    await screen.findByRole('textbox', { name: /^username$/i });

    await user.click(screen.getByRole('button', { name: /^save$/i }));

    await waitFor(() => expect(screen.getByRole('button', { name: /saving/i })).toBeDisabled());

    resolveUpdate(mockUser);
    await waitFor(() =>
      expect(screen.queryByRole('button', { name: /saving/i })).not.toBeInTheDocument(),
    );
  });

  // ---------------------------------------------------------------------------
  // Cancel
  // ---------------------------------------------------------------------------
  test('Cancel closes without an API call', async () => {
    const user = userEvent.setup();
    const onClose = vi.fn();
    renderAddEditUserModal({ mode: 'add', onClose });
    await screen.findByRole('textbox', { name: /^username$/i });

    await user.click(screen.getByRole('button', { name: /^cancel$/i }));

    expect(onClose).toHaveBeenCalledTimes(1);
    expect(createUser).not.toHaveBeenCalled();
    expect(updateUser).not.toHaveBeenCalled();
  });
});
