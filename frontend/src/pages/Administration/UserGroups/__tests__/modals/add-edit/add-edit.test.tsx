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

import { buildUserGroup, mockGroupAdmin, mockPopulatedUserGroup } from '../../fixtures/userGroup';
import { renderAddEditUserGroupModal } from '../helpers/renderAddEditUserGroupModal';

import { createUserGroup, updateUserGroup } from '@/services/userGroupApi';
import { apiError } from '@/testUtils/apiErrors';
import { deferred } from '@/testUtils/deferred';
import { knownFailure } from '@/testUtils/knownFailure';
import type { UserGroup } from '@/types/userGroup';

// =============================================================================
// Module mocks
// =============================================================================

vi.mock('@/services/userGroupApi');
vi.mock('@/components/ui/modals/Modal');
// LibraryQuotaInput, SwitchRow, Checkbox, SelectDropdown, TextInput and the zod schema run for real.

// =============================================================================
// Helpers
// =============================================================================

const nameInput = () => screen.getByRole('textbox', { name: /^name$/i });
const descriptionInput = () => screen.getByRole('textbox', { name: /^description/i });

const typeName = async (user: UserEvent, value: string) => {
  await user.clear(nameInput());
  await user.type(nameInput(), value);
};

// =============================================================================
// Tests
// =============================================================================

describe('AddEditUserGroupModal - mode', () => {
  beforeEach(() => vi.clearAllMocks());

  test('Add mode shows "Add User Group" and a "Create Group" button', async () => {
    const { dialog } = await renderAddEditUserGroupModal();

    expect(dialog).toHaveAccessibleName('Add User Group');
    expect(screen.getByRole('button', { name: 'Create Group' })).toBeInTheDocument();
  });

  test('Edit mode shows "Edit User Group" and a "Save" button', async () => {
    const { dialog } = await renderAddEditUserGroupModal({
      mode: 'edit',
      userGroup: mockPopulatedUserGroup,
    });

    expect(dialog).toHaveAccessibleName('Edit User Group');
    expect(screen.getByRole('button', { name: 'Save' })).toBeInTheDocument();
  });

  test('Notifications tab is shown to a super admin', async () => {
    await renderAddEditUserGroupModal();

    expect(screen.getByRole('tab', { name: 'General' })).toBeInTheDocument();
    expect(screen.getByRole('tab', { name: 'Notifications' })).toBeInTheDocument();
  });

  test('Notifications tab is hidden from a non-super-admin', async () => {
    await renderAddEditUserGroupModal({ user: mockGroupAdmin });

    expect(screen.getByRole('tab', { name: 'General' })).toBeInTheDocument();
    expect(screen.queryByRole('tab', { name: 'Notifications' })).not.toBeInTheDocument();
  });
});

describe('AddEditUserGroupModal - validation', () => {
  beforeEach(() => vi.clearAllMocks());

  test('empty name shows "Group name is required" and does not save', async () => {
    const user = userEvent.setup();
    await renderAddEditUserGroupModal();

    await user.click(screen.getByRole('button', { name: 'Create Group' }));

    await screen.findByText('Group name is required');
    expect(createUserGroup).not.toHaveBeenCalled();
  });

  test('name over 50 characters is rejected', async () => {
    const user = userEvent.setup();
    await renderAddEditUserGroupModal();

    await typeName(user, 'a'.repeat(51));
    await user.click(screen.getByRole('button', { name: 'Create Group' }));

    await screen.findByText('Group name must be less than 50 characters');
    expect(createUserGroup).not.toHaveBeenCalled();
  });

  test('description over 500 characters is rejected', async () => {
    const user = userEvent.setup();
    await renderAddEditUserGroupModal();

    await typeName(user, 'QA');
    await user.click(descriptionInput());
    await user.paste('a'.repeat(501));
    await user.click(screen.getByRole('button', { name: 'Create Group' }));

    await screen.findByText('Description must be less than 500 characters');
    expect(createUserGroup).not.toHaveBeenCalled();
  });

  test('a name error switches back to the General tab', async () => {
    const user = userEvent.setup();
    await renderAddEditUserGroupModal();

    await user.click(screen.getByRole('tab', { name: 'Notifications' }));
    await user.click(screen.getByRole('button', { name: 'Create Group' }));

    await waitFor(() => {
      expect(screen.getByRole('tab', { name: 'General' })).toHaveAttribute('aria-selected', 'true');
    });
    expect(screen.getByText('Group name is required')).toBeInTheDocument();
  });

  // Known issue: xibosignage/xibo#4123
  describe('Pinned until https://github.com/xibosignage/xibo/issues/4123 is fixed', () => {
    // Only name and quota errors switch back to General; a description error stays hidden.
    knownFailure(
      'a description error raised on the Notifications tab switches back to General',
      async () => {
        const user = userEvent.setup();
        await renderAddEditUserGroupModal();
        await typeName(user, 'QA');
        await user.click(descriptionInput());
        await user.paste('a'.repeat(501));
        await user.click(screen.getByRole('tab', { name: 'Notifications' }));
        await user.click(screen.getByRole('button', { name: 'Create Group' }));
        // Control: validation blocked the save.
        expect(createUserGroup).not.toHaveBeenCalled();
      },
      () =>
        expect(screen.getByRole('tab', { name: 'General' })).toHaveAttribute(
          'aria-selected',
          'true',
        ),
    );
  });

  // Known issue: xibosignage/xibo#4129
  describe('Pinned until https://github.com/xibosignage/xibo/issues/4129 is fixed', () => {
    // TextInput shows errors in a plain <p> that isn't linked to the field.
    knownFailure(
      'the Name field is described by its validation error',
      async () => {
        const user = userEvent.setup();
        await renderAddEditUserGroupModal();
        await user.click(screen.getByRole('button', { name: 'Create Group' }));
        // Control: the error is shown on screen.
        await screen.findByText('Group name is required');
      },
      () => expect(nameInput()).toHaveAccessibleDescription('Group name is required'),
    );
  });

  test('editing the description clears its error', async () => {
    const user = userEvent.setup();
    await renderAddEditUserGroupModal();

    await typeName(user, 'QA');
    await user.click(descriptionInput());
    await user.paste('a'.repeat(501));
    await user.click(screen.getByRole('button', { name: 'Create Group' }));
    await screen.findByText('Description must be less than 500 characters');

    await user.type(descriptionInput(), '{Backspace}');

    expect(
      screen.queryByText('Description must be less than 500 characters'),
    ).not.toBeInTheDocument();
  });
});

describe('AddEditUserGroupModal - save', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    vi.mocked(createUserGroup).mockResolvedValue(buildUserGroup());
    vi.mocked(updateUserGroup).mockResolvedValue(buildUserGroup());
  });

  test('super admin create sends every field', async () => {
    const user = userEvent.setup();
    await renderAddEditUserGroupModal();

    await typeName(user, 'QA');
    await user.type(descriptionInput(), 'Testers');
    const quota = screen.getByRole('spinbutton', { name: /library quota/i });
    await user.clear(quota);
    await user.type(quota, '1');
    await user.click(screen.getByRole('button', { name: 'KiB' }));
    await user.click(screen.getByRole('button', { name: 'GiB' }));
    await user.click(screen.getByRole('checkbox', { name: /show when onboarding a new user/i }));
    await user.click(screen.getByRole('combobox', { name: /default homepage/i }));
    await user.click(await screen.findByRole('option', { name: 'Status Dashboard' }));
    await user.click(screen.getByRole('tab', { name: 'Notifications' }));
    await user.click(screen.getByRole('switch', { name: 'System Notifications' }));

    await user.click(screen.getByRole('button', { name: 'Create Group' }));

    await waitFor(() => expect(createUserGroup).toHaveBeenCalledTimes(1));
    expect(createUserGroup).toHaveBeenCalledWith({
      group: 'QA',
      description: 'Testers',
      libraryQuota: 1048576,
      isShownForAddUser: 1,
      defaultHomepageId: 'statusdashboard.view',
      isSystemNotification: 1,
      isDisplayNotification: 0,
      isDataSetNotification: 0,
      isLayoutNotification: 0,
      isLibraryNotification: 0,
      isReportNotification: 0,
      isScheduleNotification: 0,
      isCustomNotification: 0,
    });
  });

  test('non-super-admin create sends only name, description and quota', async () => {
    const user = userEvent.setup();
    await renderAddEditUserGroupModal({ user: mockGroupAdmin });

    await typeName(user, 'QA');
    await user.click(screen.getByRole('button', { name: 'Create Group' }));

    await waitFor(() => expect(createUserGroup).toHaveBeenCalledTimes(1));
    const payload = vi.mocked(createUserGroup).mock.calls[0]![0];
    expect(Object.keys(payload).sort()).toEqual(['description', 'group', 'libraryQuota']);
    expect(payload).toEqual({ group: 'QA', description: undefined, libraryQuota: 0 });
  });

  test('edit saves against the group ID', async () => {
    const user = userEvent.setup();
    await renderAddEditUserGroupModal({ mode: 'edit', userGroup: mockPopulatedUserGroup });

    await typeName(user, 'Ops Team');
    await user.click(screen.getByRole('button', { name: 'Save' }));

    await waitFor(() => expect(updateUserGroup).toHaveBeenCalledTimes(1));
    expect(updateUserGroup).toHaveBeenCalledWith(
      mockPopulatedUserGroup.groupId,
      expect.objectContaining({
        group: 'Ops Team',
        description: 'Operations team',
        libraryQuota: 1048576,
        defaultHomepageId: 'statusdashboard.view',
        isSystemNotification: 1,
      }),
    );
    expect(createUserGroup).not.toHaveBeenCalled();
  });

  test('edit saves fields that were turned off or cleared', async () => {
    const user = userEvent.setup();
    await renderAddEditUserGroupModal({ mode: 'edit', userGroup: mockPopulatedUserGroup });

    await user.click(screen.getByRole('checkbox', { name: /show when onboarding a new user/i }));
    await user.click(screen.getByRole('combobox', { name: /default homepage/i }));
    await user.click(await screen.findByRole('button', { name: 'Select a homepage' }));
    await user.click(screen.getByRole('tab', { name: 'Notifications' }));
    await user.click(screen.getByRole('switch', { name: 'System Notifications' }));
    await user.click(screen.getByRole('button', { name: 'Save' }));

    await waitFor(() => expect(updateUserGroup).toHaveBeenCalledTimes(1));
    const [groupId, payload] = vi.mocked(updateUserGroup).mock.calls[0]!;
    expect(groupId).toBe(mockPopulatedUserGroup.groupId);
    expect(payload.isShownForAddUser).toBe(0);
    expect(payload.isSystemNotification).toBe(0);
    // Sent as missing, which the backend reads as null (clears the homepage).
    expect(payload.defaultHomepageId).toBeUndefined();
  });

  test('empty description and homepage are sent as undefined', async () => {
    const user = userEvent.setup();
    await renderAddEditUserGroupModal();

    await typeName(user, 'QA');
    await user.click(screen.getByRole('button', { name: 'Create Group' }));

    await waitFor(() => expect(createUserGroup).toHaveBeenCalledTimes(1));
    const payload = vi.mocked(createUserGroup).mock.calls[0]![0];
    expect(payload.description).toBeUndefined();
    expect(payload.defaultHomepageId).toBeUndefined();
    expect(payload.libraryQuota).toBe(0);
  });

  test('success calls onSuccess then onClose', async () => {
    const user = userEvent.setup();
    const { onClose, onSuccess } = await renderAddEditUserGroupModal();

    await typeName(user, 'QA');
    await user.click(screen.getByRole('button', { name: 'Create Group' }));

    await waitFor(() => expect(onClose).toHaveBeenCalledTimes(1));
    expect(onSuccess).toHaveBeenCalledTimes(1);
    expect(onSuccess.mock.invocationCallOrder[0]!).toBeLessThan(
      onClose.mock.invocationCallOrder[0]!,
    );
  });

  test('Create button shows "Creating..." and both buttons are disabled while saving', async () => {
    const user = userEvent.setup();
    const pending = deferred<UserGroup>();
    vi.mocked(createUserGroup).mockReturnValue(pending.promise);
    const { onClose } = await renderAddEditUserGroupModal();

    await typeName(user, 'QA');
    await user.click(screen.getByRole('button', { name: 'Create Group' }));

    expect(await screen.findByRole('button', { name: 'Creating...' })).toBeDisabled();
    expect(screen.getByRole('button', { name: 'Cancel' })).toBeDisabled();

    pending.resolve(buildUserGroup());
    await waitFor(() => expect(onClose).toHaveBeenCalled());
  });

  test('Save button shows "Saving..." while an edit is in progress', async () => {
    const user = userEvent.setup();
    const pending = deferred<UserGroup>();
    vi.mocked(updateUserGroup).mockReturnValue(pending.promise);
    const { onClose } = await renderAddEditUserGroupModal({
      mode: 'edit',
      userGroup: mockPopulatedUserGroup,
    });

    await user.click(screen.getByRole('button', { name: 'Save' }));

    expect(await screen.findByRole('button', { name: 'Saving...' })).toBeDisabled();

    pending.resolve(buildUserGroup());
    await waitFor(() => expect(onClose).toHaveBeenCalled());
  });
});

describe('AddEditUserGroupModal - errors and cancel', () => {
  beforeEach(() => vi.clearAllMocks());

  test('server error message is shown and the modal stays open', async () => {
    const user = userEvent.setup();
    vi.mocked(createUserGroup).mockRejectedValue(apiError('Name already in use', 409));
    const { onClose, onSuccess } = await renderAddEditUserGroupModal();

    await typeName(user, 'QA');
    await user.click(screen.getByRole('button', { name: 'Create Group' }));

    expect(await screen.findByRole('alert')).toHaveTextContent('Name already in use');
    expect(createUserGroup).toHaveBeenCalledTimes(1);
    expect(onClose).not.toHaveBeenCalled();
    expect(onSuccess).not.toHaveBeenCalled();
  });

  test('a plain Error shows its message', async () => {
    const user = userEvent.setup();
    vi.mocked(createUserGroup).mockRejectedValue(new Error('Network down'));
    await renderAddEditUserGroupModal();

    await typeName(user, 'QA');
    await user.click(screen.getByRole('button', { name: 'Create Group' }));

    expect(await screen.findByRole('alert')).toHaveTextContent('Network down');
    expect(createUserGroup).toHaveBeenCalledTimes(1);
  });

  test('an unknown rejection shows "An unexpected error occurred."', async () => {
    const user = userEvent.setup();
    vi.mocked(createUserGroup).mockRejectedValue('nope');
    await renderAddEditUserGroupModal();

    await typeName(user, 'QA');
    await user.click(screen.getByRole('button', { name: 'Create Group' }));

    expect(await screen.findByRole('alert')).toHaveTextContent('An unexpected error occurred.');
    expect(createUserGroup).toHaveBeenCalledTimes(1);
  });

  test('Cancel closes the modal without saving', async () => {
    const user = userEvent.setup();
    const { onClose } = await renderAddEditUserGroupModal();

    await typeName(user, 'QA');
    await user.click(screen.getByRole('button', { name: 'Cancel' }));

    expect(onClose).toHaveBeenCalledTimes(1);
    expect(createUserGroup).not.toHaveBeenCalled();
  });
});
